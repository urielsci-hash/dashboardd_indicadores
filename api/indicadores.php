<?php
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../includes/auth.php";
exigirLogin();
header("Content-Type: application/json; charset=utf-8");

$pdo = getConexao();
$stmt = $pdo->query("SELECT nome, tipo_grafico, categorias, valores FROM indicadores WHERE ativo = 1 AND CURDATE() BETWEEN data_inicio AND data_fim ORDER BY nome");

$indicadores = [];
foreach ($stmt->fetchAll() as $linha) {
    $indicadores[] = [
        "nome" => $linha["nome"],
        "tipo" => $linha["tipo_grafico"],
        "categorias" => json_decode($linha["categorias"], true) ?: [],
        "valores" => json_decode($linha["valores"], true) ?: [],
    ];
}

echo json_encode(["indicadores" => $indicadores], JSON_UNESCAPED_UNICODE);
