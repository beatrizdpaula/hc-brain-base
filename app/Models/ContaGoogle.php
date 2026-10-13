<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uma conta Google conectada por um administrador. Os eventos com Meet da
 * agenda principal dela viram reuniões, com o nome da conta como responsável.
 */
#[Fillable(['email', 'nome', 'refresh_token', 'sincronizada_em', 'erro'])]
#[Hidden(['refresh_token'])]
class ContaGoogle extends Model
{
    protected $table = 'contas_google';

    protected function casts(): array
    {
        return [
            'refresh_token' => 'encrypted',
            'sincronizada_em' => 'datetime',
        ];
    }

    public function reunioes(): HasMany
    {
        return $this->hasMany(Reuniao::class);
    }
}
