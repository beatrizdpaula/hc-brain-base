<?php

namespace Database\Seeders;

use App\Models\ResultadoPesquisa;
use App\Models\SugestaoSofia;
use Illuminate\Database\Seeder;

/**
 * Sugestões iniciais da Sofia. O índice da tela Pesquisar sai da base ao
 * vivo (IndiceDePesquisa); não semeamos mais resultados fantasma de PDF.
 */
class PesquisaSeeder extends Seeder
{
    /** O ícone é um nome da Lucide — o mesmo vocabulário do resto da interface. */
    private const SUGESTOES = [
        ['calendar-days', 'Quais reuniões estão agendadas hoje?'],
        ['building-2', 'Quais reuniões tivemos com a Empresa X?'],
        ['users', 'Quem é o sócio responsável pela Empresa Y?'],
        ['file-text', 'Quais documentos temos sobre a Empresa X?'],
        ['circle-dollar-sign', 'Qual o honorário atual da Empresa X?'],
        ['rocket', 'Quais projetos estão em andamento?'],
    ];

    public function run(): void
    {
        ResultadoPesquisa::query()->delete();

        SugestaoSofia::query()->delete();
        foreach (self::SUGESTOES as $ordem => [$icone, $texto]) {
            SugestaoSofia::create(compact('icone', 'texto', 'ordem'));
        }
    }
}
