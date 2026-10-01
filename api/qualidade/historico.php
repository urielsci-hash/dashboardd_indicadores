<?php
require_once __DIR__ . "/../../config/config.php";
require_once __DIR__ . "/../../includes/auth.php";
exigirLogin();
header("Content-Type: application/json; charset=utf-8");

$pdo = getConexao();

$metodo = $_SERVER["REQUEST_METHOD"];

if ($metodo === "GET") {
    $ano = (int) ($_GET["ano"] ?? date("Y"));
    $mesNum = (int) ($_GET["mes"] ?? date("n"));
    $mesSelecionado = sprintf("%04d-%02d", $ano, $mesNum);

    $stmt = $pdo->prepare("SELECT sq.*, u.nome AS usuario_nome FROM status_qualidade_dia sq JOIN usuarios u ON u.id = sq.usuario_id WHERE sq.data LIKE ? ORDER BY sq.data DESC, sq.criado_em DESC");
    $stmt->execute([$mesSelecionado . "-%"]);
    $historico = $stmt->fetchAll();

    echo json_encode(["status" => "sucesso", "dados" => $historico], JSON_UNESCAPED_UNICODE);
    exit;
} elseif ($metodo === "PUT" || $metodo === "PATCH") {
    exigirDepartamento(["Qualidade"]);
    $dados = json_decode(file_get_contents("php://input"), true);

    if (!$dados || !isset($dados["id"])) {
        http_response_code(400);
        echo json_encode(["status" => "erro", "mensagem" => "ID não fornecido"]);
        exit;
    }

    $id = $dados["id"];
    $status = $dados["status"] ?? null;
    $observacao = ($dados["observacao"] ?? "") !== "" ? $dados["observacao"] : null;
    $desvio = ($dados["desvio"] ?? "") !== "" ? $dados["desvio"] : null;
    $acao_tomada = ($dados["acao_tomada"] ?? "") !== "" ? $dados["acao_tomada"] : null;
    $como_evitar = ($dados["como_evitar"] ?? "") !== "" ? $dados["como_evitar"] : null;

    if ($status === "ok") {
        $desvio = null;
        $acao_tomada = null;
        $como_evitar = null;
    }

    $stmt = $pdo->prepare("UPDATE status_qualidade_dia SET status = ?, observacao = ?, desvio = ?, acao_tomada = ?, como_evitar = ? WHERE id = ?");
    $sucesso = $stmt->execute([$status, $observacao, $desvio, $acao_tomada, $como_evitar, $id]);

    if ($sucesso) {
        registrarLog("qualidade", "Editou um lançamento de qualidade");
        echo json_encode(["status" => "sucesso"]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "erro", "mensagem" => "Falha ao atualizar no banco"]);
    }
    exit;
} elseif ($metodo === "DELETE") {
    exigirDepartamento(["Qualidade"]);
    $dados = json_decode(file_get_contents("php://input"), true);

    if (!$dados || !isset($dados["id"])) {
        http_response_code(400);
        echo json_encode(["status" => "erro", "mensagem" => "ID não fornecido"]);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM status_qualidade_dia WHERE id = ?");
    $sucesso = $stmt->execute([$dados["id"]]);

    if ($sucesso) {
        registrarLog("qualidade", "Excluiu um lançamento de qualidade");
        echo json_encode(["status" => "sucesso"]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "erro", "mensagem" => "Falha ao excluir"]);
    }
    exit;
}

http_response_code(405);
echo json_encode(["status" => "erro", "mensagem" => "Método não permitido"]);
