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

        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $data = $worksheet->toArray();

        // Remove a linha de cabeçalho
        array_shift($data);

        foreach ($data as $row) {
            if (empty($row[0]) && empty($row[1])) {
                continue;
            }

            // Identifica o nome/razão social na planilha
            $nomeEmpresa = $row[2] ?? $row[0] ?? 'Empresa Sem Nome';

            DB::table('empresas')->insert([
                'nome'       => $nomeEmpresa,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Empresas importadas com sucesso!');
    }
}