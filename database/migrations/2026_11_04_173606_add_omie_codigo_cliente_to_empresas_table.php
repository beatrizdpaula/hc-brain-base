<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Código do cliente no Omie. Sem CNPJ no Brain, o vínculo financeiro
 * é feito manualmente por esse código (único por empresa).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->unsignedBigInteger('omie_codigo_cliente')
                ->nullable()
                ->unique()
                ->after('automacao_id');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropUnique(['omie_codigo_cliente']);
            $table->dropColumn('omie_codigo_cliente');
        });
    }
};
