/* ===================== Relógio (data + hora) ===================== */
function atualizarRelogio() {
  var agora = new Date();
  var data = agora.toLocaleDateString("pt-BR");
  var hora = agora.toLocaleTimeString("pt-BR");
  document.getElementById("relogio").innerHTML = "<span style=\"color:#fff; font-size: 1.1em; margin-right: 4px;\">&#128338;</span> " + data + " " + hora;
}
setInterval(atualizarRelogio, 1000);
atualizarRelogio();

/* ===================== Clima (emoji, cache de 30min já é feito no servidor) ===================== */
var EMOJI_CLIMA = {
  thunder: "&#9928;", drizzle: "&#127782;", rain: "&#127783;", snow: "&#10052;", mist: "&#127787;", clear: "&#9728;", clouds: "&#9729;"
};
function emojiParaCodigo(id) {
  if (id >= 200 && id < 300) return EMOJI_CLIMA.thunder;
  if (id >= 300 && id < 400) return EMOJI_CLIMA.drizzle;
  if (id >= 500 && id < 600) return EMOJI_CLIMA.rain;
  if (id >= 600 && id < 700) return EMOJI_CLIMA.snow;
  if (id >= 700 && id < 800) return EMOJI_CLIMA.mist;
  if (id === 800) return EMOJI_CLIMA.clear;
  if (id > 800) return EMOJI_CLIMA.clouds;
  return "🌡️";
}
async function carregarClima() {
  try {
    var resposta = await fetch("api/clima");
    var dados = await resposta.json();
    if (dados.erro || !dados.main) {
      document.getElementById("clima").textContent = "Clima indisponível";
      return;
    }
    var temp = Math.round(dados.main.temp);
    var emoji = emojiParaCodigo(dados.weather[0].id);
    document.getElementById("clima").innerHTML = emoji + " " + temp + "°C - " + dados.weather[0].description;
  } catch (e) {
    document.getElementById("clima").textContent = "Clima indisponível";
  }
}

/* ===================== Status da qualidade (calendário + desvios) — a cada 35s ===================== */
var desviosDisponiveis = [];
var paginaDesvioAtual = 0;

function montarPiramide(statusDias) {
  var container = document.getElementById("piramide");
  var agora = new Date();
  var hoje = agora.getDate();
  var diasNoMes = new Date(agora.getFullYear(), agora.getMonth() + 1, 0).getDate();

  var rotuloMesAno = document.getElementById("mes-ano-piramide");
  if (rotuloMesAno) {
    rotuloMesAno.textContent = agora.toLocaleDateString("pt-BR", { month: "long", year: "numeric" });
  }

  var html = "";
  for (var dia = 1; dia <= diasNoMes; dia++) {
    var status = statusDias[dia] || "vazio";
    var classeStatus = status === "vazio" ? "" : " status-dia-" + status;
    var destaque = dia === hoje ? " dia-hoje" : "";
    html += "<span class=\"dia-piramide" + classeStatus + destaque + "\">" + dia + "</span>";
  }
  container.innerHTML = html;
}

function escaparHtml(texto) {
  var div = document.createElement("div");
  div.textContent = texto || "";
  return div.innerHTML;
}

function linhaDesvioHtml(d) {
  var dataFormatada = new Date(d.data + "T00:00:00").toLocaleDateString("pt-BR");

  var classeLinha = "linha-neutro";
  var badgeHtml = "";

  if (d.status === "grave" || (!d.status && d.observacao && d.observacao.toLowerCase().includes("grave"))) {
    classeLinha = "linha-grave";
    badgeHtml = "<span style=\"display:inline-block; margin-left:8px; padding:2px 6px; font-size:9px; font-weight:bold; background-color:#ffebeb; color:#d32f2f; border-radius:4px;\">Grave</span>";
  } else if (d.status === "atencao" || (!d.status && d.observacao && d.observacao.toLowerCase().includes("resolvido"))) {
    classeLinha = "linha-resolvido";
    badgeHtml = "<span style=\"display:inline-block; margin-left:8px; padding:2px 6px; font-size:9px; font-weight:bold; background-color:#fff8e1; color:#f57f17; border-radius:4px;\">Resolvido</span>";
  } else if (d.observacao && d.observacao.toLowerCase().includes("auditoria")) {
    badgeHtml = "<span style=\"display:inline-block; margin-left:8px; padding:2px 6px; font-size:9px; font-weight:bold; background-color:#f0f0f0; color:#555; border-radius:4px;\">Auditoria</span>";
  }

  var dotColor = classeLinha === "linha-grave" ? "var(--vermelho-alerta)" : (classeLinha === "linha-resolvido" ? "var(--amarelo-alerta)" : "#999");
  var dotHtml = "<span style=\"display:inline-block; width:6px; height:6px; border-radius:50%; background-color:" + dotColor + "; margin-right:6px; vertical-align:middle;\"></span>";

  return "<tr class=\"" + classeLinha + "\">" +
    "<td class=\"col-data\">" + dotHtml + dataFormatada + "</td>" +
    "<td>" + escaparHtml(d.descricao_desvio) + "</td>" +
    "<td>" + escaparHtml(d.acao_tomada) + "</td>" +
    "<td>" + escaparHtml(d.como_evitar) + "</td>" +
    "<td>" + (d.observacao ? escaparHtml(d.observacao) : "—") + badgeHtml + "</td>" +
    "</tr>";
}

function montarTabelaDesvios(lista) {
  return "<table class=\"tabela-desvios-dash\"><thead><tr>" +
    "<th>Data</th><th>Desvio</th><th>Ação tomada</th><th>Como evitar reincidência?</th><th>Observação</th>" +
    "</tr></thead><tbody>" + lista.map(linhaDesvioHtml).join("") + "</tbody></table>";
}

// Mostra quantos desvios couberem no espaço do card (nunca menos de 1), sempre em sequência
// de data e sem deixar nenhum de fora — o que não coube aparece na próxima atualização.
function exibirPaginaDesvios() {
  var container = document.getElementById("lista-desvios");
  // Filtra apenas os desvios relevantes (Grave e Resolvido) para manter foco nos problemas reais
  var desviosFiltrados = desviosDisponiveis.filter(function(d) {
    var isGrave = d.status === "grave" || (!d.status && d.observacao && d.observacao.toLowerCase().includes("grave"));
    var isResolvido = d.status === "atencao" || (!d.status && d.observacao && d.observacao.toLowerCase().includes("resolvido"));
    return isGrave || isResolvido;
  });

  desviosFiltrados.sort(function(a, b) { return new Date(b.data + "T00:00:00") - new Date(a.data + "T00:00:00"); });

  if (!desviosFiltrados.length) {
    container.innerHTML = "<p class=\"sem-dados\">Nenhum desvio relevante neste mês.</p>";
    return;
  }
  var total = desviosFiltrados.length;

  function construirPagina(qtd) {
    var pagina = [];
    for (var i = 0; i < qtd; i++) {
      pagina.push(desviosFiltrados[(paginaDesvioAtual + i) % total]);
    }
    return pagina;
  }

  var quantidade = Math.min(3, total);
  container.innerHTML = montarTabelaDesvios(construirPagina(quantidade));
  paginaDesvioAtual = paginaDesvioAtual + quantidade;
  if (paginaDesvioAtual >= total) {
    paginaDesvioAtual = 0;
  }
}

async function carregarStatusQualidade() {
  try {
    var resposta = await fetch("api/status", { cache: "no-store" });
    var dados = await resposta.json();
    montarPiramide(dados.status_dias || {});
    desviosDisponiveis = dados.desvios || [];
    if (paginaDesvioAtual >= desviosDisponiveis.length) { paginaDesvioAtual = 0; }
    // A primeira exibição é chamada aqui, a rotação é feita por um setInterval dedicado
    exibirPaginaDesvios();
  } catch (e) {
    console.error("Erro ao carregar status da qualidade", e);
  }
}

/* ===================== Últimas notícias (RSS) ===================== */
var noticiasDisponiveis = [];

function exibirNoticias() {
  var container = document.getElementById("noticias-lista");
  if (!noticiasDisponiveis.length) {
    container.innerHTML = "<p class=\"sem-dados\">Nenhuma notícia cadastrada.</p>";
    return;
  }

  var itensHtml = noticiasDisponiveis.map(function(n) {
    var titulo = escaparHtml(n.titulo);
    var linkAbre = n.link ? "<a href=\"" + n.link + "\" target=\"_blank\" rel=\"noopener\">" + titulo + "</a>" : titulo;
    return "<div class=\"noticia-item\">" + linkAbre + (n.resumo ? "<p>" + escaparHtml(n.resumo) + "</p>" : "") + "</div>";
  }).join('<span style="margin: 0 30px; color: #ccc;">|</span>');

  container.innerHTML = "<div class=\"noticia-item-container\">" + itensHtml + "</div>";
}

async function carregarNoticias() {
  try {
    var resposta = await fetch("api/noticias");
    var dados = await resposta.json();
    noticiasDisponiveis = dados.noticias || [];
    exibirNoticias();
  } catch (e) {
    console.error("Erro ao carregar notícias", e);
  }
}

/* ===================== Mural — a cada 30s ===================== */
var muralItens = [];
var muralIndice = 0;

function exibirMuralAtual() {
  var container = document.getElementById("mural-item");
  var cardMural = document.getElementById("mural");
  var tituloMural = document.getElementById("titulo-mural-card");

  if (!muralItens.length) {
    container.innerHTML = "<p class=\"sem-dados\">Sem comunicados no momento.</p>";
    return;
  }
  var item = muralItens[muralIndice % muralItens.length];

  // Limpa classes extras antes de renderizar o novo item
  cardMural.classList.remove("mural-fullscreen");
  if (tituloMural) {
    tituloMural.style.display = "block";
    tituloMural.classList.remove("discreto");
  }

  if (item.tipo === "imagem") {
    container.className = "mural-item";
    container.innerHTML = "<img src=\"uploads/mural/" + item.imagem_path + "\" alt=\"" + escaparHtml(item.titulo) +
      "\" onerror=\"this.parentElement.innerHTML='<p class=&quot;titulo-mural&quot;>' + this.alt + '</p>';\">";

    if (tituloMural) {
        tituloMural.style.display = "none";
    }
    cardMural.classList.add("mural-fullscreen");
  } else {
    container.className = "mural-item sem-imagem";
    container.innerHTML = "<p class=\"titulo-mural\">" + escaparHtml(item.titulo) + "</p><p>" + escaparHtml(item.conteudo) + "</p>";
  }
  muralIndice = (muralIndice + 1) % muralItens.length;
}

async function carregarMural() {
  try {
    var resposta = await fetch("api/mural");
    var dados = await resposta.json();
    muralItens = dados.mural || [];
    if (muralIndice >= muralItens.length) { muralIndice = 0; }
    exibirMuralAtual();
  } catch (e) {
    console.error("Erro ao carregar mural", e);
  }
}

/* ===================== Indicadores — a cada 20s ===================== */
var graficosIndicadores = [];
var indicadoresDisponiveis = [];
var indiceRotacaoIndicadores = 0;

function inicializarGraficos() {
  var cores = ["#163A6B", "#4CAF50", "#E8A93B"];
  for (var i = 0; i < 3; i++) {
    var ctx = document.getElementById("grafico-indicador-" + i).getContext("2d");
    graficosIndicadores[i] = new Chart(ctx, {
      type: "bar",
      data: { labels: [], datasets: [{ label: "", data: [], backgroundColor: cores[i] }] },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 400 },
        plugins: { title: { display: true, text: "", font: { size: 14 } }, legend: { display: false } },
        scales: { y: { beginAtZero: true } }
      }
    });
  }
}

function atualizarGraficoSlot(slot, indicador) {
  var grafico = graficosIndicadores[slot];
  if (!indicador) {
    grafico.data.labels = [];
    grafico.data.datasets[0].data = [];
    grafico.options.plugins.title.text = "";
    grafico.update();
    return;
  }
  grafico.config.type = indicador.tipo === "linha" ? "line" : "bar";
  grafico.data.labels = indicador.categorias;
  grafico.data.datasets[0].data = indicador.valores;
  grafico.data.datasets[0].label = indicador.nome;
  grafico.options.plugins.title.text = indicador.nome;
  grafico.update();
}

// Regra: nunca repete um indicador enquanto existir outro ainda não mostrado no ciclo.
// Com 3 ou menos indicadores ativos, cada um ocupa seu próprio espaço (sem rotação, sem repetir);
// os espaços que sobrarem ficam vazios. Só entra em rotação de fato com 4 ou mais.
function atualizarIndicadoresSlots() {
  var total = indicadoresDisponiveis.length;
  if (total === 0) {
    for (var i = 0; i < 3; i++) { atualizarGraficoSlot(i, null); }
    return;
  }
  if (total <= 3) {
    for (var i = 0; i < 3; i++) { atualizarGraficoSlot(i, indicadoresDisponiveis[i] || null); }
    return;
  }
  for (var i = 0; i < 3; i++) {
    atualizarGraficoSlot(i, indicadoresDisponiveis[(indiceRotacaoIndicadores + i) % total]);
  }
  indiceRotacaoIndicadores = (indiceRotacaoIndicadores + 3) % total;
}

async function carregarIndicadores() {
  try {
    var resposta = await fetch("api/indicadores");
    var dados = await resposta.json();
    indicadoresDisponiveis = dados.indicadores || [];
    if (indiceRotacaoIndicadores >= indicadoresDisponiveis.length) { indiceRotacaoIndicadores = 0; }
    atualizarIndicadoresSlots();
  } catch (e) {
    console.error("Erro ao carregar indicadores", e);
  }
}

/* ===================== Em formulação — carregada uma vez (atualiza no refresh geral de 5min) ===================== */
function montarProducao(producao, tanques) {
  var el = document.getElementById("producao-conteudo");
  if (!producao) {
    el.innerHTML = "<p class=\"sem-dados\">Sem lançamento hoje.</p>";
    return;
  }
  var total = parseFloat(producao.capacidade_total_litros) || 1;
  var utilizado = parseFloat(producao.armazenamento_utilizado_litros) || 0;
  var percentual = Math.min(100, Math.max(0, Math.round((utilizado / total) * 100)));

  var linhasTanques = tanques.map(function (t) {
    var produto = t.produto ? " " + escaparHtml(t.produto) : "";
    return "<div class=\"lista-tanques-item\"><span>" + escaparHtml(t.tanque) + produto + "</span><span>" + escaparHtml(t.valor_litros) + " L</span></div>";
  }).join("");

  el.innerHTML =
    "<div class=\"producao-vertical\">" +
    "<div>" +
    "<div class=\"prod-item\"><p class=\"rotulo\">Capacidade total de armazenamento</p><p class=\"valor\">" + producao.capacidade_total_litros + " L</p></div>" +
    "<div class=\"prod-item\" style=\"margin-top: clamp(8px, 1.2vw, 16px);\"><div class=\"flex-between\"><p class=\"rotulo\">Armazenamento utilizado</p><span class=\"percent-badge\">" + percentual + "%</span></div><p class=\"valor\">" + producao.armazenamento_utilizado_litros + " L</p>" +
    "<div class=\"progress-bar-bg\"><div class=\"progress-bar-fill\" style=\"width: " + percentual + "%;\"></div></div>" +
    "</div>" +
    "</div>" +
    "<div>" +
    "<div class=\"lista-tanques\">" + linhasTanques + "</div>" +
    "<div class=\"rodape-producao\"><span>Tanques em Operação Ativa</span><span class=\"pulse-dot\"></span></div>" +
    "</div>" +
    "</div>";
}

async function carregarEmFormulacao() {
  try {
    var resposta = await fetch("api/em-formulacao");
    var dados = await resposta.json();
    montarProducao(dados.producao, dados.producao_tanques || []);
  } catch (e) {
    console.error("Erro ao carregar em formulação", e);
  }
}

/* ===================== Inicialização — cada componente com seu próprio intervalo ===================== */
inicializarGraficos();

carregarStatusQualidade();
carregarNoticias();
carregarMural();
carregarIndicadores();
carregarEmFormulacao();
carregarClima();

setInterval(carregarNoticias, 5 * 60 * 1000); // Busca do feed apenas a cada 5 min
setInterval(carregarMural, 30 * 1000);
setInterval(carregarIndicadores, 20 * 1000);
setInterval(carregarClima, 30 * 60 * 1000);

// 30 seconds countdown indicator for table sync
let secondsLeft = 30;
const timerElement = document.getElementById('refresh-timer');
setInterval(() => {
  secondsLeft = secondsLeft <= 1 ? 30 : secondsLeft - 1;
  if (timerElement) {
    timerElement.textContent = secondsLeft + 's';
  }

  if (secondsLeft === 30) {
    carregarStatusQualidade();
  }
}, 1000);

// Refresh completo da página a cada 5 minutos — garante que qualquer coisa nova cadastrada em
// qualquer painel apareça, mesmo que fuja do que os fetches acima já cobrem, e evita qualquer
// acúmulo de memória de uma aba ficar dias abertas na TV (o reload reinicia tudo do zero).
setInterval(function () {
  window.location.reload();
}, 5 * 60 * 1000);
