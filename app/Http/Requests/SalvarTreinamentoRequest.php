<?php

namespace App\Http\Requests;

use App\Models\Treinamento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalvarTreinamentoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::in(Treinamento::TIPOS)],
            'categoria' => ['required', 'string', 'max:120'],
            'nivel' => ['required', Rule::in(Treinamento::NIVEIS)],
            'descricao' => ['required', 'string', 'max:2000'],
            // Trilha, cursos e processo descrevem conteúdos específicos: uma
            // trilha diz quantos cursos tem, um manual não.
            'trilha' => ['nullable', 'string', 'max:120'],
            'cursos' => ['nullable', 'string', 'max:60'],
            'processo' => ['nullable', 'string', 'max:120'],
            'link' => ['nullable', 'url', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nivel' => 'nível',
            'link' => 'link do material',
        ];
    }

    public function paraOBanco(): array
    {
        $link = $this->input('link');

        return [
            'titulo' => $this->string('titulo')->toString(),
            'tipo' => $this->string('tipo')->toString(),
            'categoria' => $this->string('categoria')->toString(),
            'nivel' => $this->string('nivel')->toString(),
            'descricao' => $this->string('descricao')->toString(),
            'trilha' => $this->input('trilha'),
            'cursos' => $this->input('cursos'),
            'processo' => $this->input('processo'),
            'link' => is_string($link) && trim($link) !== '' ? trim($link) : null,
        ];
    }
}
