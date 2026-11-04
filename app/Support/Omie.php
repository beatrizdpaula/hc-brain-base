<?php

namespace App\Support;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente HTTP da API Omie (JSON). Credenciais vêm de hc.omie.
 */
final class Omie
{
    public static function configurada(): bool
    {
        return filled(config('hc.omie.app_key')) && filled(config('hc.omie.app_secret'));
    }

    /**
     * @param  array<string, mixed>  $param
     * @return array<string, mixed>
     */
    public static function chamar(string $recurso, string $call, array $param = []): array
    {
        if (! self::configurada()) {
            throw new RuntimeException(
                'OMIE_APP_KEY / OMIE_APP_SECRET vazias. Configure no .env ou no Render.'
            );
        }

        $base = rtrim((string) config('hc.omie.base_url'), '/');
        $url = $base.'/'.ltrim($recurso, '/');

        try {
            $resposta = Http::connectTimeout(5)
                ->timeout(60)
                ->acceptJson()
                ->asJson()
                ->post($url, [
                    'call' => $call,
                    'app_key' => config('hc.omie.app_key'),
                    'app_secret' => config('hc.omie.app_secret'),
                    'param' => [$param],
                ])
                ->throw()
                ->json();
        } catch (RequestException $excecao) {
            $corpo = $excecao->response?->json();
            $fault = is_array($corpo)
                ? (string) ($corpo['faultstring'] ?? $corpo['message'] ?? $excecao->getMessage())
                : $excecao->getMessage();

            throw new RuntimeException('Omie '.$call.': '.$fault, previous: $excecao);
        }

        if (! is_array($resposta)) {
            throw new RuntimeException('Omie devolveu resposta inválida em '.$call.'.');
        }

        if (isset($resposta['faultstring'])) {
            throw new RuntimeException('Omie '.$call.': '.(string) $resposta['faultstring']);
        }

        return $resposta;
    }

    /**
     * Lista todas as páginas de um método Omie com lista em `$chaveLista`.
     *
     * @param  array<string, mixed>  $filtro
     * @return list<array<string, mixed>>
     */
    public static function listarTodas(
        string $recurso,
        string $call,
        string $chaveLista,
        array $filtro = [],
        int $porPagina = 50,
    ): array {
        $pagina = 1;
        $totalPaginas = 1;
        $itens = [];

        do {
            $resposta = self::chamar($recurso, $call, [
                ...$filtro,
                'pagina' => $pagina,
                'registros_por_pagina' => $porPagina,
                'apenas_importado_api' => 'N',
            ]);

            $lote = $resposta[$chaveLista] ?? [];
            if (is_array($lote)) {
                foreach ($lote as $item) {
                    if (is_array($item)) {
                        $itens[] = $item;
                    }
                }
            }

            $totalPaginas = max(1, (int) ($resposta['total_de_paginas'] ?? 1));
            $pagina++;
        } while ($pagina <= $totalPaginas);

        return $itens;
    }
}
