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

        return [
            'omieCodigoCliente' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('empresas', 'omie_codigo_cliente')->ignore(
                    is_object($empresa) ? $empresa->getKey() : $empresa
                ),
            ],
        ];
    }
}
