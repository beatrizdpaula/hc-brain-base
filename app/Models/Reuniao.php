<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'empresa_id', 'tipo', 'data', 'horario', 'responsavel',
    'participantes', 'resumo', 'decisoes', 'proximos_passos', 'status',
    'conta_google_id', 'google_evento_id', 'link_meet',
])]
class Reuniao extends Model
{
    protected $table = 'reunioes';

    public const TIPOS = ['Abertura', 'Transferência', 'Dúvidas', 'Comercial', 'Alinhamento', 'Financeira'];

    public const STATUS = ['Concluída', 'Agendada', 'Cancelada'];

    public const SEM_EMPRESA = 'Sem empresa';

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'participantes' => 'array',
            'decisoes' => 'array',
            'proximos_passos' => 'array',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function contaGoogle(): BelongsTo
    {
        return $this->belongsTo(ContaGoogle::class);
    }

    /** Reunião vinda do Google Agenda pode ainda não estar ligada a uma empresa. */
    public function nomeDaEmpresa(): string
    {
        return $this->empresa?->nome ?? self::SEM_EMPRESA;
    }
}
