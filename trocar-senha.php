<?php
require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/config/database.php";
exigirLogin();

$erro = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $novaSenha = $_POST["nova_senha"] ?? "";
    $confirmacao = $_POST["confirmar_senha"] ?? "";

    if (strlen($novaSenha) < 6) {
        $erro = "A nova senha precisa ter pelo menos 6 caracteres.";
    } elseif ($novaSenha !== $confirmacao) {
        $erro = "As senhas não coincidem.";
    } else {
        $pdo = getConexao();
        $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE usuarios SET senha_hash = ?, deve_trocar_senha = 0 WHERE id = ?");
        $stmt->execute([$hash, $_SESSION["usuario_id"]]);
        $_SESSION["deve_trocar_senha"] = false;
        header("Location: " . destinoAposLogin());
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Trocar senha — Painel Sinergia Agro</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="pagina-login">
<form class="cartao-login" method="post">
  <img src="assets/img/logo.png" alt="Sinergia Agro" class="logo-login">
  <h1>Defina uma nova senha</h1>
  <p style="font-size:12px;color:#777;margin:0;">Este é o seu primeiro acesso — antes de continuar, crie uma senha só sua.</p>
  <?php if ($erro): ?><p class="mensagem-erro"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
  <label for="nova_senha">Nova senha</label>
  <input type="password" id="nova_senha" name="nova_senha" minlength="6" required autofocus>
  <label for="confirmar_senha">Confirmar nova senha</label>
  <input type="password" id="confirmar_senha" name="confirmar_senha" minlength="6" required>
  <button type="submit">Salvar e continuar</button>
</form>
</body>
</html>
