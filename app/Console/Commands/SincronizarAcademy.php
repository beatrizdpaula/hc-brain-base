<?php

namespace App\Console\Commands;

use App\Models\Treinamento;
use App\Support\AcademyCatalogo;
use Illuminate\Console\Command;

/**
 * Grava nos Treinamentos o link do HC Academy correspondente.
 * Seguro de repetir: só atualiza o campo `link` dos ids conhecidos.
 */
class SincronizarAcademy extends Command
{
    protected $signature = 'hc:sincronizar-academy';

    protected $description = 'Sincroniza os links do HC Academy nos Treinamentos do Brain';

    public function handle(): int
    {
        $base = AcademyCatalogo::urlBase();

        if ($base === '') {
            $this->components->error('hc.academy.url está vazio.');

            return self::FAILURE;
        }

        $atualizados = 0;
        $ausentes = 0;

        foreach (AcademyCatalogo::linksPorId() as $id => $link) {
            $treinamento = Treinamento::query()->find($id);

            if ($treinamento === null) {
                $ausentes++;
                $this->components->warn("Treinamento ausente: {$id}");

                continue;
            }

            if ($treinamento->link === $link) {
                continue;
            }

            $treinamento->update(['link' => $link]);
            $atualizados++;
            $this->line("  {$id} → {$link}");
        }

        $this->components->info(
            "Academy sync: {$atualizados} link(s) atualizado(s)"
            .($ausentes > 0 ? ", {$ausentes} id(s) sem registro" : '')
            .'.'
        );

        return self::SUCCESS;
    }
}
