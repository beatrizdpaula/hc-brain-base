<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\IndiceDePesquisa;
use Illuminate\Http\JsonResponse;

class PesquisaController extends Controller
{
    /**
     * O índice inteiro vai de uma vez: os filtros da tela são instantâneos e
     * ficam no estado compartilhado, sem uma ida ao servidor por tecla.
     * A fonte é a base ao vivo — documento criado pela Sofia entra na hora.
     */
    public function index(): JsonResponse
    {
        return response()->json(IndiceDePesquisa::todos());
    }
}
