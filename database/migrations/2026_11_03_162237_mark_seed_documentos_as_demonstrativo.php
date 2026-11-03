<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Marca os arquivos do seeder antigo que ainda estão na base publicada. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('documentos')
            ->where(function ($consulta): void {
                $consulta->where('mime', 'text/plain')
                    ->orWhereIn('nome', [
                        'Cadastro de clientes',
                        'Proposta comercial',
                        'Ata reunião Empresa X',
                        'Relatório mensal',
                        'Fluxos e procedimentos',
                    ]);
            })
            ->update(['demonstrativo' => true]);
    }

    public function down(): void
    {
        DB::table('documentos')
            ->whereIn('nome', [
                'Cadastro de clientes',
                'Proposta comercial',
                'Ata reunião Empresa X',
                'Relatório mensal',
                'Fluxos e procedimentos',
            ])
            ->update(['demonstrativo' => false]);
    }
};
