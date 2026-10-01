<?php
require_once __DIR__ . "/../includes/auth.php";
exigirDepartamento(["TI"]);
require_once __DIR__ . "/../config/database.php";

$pdo = getConexao();
$mensagem = null;
$erro = null;

function usuarioTemRegistros($pdo, $id) {
    $tabelas = [
        "producao_diaria" => "usuario_id",
        "status_qualidade_dia" => "usuario_id",
        "desvios_qualidade" => "usuario_id",
        "mural_posts" => "usuario_id",
        "indicadores" => "usuario_id",
        "indicadores_arquivos" => "usuario_id",
        "log_auditoria" => "usuario_id",
    ];
    foreach ($tabelas as $tabela => $coluna) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM $tabela WHERE $coluna = ?");
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            return true;
        }
    }
    return false;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["criar"])) {
        $hash = password_hash($_POST["senha"], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha_hash, departamento_id, deve_trocar_senha) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$_POST["nome"], $_POST["email"], $hash, $_POST["departamento_id"]]);
        registrarLog("usuarios", "Criou um novo usuário");
        $mensagem = "Usuário criado. Ele(a) vai precisar trocar a senha no primeiro acesso.";
    } elseif (isset($_POST["redefinir_senha"])) {
        $hash = password_hash($_POST["nova_senha"], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE usuarios SET senha_hash = ?, deve_trocar_senha = 1 WHERE id = ?");
        $stmt->execute([$hash, $_POST["id"]]);
        registrarLog("usuarios", "Redefiniu a senha de um usuário");
        $mensagem = "Senha redefinida — a pessoa vai precisar trocá-la no próximo acesso.";
    } elseif (isset($_POST["desativar"])) {
        $pdo->prepare("UPDATE usuarios SET ativo = 0 WHERE id = ?")->execute([$_POST["id"]]);
        registrarLog("usuarios", "Desativou um usuário");
        $mensagem = "Usuário desativado.";
    } elseif (isset($_POST["reativar"])) {
        $pdo->prepare("UPDATE usuarios SET ativo = 1 WHERE id = ?")->execute([$_POST["id"]]);
        registrarLog("usuarios", "Reativou um usuário");
        $mensagem = "Usuário reativado.";
    } elseif (isset($_POST["excluir"])) {
        if (usuarioTemRegistros($pdo, $_POST["id"])) {
            $erro = "Este usuário já tem lançamentos no sistema — para preservar o histórico, desative-o em vez de excluir.";
        } else {
            $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$_POST["id"]]);
            registrarLog("usuarios", "Excluiu um usuário sem lançamentos");
            $mensagem = "Usuário excluído.";
        }
    }
}

$mostrarInativos = isset($_GET["todos"]);
if ($mostrarInativos) {
    $usuarios = $pdo->query("SELECT u.*, d.nome AS departamento FROM usuarios u JOIN departamentos d ON d.id = u.departamento_id ORDER BY d.nome, u.nome")->fetchAll();
} else {
    $usuarios = $pdo->query("SELECT u.*, d.nome AS departamento FROM usuarios u JOIN departamentos d ON d.id = u.departamento_id WHERE u.ativo = 1 ORDER BY d.nome, u.nome")->fetchAll();
}
$departamentos = $pdo->query("SELECT * FROM departamentos ORDER BY nome")->fetchAll();

$tituloPagina = "Usuários";
require_once __DIR__ . "/../includes/layout_admin_topo.php";
?>
<h1>Usuários</h1>
<p style="font-size:13px;color:#666;">O departamento define o que a pessoa pode alimentar: Qualidade (status/indicadores/em formulação), RH ou Marketing (mural), TI (esta página e o log), Visualização (só vê a tela pública, sem acesso a nenhuma página de edição — use para a TV ou para acesso remoto da diretoria). Todo usuário novo precisa trocar a senha inicial no primeiro acesso.</p>
<?php if ($mensagem): ?><p style="color:var(--verde-ok)"><?= htmlspecialchars($mensagem) ?></p><?php endif; ?>
<?php if ($erro): ?><p class="mensagem-erro"><?= htmlspecialchars($erro) ?></p><?php endif; ?>

<form class="formulario" method="post">
  <label>Nome</label>
  <input type="text" name="nome" required>
  <label>E-mail</label>
  <input type="email" name="email" required>
  <label>Senha inicial</label>
  <input type="text" name="senha" required>
  <label>Departamento</label>
  <select name="departamento_id">
    <?php foreach ($departamentos as $d): ?>
      <option value="<?= $d["id"] ?>"><?= htmlspecialchars($d["nome"]) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" name="criar" value="1">Criar usuário</button>
</form>

<h2 style="margin-top:32px;">Usuários <?= $mostrarInativos ? "(todos)" : "ativos" ?></h2>
<p style="font-size:12px;"><a href="?<?= $mostrarInativos ? "" : "todos=1" ?>"><?= $mostrarInativos ? "Mostrar só os ativos" : "Mostrar todos (inclusive desativados)" ?></a></p>
<table class="tabela-simples">
  <tr><th>Nome</th><th>E-mail</th><th>Departamento</th><th>Status</th><th>Ações</th></tr>
  <?php foreach ($usuarios as $u): ?>
  <tr>
    <td><?= htmlspecialchars($u["nome"]) ?><?= $u["deve_trocar_senha"] ? " <span title=\"Aguardando troca da senha inicial\">🔑</span>" : "" ?></td>
    <td><?= htmlspecialchars($u["email"]) ?></td>
    <td><?= htmlspecialchars($u["departamento"]) ?></td>
    <td><?= $u["ativo"] ? "Ativo" : "Inativo" ?></td>
    <td class="acoes-linha">
      <a href="usuario-editar?id=<?= $u["id"] ?>">Editar</a>
      <form method="post" style="display:inline-flex;gap:4px;">
        <input type="hidden" name="id" value="<?= $u["id"] ?>">
        <input type="text" name="nova_senha" placeholder="Nova senha" style="width:100px;">
        <button type="submit" name="redefinir_senha" value="1" class="botao-link">Redefinir</button>
      </form>
      <form method="post" style="display:inline;">
        <input type="hidden" name="id" value="<?= $u["id"] ?>">
        <?php if ($u["ativo"]): ?>
          <button type="submit" name="desativar" value="1" class="botao-link">Desativar</button>
        <?php else: ?>
          <button type="submit" name="reativar" value="1" class="botao-link">Reativar</button>
        <?php endif; ?>
      </form>
      <form method="post" onsubmit="return confirm(&quot;Excluir definitivamente este usuário? Essa ação não pode ser desfeita.&quot;);" style="display:inline;">
        <input type="hidden" name="id" value="<?= $u["id"] ?>">
        <button type="submit" name="excluir" value="1" class="botao-link-perigo">Excluir</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php require_once __DIR__ . "/../includes/layout_admin_rodape.php"; ?>
