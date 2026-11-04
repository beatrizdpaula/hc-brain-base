<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SincronizarOmieTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['hc.senha_semeada' => self::SENHA]);
        $this->seed();
    }

    private function autenticado(): static
    {
        return $this->actingAs(User::firstOrFail());
    }

    public function test_salva_codigo_omie_na_empresa(): void
    {
        $empresa = Empresa::query()->firstOrFail();

        $this->autenticado()
            ->patchJson('/api/empresas/'.$empresa->id.'/omie', [
                'omieCodigoCliente' => 424242,
            ])
            ->assertOk()
            ->assertJsonPath('data.omieCodigoCliente', 424242);

        $this->assertDatabaseHas('empresas', [
            'id' => $empresa->id,
            'omie_codigo_cliente' => 424242,
        ]);
    }

    public function test_sincroniza_financeiro_a_partir_do_omie(): void
    {
        config([
            'hc.omie.app_key' => 'chave-teste',
            'hc.omie.app_secret' => 'segredo-teste',
        ]);

        $empresa = Empresa::query()->firstOrFail();
        $empresa->update(['omie_codigo_cliente' => 999001]);

        Http::fake([
            'app.omie.com.br/api/v1/financas/contareceber/*' => Http::response([
                'pagina' => 1,
                'total_de_paginas' => 1,
                'conta_receber_cadastro' => [
                    [
                        'codigo_lancamento_omie' => 1,
                        'valor_documento' => 1500.5,
                        'status_titulo' => 'RECEBIDO',
                        'data_emissao' => '10/01/2026',
                        'codigo_categoria' => '1.01.01',
                        'numero_documento_fiscal' => 'NF-1',
                    ],
                    [
                        'codigo_lancamento_omie' => 2,
                        'valor_documento' => 800,
                        'status_titulo' => 'A VENCER',
                        'data_vencimento' => '20/03/2026',
                        'codigo_categoria' => '1.01.01',
                        'numero_documento_fiscal' => 'NF-2',
                    ],
                ],
            ]),
            'app.omie.com.br/api/v1/financas/contapagar/*' => Http::response([
                'pagina' => 1,
                'total_de_paginas' => 1,
                'conta_pagar_cadastro' => [
                    [
                        'codigo_lancamento_omie' => 9,
                        'valor_documento' => 200,
                        'status_titulo' => 'A VENCER',
                        'data_vencimento' => '15/02/2026',
                        'codigo_categoria' => '2.01.01',
                    ],
                ],
            ]),
        ]);

        $this->artisan('hc:sincronizar-omie', ['--empresa' => $empresa->id])
            ->assertSuccessful();

        $empresa->refresh()->load(['financeiro', 'receitasMensais', 'transacoes']);

        $this->assertSame(1500.5, (float) $empresa->financeiro->total);
        $this->assertSame(800.0, (float) $empresa->financeiro->receber);
        $this->assertSame(200.0, (float) $empresa->financeiro->despesas);
        $this->assertGreaterThan(0, $empresa->receitasMensais->count());
        $this->assertGreaterThan(0, $empresa->transacoes->count());
    }

    public function test_sincronizar_pela_api_exige_codigo(): void
    {
        $empresa = Empresa::query()->firstOrFail();
        $empresa->update(['omie_codigo_cliente' => null]);

        $this->autenticado()
            ->postJson('/api/empresas/'.$empresa->id.'/omie/sincronizar')
            ->assertStatus(422);
    }
}
