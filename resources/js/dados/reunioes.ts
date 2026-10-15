/* =========================================================
   REUNIÕES — histórico central, vinculado à empresa e ao sócio
   ========================================================= */

import { atualizar, obter, remover } from "../comum/api.ts";
import type { CorTag } from "./empresas.ts";

export type TipoReuniao =
    | "Abertura"
    | "Transferência"
    | "Dúvidas"
    | "Comercial"
    | "Alinhamento"
    | "Financeira"
    | "Interna";

export type StatusReuniao = "Concluída" | "Agendada" | "Cancelada";

export interface Reuniao {
    id: number;
    /** "Sem empresa" quando veio do Google Agenda sem pista de quem é. */
    empresa: string;
    empresaId: string | null;
    /** Sócio responsável da empresa, mostrado no modal da reunião. */
    socio: string | null;
    tipo: TipoReuniao;
    data: string;
    dataOrd: string;
    /** O horário ainda não é registrado na base; a agenda mostra "—" sem ele. */
    horario: string | null;
    responsavel: string;
    participantes: string[];
    resumo: string;
    decisoes: string[];
    proximosPassos: string[];
    status: StatusReuniao;
    /** Só as reuniões trazidas do Google Agenda têm link do Meet. */
    linkMeet: string | null;
    /** A cor do evento no Google Agenda (#rrggbb); sem ela, vale a do status. */
    cor: string | null;
}

/** A empresa como o filtro e o cadastro precisam dela: nome para ler, id para gravar. */
export interface EmpresaDaReuniao {
    id: string;
    nome: string;
}

export interface BaseReunioes {
    reunioes: Reuniao[];
    empresas: EmpresaDaReuniao[];
}

/** O que o formulário de reunião envia; o id fica fora porque vai na URL. */
export interface DadosDeReuniao {
    empresaId: string;
    tipo: string;
    data: string;
    horario: string | null;
    responsavel: string;
    status: string;
    resumo: string;
    participantes: string[];
    decisoes: string[];
    proximosPassos: string[];
}

export const tagDoTipoDeReuniao: Record<TipoReuniao, CorTag> = {
    Abertura: "green",
    Transferência: "blue",
    Dúvidas: "yellow",
    Comercial: "purple",
    Alinhamento: "gray",
    Financeira: "red",
    // Amarelo como o Banana que marca a reunião interna no Google Agenda.
    Interna: "yellow",
};

export const tagDoStatus: Record<StatusReuniao, CorTag> = {
    Concluída: "green",
    Agendada: "blue",
    Cancelada: "red",
};

/** Os tipos e os status aceitos, na ordem em que aparecem nos formulários. */
export const TIPOS_DE_REUNIAO: TipoReuniao[] = [
    "Abertura",
    "Transferência",
    "Dúvidas",
    "Comercial",
    "Alinhamento",
    "Financeira",
    "Interna",
];

export const STATUS_DE_REUNIAO: StatusReuniao[] = ["Agendada", "Concluída", "Cancelada"];

export function carregarReunioes(): Promise<BaseReunioes> {
    return obter<BaseReunioes>("/reunioes");
}

export async function salvarReuniao(id: number, dados: DadosDeReuniao): Promise<Reuniao> {
    const resposta = await atualizar<{ data: Reuniao }>(`/reunioes/${id}`, dados);
    return resposta.data;
}

export function excluirReuniao(id: number): Promise<void> {
    return remover(`/reunioes/${id}`);
}
