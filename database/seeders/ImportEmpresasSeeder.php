<?php

namespace Database\Seeders;

Illuminate\Database\Seeder;
Illuminate\Support\Facades\DB;
Maatwebsite\Excel\Facades\Excel;

class ImportEmpresasSeeder extends Seeder
{
    public function run(): void
    {
        $filePath = storage_path('app/empresas.xlsx');

        if (!file_exists($filePath)) {
            $this->command->error("Arquivo não encontrado em: {$filePath}");
            return;
        }

        // Leitura das linhas da planilha
        $data = Excel::toArray([], $filePath)[0];

        // Remove a primeira linha (cabeçalhos)
        array_shift($data);

        foreach ($data as $row) {
            // Ignora linhas vazias
            if (empty($row[0]) && empty($row[1])) {
                continue;
            }

            DB::table('companies')->updateOrInsert(
                ['cnpj' => $row[1]], // Evita duplicar se o CNPJ já existir
                [
                    'code'             => $row[0] ?? null,  // Código
                    'cnpj'             => $row[1] ?? null,  // CNPJ
                    'social_reason'    => $row[2] ?? null,  // Razão Social
                    'status'           => $row[3] ?? null,  // Status
                    'tax_regime'       => $row[4] ?? null,  // Regime Tributário
                    'reduced_base'     => $row[5] ?? null,  // Base Reduzida
                    'annex'            => $row[6] ?? null,  // Anexo
                    'fator_r'          => $row[7] ?? null,  // Fator R
                    'partners'         => $row[8] ?? null,  // Sócios
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]
            );
        }

        $this->command->info('Empresas importadas com sucesso!');
    }
}