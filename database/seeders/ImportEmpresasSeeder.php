<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportEmpresasSeeder extends Seeder
{
    public function run(): void
    {
        $filePath = storage_path('app/empresas.xlsx');

        if (!file_exists($filePath)) {
            $this->command->error("Arquivo não encontrado em: {$filePath}");
            return;
        }

        // Carrega a planilha usando o PhpSpreadsheet diretamente
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $data = $worksheet->toArray();

        // Remove a primeira linha (cabeçalhos)
        array_shift($data);

        foreach ($data as $row) {
            // Ignora linhas totalmente vazias
            if (empty($row[0]) && empty($row[1])) {
                continue;
            }

            // Insere ou atualiza os dados na tabela do banco
            DB::table('empresas')->updateOrInsert(
                ['cnpj' => $row[1]], // Evita duplicar se o CNPJ já existir
                [
                    'codigo'            => $row[0] ?? null,  // Código
                    'cnpj'              => $row[1] ?? null,  // CNPJ
                    'razao_social'      => $row[2] ?? null,  // Razão Social
                    'status'            => $row[3] ?? null,  // Status
                    'regime_tributario' => $row[4] ?? null,  // Regime Tributário
                    'base_reduzida'     => $row[5] ?? null,  // Base Reduzida
                    'anexo'             => $row[6] ?? null,  // Anexo
                    'fator_r'           => $row[7] ?? null,  // Fator R
                    'socios'            => $row[8] ?? null,  // Sócios
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]
            );
        }

        $this->command->info('Empresas importadas com sucesso!');
    }
}