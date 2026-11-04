<?php

namespace App\Support;

use App\Models\Empresa;
use App\Models\EmpresaFinanceiro;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Puxa contas a receber/pagar do Omie para uma empresa já vinculada
 * por `omie_codigo_cliente` e grava no financeiro do Brain.
 */
final class OmieFinanceiroSync
{
    /** @var list<string> */
    private const RECEBIDOS = ['RECEBIDO', 'LIQUIDADO', 'PAGO'];

    /** @var list<string> */
    private const CANCELADOS = ['CANCELADO', 'CANCELADA'];

    public static function sincronizar(Empresa $empresa): EmpresaFinanceiro
    {
        $empresa->loadMissing('omieApp');
        $codigo = $empresa->omie_codigo_cliente;
        $app = $empresa->omieApp;

        if ($codigo === null || $app === null) {
            throw new RuntimeException(
                "Empresa {$empresa->id} sem app Omie ou código de cliente."
            );
        }

        $receberTitulos = Omie::listarTodas(
            $app,
            'financas/contareceber/',
            'ListarContasReceber',
            'conta_receber_cadastro',
            ['filtrar_cliente' => (int) $codigo],
        );

        $pagarTitulos = [];

        try {
            $pagarTitulos = Omie::listarTodas(
                $app,
                'financas/contapagar/',
                'ListarContasPagar',
                'conta_pagar_cadastro',
                ['filtrar_cliente' => (int) $codigo],
            );
        } catch (RuntimeException) {
            // Conta a pagar pode falhar por permissão; segue só com a receber.
        }

        return DB::transaction(function () use ($empresa, $receberTitulos, $pagarTitulos) {
            return self::gravar($empresa, $receberTitulos, $pagarTitulos);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $receberTitulos
     * @param  list<array<string, mixed>>  $pagarTitulos
     */
    private static function gravar(
        Empresa $empresa,
        array $receberTitulos,
        array $pagarTitulos,
    ): EmpresaFinanceiro {
        $entradasAbertas = 0.0;
        $entradasPagas = 0.0;
        $porMes = [];
        $datasRecebidas = [];
        $transacoes = [];

        foreach ($receberTitulos as $titulo) {
            $status = self::statusDe($titulo);
            $valor = self::valorDe($titulo);

            if (in_array($status, self::CANCELADOS, true) || $valor <= 0) {
                continue;
            }

            $data = self::dataDe($titulo);
            $descricao = self::texto($titulo['numero_documento_fiscal']
                ?? $titulo['numero_pedido']
                ?? $titulo['codigo_lancamento_omie']
                ?? 'Conta a receber Omie');

            if (in_array($status, self::RECEBIDOS, true)) {
                $entradasPagas += $valor;
                $chaveMes = $data?->format('Y-m') ?? 'sem-data';
                $porMes[$chaveMes] = ($porMes[$chaveMes] ?? 0) + $valor;
                if ($data !== null) {
                    $datasRecebidas[] = $data;
                }
                $transacoes[] = [
                    'data' => $data?->format('d/m/Y') ?? '—',
                    'descricao' => $descricao,
                    'categoria' => self::texto($titulo['codigo_categoria'] ?? 'Receita'),
                    'tipo' => 'Entrada',
                    'valor' => $valor,
                    'status' => 'Recebido',
                    'ordem_data' => $data?->timestamp ?? 0,
                ];
            } else {
                $entradasAbertas += $valor;
                $transacoes[] = [
                    'data' => $data?->format('d/m/Y') ?? '—',
                    'descricao' => $descricao,
                    'categoria' => self::texto($titulo['codigo_categoria'] ?? 'A receber'),
                    'tipo' => 'Entrada',
                    'valor' => $valor,
                    'status' => 'Pendente',
                    'ordem_data' => $data?->timestamp ?? 0,
                ];
            }
        }

        $saidas = 0.0;

        foreach ($pagarTitulos as $titulo) {
            $status = self::statusDe($titulo);
            $valor = self::valorDe($titulo);

            if (in_array($status, self::CANCELADOS, true) || $valor <= 0) {
                continue;
            }

            if (! in_array($status, self::RECEBIDOS, true)) {
                // Em aberto no pagar conta como despesa prevista do período.
                $saidas += $valor;
            } else {
                $saidas += $valor;
            }

            $data = self::dataDe($titulo);
            $transacoes[] = [
                'data' => $data?->format('d/m/Y') ?? '—',
                'descricao' => self::texto($titulo['numero_documento_fiscal']
                    ?? $titulo['codigo_lancamento_omie']
                    ?? 'Conta a pagar Omie'),
                'categoria' => self::texto($titulo['codigo_categoria'] ?? 'Despesa'),
                'tipo' => 'Saída',
                'valor' => $valor,
                'status' => in_array($status, self::RECEBIDOS, true) ? 'Pago' : 'Pendente',
                'ordem_data' => $data?->timestamp ?? 0,
            ];
        }

        ksort($porMes);
        $valoresMensais = array_values($porMes);
        $primeiro = $valoresMensais[0] ?? 0.0;
        $atual = $valoresMensais === [] ? 0.0 : $valoresMensais[array_key_last($valoresMensais)];
        $desde = $datasRecebidas === []
            ? today()
            : collect($datasRecebidas)->sort()->first();

        $base = max($entradasPagas, 0.01);
        $margem = (int) round(max(0, min(100, (($entradasPagas - $saidas) / $base) * 100)));
        $saldo = $entradasAbertas; // posição a receber consolidada

        $existente = $empresa->financeiro;
        $regime = $existente?->regime ?: 'Simples Nacional';

        $financeiro = EmpresaFinanceiro::query()->updateOrCreate(
            ['empresa_id' => $empresa->id],
            [
                'regime' => $regime,
                'desde' => $desde,
                'primeiro' => round($primeiro, 2),
                'atual' => round($atual, 2),
                'total' => round($entradasPagas, 2),
                'saldo' => round($saldo, 2),
                'receber' => round($entradasAbertas, 2),
                'despesas' => round($saidas, 2),
                'margem' => $margem,
            ],
        );

        $empresa->receitasMensais()->delete();
        $ordem = 0;
        foreach ($porMes as $chave => $valor) {
            $rotulo = $chave === 'sem-data'
                ? '—'
                : Carbon::createFromFormat('Y-m', $chave)->locale('pt_BR')->isoFormat('MMM/YY');

            $empresa->receitasMensais()->create([
                'mes' => $rotulo,
                'valor' => round($valor, 2),
                'ordem' => $ordem++,
            ]);
        }

        usort($transacoes, fn (array $a, array $b) => $b['ordem_data'] <=> $a['ordem_data']);
        $empresa->transacoes()->delete();
        foreach (array_slice($transacoes, 0, 50) as $indice => $item) {
            unset($item['ordem_data']);
            $empresa->transacoes()->create([...$item, 'ordem' => $indice]);
        }

        return $financeiro->refresh();
    }

    /** @param  array<string, mixed>  $titulo */
    private static function statusDe(array $titulo): string
    {
        return mb_strtoupper(trim(self::texto($titulo['status_titulo'] ?? '')));
    }

    /** @param  array<string, mixed>  $titulo */
    private static function valorDe(array $titulo): float
    {
        return (float) ($titulo['valor_documento'] ?? 0);
    }

    /** @param  array<string, mixed>  $titulo */
    private static function dataDe(array $titulo): ?Carbon
    {
        foreach (['data_emissao', 'data_vencimento', 'data_previsao', 'data_registro'] as $campo) {
            $bruto = $titulo[$campo] ?? null;
            if (! is_string($bruto) || trim($bruto) === '') {
                continue;
            }

            try {
                return Carbon::createFromFormat('d/m/Y', trim($bruto))->startOfDay();
            } catch (\Throwable) {
                try {
                    return Carbon::parse($bruto)->startOfDay();
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return null;
    }

    private static function texto(mixed $valor): string
    {
        if (is_scalar($valor)) {
            return trim((string) $valor);
        }

        return '';
    }
}
