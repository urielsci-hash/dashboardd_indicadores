<?php
require_once __DIR__ . "/../includes/auth.php";
exigirDepartamento(["TI"]);
require_once __DIR__ . "/../config/database.php";

$pdo = getConexao();
limparLogsAntigos($pdo);

$dataFiltro = $_GET["data"] ?? null;

$sql = "SELECT la.*, u.nome AS usuario_nome, d.nome AS departamento FROM log_auditoria la
        JOIN usuarios u ON u.id = la.usuario_id
        JOIN departamentos d ON d.id = u.departamento_id";
$parametros = [];
if ($dataFiltro) {
    $sql .= " WHERE la.data_hora LIKE ?";
    $parametros[] = $dataFiltro . "%";
}
$sql .= " ORDER BY la.data_hora DESC LIMIT 300";

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$registros = $stmt->fetchAll();

$tituloPagina = "Log de auditoria";
require_once __DIR__ . "/../includes/layout_admin_topo.php";
?>
<h1>Log de auditoria</h1>
<p style="font-size:13px;color:#666;">Aqui aparece quem mexeu em qual módulo e quando — sem mostrar o conteúdo alterado. Registros com mais de 30 dias são apagados automaticamente.</p>

<form method="get" style="margin-bottom:16px;">
  <label>Filtrar por data: <input type="date" name="data" value="<?= htmlspecialchars($dataFiltro ?? "") ?>" onchange="this.form.submit()"></label>
</form>

<table class="tabela-simples">
  <tr><th>Data/hora</th><th>Usuário</th><th>Departamento</th><th>Módulo</th><th>Ação</th></tr>
  <?php foreach ($registros as $r): ?>
  <tr>
    <td><?= date("d/m/Y H:i", strtotime($r["data_hora"])) ?></td>
    <td><?= htmlspecialchars($r["usuario_nome"]) ?></td>
    <td><?= htmlspecialchars($r["departamento"]) ?></td>
    <td><?= htmlspecialchars($r["modulo"]) ?></td>
    <td><?= htmlspecialchars($r["descricao"]) ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$registros): ?><tr><td colspan="5">Nenhum registro.</td></tr><?php endif; ?>
</table>
<?php require_once __DIR__ . "/../includes/layout_admin_rodape.php"; ?>
