<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BLOB/BYTEA atrapalhava a leitura em alguns drivers (stream vazio → 404).
 * LONGTEXT + base64 no app sobrevive a MySQL, Postgres e SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE documentos MODIFY conteudo LONGTEXT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE documentos ALTER COLUMN conteudo TYPE TEXT USING encode(conteudo, \'escape\')');
        }
        // SQLite já trata binary como flexível; nada a fazer.
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE documentos MODIFY conteudo LONGBLOB NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE documentos ALTER COLUMN conteudo TYPE BYTEA USING conteudo::bytea');
        }
    }
};
