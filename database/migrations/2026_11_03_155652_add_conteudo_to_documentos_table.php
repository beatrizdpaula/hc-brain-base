<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O disco local do contêiner some a cada deploy. Guardar os bytes na base
 * deixa o download sobreviver mesmo quando o arquivo no storage sumiu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->binary('conteudo')->nullable()->after('arquivo');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE documentos MODIFY conteudo LONGBLOB NULL');
        }
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropColumn('conteudo');
        });
    }
};
