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

        // Carrega a planilha usando o PhpSpreadsheet
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $data = $worksheet->toArray();

        // Remove a primeira linha (cabeçalhos)
        array_shift($data);

        foreach ($data as $row) {
            // Ignora linhas vazias
            if (empty($row[0]) && empty($row[1])) {
                continue;
            }

            // Repare na ordem das colunas vindo da planilha no log:
            // $row[0] = CNPJ sem formatação (ex: 40460376000101)
            // $row[1] = Código (ex: 190)
            // $row[2] = CNPJ formatado (ex: 41.649.408/0001-76)
            // $row[3] = Status (ex: Inativo)
            // $row[4] = Regime Tributário (ex: Simples Nacional)

            DB::table('empresas')->insert([
                'codigo'            => $row[1] ?? null,
                'razao_social'      => $row[2] ?? null,
                'status'            => $row[3] ?? null,
                'regime_tributario' => $row[4] ?? null,
                'base_reduzida'     => $row[5] ?? null,
                'anexo'             => $row[6] ?? null,
                'fator_r'           => $row[7] ?? null,
                'socios'            => $row[8] ?? null,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }

        $this->command->info('Empresas importadas com sucesso!');
    }
}