<?php
require_once __DIR__ . "/auth.php";
exigirLogin();
$dep = departamentoUsuario();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $tituloPagina ?? "Administração" ?> — Sinergia Agro</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout-admin">
  <nav class="menu-admin">
    <img src="../assets/img/logo.png" alt="Sinergia Agro" style="height:36px;margin-bottom:24px;">
    <?php if ($dep === "Qualidade"): ?>
      <a href="status">Status da qualidade</a>
      <a href="indicadores">Indicadores</a>
      <a href="em-formulacao">Em formulação</a>
    <?php elseif (in_array($dep, ["RH", "Marketing"], true)): ?>
      <a href="mural">Mural</a>
      <a href="noticias">Notícias (RSS)</a>
    <?php elseif ($dep === "TI"): ?>
      <a href="usuarios">Usuários</a>
      <a href="auditoria">Log de auditoria</a>
    <?php endif; ?>
    <a href="../index">Ver painel</a>
    <a href="../trocar-senha">Trocar senha</a>
    <a href="../logout">Sair</a>
  </nav>
  <main class="area-admin">
