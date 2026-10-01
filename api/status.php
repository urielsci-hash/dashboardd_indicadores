<?php
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../includes/auth.php";
exigirLogin();
header("Content-Type: application/json; charset=utf-8");

$pdo = getConexao();
$mesAtual = date("Y-m");

// Status "atual" de cada dia = registro mais recente daquele dia (histórico preservado no banco).
$stmt = $pdo->prepare("
    SELECT s1.data, s1.status
    FROM status_qualidade_dia s1
    INNER JOIN (
        SELECT data, MAX(criado_em) AS max_criado
        FROM status_qualidade_dia
        WHERE data LIKE ?
        GROUP BY data
    ) s2 ON s1.data = s2.data AND s1.criado_em = s2.max_criado
");
$stmt->execute([$mesAtual . "-%"]);
$statusPorDia = [];
foreach ($stmt->fetchAll() as $linha) {
    $statusPorDia[(int) date("j", strtotime($linha["data"]))] = $linha["status"];
}

// Desvios do mês (status atencao ou grave), em ordem cronológica (para a rotação seguir sempre a mesma sequência).
// Agora lemos direto da tabela unificada status_qualidade_dia.
// Precisamos trazer o registro *mais recente* de cada dia para saber se o desvio final daquele dia é válido.
$stmt = $pdo->prepare("
    SELECT s.data, s.status, s.desvio AS descricao_desvio, s.acao_tomada, s.como_evitar, s.observacao
    FROM status_qualidade_dia s
    INNER JOIN (
        SELECT data, MAX(criado_em) AS max_criado
        FROM status_qualidade_dia
        WHERE data LIKE ?
        GROUP BY data
    ) max_s ON s.data = max_s.data AND s.criado_em = max_s.max_criado
    WHERE s.status IN ('atencao', 'grave') AND s.desvio IS NOT NULL AND s.desvio != ''
    ORDER BY s.data DESC, s.id DESC
");
$stmt->execute([$mesAtual . "-%"]);
$desvios = $stmt->fetchAll();

echo json_encode([
    "status_dias" => $statusPorDia,
    "desvios" => $desvios,
], JSON_UNESCAPED_UNICODE);
