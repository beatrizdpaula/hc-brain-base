<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remove o histórico de aprendizagem semeado (demonstrativo).
 * A tela fica vazia até existir sync real de progresso.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('treinamento_historicos')->delete();
    }

    public function down(): void
    {
        // Dados demonstrativos não são restaurados.
    }
};
