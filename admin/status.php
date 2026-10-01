<?php
require_once __DIR__ . "/../includes/auth.php";
exigirDepartamento(["Qualidade"]);
require_once __DIR__ . "/../config/database.php";

$pdo = getConexao();
$mensagem = null;

$nomesMeses = [1=>"Janeiro",2=>"Fevereiro",3=>"Março",4=>"Abril",5=>"Maio",6=>"Junho",7=>"Julho",8=>"Agosto",9=>"Setembro",10=>"Outubro",11=>"Novembro",12=>"Dezembro"];

$anoAtualServidor = (int) date("Y");
$anoMinimo = $anoAtualServidor - 5;
$anoMaximo = max(2050, $anoAtualServidor + 25);

$anoSelecionado = (int) ($_GET["ano"] ?? $anoAtualServidor);
$mesSelecionadoNum = (int) ($_GET["mes_num"] ?? date("n"));
$diaPreSelecionado = $_GET["dia"] ?? date("Y-m-d");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["salvar_status"])) {
        $data = $_POST["data"];
        $status = $_POST["status"];
        $observacao = $_POST["observacao"] !== "" ? $_POST["observacao"] : null;

        // Sempre insere uma linha nova (nunca sobrescreve): preserva o histórico de observações.
        $desvio = null;
        $acao_tomada = null;
        $como_evitar = null;

        if ($status === "atencao" || $status === "grave") {
            $desvio = $_POST["desvio"] !== "" ? $_POST["desvio"] : null;
            $acao_tomada = $_POST["acao_tomada"] !== "" ? $_POST["acao_tomada"] : null;
            $como_evitar = $_POST["como_evitar"] !== "" ? $_POST["como_evitar"] : null;
        }

        $stmt = $pdo->prepare("INSERT INTO status_qualidade_dia (data, status, observacao, desvio, acao_tomada, como_evitar, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$data, $status, $observacao, $desvio, $acao_tomada, $como_evitar, $_SESSION["usuario_id"]]);

        registrarLog("qualidade", "Lançou o status de qualidade de um dia do mês");
        $mensagem = "Status do dia salvo.";
        $anoSelecionado = (int) substr($data, 0, 4);
        $mesSelecionadoNum = (int) substr($data, 5, 2);
    } elseif (isset($_POST["atualizar_status_historico"])) {
        // Correção de um lançamento específico do histórico (ex.: erro de digitação).
        $observacao = $_POST["observacao"] !== "" ? $_POST["observacao"] : null;
        $stmt = $pdo->prepare("UPDATE status_qualidade_dia SET status = ?, observacao = ? WHERE id = ?");
        $stmt->execute([$_POST["status"], $observacao, $_POST["id"]]);
        registrarLog("qualidade", "Editou um lançamento do histórico de status");
        $mensagem = "Lançamento do histórico atualizado.";
    } elseif (isset($_POST["excluir_status_historico"])) {
        $pdo->prepare("DELETE FROM status_qualidade_dia WHERE id = ?")->execute([$_POST["id"]]);
        registrarLog("qualidade", "Excluiu um lançamento do histórico de status");
        $mensagem = "Lançamento do histórico excluído.";
    }
}

$mesSelecionado = sprintf("%04d-%02d", $anoSelecionado, $mesSelecionadoNum);
$diasNoMes = (int) date("t", strtotime($mesSelecionado . "-01"));

$stmt = $pdo->prepare("
    SELECT s1.data, s1.status
    FROM status_qualidade_dia s1
    INNER JOIN (
        SELECT data, MAX(criado_em) AS max_criado
        FROM status_qualidade_dia
        WHERE data LIKE ?
        GROUP BY data
    ) s2 ON s1.data = s2.data AND s1.criado_em = s2.max_criado
");
$stmt->execute([$mesSelecionado . "-%"]);
$statusPorDia = [];
foreach ($stmt->fetchAll() as $linha) {
    $statusPorDia[(int) date("j", strtotime($linha["data"]))] = $linha["status"];
}

$stmt = $pdo->prepare("SELECT sq.*, u.nome AS usuario_nome FROM status_qualidade_dia sq JOIN usuarios u ON u.id = sq.usuario_id WHERE sq.data LIKE ? ORDER BY sq.data DESC, sq.criado_em DESC");
$stmt->execute([$mesSelecionado . "-%"]);
$historicoStatus = $stmt->fetchAll();

$historicoEmEdicao = null;

$tituloPagina = "Status da qualidade";
require_once __DIR__ . "/../includes/layout_admin_topo.php";
?>
<h1>Status da qualidade</h1>
<?php if ($mensagem): ?><p style="color:var(--verde-ok)"><?= htmlspecialchars($mensagem) ?></p><?php endif; ?>

<form method="get" style="margin-bottom:16px;display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap;">
  <div>
    <label>Mês</label><br>
    <select name="mes_num" onchange="this.form.submit()">
      <?php for ($m = 1; $m <= 12; $m++): ?>
        <option value="<?= $m ?>" <?= $m === $mesSelecionadoNum ? "selected" : "" ?>><?= $nomesMeses[$m] ?></option>
      <?php endfor; ?>
    </select>
  </div>
  <div>
    <label>Ano</label><br>
    <select name="ano" onchange="this.form.submit()">
      <?php for ($a = $anoMinimo; $a <= $anoMaximo; $a++): ?>
        <option value="<?= $a ?>" <?= $a === $anoSelecionado ? "selected" : "" ?>><?= $a ?></option>
      <?php endfor; ?>
    </select>
  </div>
</form>

<div class="piramide-admin">
  <?php for ($dia = 1; $dia <= $diasNoMes; $dia++): $status = $statusPorDia[$dia] ?? "vazio"; $classe = $status === "vazio" ? "" : " status-dia-" . $status; ?>
    <span class="dia-piramide<?= $classe ?>"><?= $dia ?></span>
  <?php endfor; ?>
</div>
<p style="font-size:12px;color:#777;">Em branco = ainda sem registro &middot; Verde = sem problema &middot; Amarelo = resolvido com ação imediata &middot; Vermelho = problema grave</p>

<form class="formulario" method="post" id="form-status" style="margin-top:24px;">
  <label>Dia</label>
  <input type="date" name="data" value="<?= htmlspecialchars($diaPreSelecionado) ?>" required>
  <label>Status</label>
  <select name="status" id="campo-status" onchange="alternarCamposStatus()">
    <option value="ok">Sem problema de qualidade</option>
    <option value="atencao">Problema resolvido com ação imediata</option>
    <option value="grave">Problema grave</option>
  </select>
  <div id="bloco-observacao">
    <label>Observação (opcional)</label>
    <textarea name="observacao"></textarea>
  </div>
  <div id="bloco-desvio" style="display:none;">
    <label>Desvio</label>
    <textarea name="desvio"></textarea>
    <label>Ação tomada</label>
    <textarea name="acao_tomada"></textarea>
    <label>Como evitar reincidência</label>
    <textarea name="como_evitar"></textarea>
  </div>
  <button type="submit" name="salvar_status" value="1">Salvar</button>
</form>

<script>
function alternarCamposStatus() {
  var status = document.getElementById("campo-status").value;
  document.getElementById("bloco-desvio").style.display = (status === "atencao" || status === "grave") ? "block" : "none";
}
</script>

<h2 style="margin-top:32px;">Histórico unificado do mês</h2>
<p style="font-size:12px;color:#777;">Cada lançamento fica registrado — nada é sobrescrito, mesmo que o mesmo dia seja atualizado mais de uma vez. Use Editar só para corrigir um erro de digitação.</p>

<div id="historico-container">Carregando histórico...</div>

<script>
const mesSelecionado = "<?= $mesSelecionadoNum ?>";
const anoSelecionado = "<?= $anoSelecionado ?>";

async function carregarHistorico() {
  try {
    const res = await fetch(`../api/qualidade/historico.php?mes=${mesSelecionado}&ano=${anoSelecionado}`);
    const json = await res.json();
    if (json.status === "sucesso") {
      renderizarHistorico(json.dados);
    } else {
      document.getElementById("historico-container").innerText = "Erro ao carregar histórico: " + (json.mensagem || "");
    }
  } catch (err) {
    document.getElementById("historico-container").innerText = "Erro ao carregar histórico.";
  }
}
function formatarDataHora(str) {
  if (!str) return "—";
  const p = str.split(/[- :]/);
  return `${p[2]}/${p[1]}/${p[0]} ${p[3]}:${p[4]}`;
}
function formatarData(str) {
  if (!str) return "—";
  const p = str.split("-");
  return `${p[2]}/${p[1]}/${p[0]}`;
}
function escapeHtml(unsafe) {
  return (unsafe || "").toString()
       .replace(/&/g, "&amp;")
       .replace(/</g, "&lt;")
       .replace(/>/g, "&gt;")
       .replace(/"/g, "&quot;")
       .replace(/'/g, "&#039;");
}

function renderizarHistorico(dados) {
  if (!dados || dados.length === 0) {
    document.getElementById("historico-container").innerHTML = "<p>Nenhum registro neste mês.</p>";
    return;
  }
  let html = `<table class="tabela-simples">
    <tr>
      <th>Data</th>
      <th>Status</th>
      <th>Desvio</th>
      <th>Ação tomada</th>
      <th>Como evitar reincidência</th>
      <th>Observação</th>
      <th>Lançado por</th>
      <th>Quando</th>
      <th>Ações</th>
    </tr>`;

  dados.forEach(h => {
    let classePill = "status-encerrado";
    if (h.status === "atencao") classePill = "status-em_andamento";
    if (h.status === "grave") classePill = "status-aberto";

    html += `<tr>
      <td>${formatarData(h.data)}</td>
      <td><span class="status-pill ${classePill}">${escapeHtml(h.status)}</span></td>
      <td>${h.status === "ok" || !h.desvio ? "—" : escapeHtml(h.desvio)}</td>
      <td>${!h.acao_tomada ? "—" : escapeHtml(h.acao_tomada)}</td>
      <td>${!h.como_evitar ? "—" : escapeHtml(h.como_evitar)}</td>
      <td>${!h.observacao ? "—" : escapeHtml(h.observacao)}</td>
      <td>${escapeHtml(h.usuario_nome)}</td>
      <td>${formatarDataHora(h.criado_em)}</td>
      <td class="acoes-linha">
        <a href="#" onclick="editarRegistro(${h.id}, '${h.status}', '${escapeHtml(h.observacao).replace(/'/g, "\\'")}', '${escapeHtml(h.desvio).replace(/'/g, "\\'")}', '${escapeHtml(h.acao_tomada).replace(/'/g, "\\'")}', '${escapeHtml(h.como_evitar).replace(/'/g, "\\'")}', '${h.data}'); return false;">Editar</a>
        <form method="post" onsubmit="excluirRegistro(event, ${h.id});" style="display:inline;">
          <button type="submit" class="botao-link-perigo">Excluir</button>
        </form>
      </td>
    </tr>`;
  });
  html += "</table>";
  document.getElementById("historico-container").innerHTML = html;
}

function editarRegistro(id, status, observacao, desvio, acao, comoEvitar, data) {
  document.getElementById("modal-edicao").style.display = "block";
  document.getElementById("edit-id").value = id;
  document.getElementById("edit-status").value = status;
  document.getElementById("edit-observacao").value = observacao !== "null" ? observacao : "";
  document.getElementById("edit-desvio").value = desvio !== "null" ? desvio : "";
  document.getElementById("edit-acao").value = acao !== "null" ? acao : "";
  document.getElementById("edit-como-evitar").value = comoEvitar !== "null" ? comoEvitar : "";
  document.getElementById("edit-titulo").innerText = `Editando lançamento de ${formatarData(data)}`;
  alternarCamposEdicao();
  document.getElementById("modal-edicao").scrollIntoView({ behavior: 'smooth' });
}

function alternarCamposEdicao() {
  const st = document.getElementById("edit-status").value;
  document.getElementById("edit-bloco-desvio").style.display = (st === "atencao" || st === "grave") ? "block" : "none";
}

function fecharEdicao() {
  document.getElementById("modal-edicao").style.display = "none";
}

async function salvarEdicao(event) {
  event.preventDefault();
  const id = document.getElementById("edit-id").value;
  const status = document.getElementById("edit-status").value;
  const observacao = document.getElementById("edit-observacao").value;
  const desvio = document.getElementById("edit-desvio").value;
  const acao_tomada = document.getElementById("edit-acao").value;
  const como_evitar = document.getElementById("edit-como-evitar").value;

  try {
    const res = await fetch("../api/qualidade/historico.php", {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id, status, observacao, desvio, acao_tomada, como_evitar })
    });
    const json = await res.json();
    if (json.status === "sucesso") {
      fecharEdicao();
      carregarHistorico();
      alert("Registro atualizado com sucesso.");
    } else {
      alert("Erro ao salvar: " + (json.mensagem || ""));
    }
  } catch(err) {
    alert("Erro de conexão ao salvar.");
  }
}

async function excluirRegistro(event, id) {
  event.preventDefault();
  if (!confirm("Excluir este lançamento do histórico? Essa ação não pode ser desfeita.")) return;
  try {
    const res = await fetch("../api/qualidade/historico.php", {
      method: "DELETE",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id })
    });
    const json = await res.json();
    if (json.status === "sucesso") {
      carregarHistorico();
      alert("Registro excluído.");
    } else {
      alert("Erro ao excluir: " + (json.mensagem || ""));
    }
  } catch(err) {
    alert("Erro de conexão ao excluir.");
  }
}

document.addEventListener("DOMContentLoaded", carregarHistorico);
</script>

<!-- Modal / Formulario Inline de Edição -->
<div id="modal-edicao" style="display:none; border:1px solid #ddd; padding:16px; margin-bottom:16px; background:#f9f9f9; border-radius:8px;">
  <form class="formulario" id="form-edicao" onsubmit="salvarEdicao(event)">
    <input type="hidden" id="edit-id">
    <label id="edit-titulo">Lançamento de ...</label>
    <select id="edit-status" onchange="alternarCamposEdicao()">
      <option value="ok">Sem problema de qualidade</option>
      <option value="atencao">Problema resolvido com ação imediata</option>
      <option value="grave">Problema grave</option>
    </select>

    <label>Observação</label>
    <textarea id="edit-observacao"></textarea>

    <div id="edit-bloco-desvio" style="display:none;">
      <label>Desvio</label>
      <textarea id="edit-desvio"></textarea>
      <label>Ação tomada</label>
      <textarea id="edit-acao"></textarea>
      <label>Como evitar reincidência</label>
      <textarea id="edit-como-evitar"></textarea>
    </div>

    <div style="display:flex; gap:16px;">
      <button type="submit">Salvar edição</button>
      <button type="button" onclick="fecharEdicao()" style="background:#888;">Cancelar</button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . "/../includes/layout_admin_rodape.php"; ?>
