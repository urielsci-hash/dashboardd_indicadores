<?php
require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/includes/auth.php";

$erro = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $senha = $_POST["senha"] ?? "";
    $lembrar = isset($_POST["lembrar"]);

    $pdo = getConexao();
    $stmt = $pdo->prepare("SELECT u.*, d.nome AS departamento_nome FROM usuarios u JOIN departamentos d ON d.id = u.departamento_id WHERE u.email = ? AND u.ativo = 1");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($senha, $usuario["senha_hash"])) {
        fazerLogin($usuario);
        if ($lembrar) {
            criarTokenLembrar($usuario["id"]);
        }
        if (precisaTrocarSenha()) {
            header("Location: trocar-senha");
        } else {
            header("Location: " . destinoAposLogin());
        }
        exit;
    }
    $erro = "E-mail ou senha inválidos.";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entrar — Painel Sinergia Agro</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="pagina-login">
<form class="cartao-login" method="post">
  <img src="assets/img/logo.png" alt="Sinergia Agro" class="logo-login">
  <h1>Painel de Qualidade</h1>
  <?php if ($erro): ?><p class="mensagem-erro"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
  <label for="email">E-mail</label>
  <input type="email" id="email" name="email" required autofocus>
  <label for="senha">Senha</label>
  <input type="password" id="senha" name="senha" required>
  <label class="opcao-lembrar"><input type="checkbox" name="lembrar" checked> Manter conectado (usar na TV)</label>
  <button type="submit">Entrar</button>
</form>
</body>
</html>
