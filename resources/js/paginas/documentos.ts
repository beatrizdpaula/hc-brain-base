/* =========================================================
   TELA DOCUMENTOS
   Os arquivos são reais: sobem para o disco do servidor, voltam
   pela sessão e somem do disco quando excluídos. Pasta escolhida,
   busca, tipo e modo de exibição ficam no estado compartilhado —
   ao voltar para cá, a navegação continua de onde parou.
   ========================================================= */

import {
    aparenciaPorTipo,
    carregarDocumentos,
    criarPasta,
    enderecoDoArquivo,
    enviarDocumento,
    excluirDocumento,
    excluirPasta,
    podePrevisualizar,
    salvarDocumento,
    salvarPasta,
    TODAS_AS_PASTAS,
    type Documento,
    type Pasta,
} from "../dados/documentos.ts";
import { combina, termoDeBusca } from "../comum/busca.ts";
import { alvoMaisProximo, campo, dado, porId, selecao, todos } from "../comum/dom.ts";
import {
    atualizarSecao,
    observarEstado,
    obterSecao,
    type VisualizacaoDocumentos,
} from "../comum/estado.ts";
import { escapar, plural } from "../comum/formato.ts";
import { abrirFormulario, confirmarAcao } from "../comum/formulario.ts";
import { desenharIcones, icone } from "../comum/icones.ts";
import { showModal } from "../comum/modal.ts";
import { iniciarPagina } from "../comum/shell.ts";

iniciarPagina("documentos");

const grid = porId("documentsGrid");
const menu = porId("folderMenu");
const busca = campo("documentSearch");
const tipo = selecao("documentType");
const contador = porId("counter");
const alternadorDeVisao = porId("documentViewToggle");
const acoesDaPasta = porId("acoesDaPasta");

let { pastas, documentos } = await carregarDocumentos();

/** "Explorar" navega por pasta; "Recentes" ignora a pasta e ordena por data. */
let ordem: "pastas" | "recentes" = "pastas";

/** URL do blob aberto na prévia — revogada ao fechar. */
let urlDaPrevia: string | null = null;

function pastaAberta(): Pasta | null {
    const { pasta } = obterSecao("documentos");
    return pastas.find((item) => item.nome === pasta) ?? null;
}

function opcoesDePasta(): { valor: string; rotulo: string }[] {
    return pastas.map((item) => ({ valor: String(item.id), rotulo: item.nome }));
}

async function recarregar(): Promise<void> {
    ({ pastas, documentos } = await carregarDocumentos());
    renderMenu();
    renderGrid();
}

function documentosFiltrados(): Documento[] {
    const estado = obterSecao("documentos");
    const palavras = termoDeBusca(estado.busca);

    const filtrados = documentos.filter(
        (item) =>
            combina(item.nome, palavras) &&
            (estado.tipo === "all" || item.tipo === estado.tipo) &&
            (ordem === "recentes" ||
                estado.pasta === TODAS_AS_PASTAS ||
                item.pasta === estado.pasta),
    );

    // A API já devolve por data; em "Explorar" a ordem alfabética ajuda mais
    // a encontrar um arquivo dentro da pasta.
    return ordem === "recentes"
        ? filtrados
        : [...filtrados].sort((a, b) => a.nome.localeCompare(b.nome, "pt-BR"));
}

function renderMenu(): void {
    const { pasta } = obterSecao("documentos");
    const total = documentos.length;

    const itens = [
        { nome: TODAS_AS_PASTAS, total },
        ...pastas.map((item) => ({ nome: item.nome, total: item.total })),
    ];

    menu.innerHTML = itens
        .map(
            (item) => `
        <button type="button" class="${item.nome === pasta ? "active" : ""}" data-folder="${escapar(item.nome)}">
          ${icone(item.nome === TODAS_AS_PASTAS ? "folder-open" : "folder")}
          <span>${escapar(item.nome)}</span>
          <small>${item.total}</small>
        </button>
      `,
        )
        .join("");

    todos("[data-folder]", menu).forEach((botao) => {
        botao.addEventListener("click", () =>
            atualizarSecao("documentos", { pasta: dado(botao, "folder") }),
        );
    });

    desenharIcones(menu);
}

function renderGrid(): void {
    const estado = obterSecao("documentos");
    const itens = documentosFiltrados();
    const aberta = pastaAberta();

    porId("currentFolder").textContent =
        ordem === "recentes"
            ? "Alterados por último"
            : (aberta?.nome ?? "Todas as pastas");

    acoesDaPasta.hidden = ordem === "recentes" || aberta === null;

    grid.classList.toggle("list-view", estado.visualizacao === "list");
    todos("[data-document-view]", alternadorDeVisao).forEach((botao) => {
        botao.classList.toggle(
            "active",
            dado(botao, "documentView") === estado.visualizacao,
        );
    });

    contador.textContent = plural(itens.length, "item encontrado", "itens encontrados");

    grid.innerHTML = itens.length
        ? itens
              .map((item) => {
                  const aparencia = aparenciaPorTipo[item.tipo];
                  const previa = podePrevisualizar(item);
                  return `
            <article class="document-card" data-documento="${item.id}">
              <span class="icone-quadro ${aparencia.cor}">${icone(aparencia.icone)}</span>
              <div class="document-info">
                <h3 title="${escapar(item.exibicao)}">${escapar(item.exibicao)}</h3>
                <p>${escapar(item.detalhe)} · ${escapar(item.pasta)}</p>
                <div class="document-acoes">
                  ${
                      previa
                          ? `<button type="button" class="document-acao" data-previsualizar="${item.id}" aria-label="Pré-visualizar ${escapar(item.exibicao)}">${icone("eye")}</button>`
                          : ""
                  }
                  <button type="button" class="document-acao" data-baixar="${item.id}" aria-label="Baixar ${escapar(item.exibicao)}" ${item.temArquivo ? "" : "disabled"}>
                    ${icone("download")}
                  </button>
                </div>
              </div>
              <button type="button" class="icon-button document-more" data-opcoes="${item.id}" aria-label="Opções de ${escapar(item.exibicao)}">
                ${icone("ellipsis-vertical")}
              </button>
            </article>
          `;
              })
              .join("")
        : `<div class="empty-state">Nenhum documento corresponde à pasta e aos filtros atuais.</div>`;

    todos("[data-previsualizar]", grid).forEach((botao) => {
        botao.addEventListener("click", (evento) => {
            evento.stopPropagation();
            void previsualizar(Number(dado(botao, "previsualizar")));
        });
    });

    todos("[data-baixar]", grid).forEach((botao) => {
        botao.addEventListener("click", (evento) => {
            evento.stopPropagation();
            void baixar(Number(dado(botao, "baixar")));
        });
    });

    todos("[data-opcoes]", grid).forEach((botao) => {
        botao.addEventListener("click", (evento) => {
            evento.stopPropagation();
            abrirOpcoes(Number(dado(botao, "opcoes")));
        });
    });

    desenharIcones(grid);
}

function porIdDoDocumento(id: number): Documento | undefined {
    return documentos.find((item) => item.id === id);
}

async function carregarBlob(documento: Documento): Promise<Blob | null> {
    if (!documento.temArquivo) {
        showModal(
            documento.exibicao,
            "Este registro não tem arquivo anexado. Envie o arquivo novamente para poder baixá-lo.",
        );
        return null;
    }

    try {
        const resposta = await fetch(enderecoDoArquivo(documento.id), {
            credentials: "same-origin",
            headers: { Accept: "application/pdf,application/octet-stream,*/*" },
        });

        if (!resposta.ok) {
            showModal(
                documento.exibicao,
                resposta.status === 404
                    ? "Não encontrei o arquivo no servidor. Peça à Sofia para gerar de novo, ou envie o PDF outra vez."
                    : "Não consegui abrir este arquivo agora. Tente de novo em instantes.",
            );
            return null;
        }

        return await resposta.blob();
    } catch {
        showModal(
            documento.exibicao,
            "Falha de rede ao baixar o arquivo. Confira a conexão e tente de novo.",
        );
        return null;
    }
}

/** Baixa via fetch (mesma sessão), sem trocar a página por um 404. */
async function baixar(id: number): Promise<void> {
    const documento = porIdDoDocumento(id);
    if (!documento) return;

    const blob = await carregarBlob(documento);
    if (!blob) return;

    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = documento.exibicao;
    link.rel = "noopener";
    document.body.append(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}

function tituloDaPrevia(documento: Documento): string {
    if (documento.tipo === "pdf") return "Pré-visualização de PDF";
    if (documento.tipo === "imagem") return "Pré-visualização de imagem";
    if (documento.extensao === "txt") return "Pré-visualização de texto";
    return "Pré-visualização do documento";
}

function garantirModalDePrevia(): HTMLElement {
    let modal = document.getElementById("documentPreviewModal");
    if (modal) return modal;

    const wrapper = document.createElement("div");
    wrapper.innerHTML = `
      <div class="modal document-preview-modal" id="documentPreviewModal" role="dialog" aria-modal="true" aria-labelledby="documentPreviewTitle">
        <div class="modal-box document-preview-box">
          <div class="document-preview-topo">
            <div class="document-preview-titulo">
              <h2 id="documentPreviewTitle">Pré-visualização</h2>
              <p class="muted" id="documentPreviewFonte"></p>
            </div>
            <div class="document-preview-topo-acoes">
              <button type="button" class="secondary" id="documentPreviewDownload">
                ${icone("download")}
                <span>Download</span>
              </button>
              <button type="button" class="icon-button" id="documentPreviewClose" aria-label="Fechar">
                ${icone("x")}
              </button>
            </div>
          </div>
          <div class="document-preview-corpo" id="documentPreviewBody"></div>
        </div>
      </div>
    `;
    document.body.append(...wrapper.children);
    modal = porId("documentPreviewModal");

    porId("documentPreviewClose").addEventListener("click", fecharPrevia);
    modal.addEventListener("click", (evento) => {
        if (evento.target === modal) fecharPrevia();
    });
    document.addEventListener("keydown", (evento) => {
        if (evento.key === "Escape" && modal?.classList.contains("show")) {
            fecharPrevia();
        }
    });

    desenharIcones(modal);
    return modal;
}

function fecharPrevia(): void {
    const modal = document.getElementById("documentPreviewModal");
    modal?.classList.remove("show");
    document.documentElement.classList.remove("modal-aberto");
    document.body.classList.remove("modal-aberto");
    if (urlDaPrevia) {
        URL.revokeObjectURL(urlDaPrevia);
        urlDaPrevia = null;
    }
    const corpo = document.getElementById("documentPreviewBody");
    if (corpo) corpo.innerHTML = "";
}

async function previsualizar(id: number): Promise<void> {
    const documento = porIdDoDocumento(id);
    if (!documento || !podePrevisualizar(documento)) return;

    const blob = await carregarBlob(documento);
    if (!blob) return;

    const modal = garantirModalDePrevia();
    const corpo = porId("documentPreviewBody");
    porId("documentPreviewTitle").textContent = tituloDaPrevia(documento);
    porId("documentPreviewFonte").textContent =
        `Fonte: ${documento.pasta} · ${documento.exibicao}`;

    if (urlDaPrevia) URL.revokeObjectURL(urlDaPrevia);
    urlDaPrevia = URL.createObjectURL(blob);

    const extensao = documento.extensao;
    if (extensao === "pdf") {
        corpo.innerHTML = `<iframe class="document-preview-frame" title="${escapar(documento.exibicao)}" src="${urlDaPrevia}"></iframe>`;
    } else if (documento.tipo === "imagem") {
        corpo.innerHTML = `<img class="document-preview-imagem" src="${urlDaPrevia}" alt="${escapar(documento.exibicao)}" />`;
    } else if (extensao === "txt") {
        const texto = await blob.text();
        corpo.innerHTML = `<pre class="document-preview-texto">${escapar(texto)}</pre>`;
    } else {
        // Word e afins: o navegador não renderiza; a prévia oferece o download.
        corpo.innerHTML = `
          <div class="document-preview-aviso">
            <p>Este formato (.${escapar(extensao)}) não tem prévia no navegador.</p>
            <p class="muted">Use o botão Download para abrir o arquivo no aplicativo.</p>
          </div>
        `;
    }

    const baixarBtn = porId("documentPreviewDownload");
    baixarBtn.onclick = () => {
        void baixar(id);
    };

    modal.classList.add("show");
    document.documentElement.classList.add("modal-aberto");
    document.body.classList.add("modal-aberto");
    desenharIcones(modal);
}

function abrirOpcoes(id: number): void {
    const documento = porIdDoDocumento(id);
    if (!documento) return;

    void abrirFormulario({
        titulo: documento.exibicao,
        descricao: `${documento.detalhe}. Altere o nome ou mova para outra pasta.`,
        campos: [
            {
                nome: "nome",
                rotulo: "Nome do documento",
                valor: documento.nome,
                obrigatorio: true,
                largo: true,
                dica: `A extensão .${documento.extensao} é mantida.`,
            },
            {
                nome: "pastaId",
                rotulo: "Pasta",
                tipo: "selecao",
                valor: String(documento.pastaId),
                opcoes: opcoesDePasta(),
            },
        ],
        confirmar: "Salvar alterações",
        excluir: {
            confirmacao:
                "O arquivo sai do banco de documentos e do armazenamento. Não há como desfazer.",
            aoExcluir: async () => {
                await excluirDocumento(id);
                await recarregar();
            },
        },
        aoSalvar: async (valores) => {
            await salvarDocumento(id, valores.texto("nome"), valores.numero("pastaId"));
            await recarregar();
        },
    });
}

function aplicarEstadoNosControles(): void {
    const estado = obterSecao("documentos");
    if (busca.value !== estado.busca) busca.value = estado.busca;
    tipo.value = estado.tipo;
}

busca.addEventListener("input", () =>
    atualizarSecao("documentos", { busca: busca.value }),
);
tipo.addEventListener("change", () => atualizarSecao("documentos", { tipo: tipo.value }));

alternadorDeVisao.addEventListener("click", (evento) => {
    const botao = alvoMaisProximo(evento, "[data-document-view]");
    if (botao) {
        atualizarSecao("documentos", {
            visualizacao: dado(botao, "documentView") as VisualizacaoDocumentos,
        });
    }
});

todos("[data-ordem]").forEach((aba) => {
    aba.addEventListener("click", () => {
        ordem = dado(aba, "ordem") === "recentes" ? "recentes" : "pastas";
        todos("[data-ordem]").forEach((outra) =>
            outra.classList.toggle("active", outra === aba),
        );
        renderGrid();
    });
});

porId("newFolder").addEventListener("click", () => {
    void abrirFormulario({
        titulo: "Nova pasta",
        descricao: "As pastas organizam o banco de documentos da HC.",
        campos: [
            { nome: "nome", rotulo: "Nome da pasta", obrigatorio: true, largo: true },
        ],
        confirmar: "Criar pasta",
        aoSalvar: async (valores) => {
            const nova = await criarPasta(valores.texto("nome"));
            await recarregar();
            atualizarSecao("documentos", { pasta: nova.nome });
        },
    });
});

porId("uploadFile").addEventListener("click", () => {
    if (!pastas.length) {
        showModal(
            "Nenhuma pasta ainda",
            "Crie uma pasta antes de enviar o primeiro arquivo: todo documento fica guardado em uma delas.",
        );
        return;
    }

    const aberta = pastaAberta();

    void abrirFormulario({
        titulo: "Enviar arquivo",
        descricao:
            "O arquivo fica guardado no servidor e só é acessível por quem tem acesso ao HC Brain.",
        campos: [
            {
                nome: "arquivo",
                rotulo: "Arquivo",
                tipo: "arquivo",
                obrigatorio: true,
                largo: true,
            },
            {
                nome: "pastaId",
                rotulo: "Pasta",
                tipo: "selecao",
                valor: String(aberta?.id ?? pastas[0].id),
                opcoes: opcoesDePasta(),
            },
            {
                nome: "nome",
                rotulo: "Nome do documento",
                dica: "Em branco, usa o nome do próprio arquivo.",
            },
        ],
        confirmar: "Enviar",
        aoSalvar: async (valores) => {
            const arquivo = valores.arquivo("arquivo");
            if (!arquivo) return;

            await enviarDocumento(
                valores.numero("pastaId"),
                arquivo,
                valores.texto("nome"),
            );
            await recarregar();
        },
    });
});

porId("renomearPasta").addEventListener("click", () => {
    const aberta = pastaAberta();
    if (!aberta) return;

    void abrirFormulario({
        titulo: "Renomear pasta",
        campos: [
            {
                nome: "nome",
                rotulo: "Nome da pasta",
                valor: aberta.nome,
                obrigatorio: true,
                largo: true,
            },
        ],
        aoSalvar: async (valores) => {
            const salva = await salvarPasta(aberta.id, valores.texto("nome"));
            await recarregar();
            atualizarSecao("documentos", { pasta: salva.nome });
        },
    });
});

porId("excluirPasta").addEventListener("click", async () => {
    const aberta = pastaAberta();
    if (!aberta) return;

    const confirmou = await confirmarAcao(
        `Excluir a pasta ${aberta.nome}?`,
        aberta.total > 0
            ? `Os ${aberta.total} documentos dentro dela também serão excluídos, junto com os arquivos. Não há como desfazer.`
            : "A pasta está vazia e será removida do banco de documentos.",
    );

    if (!confirmou) return;

    await excluirPasta(aberta.id);
    atualizarSecao("documentos", { pasta: TODAS_AS_PASTAS });
    await recarregar();
});

observarEstado(["documentos"], () => {
    aplicarEstadoNosControles();
    renderMenu();
    renderGrid();
});

// A pasta aberta fica no navegador, e ela pode ter sido excluída desde a
// última visita — sem isso a tela abriria filtrada por algo que não existe.
if (obterSecao("documentos").pasta !== TODAS_AS_PASTAS && pastaAberta() === null) {
    atualizarSecao("documentos", { pasta: TODAS_AS_PASTAS });
}

if (![...tipo.options].some((opcao) => opcao.value === obterSecao("documentos").tipo)) {
    atualizarSecao("documentos", { tipo: "all" });
}

aplicarEstadoNosControles();
renderMenu();
renderGrid();
