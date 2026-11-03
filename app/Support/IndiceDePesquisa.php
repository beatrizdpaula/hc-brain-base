<?php

namespace App\Support;

use App\Models\Documento;
use App\Models\Projeto;
use App\Models\Reuniao;
use App\Models\Socio;
use App\Models\Treinamento;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Índice da tela Pesquisar. Sai da base ao vivo (documentos, pessoas,
 * projetos, reuniões, treinamentos), não de uma tabela estática — senão um
 * PDF criado pela Sofia nunca aparece na busca.
 */
final class IndiceDePesquisa
{
    /**
     * @return list<array{type: string, area: string, title: string, text: string, meta: string, caminho: string|null}>
     */
    public static function todos(): array
    {
        return self::documentos()
            ->concat(self::pessoas())
            ->concat(self::projetos())
            ->concat(self::reunioes())
            ->concat(self::treinamentos())
            ->values()
            ->all();
    }

    /** @return Collection<int, array{type: string, area: string, title: string, text: string, meta: string, caminho: string|null}> */
    private static function documentos(): Collection
    {
        return Documento::query()
            ->with('pasta')
            ->select(Documento::COLUNAS_LISTA)
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (Documento $documento): array {
                $pasta = $documento->pasta?->nome ?: 'Documentos';
                $quando = $documento->updated_at?->format('d/m/Y') ?? '';

                return [
                    'type' => 'Documento',
                    'area' => $pasta,
                    'title' => $documento->nomeDoArquivo(),
                    'text' => "Arquivo na pasta {$pasta}.",
                    'meta' => strtoupper($documento->extensao).($quando !== '' ? " • Atualizado em {$quando}" : ''),
                    'caminho' => route('documentos.arquivo', $documento, false),
                ];
            });
    }

    /** @return Collection<int, array{type: string, area: string, title: string, text: string, meta: string, caminho: string|null}> */
    private static function pessoas(): Collection
    {
        $socios = Socio::query()
            ->with('empresa')
            ->orderBy('nome')
            ->get()
            ->map(function (Socio $socio): array {
                $empresa = $socio->empresa;
                $area = $empresa?->setor ?: 'Comercial';

                return [
                    'type' => 'Pessoa',
                    'area' => $area,
                    'title' => $empresa
                        ? "{$socio->nome} — {$empresa->nome}"
                        : $socio->nome,
                    'text' => trim(($socio->cargo ?: 'Sócio').($empresa ? ", responsável por {$empresa->nome}." : '.')),
                    'meta' => 'Cadastro de cliente',
                    'caminho' => $empresa
                        ? Telas::caminho('cliente', ['id' => $empresa->id])
                        : Telas::caminho('clientes'),
                ];
            });

        $equipe = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'perfil', 'area', 'status'])
            ->map(fn (User $usuario): array => [
                'type' => 'Pessoa',
                'area' => $usuario->area ?: 'Gestão',
                'title' => $usuario->name,
                'text' => "{$usuario->perfil} · {$usuario->email} · {$usuario->status}",
                'meta' => 'Equipe HC Brain',
                'caminho' => Telas::caminho('usuarios'),
            ]);

        return $socios->concat($equipe);
    }

    /** @return Collection<int, array{type: string, area: string, title: string, text: string, meta: string, caminho: string|null}> */
    private static function projetos(): Collection
    {
        return Projeto::query()
            ->orderBy('nome')
            ->get()
            ->map(fn (Projeto $projeto): array => [
                'type' => 'Projeto',
                'area' => $projeto->area ?: 'Projetos',
                'title' => $projeto->nome,
                'text' => (string) $projeto->descricao,
                'meta' => 'Status: '.strtolower((string) $projeto->status),
                'caminho' => Telas::caminho('projetos'),
            ]);
    }

    /** @return Collection<int, array{type: string, area: string, title: string, text: string, meta: string, caminho: string|null}> */
    private static function reunioes(): Collection
    {
        return Reuniao::query()
            ->with('empresa')
            ->orderByDesc('data')
            ->limit(200)
            ->get()
            ->map(function (Reuniao $reuniao): array {
                $data = $reuniao->data?->format('d/m/Y') ?? '';
                $empresa = $reuniao->empresa?->nome;
                $titulo = $reuniao->titulo;
                if ($empresa !== null && $empresa !== '') {
                    $titulo .= " — {$empresa}";
                }
                if ($data !== '') {
                    $titulo .= " — {$data}";
                }

                return [
                    'type' => 'Reunião',
                    'area' => $reuniao->empresa?->setor ?: 'Operações',
                    'title' => $titulo,
                    'text' => (string) ($reuniao->resumo ?: 'Reunião cadastrada na agenda HC.'),
                    'meta' => "{$reuniao->tipo} • {$reuniao->status}".($data !== '' ? " • {$data}" : ''),
                    'caminho' => Telas::caminho('reunioes'),
                ];
            });
    }

    /** @return Collection<int, array{type: string, area: string, title: string, text: string, meta: string, caminho: string|null}> */
    private static function treinamentos(): Collection
    {
        return Treinamento::query()
            ->orderBy('ordem')
            ->get()
            ->map(fn (Treinamento $item): array => [
                'type' => 'Treinamento',
                'area' => $item->categoria ?: 'Integração',
                'title' => $item->titulo,
                'text' => (string) $item->descricao,
                'meta' => trim($item->tipo.($item->nivel ? " • {$item->nivel}" : '').' • Sistema de treinamento'),
                'caminho' => Telas::caminho('treinamentos'),
            ]);
    }
}
