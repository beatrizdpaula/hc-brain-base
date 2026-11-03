<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Documento;
use App\Models\Projeto;
use App\Models\Reuniao;
use App\Support\Contadores;
use App\Support\GoogleAgenda;
use App\Support\Telas;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * O painel do Início. Os números e a atividade recente eram texto escrito na
 * view; agora saem das mesmas tabelas que as outras telas, então a primeira
 * tela conta a situação real da base.
 */
class InicioController extends Controller
{
    /** Quantos itens cada lista do painel mostra. */
    private const LIMITE = 5;

    public function index(): JsonResponse
    {
        return response()->json([
            'contadores' => Contadores::doInicio(),
            'proximasReunioes' => $this->proximasReunioes(),
            'atividade' => $this->atividadeRecente(),
        ]);
    }

    /**
     * Reuniões de hoje em diante — agenda, não histórico.
     *
     * @return list<array<string, string>>
     */
    private function proximasReunioes(): array
    {
        $hoje = now(GoogleAgenda::FUSO)->toDateString();

        return Reuniao::query()
            ->with('empresa')
            ->whereDate('data', '>=', $hoje)
            ->where('status', 'Agendada')
            ->orderBy('data')
            ->orderBy('horario')
            ->limit(self::LIMITE)
            ->get()
            ->map(fn (Reuniao $reuniao) => Collection::make(
                $this->itemDeReuniao($reuniao, proxima: true),
            )->except('ordem')->all())
            ->all();
    }

    /**
     * Histórico recente: reuniões já ocorridas, projetos e documentos
     * atualizados. Reunião futura não entra aqui.
     *
     * @return list<array<string, string>>
     */
    private function atividadeRecente(): array
    {
        $hoje = now(GoogleAgenda::FUSO)->toDateString();

        $reunioes = Reuniao::query()
            ->with('empresa')
            ->whereDate('data', '<', $hoje)
            ->orderByDesc('data')
            ->limit(self::LIMITE)
            ->get()
            ->map(fn (Reuniao $reuniao) => $this->itemDeReuniao($reuniao, proxima: false));

        $projetos = Projeto::query()
            ->orderByDesc('updated_at')
            ->limit(self::LIMITE)
            ->get()
            ->map(fn (Projeto $projeto) => [
                'titulo' => $projeto->nome,
                'detalhe' => "{$projeto->progresso}% concluído · responsável: {$projeto->responsavel}",
                'etiqueta' => $projeto->status,
                'quando' => $projeto->updated_at->timezone(GoogleAgenda::FUSO)->format('d/m/Y'),
                'ordem' => $projeto->updated_at->format('Y-m-d H:i:s'),
                'destino' => 'projetos',
                'caminho' => Telas::caminho('projetos'),
            ]);

        $documentos = Documento::query()
            ->with('pasta')
            ->orderByDesc('updated_at')
            ->limit(self::LIMITE)
            ->get()
            ->map(fn (Documento $documento) => [
                'titulo' => $documento->nomeDoArquivo(),
                'detalhe' => 'Arquivado em '.($documento->pasta?->nome ?: 'Documentos'),
                'etiqueta' => $documento->demonstrativo ? 'Demonstrativo' : 'Documento',
                'quando' => $documento->updated_at->timezone(GoogleAgenda::FUSO)->format('d/m/Y'),
                'ordem' => $documento->updated_at->format('Y-m-d H:i:s'),
                'destino' => 'documentos',
                'caminho' => Telas::caminho('documentos'),
            ]);

        // Cota por tipo: senão documentos/projetos recém-atualizados empurram
        // todas as reuniões passadas para fora da lista.
        return $reunioes->take(4)
            ->concat($projetos->take(3))
            ->concat($documentos->take(3))
            ->sortByDesc('ordem')
            ->map(fn (array $item) => Collection::make($item)->except('ordem')->all())
            ->values()
            ->all();
    }

    /**
     * @return array{titulo: string, detalhe: string, etiqueta: string, quando: string, ordem: string, destino: string, caminho: string}
     */
    private function itemDeReuniao(Reuniao $reuniao, bool $proxima): array
    {
        $horario = is_string($reuniao->horario) && $reuniao->horario !== ''
            ? ' · '.$reuniao->horario
            : '';

        return [
            'titulo' => "Reunião de {$reuniao->tipo} — {$reuniao->nomeDaEmpresa()}",
            'detalhe' => (string) ($reuniao->resumo ?: ($proxima
                ? "Status: {$reuniao->status}{$horario}"
                : "Status: {$reuniao->status}")),
            'etiqueta' => $proxima ? $reuniao->status : $reuniao->tipo,
            'quando' => $reuniao->data->format('d/m/Y').$horario,
            'ordem' => $reuniao->data->format('Y-m-d').' '.($reuniao->horario ?: '00:00'),
            'destino' => 'reunioes',
            'caminho' => Telas::caminho('reunioes').'?reuniao='.$reuniao->id,
        ];
    }
}
