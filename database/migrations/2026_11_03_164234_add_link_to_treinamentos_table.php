<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** URL do material (curso, manual, fluxograma) — separada do cadastro na tela. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treinamentos', function (Blueprint $table) {
            $table->string('link', 500)->nullable()->after('processo');
        });
    }

    public function down(): void
    {
        Schema::table('treinamentos', function (Blueprint $table) {
            $table->dropColumn('link');
        });
    }
};
