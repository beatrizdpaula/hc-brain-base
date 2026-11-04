<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AtualizarOmieEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $empresa = $this->route('empresa');
        $empresaId = is_object($empresa) ? $empresa->getKey() : $empresa;
        $appId = $this->input('omieAppId');

        return [
            'omieAppId' => ['nullable', 'integer', 'exists:omie_apps,id', 'required_with:omieCodigoCliente'],
            'omieCodigoCliente' => [
                'nullable',
                'integer',
                'min:1',
                'required_with:omieAppId',
                Rule::unique('empresas', 'omie_codigo_cliente')
                    ->where(fn ($query) => $query->where('omie_app_id', $appId))
                    ->ignore($empresaId),
            ],
        ];
    }
}
