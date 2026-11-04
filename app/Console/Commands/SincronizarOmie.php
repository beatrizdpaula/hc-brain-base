<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Support\Omie;
use App\Support\OmieFinanceiroSync;
use Illuminate\Console\Command;
use Throwable;

/**
 * Atualiza o financeiro das empresas que têm `omie_codigo_cliente`.
 * Seguro de repetir: regrava totais, receitas mensais e transações.
 */
class SincronizarOmie extends Command
{
    protected $signature = 'hc:sincronizar-omie
        {--empresa= : Id da empresa no Brain (opcional)}';

    protected $description = 'Sincroniza o financeiro do Omie para empresas vinculadas por código de cliente';

    public function handle(): int
    {
        if (! Omie::configurada()) {
            $this->components->error('Omie não configurada: defina OMIE_APP_KEY e OMIE_APP_SECRET.');

            return self::FAILURE;
        }

        $consulta = Empresa::query()
            ->whereNotNull('omie_codigo_cliente')
            ->orderBy('nome');

        if ($this->option('empresa')) {
            $consulta->whereKey((string) $this->option('empresa'));
        }

        $empresas = $consulta->get();

        if ($empresas->isEmpty()) {
            $this->components->warn('Nenhuma empresa com omie_codigo_cliente para sincronizar.');

            return self::SUCCESS;
        }

        $ok = 0;
        $falhas = 0;

        foreach ($empresas as $empresa) {
            try {
                OmieFinanceiroSync::sincronizar($empresa);
                $ok++;
                $this->line("  {$empresa->nome} (Omie {$empresa->omie_codigo_cliente})");
            } catch (Throwable $erro) {
                $falhas++;
                $this->components->error("{$empresa->nome}: {$erro->getMessage()}");
                report($erro);
            }
        }

        $this->components->info("Omie: {$ok} empresa(s) atualizada(s)".($falhas > 0 ? ", {$falhas} falha(s)" : '').'.');

        return $falhas > 0 && $ok === 0 ? self::FAILURE : self::SUCCESS;
    }
}
