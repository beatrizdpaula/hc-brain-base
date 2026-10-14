/* =========================================================
   AGENDAS DO GOOGLE — contas cujos eventos com Meet viram reunião
   Conectar sai do HC Brain para a tela de consentimento do Google,
   por isso é um link no Blade e não uma chamada da API.
   ========================================================= */

import { enviar, obter, remover } from "../comum/api.ts";

export interface ContaGoogle {
    id: number;
    email: string;
    nome: string;
    /** Data e hora da última leitura bem-sucedida, no horário de Brasília. */
    sincronizadaEm: string | null;
    /** O motivo da última leitura ter falhado; some na próxima que der certo. */
    erro: string | null;
}

export interface AgendasGoogle {
    /** Sem o cliente OAuth no ambiente não há como conectar nenhuma conta. */
    configurada: boolean;
    contas: ContaGoogle[];
}

export function carregarAgendas(): Promise<AgendasGoogle> {
    return obter<AgendasGoogle>("/agendas-google");
}

/** Lê as agendas na hora e devolve como ficaram, com o erro de cada conta se houver. */
export function sincronizarAgendas(): Promise<AgendasGoogle> {
    return enviar<AgendasGoogle>("/agendas-google/sincronizar", {});
}

export function desconectarAgenda(id: number): Promise<void> {
    return remover(`/agendas-google/${id}`);
}
