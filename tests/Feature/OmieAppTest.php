<?php

namespace Tests\Feature;

use App\Models\OmieApp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OmieAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['hc.senha_semeada' => self::SENHA]);
        $this->seed();
    }

    private function autenticado(?string $perfil = 'Administrador'): static
    {
        $usuario = User::query()->firstOrFail();
        $usuario->update(['perfil' => $perfil]);

        return $this->actingAs($usuario);
    }

    public function test_admin_cadastra_app_omie(): void
    {
        $this->autenticado()
            ->postJson('/api/omie-apps', [
                'nome' => 'HC Contábil',
                'appKey' => 'chave-123',
                'appSecret' => 'segredo-abc',
                'ativo' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.nome', 'HC Contábil')
            ->assertJsonPath('data.appKey', 'chave-123')
            ->assertJsonMissingPath('data.appSecret');

        $app = OmieApp::query()->sole();
        $this->assertSame('segredo-abc', $app->app_secret);
    }

    public function test_colaborador_nao_cadastra_app_omie(): void
    {
        $this->autenticado('Colaborador')
            ->postJson('/api/omie-apps', [
                'nome' => 'HC Contábil',
                'appKey' => 'chave-123',
                'appSecret' => 'segredo-abc',
            ])
            ->assertForbidden();
    }

    public function test_lista_apps_sem_expor_secret(): void
    {
        OmieApp::query()->create([
            'nome' => 'App A',
            'app_key' => 'k1',
            'app_secret' => 's1',
            'ativo' => true,
        ]);

        $this->autenticado('Colaborador')
            ->getJson('/api/omie-apps')
            ->assertOk()
            ->assertJsonPath('podeGerenciar', false)
            ->assertJsonPath('apps.0.nome', 'App A')
            ->assertJsonMissingPath('apps.0.appSecret');
    }
}
