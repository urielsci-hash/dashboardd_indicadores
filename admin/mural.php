<?php
require_once __DIR__ . "/../includes/auth.php";
exigirDepartamento(["RH", "Marketing"]);
require_once __DIR__ . "/../config/database.php";

$pdo = getConexao();
$mensagem = null;
$erro = null;
$pastaUploads = __DIR__ . "/../uploads/mural/";

function processarUploadImagem($erro) {
    global $pastaUploads;
    if (empty($_FILES["imagem"]["name"])) {
        return [null, $erro];
    }
    $codigoErro = $_FILES["imagem"]["error"];
    if ($codigoErro !== UPLOAD_ERR_OK) {
        return [null, "Falha no envio da imagem (código " . $codigoErro . "). Verifique o tamanho do arquivo."];
    }
    $extensao = strtolower(pathinfo($_FILES["imagem"]["name"], PATHINFO_EXTENSION));
    if (!in_array($extensao, ["jpg", "jpeg", "png"], true)) {
        return [null, "Envie apenas arquivos JPG ou PNG."];
    }
    $nomeArquivo = uniqid("mural_") . "." . $extensao;
    if (!move_uploaded_file($_FILES["imagem"]["tmp_name"], $pastaUploads . $nomeArquivo)) {
        return [null, "Não foi possível salvar a imagem no servidor. Confira a permissão da pasta uploads/mural."];
    }
    return [$nomeArquivo, null];
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["publicar"])) {
    $titulo = trim($_POST["titulo"]);
    $tipo = $_POST["tipo"];
    $imagemNome = null;

    if ($tipo === "imagem") {
        [$imagemNome, $erro] = processarUploadImagem(null);
        if (!$imagemNome && !$erro) {
            $erro = "Selecione uma imagem para publicar.";
        }
    }

    if (!$erro) {
        $stmt = $pdo->prepare("INSERT INTO mural_posts (tipo, titulo, conteudo, imagem_path, departamento_id, data_inicio, data_fim, usuario_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $tipo, $titulo, $_POST["conteudo"] ?? null, $imagemNome,
            $_SESSION["departamento_id"], $_POST["data_inicio"], $_POST["data_fim"], $_SESSION["usuario_id"],
        ]);
        registrarLog("mural", "Publicou um novo item no mural");
        $mensagem = "Comunicado publicado.";
    }
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["atualizar"])) {
    $id = $_POST["id"];
    $titulo = trim($_POST["titulo"]);
    $tipo = $_POST["tipo"];

    $stmt = $pdo->prepare("SELECT * FROM mural_posts WHERE id = ?");
    $stmt->execute([$id]);
    $postAtual = $stmt->fetch();
    $imagemNome = $postAtual["imagem_path"] ?? null;

    if ($tipo === "imagem" && !empty($_FILES["imagem"]["name"])) {
        [$novaImagem, $erro] = processarUploadImagem(null);
        if ($novaImagem) {
            if ($imagemNome && file_exists($pastaUploads . $imagemNome)) {
                @unlink($pastaUploads . $imagemNome);
            }
            $imagemNome = $novaImagem;
        }
    }

    if (!$erro) {
        $stmt = $pdo->prepare("UPDATE mural_posts SET tipo = ?, titulo = ?, conteudo = ?, imagem_path = ?, data_inicio = ?, data_fim = ? WHERE id = ?");
        $stmt->execute([$tipo, $titulo, $_POST["conteudo"] ?? null, $imagemNome, $_POST["data_inicio"], $_POST["data_fim"], $id]);
        registrarLog("mural", "Editou um item do mural");
        $mensagem = "Comunicado atualizado.";
    }
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["excluir"])) {
    $stmt = $pdo->prepare("SELECT imagem_path FROM mural_posts WHERE id = ?");
    $stmt->execute([$_POST["id"]]);
    $post = $stmt->fetch();
    if ($post && $post["imagem_path"] && file_exists($pastaUploads . $post["imagem_path"])) {
        @unlink($pastaUploads . $post["imagem_path"]);
    }
    $pdo->prepare("DELETE FROM mural_posts WHERE id = ?")->execute([$_POST["id"]]);
    registrarLog("mural", "Excluiu um item do mural");
    $mensagem = "Comunicado excluído.";
}

$mostrarTodos = isset($_GET["todos"]);
if ($mostrarTodos) {
    $posts = $pdo->query("SELECT * FROM mural_posts ORDER BY criado_em DESC LIMIT 50")->fetchAll();
} else {
    $posts = $pdo->query("SELECT * FROM mural_posts WHERE data_fim >= CURDATE() ORDER BY criado_em DESC")->fetchAll();
}

$postEmEdicao = null;
if (!empty($_GET["editar"])) {
    $stmt = $pdo->prepare("SELECT * FROM mural_posts WHERE id = ?");
    $stmt->execute([$_GET["editar"]]);
    $postEmEdicao = $stmt->fetch();
}

$tituloPagina = "Mural";
require_once __DIR__ . "/../includes/layout_admin_topo.php";
?>
<h1>Mural de comunicados</h1>
<p style="font-size:13px;color:#666;">Imagens na horizontal ou vertical são ajustadas automaticamente para preencher o espaço sem distorcer.</p>
<?php if ($mensagem): ?><p style="color:var(--verde-ok)"><?= htmlspecialchars($mensagem) ?></p><?php endif; ?>
<?php if ($erro): ?><p class="mensagem-erro"><?= htmlspecialchars($erro) ?></p><?php endif; ?>

<h2><?= $postEmEdicao ? "Editar comunicado" : "Novo comunicado" ?></h2>
<form class="formulario" method="post" enctype="multipart/form-data">
  <?php if ($postEmEdicao): ?><input type="hidden" name="id" value="<?= $postEmEdicao["id"] ?>"><?php endif; ?>
  <label>Título</label>
  <input type="text" name="titulo" value="<?= $postEmEdicao ? htmlspecialchars($postEmEdicao["titulo"]) : "" ?>" required>
  <label>Tipo</label>
  <select name="tipo">
    <option value="texto" <?= ($postEmEdicao && $postEmEdicao["tipo"] === "texto") ? "selected" : "" ?>>Texto</option>
    <option value="imagem" <?= ($postEmEdicao && $postEmEdicao["tipo"] === "imagem") ? "selected" : "" ?>>Imagem</option>
  </select>
  <label>Texto (se tipo = texto)</label>
  <textarea name="conteudo"><?= $postEmEdicao ? htmlspecialchars($postEmEdicao["conteudo"] ?? "") : "" ?></textarea>
  <label>Imagem (se tipo = imagem, JPG ou PNG<?= $postEmEdicao && $postEmEdicao["imagem_path"] ? " — deixe em branco para manter a atual" : "" ?>)</label>
  <input type="file" name="imagem" accept=".jpg,.jpeg,.png">
  <?php if ($postEmEdicao && $postEmEdicao["imagem_path"]): ?>
    <img src="../uploads/mural/<?= htmlspecialchars($postEmEdicao["imagem_path"]) ?>" alt="Imagem atual" style="max-width:160px;border-radius:6px;">
  <?php endif; ?>
  <label>Exibir de</label>
  <input type="date" name="data_inicio" value="<?= $postEmEdicao ? $postEmEdicao["data_inicio"] : date("Y-m-d") ?>" required>
  <label>até</label>
  <input type="date" name="data_fim" value="<?= $postEmEdicao ? $postEmEdicao["data_fim"] : date("Y-m-d", strtotime("+7 days")) ?>" required>
  <button type="submit" name="<?= $postEmEdicao ? "atualizar" : "publicar" ?>" value="1"><?= $postEmEdicao ? "Salvar edição" : "Publicar" ?></button>
</form>

<h2 style="margin-top:32px;">Comunicados <?= $mostrarTodos ? "(todos)" : "em exibição" ?></h2>
<p style="font-size:12px;"><a href="?<?= $mostrarTodos ? "" : "todos=1" ?>"><?= $mostrarTodos ? "Mostrar só os em exibição" : "Mostrar todos (inclusive expirados)" ?></a></p>
<table class="tabela-simples">
  <tr><th>Título</th><th>Tipo</th><th>Período</th><th>Status</th><th>Ações</th></tr>
  <?php foreach ($posts as $p): ?>
  <tr>
    <td><?= htmlspecialchars($p["titulo"]) ?></td>
    <td><?= $p["tipo"] ?></td>
    <td><?= date("d/m", strtotime($p["data_inicio"])) ?> a <?= date("d/m", strtotime($p["data_fim"])) ?></td>
    <td><?= $p["ativo"] ? "Ativo" : "Inativo" ?></td>
    <td class="acoes-linha">
      <a href="?editar=<?= $p["id"] ?>">Editar</a>
      <form method="post" onsubmit="return confirm(&quot;Excluir este comunicado? Essa ação não pode ser desfeita.&quot;);" style="display:inline;">
        <input type="hidden" name="id" value="<?= $p["id"] ?>">
        <button type="submit" name="excluir" value="1" class="botao-link-perigo">Excluir</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$posts): ?><tr><td colspan="5">Nenhum comunicado <?= $mostrarTodos ? "cadastrado" : "em exibição no momento" ?>.</td></tr><?php endif; ?>
</table>
<?php require_once __DIR__ . "/../includes/layout_admin_rodape.php"; ?>
