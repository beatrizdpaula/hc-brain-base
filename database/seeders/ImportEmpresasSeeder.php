<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

        // Remove o cabeçalho
        array_shift($data);

        foreach ($data as $row) {
            // Ignora linhas totalmente vazias
            if (empty($row[0]) && empty($row[1])) {
                continue;
            }

            // Preenche todos os campos com valores seguros para evitar erros de NOT NULL
            DB::table('empresas')->insert([
                'id'         => (string) Str::uuid(),
                'nome'       => $row[2] ?? $row[0] ?? 'Empresa Sem Nome',
                'setor'      => $row[3] ?? 'Geral',
                'status'     => $row[4] ?? 'Ativo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Empresas importadas com sucesso!');
    }
}