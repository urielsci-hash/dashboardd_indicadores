<?php
require_once __DIR__ . "/../includes/auth.php";
exigirDepartamento(["Qualidade"]);
require_once __DIR__ . "/../config/database.php";

$pdo = getConexao();
$mensagem = null;
$tanquesDisponiveis = ["T1", "T2", "T3", "T4"];
$diaSelecionado = $_GET["dia"] ?? date("Y-m-d");

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["salvar"])) {
    $data = $_POST["data"];
    $capacidadeTotal = str_replace(",", ".", $_POST["capacidade_total"]);
    $valoresTanque = $_POST["tanque"] ?? [];
    $produtosTanque = $_POST["tanque_produto"] ?? [];

    $armazenamentoUtilizado = 0;
    foreach ($tanquesDisponiveis as $nomeTanque) {
        $valor = $valoresTanque[$nomeTanque] ?? "";
        if ($valor !== "") {
            $armazenamentoUtilizado += (float) str_replace(",", ".", $valor);
        }
    }

    $stmt = $pdo->prepare("INSERT INTO producao_diaria (data, capacidade_total_litros, armazenamento_utilizado_litros, usuario_id)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE capacidade_total_litros = VALUES(capacidade_total_litros), armazenamento_utilizado_litros = VALUES(armazenamento_utilizado_litros), usuario_id = VALUES(usuario_id)");
    $stmt->execute([$data, $capacidadeTotal, $armazenamentoUtilizado, $_SESSION["usuario_id"]]);

    $stmtId = $pdo->prepare("SELECT id FROM producao_diaria WHERE data = ?");
    $stmtId->execute([$data]);
    $producaoId = $stmtId->fetchColumn();

    $pdo->prepare("DELETE FROM producao_tanques WHERE producao_diaria_id = ?")->execute([$producaoId]);
    $stmtTanque = $pdo->prepare("INSERT INTO producao_tanques (producao_diaria_id, tanque, produto, valor_litros) VALUES (?, ?, ?, ?)");
    foreach ($tanquesDisponiveis as $nomeTanque) {
        $valor = $valoresTanque[$nomeTanque] ?? "";
        if ($valor !== "") {
            $produto = trim($produtosTanque[$nomeTanque] ?? "") ?: null;
            $stmtTanque->execute([$producaoId, $nomeTanque, $produto, str_replace(",", ".", $valor)]);
        }
    }
    registrarLog("em_formulacao", "Lançou/atualizou o registro de um dia");
    $mensagem = "Registro salvo.";
    $diaSelecionado = $data;
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["excluir"])) {
    $pdo->prepare("DELETE FROM producao_diaria WHERE id = ?")->execute([$_POST["id"]]);
    registrarLog("em_formulacao", "Excluiu o registro de um dia");
    $mensagem = "Registro excluído.";
}

$stmt = $pdo->prepare("SELECT * FROM producao_diaria WHERE data = ?");
$stmt->execute([$diaSelecionado]);
$registroDoDia = $stmt->fetch();
$tanquesDoDia = [];
if ($registroDoDia) {
    $stmt = $pdo->prepare("SELECT tanque, produto, valor_litros FROM producao_tanques WHERE producao_diaria_id = ?");
    $stmt->execute([$registroDoDia["id"]]);
    foreach ($stmt->fetchAll() as $t) {
        $tanquesDoDia[$t["tanque"]] = $t;
    }
}

$historico = $pdo->query("SELECT * FROM producao_diaria ORDER BY data DESC LIMIT 30")->fetchAll();

$tituloPagina = "Em formulação";
require_once __DIR__ . "/../includes/layout_admin_topo.php";
?>
<h1>Em formulação</h1>
<?php if ($mensagem): ?><p style="color:var(--verde-ok)"><?= htmlspecialchars($mensagem) ?></p><?php endif; ?>

<form method="get" style="margin-bottom:16px;">
  <label>Ver/editar o dia: <input type="date" name="dia" value="<?= htmlspecialchars($diaSelecionado) ?>" onchange="this.form.submit()"></label>
</form>

<form class="formulario" method="post" id="form-formulacao">
  <input type="hidden" name="data" value="<?= htmlspecialchars($diaSelecionado) ?>">
  <label>Capacidade total de armazenamento (litros)</label>
  <input type="text" name="capacidade_total" value="<?= $registroDoDia ? $registroDoDia["capacidade_total_litros"] : "" ?>" required>
  <?php foreach ($tanquesDisponiveis as $t): $tanqueAtual = $tanquesDoDia[$t] ?? null; ?>
    <label><?= $t ?> — Produto</label>
    <input type="text" name="tanque_produto[<?= $t ?>]" placeholder="Nome do produto neste tanque" value="<?= $tanqueAtual ? htmlspecialchars($tanqueAtual["produto"]) : "" ?>">
    <label><?= $t ?> — Litros</label>
    <input type="text" class="campo-tanque" name="tanque[<?= $t ?>]" value="<?= $tanqueAtual ? $tanqueAtual["valor_litros"] : "" ?>" oninput="atualizarSomaTanques()">
  <?php endforeach; ?>
  <p style="font-size:13px;color:#555;">Armazenamento utilizado (calculado automaticamente): <strong id="soma-tanques"><?= $registroDoDia ? $registroDoDia["armazenamento_utilizado_litros"] : "0" ?></strong> L</p>
  <button type="submit" name="salvar" value="1">Salvar</button>
</form>

<script>
function atualizarSomaTanques() {
  var campos = document.querySelectorAll(".campo-tanque");
  var soma = 0;
  campos.forEach(function (campo) {
    var valor = parseFloat((campo.value || "0").replace(",", "."));
    if (!isNaN(valor)) { soma += valor; }
  });
  document.getElementById("soma-tanques").textContent = soma.toFixed(2);
}
</script>

<h2 style="margin-top:32px;">Lançamentos recentes</h2>
<table class="tabela-simples">
  <tr><th>Data</th><th>Armazenamento utilizado</th><th>Capacidade total</th><th>Ações</th></tr>
  <?php foreach ($historico as $h): ?>
  <tr>
    <td><?= date("d/m/Y", strtotime($h["data"])) ?></td>
    <td><?= $h["armazenamento_utilizado_litros"] ?> L</td>
    <td><?= $h["capacidade_total_litros"] ?> L</td>
    <td class="acoes-linha">
      <a href="?dia=<?= $h["data"] ?>">Editar</a>
      <form method="post" onsubmit="return confirm(&quot;Excluir o lançamento deste dia? Essa ação não pode ser desfeita.&quot;);" style="display:inline;">
        <input type="hidden" name="id" value="<?= $h["id"] ?>">
        <button type="submit" name="excluir" value="1" class="botao-link-perigo">Excluir</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$historico): ?><tr><td colspan="4">Nenhum lançamento ainda.</td></tr><?php endif; ?>
</table>
<?php require_once __DIR__ . "/../includes/layout_admin_rodape.php"; ?>
