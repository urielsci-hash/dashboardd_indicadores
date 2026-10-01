<?php
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/leitor_rss.php";
exigirLogin();
header("Content-Type: application/json; charset=utf-8");

$pdo = getConexao();
$urls = $pdo->query("SELECT url_feed FROM noticias_rss WHERE ativo = 1")->fetchAll(PDO::FETCH_COLUMN);

$noticias = $urls ? LeitorRss::buscarItens($urls, 15) : [];

echo json_encode(["noticias" => $noticias], JSON_UNESCAPED_UNICODE);
