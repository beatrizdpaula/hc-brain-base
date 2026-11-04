<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->foreignId('omie_app_id')
                ->nullable()
                ->after('omie_codigo_cliente')
                ->constrained('omie_apps')
                ->nullOnDelete();

            // O mesmo código Omie pode existir em apps diferentes.
            $table->dropUnique(['omie_codigo_cliente']);
            $table->unique(['omie_app_id', 'omie_codigo_cliente']);
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropUnique(['omie_app_id', 'omie_codigo_cliente']);
            $table->dropConstrainedForeignId('omie_app_id');
            $table->unique('omie_codigo_cliente');
        });
    }
};
