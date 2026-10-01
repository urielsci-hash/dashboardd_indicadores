<?php
require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/includes/auth.php";
exigirLogin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Painel de Qualidade — Sinergia Agro</title>
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
</head>
<body class="painel">
  <header class="cabecalho">
    <div class="marca">
      <img src="assets/img/logo.png" alt="Sinergia Agro" class="logo">
      <div class="separador-cabecalho"></div>
      <div>
        <h1>Painel de Qualidade</h1>
        <p>Gestão à vista &middot; Serra Negra/SP</p>
      </div>
    </div>
    <div class="area-topo-direita">
      <div class="info-topo">
        <div id="clima" class="clima">Carregando clima...</div>
        <div id="relogio" class="relogio-box">--:--:--</div>
      </div>
    </div>
  </header>

  <main class="conteudo">
    <!-- Top row -->
    <section class="cartao cartao-status">
      <div class="cabecalho-status status-header-centered">
        <h2 class="titulo-cartao uppercase">Status da qualidade</h2>
        <span id="mes-ano-piramide" class="mes-ano-piramide badge-mes"></span>
      </div>
      <div id="piramide" class="piramide"></div>
      <div class="legenda-status">
        <span><i class="bolinha bolinha-ok"></i> Sem problema</span>
        <span><i class="bolinha bolinha-atencao"></i> Resolvido</span>
        <span><i class="bolinha bolinha-grave"></i> Grave</span>
      </div>
    </section>

    <section class="cartao cartao-indicador"><canvas id="grafico-indicador-0"></canvas></section>
    <section class="cartao cartao-indicador"><canvas id="grafico-indicador-1"></canvas></section>
    <section class="cartao cartao-mural" id="mural">
      <h2 class="titulo-cartao" id="titulo-mural-card">Mural</h2>
      <div id="mural-item" class="mural-item">Carregando comunicados...</div>
    </section>

    <!-- Bottom row -->
    <section class="cartao cartao-desvios">
      <div class="cabecalho-desvios">
        <div class="desvios-titulo-badge">
          <h2 class="titulo-cartao uppercase">Desvios de qualidade</h2>
          <span class="badge-registros-ativos">Registros ativos</span>
        </div>
        <div class="desvios-refresh">
          <span class="pulse-dot"></span> Atualiza em: <strong id="refresh-timer">30s</strong>
        </div>
      </div>
      <div id="lista-desvios" class="lista-desvios"></div>
      <div class="rodape-desvios">
        <span>Exibindo registros recentes de ocorrências em fábrica</span>
        <span>Sinergia Agro CQ</span>
      </div>
    </section>

    <section class="cartao cartao-indicador"><canvas id="grafico-indicador-2"></canvas></section>
    <section class="cartao cartao-producao">
      <h2 class="titulo-cartao uppercase">Em formulação</h2>
      <div id="producao-conteudo">Carregando...</div>
    </section>
  </main>

  <footer class="rodape-noticias">
    <div class="rodape-noticias-titulo">Últimas notícias</div>
    <div id="noticias-lista" class="noticias-lista"></div>
  </footer>

  <script src="assets/js/dashboard.js"></script>
</body>
</html>
