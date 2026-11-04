<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalvarOmieAppRequest;
use App\Models\OmieApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OmieAppController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $apps = OmieApp::query()
            ->orderBy('nome')
            ->get()
            ->map(fn (OmieApp $app) => $this->resumo($app, incluirSecret: false));

        return response()->json([
            'podeGerenciar' => $request->user()?->ehAdministrador() ?? false,
            'apps' => $apps,
        ]);
    }

    public function store(SalvarOmieAppRequest $request): JsonResponse
    {
        $app = OmieApp::query()->create($this->dadosDe($request));

        return response()->json(['data' => $this->resumo($app)], 201);
    }

    public function update(SalvarOmieAppRequest $request, OmieApp $omieApp): JsonResponse
    {
        $dados = $this->dadosDe($request);

        if (! filled($request->validated('appSecret'))) {
            unset($dados['app_secret']);
        }

        $omieApp->update($dados);

        return response()->json(['data' => $this->resumo($omieApp->fresh())]);
    }

    public function destroy(Request $request, OmieApp $omieApp): Response
    {
        abort_unless($request->user()?->ehAdministrador() ?? false, 403);

        $omieApp->delete();

        return response()->noContent();
    }

    /**
     * @return array{nome: string, app_key: string, app_secret?: string, ativo: bool}
     */
    private function dadosDe(SalvarOmieAppRequest $request): array
    {
        $dados = [
            'nome' => $request->string('nome')->toString(),
            'app_key' => $request->string('appKey')->toString(),
            'ativo' => $request->boolean('ativo', true),
        ];

        if ($request->filled('appSecret')) {
            $dados['app_secret'] = $request->string('appSecret')->toString();
        }

        return $dados;
    }

    /** @return array{id: int, nome: string, appKey: string, ativo: bool, temSecret: bool} */
    private function resumo(OmieApp $app, bool $incluirSecret = false): array
    {
        $resumo = [
            'id' => $app->id,
            'nome' => $app->nome,
            'appKey' => $app->app_key,
            'ativo' => $app->ativo,
            'temSecret' => filled($app->app_secret),
        ];

        if ($incluirSecret) {
            $resumo['appSecret'] = $app->app_secret;
        }

        return $resumo;
    }
}
