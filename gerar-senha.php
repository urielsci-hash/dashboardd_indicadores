<?php
$hash = null;
if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST["senha"])) {
    $hash = password_hash($_POST["senha"], PASSWORD_DEFAULT);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><title>Gerar hash de senha</title></head>
<body style="font-family:Arial;max-width:480px;margin:60px auto;">
  <h1>Gerar hash de senha (uso único)</h1>
  <p>Use isto apenas para criar o primeiro usuário administrador direto no banco (via phpMyAdmin da Locaweb). Depois de criar esse usuário, <strong>apague este arquivo do servidor</strong>.</p>
  <form method="post">
    <input type="text" name="senha" placeholder="Senha desejada" style="padding:8px;width:100%;">
    <button type="submit" style="margin-top:8px;padding:8px 16px;">Gerar hash</button>
  </form>
  <?php if ($hash): ?>
    <p><strong>Hash gerado:</strong></p>
    <textarea readonly style="width:100%;height:80px;"><?= htmlspecialchars($hash) ?></textarea>
    <p>Cole esse hash no INSERT INTO usuários comentado no arquivo sql/schema.sql.</p>
  <?php endif; ?>
</body>
</html>
