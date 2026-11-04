<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Apps Omie da HC. Cada empresa financeira aponta para um app + código
 * de cliente — a HC tem vários apps, então a chave não fica no .env.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omie_apps', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('app_key');
            $table->text('app_secret');
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique('app_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('omie_apps');
    }
};
