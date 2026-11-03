/* =========================================================
   PESQUISA — índice com empresas, sócios, reuniões e treinamentos
   ========================================================= */

import { obter } from "../comum/api.ts";

export type TipoResultado =
    "Treinamento" | "Pessoa" | "Documento" | "Reunião" | "Projeto";

export interface ResultadoPesquisa {
    type: TipoResultado;
    area: string;
    title: string;
    text: string;
    meta: string;
    /** Quando existe, o clique abre esse caminho em vez de só um modal. */
    caminho?: string | null;
    /** Download do arquivo, mesmo quando o clique abre o detalhe demonstrativo. */
    arquivo?: string | null;
    /** Material de exemplo da base semeada — não é carteira real. */
    demonstrativo?: boolean;
}

/** Escopo escolhido na tela → tipo correspondente no índice. */
export const scopeToType: Record<string, string | undefined> = {
    Tudo: "Tudo",
    Documentos: "Documento",
    Pessoas: "Pessoa",
    Projetos: "Projeto",
    Reuniões: "Reunião",
    Treinamentos: "Treinamento",
};

export function carregarPesquisa(): Promise<ResultadoPesquisa[]> {
    return obter<ResultadoPesquisa[]>("/pesquisa");
}
