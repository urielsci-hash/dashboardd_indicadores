<?php
require_once __DIR__ . "/../includes/auth.php";
exigirDepartamento(["RH", "Marketing"]);
require_once __DIR__ . "/../config/database.php";

$pdo = getConexao();
$mensagem = null;
$erro = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["adicionar"])) {
        $url = trim($_POST["url_feed"]);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $erro = "Informe uma URL de feed válida (ex.: https://site.com/rss).";
        } else {
            $stmt = $pdo->prepare("INSERT INTO noticias_rss (url_feed, departamento_id, usuario_id) VALUES (?, ?, ?)");
            $stmt->execute([$url, $_SESSION["departamento_id"], $_SESSION["usuario_id"]]);
            registrarLog("noticias", "Cadastrou um novo feed de notícias (RSS)");
            $mensagem = "Feed cadastrado.";
        }
    } elseif (isset($_POST["editar"])) {
        $url = trim($_POST["url_feed"]);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $erro = "Informe uma URL de feed válida (ex.: https://site.com/rss).";
        } else {
            $pdo->prepare("UPDATE noticias_rss SET url_feed = ? WHERE id = ?")->execute([$url, $_POST["id"]]);
            registrarLog("noticias", "Editou um feed de notícias (RSS)");
            $mensagem = "Feed atualizado.";
        }
    } elseif (isset($_POST["alternar_ativo"])) {
        $pdo->prepare("UPDATE noticias_rss SET ativo = 1 - ativo WHERE id = ?")->execute([$_POST["id"]]);
        registrarLog("noticias", "Ativou/desativou um feed de notícias");
        $mensagem = "Feed atualizado.";
    } elseif (isset($_POST["excluir"])) {
        $pdo->prepare("DELETE FROM noticias_rss WHERE id = ?")->execute([$_POST["id"]]);
        registrarLog("noticias", "Excluiu um feed de notícias");
        $mensagem = "Feed excluído.";
    }
}

$mostrarTodos = isset($_GET["todos"]);
if ($mostrarTodos) {
    $feeds = $pdo->query("SELECT * FROM noticias_rss ORDER BY ativo DESC, id DESC")->fetchAll();
} else {
    $feeds = $pdo->query("SELECT * FROM noticias_rss WHERE ativo = 1 ORDER BY id DESC")->fetchAll();
}

$feedEmEdicao = null;
if (!empty($_GET["editar"])) {
    $stmt = $pdo->prepare("SELECT * FROM noticias_rss WHERE id = ?");
    $stmt->execute([$_GET["editar"]]);
    $feedEmEdicao = $stmt->fetch();
}

$tituloPagina = "Notícias (RSS)";
require_once __DIR__ . "/../includes/layout_admin_topo.php";
?>
<h1>Últimas notícias (RSS)</h1>
<p style="max-width:560px;font-size:13px;color:#666;">
  Cadastre aqui a URL de um feed RSS (ex.: o feed de notícias do site da empresa ou de um portal do
  setor). O dashboard busca as notícias mais recentes automaticamente — não é preciso digitar nada
  manualmente.
</p>
<?php if ($mensagem): ?><p style="color:var(--verde-ok)"><?= htmlspecialchars($mensagem) ?></p><?php endif; ?>
<?php if ($erro): ?><p class="mensagem-erro"><?= htmlspecialchars($erro) ?></p><?php endif; ?>

<h2><?= $feedEmEdicao ? "Editar feed" : "Novo feed" ?></h2>
<form class="formulario" method="post">
  <?php if ($feedEmEdicao): ?><input type="hidden" name="id" value="<?= $feedEmEdicao["id"] ?>"><?php endif; ?>
  <label>URL do feed RSS</label>
  <input type="url" name="url_feed" placeholder="https://exemplo.com/rss" value="<?= $feedEmEdicao ? htmlspecialchars($feedEmEdicao["url_feed"]) : "" ?>" required>
  <button type="submit" name="<?= $feedEmEdicao ? "editar" : "adicionar" ?>" value="1"><?= $feedEmEdicao ? "Salvar edição" : "Adicionar feed" ?></button>
</form>

<h2 style="margin-top:32px;">Feeds <?= $mostrarTodos ? "(todos)" : "ativos" ?></h2>
<p style="font-size:12px;"><a href="?<?= $mostrarTodos ? "" : "todos=1" ?>"><?= $mostrarTodos ? "Mostrar só os ativos" : "Mostrar todos (inclusive desativados)" ?></a></p>
<table class="tabela-simples">
  <tr><th>URL</th><th>Status</th><th>Ações</th></tr>
  <?php foreach ($feeds as $f): ?>
  <tr>
    <td><?= htmlspecialchars($f["url_feed"]) ?></td>
    <td><?= $f["ativo"] ? "Ativo" : "Inativo" ?></td>
    <td class="acoes-linha">
      <a href="?editar=<?= $f["id"] ?>">Editar</a>
      <form method="post" style="display:inline;">
        <input type="hidden" name="id" value="<?= $f["id"] ?>">
        <button type="submit" name="alternar_ativo" value="1" class="botao-link"><?= $f["ativo"] ? "Desativar" : "Ativar" ?></button>
      </form>
      <form method="post" onsubmit="return confirm(&quot;Excluir este feed? Essa ação não pode ser desfeita.&quot;);" style="display:inline;">
        <input type="hidden" name="id" value="<?= $f["id"] ?>">
        <button type="submit" name="excluir" value="1" class="botao-link-perigo">Excluir</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$feeds): ?><tr><td colspan="3">Nenhum feed cadastrado.</td></tr><?php endif; ?>
</table>
<?php require_once __DIR__ . "/../includes/layout_admin_rodape.php"; ?>
