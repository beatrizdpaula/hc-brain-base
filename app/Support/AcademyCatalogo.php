<?php

namespace App\Support;

/**
 * Mapa estático do HC Academy → Treinamentos do Brain.
 *
 * Só URLs públicas do catálogo (sem login embutido). O material (vídeo Mux /
 * PDF) continua no Academy; o Brain guarda o link para “Acessar material”.
 */
final class AcademyCatalogo
{
    /**
     * id do treinamento no Brain => caminho relativo no Academy.
     *
     * @return array<string, string>
     */
    public static function caminhosPorId(): array
    {
        return [
            // Cursos
            'curso-abertura' => '/cursos/17',
            'curso-atendimento' => '/cursos/18',
            'curso-processos' => '/cursos/19',
            'curso-registros' => '/cursos/21',
            'curso-simples' => '/cursos/35',
            'curso-contabilidade' => '/cursos/16',
            'curso-gessta' => '/cursos/20',

            // Trilhas
            'trilha-mentalidade' => '/trilhas/4',
            'trilha-regime' => '/trilhas/3',
            'trilha-comercial' => '/trilhas/2',
            'trilha-atendimento' => '/trilhas/1',

            // Manuais
            'manual-processo' => '/manuais/9',
            'manual-drive' => '/manuais/8',
            'manual-propostas' => '/manuais/10',
            'manual-planilhas' => '/manuais/14',
            'manual-modelos' => '/manuais/7',

            // Fluxogramas
            'fluxo-comercial' => '/fluxogramas/4/visualizar',
            'fluxo-legalizacao' => '/fluxogramas/2/visualizar',
            'fluxo-contabil' => '/fluxogramas/1/visualizar',
        ];
    }

    public static function urlBase(): string
    {
        return rtrim((string) config('hc.academy.url'), '/');
    }

    public static function urlPara(string $caminho): string
    {
        $caminho = str_starts_with($caminho, '/') ? $caminho : '/'.$caminho;

        return self::urlBase().$caminho;
    }

    /**
     * @return array<string, string> id => url absoluta
     */
    public static function linksPorId(): array
    {
        $links = [];

        foreach (self::caminhosPorId() as $id => $caminho) {
            $links[$id] = self::urlPara($caminho);
        }

        return $links;
    }
}
