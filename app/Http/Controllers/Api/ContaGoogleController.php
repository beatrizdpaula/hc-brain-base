<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContaGoogle;
use App\Support\GoogleAgenda;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;

class ContaGoogleController extends Controller
{
    /** As agendas conectadas e se o ambiente tem o cliente do Google configurado. */
    public function index(): JsonResponse
    {
        return response()->json([
            'configurada' => GoogleAgenda::configurada(),
            'contas' => ContaGoogle::orderBy('email')->get()->map(fn (ContaGoogle $conta) => [
                'id' => $conta->id,
                'email' => $conta->email,
                'nome' => $conta->nome,
                'sincronizadaEm' => $conta->sincronizada_em?->setTimezone(GoogleAgenda::FUSO)->format('d/m/Y H:i'),
                'erro' => $conta->erro,
            ]),
        ]);
    }

    /**
     * Lê as agendas na hora, sem esperar o agendador — que no Render para
     * quando a instância hiberna. Falhas ficam gravadas em cada conta.
     */
    public function sincronizar(Request $request): JsonResponse
    {
        abort_unless($request->user()->ehAdministrador(), 403, 'Só um administrador sincroniza as agendas do Google.');

        Artisan::call('hc:sincronizar-agendas');

        return $this->index();
    }

    /** As reuniões que vieram desta agenda ficam no histórico, só perdem o vínculo. */
    public function destroy(Request $request, ContaGoogle $contaGoogle): Response
    {
        abort_unless($request->user()->ehAdministrador(), 403, 'Só um administrador desconecta agendas do Google.');

        $contaGoogle->delete();

        return response()->noContent();
    }
}
