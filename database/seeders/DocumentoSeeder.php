<?php

namespace Database\Seeders;

use App\Models\Documento;
use App\Models\Pasta;
use App\Support\TextoPdf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * As pastas da HC e alguns arquivos de exemplo. São demonstrativos de
 * propósito: a pesquisa e a tela de Documentos deixam isso explícito.
 */
class DocumentoSeeder extends Seeder
{
    private const PASTAS = ['Comercial', 'Projetos', 'Financeiro', 'Operações', 'Marketing'];

    /** pasta, nome do documento e extensão. */
    private const DOCUMENTOS = [
        ['Comercial', 'Cadastro de clientes', 'pdf'],
        ['Comercial', 'Proposta comercial', 'pdf'],
        ['Comercial', 'Ata reunião Empresa X', 'pdf'],
        ['Financeiro', 'Relatório mensal', 'pdf'],
        ['Operações', 'Fluxos e procedimentos', 'pdf'],
    ];

    public function run(): void
    {
        $disco = Storage::disk(config('hc.documentos.disco'));

        Documento::all()->each->apagarArquivo();
        Documento::query()->delete();

        $pastas = [];
        foreach (self::PASTAS as $ordem => $nome) {
            $pastas[$nome] = Pasta::updateOrCreate(['nome' => $nome], ['ordem' => $ordem]);
        }

        foreach (self::DOCUMENTOS as [$pasta, $nome, $extensao]) {
            $pastaId = $pastas[$pasta]->id;
            $caminho = "documentos/{$pastaId}/".str()->random(40).".{$extensao}";
            $texto = "Material demonstrativo do HC Brain.\n\n"
                ."Este arquivo é só exemplo da base semeada.\n"
                ."Não representa um documento real da carteira da HC.\n\n"
                ."Pasta: {$pasta}\n"
                ."Nome: {$nome}";
            $bytes = TextoPdf::de($nome.' (demonstrativo)', $texto);

            $disco->put($caminho, $bytes);

            Documento::create([
                'pasta_id' => $pastaId,
                'nome' => $nome,
                'extensao' => $extensao,
                'arquivo' => $caminho,
                'conteudo' => $bytes,
                'tamanho' => strlen($bytes),
                'mime' => 'application/pdf',
                'demonstrativo' => true,
            ]);
        }
    }
}
