<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AtualizarDocumentoRequest;
use App\Http\Requests\EnviarDocumentoRequest;
use App\Http\Resources\DocumentoResource;
use App\Models\Documento;
use App\Models\Pasta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoController extends Controller
{
    public function index(): JsonResponse
    {
        $documentos = Documento::query()
            ->with('pasta')
            ->select(Documento::COLUNAS_LISTA)
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            // A contagem de cada pasta é contada de verdade, então enviar ou
            // excluir um arquivo muda o número no menu na mesma hora.
            'pastas' => Pasta::withCount('documentos')
                ->orderBy('ordem')
                ->orderBy('nome')
                ->get()
                ->map(fn (Pasta $pasta) => [
                    'id' => $pasta->id,
                    'nome' => $pasta->nome,
                    'total' => $pasta->documentos_count,
                ]),
            'documentos' => DocumentoResource::collection($documentos),
        ]);
    }

    public function store(EnviarDocumentoRequest $request): JsonResponse
    {
        $arquivo = $request->file('arquivo');
        $extensao = strtolower($arquivo->getClientOriginalExtension());
        $bytes = $arquivo->get();

        $caminho = $arquivo->store(
            'documentos/'.$request->integer('pastaId'),
            config('hc.documentos.disco')
        );

        $documento = Documento::create([
            'pasta_id' => $request->integer('pastaId'),
            'nome' => $request->filled('nome')
                ? $request->string('nome')->toString()
                : pathinfo($arquivo->getClientOriginalName(), PATHINFO_FILENAME),
            'extensao' => $extensao,
            'arquivo' => $caminho,
            'conteudo' => $bytes,
            'tamanho' => $arquivo->getSize(),
            'mime' => $arquivo->getClientMimeType(),
            'enviado_por' => $request->user()->id,
        ]);

        // Garante o blob na base mesmo se o cast/coluna falhar no insert.
        if ($documento->lerBytes() === null && is_string($bytes) && $bytes !== '') {
            $documento->guardarBytes($bytes);
        }

        return (new DocumentoResource($documento->load('pasta')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(AtualizarDocumentoRequest $request, Documento $documento): DocumentoResource
    {
        $documento->update([
            'nome' => $request->string('nome')->toString(),
            'pasta_id' => $request->integer('pastaId'),
        ]);

        return new DocumentoResource($documento->load('pasta'));
    }

    public function destroy(Documento $documento): Response
    {
        $documento->apagarArquivo();
        $documento->delete();

        return response()->noContent();
    }

    /**
     * O arquivo sai pelo Laravel, e não por uma URL pública do disco, porque
     * documento da HC não deve ser acessível a quem não está na sessão.
     * Se o disco do contêiner sumiu no deploy, serve o conteúdo guardado na base.
     *
     * `?inline=1` abre no navegador (prévia); sem isso, força o download.
     */
    public function download(Request $request, Documento $documento): StreamedResponse
    {
        $bytes = $documento->lerBytes() ?? $documento->tentarRecuperarBytes();
        abort_if($bytes === null, 404, 'Este registro não tem arquivo anexado.');

        $disco = Storage::disk(config('hc.documentos.disco'));
        if (
            is_string($documento->arquivo)
            && $documento->arquivo !== ''
            && ! $disco->exists($documento->arquivo)
        ) {
            try {
                $disco->put($documento->arquivo, $bytes);
            } catch (\Throwable) {
                // Disco efêmero/cheio: ainda assim entrega os bytes da base.
            }
        }

        $nome = $documento->nomeDoArquivo();
        $mime = $this->mimeParaResposta($documento);
        $disposicao = $request->boolean('inline') ? 'inline' : 'attachment';
        $arquivoSeguro = str_replace(['"', '\\', "\r", "\n"], '', $nome);

        return response()->stream(
            function () use ($bytes): void {
                echo $bytes;
            },
            200,
            [
                'Content-Type' => $mime,
                'Content-Length' => (string) strlen($bytes),
                'Content-Disposition' => $disposicao.'; filename="'.$arquivoSeguro.'"',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    private function mimeParaResposta(Documento $documento): string
    {
        if ($documento->extensao === 'pdf') {
            return 'application/pdf';
        }

        if ($documento->extensao === 'txt') {
            return 'text/plain; charset=UTF-8';
        }

        if (in_array($documento->extensao, ['png', 'jpg', 'jpeg'], true)) {
            return $documento->extensao === 'png' ? 'image/png' : 'image/jpeg';
        }

        return $documento->mime ?: 'application/octet-stream';
    }
}
