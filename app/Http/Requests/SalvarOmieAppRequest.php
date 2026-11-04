<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalvarOmieAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->ehAdministrador() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $app = $this->route('omieApp');
        $id = is_object($app) ? $app->getKey() : null;
        $atualizando = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'nome' => ['required', 'string', 'max:120'],
            'appKey' => ['required', 'string', 'max:120', Rule::unique('omie_apps', 'app_key')->ignore($id)],
            'appSecret' => [$atualizando ? 'nullable' : 'required', 'string', 'max:255'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
