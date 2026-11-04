<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AtualizarOmieEmpresaRequest;
use App\Http\Resources\EmpresaResource;
use App\Http\Resources\ReuniaoResource;
use App\Http\Resources\TreinamentoResource;
use App\Models\Empresa;
use App\Models\ReceitaMensal;
use App\Models\Transacao;
use App\Support\Omie;
use App\Support\OmieFinanceiroSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Throwable;

class EmpresaController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $empresas = Empresa::with(['socio', 'ultimaReuniao'])
            ->withCount(['reunioes', 'fontes'])
            ->orderBy('nome')
            ->get();

        return EmpresaResource::collection($empresas);
    }

    /** Tudo o que o detalhe do cliente mostra, em uma resposta só. */
    public function show(Empresa $empresa): JsonResponse
    {
        $empresa->load([
            'socio', 'fontes', 'ultimaReuniao', 'reunioes.empresa.socio', 'treinamentos',
            'financeiro', 'receitasMensais', 'transacoes',
        ]);

        $financeiro = $empresa->financeiro;

        return response()->json([
            'empresa' => new EmpresaResource($empresa),
            'reunioes' => ReuniaoResource::collection($empresa->reunioes),
            'treinamentos' => TreinamentoResource::collection($empresa->treinamentos),
            'financeiro' => $financeiro === null ? null : [
                'regime' => $financeiro->regime,
                'desde' => $financeiro->desde->format('Y-m-d'),
                'meses' => $financeiro->mesesDeVida(),
                'primeiro' => $financeiro->primeiro,
                'atual' => $financeiro->atual,
                'total' => $financeiro->total,
                'saldo' => $financeiro->saldo,
                'receber' => $financeiro->receber,
                'despesas' => $financeiro->despesas,
                'margem' => $financeiro->margem,
                'receitaMensal' => $empresa->receitasMensais
                    ->map(fn (ReceitaMensal $item) => ['mes' => $item->mes, 'valor' => $item->valor])
                    ->all(),
                'transacoes' => $empresa->transacoes->map(fn (Transacao $item) => [
                    'data' => $item->data,
                    'desc' => $item->descricao,
                    'cat' => $item->categoria,
                    'tipo' => $item->tipo,
                    'valor' => $item->valor,
                    'status' => $item->status,
                ])->all(),
            ],
        ]);
    }

    /**
     * A carteira é só leitura no Brain: empresas entram pela sincronização
     * com a automação. Ninguém com acesso ao HC Brain cria ou edita por aqui.
     */
    public function store(Request $request): JsonResponse
    {
        abort(403, 'Empresas são só para consulta. O cadastro vem da automação.');
    }

    public function update(Request $request, Empresa $empresa): JsonResponse
    {
        abort(403, 'Empresas são só para consulta. O cadastro vem da automação.');
    }

    public function destroy(Empresa $empresa): Response
    {
        abort(403, 'Empresas são só para consulta. O cadastro vem da automação.');
    }

    /**
     * Único campo editável da carteira no Brain: o código do cliente no Omie,
     * necessário para puxar o financeiro sem CNPJ local.
     */
    public function atualizarOmie(AtualizarOmieEmpresaRequest $request, Empresa $empresa): EmpresaResource
    {
        $empresa->update([
            'omie_codigo_cliente' => $request->validated('omieCodigoCliente'),
        ]);

        return new EmpresaResource($empresa->load(['socio', 'ultimaReuniao']));
    }

    /** Sincroniza agora o financeiro dessa empresa a partir do Omie. */
    public function sincronizarOmie(Empresa $empresa): JsonResponse
    {
        if ($empresa->omie_codigo_cliente === null) {
            return response()->json([
                'message' => 'Informe o código do cliente Omie antes de sincronizar.',
            ], 422);
        }

        if (! Omie::configurada()) {
            return response()->json([
                'message' => 'Omie não configurada neste ambiente (OMIE_APP_KEY / OMIE_APP_SECRET).',
            ], 503);
        }

        try {
            OmieFinanceiroSync::sincronizar($empresa->fresh());
        } catch (Throwable $erro) {
            report($erro);

            return response()->json([
                'message' => 'Falha ao sincronizar com o Omie: '.$erro->getMessage(),
            ], 502);
        }

        return $this->show($empresa->fresh());
    }
}
