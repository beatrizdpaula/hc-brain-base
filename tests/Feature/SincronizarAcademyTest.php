<?php

namespace Tests\Feature;

use App\Models\Treinamento;
use App\Support\AcademyCatalogo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SincronizarAcademyTest extends TestCase
{
    use RefreshDatabase;

    public function test_sincroniza_links_do_academy_nos_treinamentos(): void
    {
        $this->seed();

        Treinamento::query()->whereKey('curso-abertura')->update(['link' => null]);

        $this->artisan('hc:sincronizar-academy')->assertSuccessful();

        $esperado = AcademyCatalogo::urlPara('/cursos/17');

        $this->assertSame(
            $esperado,
            Treinamento::query()->whereKey('curso-abertura')->value('link'),
        );

        $this->assertSame(
            AcademyCatalogo::urlPara('/trilhas/1'),
            Treinamento::query()->whereKey('trilha-atendimento')->value('link'),
        );

        $this->assertSame(
            AcademyCatalogo::urlPara('/manuais/9'),
            Treinamento::query()->whereKey('manual-processo')->value('link'),
        );
    }

    public function test_seeder_ja_grava_link_do_academy(): void
    {
        $this->seed();

        $this->assertSame(
            AcademyCatalogo::urlPara('/cursos/18'),
            Treinamento::query()->whereKey('curso-atendimento')->value('link'),
        );
    }
}
