<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Support\OmieFinanceiroSync;
use Illuminate\Console\Command;
use Throwable;

/**
 * Atualiza o financeiro das empresas vinculadas a um app Omie + código.
 * Credenciais vêm da tabela omie_apps. Seguro de repetir.
 */
class SincronizarOmie extends Command
{
    protected $signature = 'hc:sincronizar-omie
        {--empresa= : Id da empresa no Brain (opcional)}';

    protected $description = 'Sincroniza o financeiro do Omie para empresas vinculadas a um app';

    public function handle(): int
    {
        $consulta = Empresa::query()
            ->with('omieApp')
            ->whereNotNull('omie_codigo_cliente')
            ->whereNotNull('omie_app_id')
            ->orderBy('nome');

        if ($this->option('empresa')) {
            $consulta->whereKey((string) $this->option('empresa'));
        }

        $empresas = $consulta->get();

        if ($empresas->isEmpty()) {
            $this->components->warn('Nenhuma empresa com app Omie e código de cliente para sincronizar.');

            return self::SUCCESS;
        }

        $ok = 0;
        $falhas = 0;

        foreach ($empresas as $empresa) {
            try {
                OmieFinanceiroSync::sincronizar($empresa);
                $ok++;
                $app = $empresa->omieApp?->nome ?? '?';
                $this->line("  {$empresa->nome} ({$app} / {$empresa->omie_codigo_cliente})");
            } catch (Throwable $erro) {
                $falhas++;
                $this->components->error("{$empresa->nome}: {$erro->getMessage()}");
                report($erro);
            }
        }

        $this->components->info("Omie: {$ok} empresa(s) atualizada(s)".($falhas > 0 ? ", {$falhas} falha(s)" : '').'.');

        return $falhas > 0 && $ok === 0 ? self::FAILURE : self::SUCCESS;
    }
}
