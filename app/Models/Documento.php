<?php

namespace App\Models;

use App\Support\TextoPdf;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['pasta_id', 'nome', 'extensao', 'arquivo', 'conteudo', 'tamanho', 'mime', 'demonstrativo', 'enviado_por'])]
#[Hidden(['conteudo'])]
class Documento extends Model
{
    private const PREFIXO_BASE64 = 'hc64:';

    protected $table = 'documentos';

    /**
     * Colunas da listagem — o blob fica de fora para não carregar megabytes
     * em cada abertura da tela de Documentos.
     *
     * @var list<string>
     */
    public const COLUNAS_LISTA = [
        'id',
        'pasta_id',
        'nome',
        'extensao',
        'arquivo',
        'tamanho',
        'mime',
        'demonstrativo',
        'enviado_por',
        'created_at',
        'updated_at',
    ];

    /**
     * Como cada extensão é agrupada na tela. O filtro de tipo e o ícone saem
     * daqui, então um `.docx` e um `.txt` se comportam igual sem que cada
     * lugar repita a lista.
     */
    private const TIPO_POR_EXTENSAO = [
        'pdf' => 'pdf',
        'doc' => 'doc', 'docx' => 'doc', 'txt' => 'doc',
        'xls' => 'sheet', 'xlsx' => 'sheet', 'csv' => 'sheet',
        'ppt' => 'slide', 'pptx' => 'slide',
        'png' => 'imagem', 'jpg' => 'imagem', 'jpeg' => 'imagem',
    ];

    protected function casts(): array
    {
        return [
            'tamanho' => 'integer',
            'demonstrativo' => 'boolean',
        ];
    }

    public function pasta(): BelongsTo
    {
        return $this->belongsTo(Pasta::class);
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por');
    }

    public function tipo(): string
    {
        return self::TIPO_POR_EXTENSAO[$this->extensao] ?? 'outro';
    }

    /** Nome com a extensão, que é como o arquivo aparece e é baixado. */
    public function nomeDoArquivo(): string
    {
        return "{$this->nome}.{$this->extensao}";
    }

    /**
     * Bytes do arquivo: disco primeiro; se o contêiner perdeu o storage
     * (deploy), cai no conteúdo guardado na base.
     */
    public function lerBytes(): ?string
    {
        if (is_string($this->arquivo) && $this->arquivo !== '') {
            $disco = Storage::disk(config('hc.documentos.disco'));
            if ($disco->exists($this->arquivo)) {
                $doDisco = $disco->get($this->arquivo);
                if (is_string($doDisco) && $doDisco !== '') {
                    $this->guardarBytes($doDisco);

                    return $doDisco;
                }
            }
        }

        $daBase = self::decodificarConteudo(
            $this->newQuery()->whereKey($this->getKey())->value('conteudo'),
        );

        if ($daBase !== null && $daBase !== '') {
            return $daBase;
        }

        if (array_key_exists('conteudo', $this->attributes)) {
            return self::decodificarConteudo($this->attributes['conteudo']);
        }

        return null;
    }

    /**
     * Se o blob sumiu mas o documento é uma lista de usuários da Sofia,
     * remonta o PDF a partir da base ao vivo e grava de novo.
     */
    public function tentarRecuperarBytes(): ?string
    {
        if ($this->extensao !== 'pdf' || ! $this->pareceListaDeUsuarios()) {
            return null;
        }

        $bytes = self::montarPdfListaUsuarios($this->nome);
        $this->guardarBytes($bytes);

        return $bytes;
    }

    /** Grava os bytes na base (e no disco, se houver caminho). */
    public function guardarBytes(string $bytes): void
    {
        if ($bytes === '') {
            return;
        }

        $empacotado = self::PREFIXO_BASE64.base64_encode($bytes);

        // Query builder: evita o cast mascarar um save que não chegou na base.
        static::query()->whereKey($this->getKey())->update([
            'conteudo' => $empacotado,
            'tamanho' => strlen($bytes),
            'updated_at' => now(),
        ]);

        $this->attributes['conteudo'] = $empacotado;
        $this->tamanho = strlen($bytes);

        if (! is_string($this->arquivo) || $this->arquivo === '') {
            return;
        }

        try {
            Storage::disk(config('hc.documentos.disco'))->put($this->arquivo, $bytes);
        } catch (\Throwable) {
            // Disco efêmero/cheio: a base já tem o arquivo.
        }
    }

    /**
     * Converte o valor cru da coluna (string, stream ou hc64) em bytes.
     */
    public static function decodificarConteudo(mixed $valor): ?string
    {
        if (is_resource($valor)) {
            $lido = stream_get_contents($valor);
            $valor = is_string($lido) ? $lido : null;
        }

        if (! is_string($valor) || $valor === '') {
            return null;
        }

        if (str_starts_with($valor, self::PREFIXO_BASE64)) {
            $decoded = base64_decode(substr($valor, strlen(self::PREFIXO_BASE64)), true);

            return $decoded === false ? null : $decoded;
        }

        return $valor;
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function conteudo(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): ?string => self::decodificarConteudo($value),
            set: function (?string $value): ?string {
                if ($value === null || $value === '') {
                    return null;
                }

                if (str_starts_with($value, self::PREFIXO_BASE64)) {
                    return $value;
                }

                return self::PREFIXO_BASE64.base64_encode($value);
            },
        );
    }

    private function pareceListaDeUsuarios(): bool
    {
        $nome = mb_strtolower($this->nome);

        return str_contains($nome, 'usuário')
            || str_contains($nome, 'usuario')
            || str_contains($nome, 'usuários')
            || str_contains($nome, 'usuarios');
    }

    public static function montarPdfListaUsuarios(string $titulo): string
    {
        $usuarios = User::query()
            ->orderBy('name')
            ->get(['name', 'email', 'perfil', 'area', 'status']);

        $linhas = $usuarios->values()->map(function (User $usuario, int $indice): string {
            $n = $indice + 1;

            return "{$n}. {$usuario->name} · {$usuario->email} · {$usuario->perfil} · {$usuario->area} · {$usuario->status}";
        })->all();

        $corpo = 'Total: '.$usuarios->count()."\n\n".implode("\n", $linhas);

        return TextoPdf::de($titulo !== '' ? $titulo : 'Lista de Usuários', $corpo);
    }

    /** Apaga o arquivo do disco junto com o registro — nada fica órfão lá. */
    public function apagarArquivo(): void
    {
        if ($this->arquivo !== null) {
            Storage::disk(config('hc.documentos.disco'))->delete($this->arquivo);
        }
    }
}
