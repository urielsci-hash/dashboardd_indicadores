<?php
require_once __DIR__ . "/../includes/auth.php";
exigirDepartamento(["TI"]);
require_once __DIR__ . "/../config/database.php";

$pdo = getConexao();
$mensagem = null;
$id = $_GET["id"] ?? $_POST["id"] ?? null;

if (!$id) {
    header("Location: usuarios");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, departamento_id = ? WHERE id = ?");
    $stmt->execute([$_POST["nome"], $_POST["email"], $_POST["departamento_id"], $id]);
    registrarLog("usuarios", "Editou os dados de um usuário");
    $mensagem = "Usuário atualizado.";
}

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$usuario = $stmt->fetch();

if (!$usuario) {
    header("Location: usuarios");
    exit;
}

$departamentos = $pdo->query("SELECT * FROM departamentos ORDER BY nome")->fetchAll();

$tituloPagina = "Editar usuário";
require_once __DIR__ . "/../includes/layout_admin_topo.php";
?>
<h1>Editar usuário</h1>
<?php if ($mensagem): ?><p style="color:var(--verde-ok)"><?= htmlspecialchars($mensagem) ?></p><?php endif; ?>

<form class="formulario" method="post">
  <input type="hidden" name="id" value="<?= $usuario["id"] ?>">
  <label>Nome</label>
  <input type="text" name="nome" value="<?= htmlspecialchars($usuario["nome"]) ?>" required>
  <label>E-mail</label>
  <input type="email" name="email" value="<?= htmlspecialchars($usuario["email"]) ?>" required>
  <label>Departamento</label>
  <select name="departamento_id">
    <?php foreach ($departamentos as $d): ?>
      <option value="<?= $d["id"] ?>" <?= $d["id"] == $usuario["departamento_id"] ? "selected" : "" ?>><?= htmlspecialchars($d["nome"]) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit">Salvar</button>
</form>
<p style="margin-top:16px;"><a href="usuarios">&larr; Voltar para a lista de usuários</a></p>
<?php require_once __DIR__ . "/../includes/layout_admin_rodape.php"; ?>
