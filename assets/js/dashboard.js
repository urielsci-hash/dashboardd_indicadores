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
    var texto = await resposta.text();
    var dados = {};
    try {
      dados = JSON.parse(texto);
    } catch (e) {
      console.error("Erro JSON na API de indicadores: ", texto);
      throw e;
    }
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
    var texto = await resposta.text();
    var dados = {};
    try {
      dados = JSON.parse(texto);
    } catch (e) {
      console.error("Erro JSON na API de indicadores: ", texto);
      throw e;
    }
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
    var texto = await resposta.text();
    var dados = {};
    try {
      dados = JSON.parse(texto);
    } catch (e) {
      console.error("Erro JSON na API de indicadores: ", texto);
      throw e;
    }
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
    var texto = await resposta.text();
    var dados = {};
    try {
      dados = JSON.parse(texto);
    } catch (e) {
      console.error("Erro JSON na API de indicadores: ", texto);
      throw e;
    }
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

Chart.register(ChartDataLabels);
Chart.defaults.font.family = '"Segoe UI",system-ui,Arial,sans-serif';
Chart.defaults.plugins.datalabels.display = false;

var M = ['JAN','FEV','MAR','ABR','MAI','JUN','JUL','AGO','SET','OUT','NOV','DEZ'];
var B = ['JAN / FEV','MAR / ABR','MAI / JUN','JUL / AGO','SET / OUT','NOV / DEZ'];
var pad = function(a){var r=a.slice();while(r.length<12)r.push(null);return r;};
var pct = function(v){return Math.round(v)+'%';};
var pct2 = function(v){return v.toFixed(2).replace('.',',')+'%';};
var metaLine = function(v,label){return {type:'line',label:label,data:Array(12).fill(v),borderColor:'#c0392b',borderDash:[6,4],borderWidth:1.5,pointRadius:0,hidden:false,datalabels:{display:false}};};

var baseOptions = {
  responsive: true,
  maintainAspectRatio: false,
  layout: { padding: { top: 22 } },
  animation: { duration: 400 },
  scales: { x: { grid: { display: false } }, y: { beginAtZero: true } },
  plugins: { legend: { display: false } }
};

function inicializarGraficos() {
  // Chart.js instances are now initialized dynamically inside atualizarGraficoSlot
}

function atualizarGraficoSlot(slot, indicador) {
  try {
  var ctx = document.getElementById("grafico-indicador-" + slot).getContext("2d");
  var box = document.getElementById("box-indicador-" + slot);
  var aviso = document.getElementById("aviso-indicador-" + slot);

  if (graficosIndicadores[slot]) {
    graficosIndicadores[slot].destroy();
  }

  if (!indicador) {
    box.innerHTML = "";
    aviso.innerHTML = "";
    graficosIndicadores[slot] = new Chart(ctx, { type: "bar", data: { labels: [], datasets: [] }, options: baseOptions });
    return;
  }

  var conf = { type: 'bar', data: { labels: [], datasets: [] }, options: JSON.parse(JSON.stringify(baseOptions)) };

  if (indicador.tipo === "manutencao") {
    box.innerHTML = '<div class="box" style="background:#f4a08a">Indicador de Manutenção_2026<span>Objetivo: Cumprir o Plano de Manutenção</span><span>Meta: &gt; 90 % / Mensal</span></div>';
    aviso.innerHTML = '<div class="ok">Todos os meses lançados cumpriram a meta (100%).</div>';
    conf.data.labels = M;
    conf.data.datasets = [
      {
        label: '% Manutenção realizada',
        data: pad((indicador.valores.data || []).map(function(v){ return v * 100; })),
        backgroundColor: '#2979ff',
        borderWidth: 0,
        datalabels: {
          display: function(c){return c.raw!==null},
          color: '#fff',
          font: { size: 10, weight: 'bold' },
          formatter: pct,
          anchor: 'end',
          align: 'end',
          offset: -14,
          backgroundColor: '#2979ff',
          borderRadius: 2
        }
      },
      metaLine(90, 'Meta 90%')
    ];
    conf.options.scales.y = { min: 0, max: 100, ticks: { callback: function(v){return v+'%'}, stepSize: 10 } };
  }
  else if (indicador.tipo === "producao_pcp") {
    box.innerHTML = '<div class="box" style="background:#4a7fe0;color:#fff">Indicador Produção_ 2026<span>Objetivo: Entregar a Produção no Prazo Planejado</span><span>Meta: ≥90 % Mensal</span></div>';

    var a2 = [];
    if(indicador.valores.entrega) {
      indicador.valores.entrega.forEach(function(v, i) {
        if (v < 0.90 && indicador.valores.acoes[i] && /sem a[cç][aã]o/i.test(indicador.valores.acoes[i])) {
          a2.push(M[i] + ' (' + (v * 100).toFixed(1).replace('.', ',') + '%)');
        }
      });
    }
    aviso.innerHTML = a2.length
      ? '<div class="warn"><b>Atenção para auditoria:</b> abaixo da meta com ação "Sem ação" em ' + a2.join(', ') + '. O sistema final exigirá o preenchimento da ação.</div>'
      : '<div class="ok">Nenhum mês abaixo da meta sem ação registrada.</div>';

    conf.data.labels = M;
    conf.data.datasets = [
      {
        label: '% OP no Prazo',
        data: pad((indicador.valores.op || []).map(function(v){ return v * 100; })),
        backgroundColor: '#4a7fe0',
        datalabels: { display: function(c){return c.raw!==null}, rotation: -90, color: '#111', font: { size: 9 }, anchor: 'end', align: 'end', formatter: pct }
      },
      {
        label: '% OF no Prazo',
        data: pad((indicador.valores.of || []).map(function(v){ return v * 100; })),
        backgroundColor: '#a6a6a6',
        datalabels: { display: function(c){return c.raw!==null}, rotation: -90, color: '#111', font: { size: 9 }, anchor: 'end', align: 'end', formatter: pct }
      },
      {
        type: 'line',
        label: '% Entrega no Prazo Programado',
        data: pad((indicador.valores.entrega || []).map(function(v){ return v * 100; })),
        borderColor: '#2c4fa8',
        borderWidth: 1.5,
        pointRadius: 0,
        spanGaps: false,
        datalabels: { display: function(c){return c.raw!==null}, backgroundColor: '#ffc000', color: '#fff', font: { size: 10, weight: 'bold' }, borderRadius: 2, padding: 3, formatter: pct, align: 'bottom', offset: 6 }
      },
      metaLine(90, 'Meta 90%')
    ];
    conf.options.scales.y = { min: 60, max: 105, ticks: { callback: function(v){return v+'%'} } };
    conf.options.plugins.legend = { display: true, position: 'bottom', labels: { boxWidth: 12 } };
  }
  else if (indicador.tipo === "qualidade") {
    box.innerHTML = '<div class="box" style="background:#f5c26b">Indicador_Qualidade_2026<span>Objetivo: produto NÃO CONFORME (refugo + reprocesso)</span><span>Meta: ≤ 2 % / semestral</span></div>';
    aviso.innerHTML = '<div class="warn"><b>Meta divergente:</b> o JPG indica ≤ 2% e a planilha indica &lt; 1%. Defina qual vale antes de publicar. 1º semestre: 0,80%.</div>';
    conf.data.labels = ['1º Semestre', '2º Semestre'];
    conf.data.datasets = [
      {
        label: '% Total refugo',
        data: (indicador.valores.refugo || []).map(function(v){ return v * 100; }),
        backgroundColor: '#7ed957',
        datalabels: { display: true, formatter: pct2, color: '#111', font: { size: 10 }, anchor: 'end', align: 'end' }
      },
      {
        label: '% Total produto reprocessado',
        data: (indicador.valores.reprocesso || []).map(function(v){ return v * 100; }),
        backgroundColor: '#9ec9f5',
        datalabels: { display: true, formatter: pct2, color: '#111', font: { size: 10 }, anchor: 'end', align: 'end' }
      },
      {
        type: 'line',
        label: '% Reprocesso + refugo',
        data: (indicador.valores.total || []).map(function(v){ return v * 100; }),
        borderColor: '#f2a900',
        backgroundColor: '#f2a900',
        borderWidth: 2,
        pointRadius: 4,
        datalabels: { display: true, backgroundColor: '#ffc000', color: '#111', font: { size: 11, weight: 'bold' }, borderRadius: 2, padding: 3, formatter: pct2, align: 'top', offset: 8 }
      }
    ];
    conf.options.scales.y = { min: 0, max: 1.2, ticks: { callback: function(v){return v.toFixed(1).replace('.',',')+'%'} } };
    conf.options.plugins.legend = { display: true, position: 'bottom', labels: { boxWidth: 12 } };
  }
  else if (indicador.tipo === "perdas") {
    box.innerHTML = '<div class="box" style="background:#f4a08a">Monitoramento Produção_2026<span>Objetivo: Monitorar os tipos de perdas durante o processo produtivo</span></div>';
    aviso.innerHTML = '<div class="ok">Valores da planilha (jul/ago = 33, 1, 19, 1, 0, 6, 4) diferem do JPG de exemplo; o protótipo segue a planilha.</div>';
    conf.data.labels = B;

    var coresPerdas = ['#f4a261', '#f6d743', '#8be05a', '#b5651d', '#b59b1f', '#4f9a3c', '#8fd3f4'];
    conf.data.datasets = (indicador.valores.datasets || []).map(function(ds, idx) {
      return {
        label: ds.label,
        data: ds.data.concat([null, null]),
        backgroundColor: coresPerdas[idx % coresPerdas.length],
        datalabels: { display: function(c){return c.raw!==null}, color: '#111', font: { size: 8 }, anchor: 'end', align: 'end', offset: 0 }
      };
    });
    conf.options.scales.y = { display: false, grid: { display: false } };
    conf.options.plugins.legend = { display: true, position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } };
  }

  graficosIndicadores[slot] = new Chart(ctx, conf);
  } catch (e) {
    console.error("Erro ao renderizar grafico do slot " + slot, e);
    document.getElementById("box-indicador-" + slot).innerHTML = "<div class='box erro'>Erro ao exibir indicador</div>";
  }
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
    var texto = await resposta.text();
    var dados = {};
    try {
      dados = JSON.parse(texto);
    } catch (e) {
      console.error("Erro JSON na API de indicadores: ", texto);
      throw e;
    }
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
    var texto = await resposta.text();
    var dados = {};
    try {
      dados = JSON.parse(texto);
    } catch (e) {
      console.error("Erro JSON na API de indicadores: ", texto);
      throw e;
    }
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
