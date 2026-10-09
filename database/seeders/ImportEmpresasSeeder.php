<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Empresa;
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
            if (empty($row[0]) && empty($row[1])) {
                continue;
            }

            // Mapeia o nome/razão social
            $nomeEmpresa = $row[2] ?? $row[0] ?? 'Empresa Sem Nome';

            // Cria o registo usando o Model da aplicação para gerir a chave primária e colunas
            Empresa::create([
                'nome' => $nomeEmpresa,
            ]);
        }

        $this->command->info('Empresas importadas com sucesso!');
    }
}