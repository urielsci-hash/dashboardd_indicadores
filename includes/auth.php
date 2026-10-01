<?php
require_once __DIR__ . "/../config/database.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function usuarioLogado() {
    return isset($_SESSION["usuario_id"]);
}

function departamentoUsuario() {
    return $_SESSION["departamento_nome"] ?? null;
}

function precisaTrocarSenha() {
    return !empty($_SESSION["deve_trocar_senha"]);
}

// Nome do script atual sem extensão (ex.: "status", "trocar-senha"), usado para não criar
// loop de redirecionamento na troca de senha obrigatoria.
function nomeScriptAtual() {
    return basename($_SERVER["SCRIPT_NAME"] ?? "", ".php");
}

function exigirLogin() {
    if (!usuarioLogado()) {
        tentarLoginPorToken();
    }
    if (!usuarioLogado()) {
        header("Location: " . SITE_URL . "/login");
        exit;
    }
    // Usuario com senha inicial pendente so pode acessar a propria tela de troca de senha
    // (e o logout); qualquer outra página redireciona para lá primeiro.
    if (precisaTrocarSenha() && !in_array(nomeScriptAtual(), ["trocar-senha", "logout"], true)) {
        header("Location: " . SITE_URL . "/trocar-senha");
        exit;
    }
}

// Só deixa passar se o departamento do usuário logado estiver na lista permitida.
// Cada página de admin chama isso dizendo quem pode entrar, ex: exigirDepartamento(["Qualidade"]).
function exigirDepartamento($departamentosPermitidos) {
    exigirLogin();
    if (!in_array(departamentoUsuario(), (array) $departamentosPermitidos, true)) {
        http_response_code(403);
        die("Você não tem permissão para acessar esta página.");
    }
}

function fazerLogin($usuario) {
    session_regenerate_id(true);
    $_SESSION["usuario_id"] = $usuario["id"];
    $_SESSION["nome"] = $usuario["nome"];
    $_SESSION["departamento_id"] = $usuario["departamento_id"];
    $_SESSION["departamento_nome"] = $usuario["departamento_nome"];
    $_SESSION["deve_trocar_senha"] = (bool) $usuario["deve_trocar_senha"];
}

// Para onde mandar o usuário logo após autenticar (login ou troca de senha obrigatória):
// Visualizacao vai direto para a tela publica; os demais (que tem painel) vao para o /admin.
function destinoAposLogin() {
    if (departamentoUsuario() === "Visualizacao") {
        return SITE_URL . "/index";
    }
    return SITE_URL . "/admin/";
}

// Grava so uma linha curta no log (quem, em qual modulo, uma frase) — nunca o conteudo alterado,
// porque o TI (quem vê esse log) não deve ter acesso ao conteúdo em si. Os logs com mais de
// 30 dias são apagados automaticamente a cada chamada (não precisa de cron configurado).
function registrarLog($modulo, $descricao) {
    if (!usuarioLogado()) {
        return;
    }
    $pdo = getConexao();
    $stmt = $pdo->prepare("INSERT INTO log_auditoria (usuario_id, modulo, descricao) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION["usuario_id"], $modulo, $descricao]);
    limparLogsAntigos($pdo);
}

function limparLogsAntigos($pdo = null) {
    $pdo = $pdo ?: getConexao();
    $pdo->exec("DELETE FROM log_auditoria WHERE data_hora < DATE_SUB(NOW(), INTERVAL 30 DAY)");
}

function criarTokenLembrar($usuarioId) {
    $pdo = getConexao();
    $token = bin2hex(random_bytes(32));
    $hash = password_hash($token, PASSWORD_DEFAULT);
    $expira = date("Y-m-d H:i:s", strtotime("+" . SESSAO_VISUALIZACAO_DIAS . " days"));
    $stmt = $pdo->prepare("INSERT INTO tokens_lembrar (usuario_id, token_hash, expira_em) VALUES (?, ?, ?)");
    $stmt->execute([$usuarioId, $hash, $expira]);
    setcookie("lembrar_token", $usuarioId . ":" . $token, time() + SESSAO_VISUALIZACAO_DIAS * 86400, "/", "", true, true);
}

function tentarLoginPorToken() {
    if (empty($_COOKIE["lembrar_token"])) {
        return;
    }
    [$usuarioId, $token] = array_pad(explode(":", $_COOKIE["lembrar_token"], 2), 2, null);
    if (!$usuarioId || !$token) {
        return;
    }

    $pdo = getConexao();
    $stmt = $pdo->prepare("SELECT * FROM tokens_lembrar WHERE usuario_id = ? AND expira_em > NOW()");
    $stmt->execute([$usuarioId]);
    foreach ($stmt->fetchAll() as $registro) {
        if (password_verify($token, $registro["token_hash"])) {
            $stmtU = $pdo->prepare("SELECT u.*, d.nome AS departamento_nome FROM usuarios u JOIN departamentos d ON d.id = u.departamento_id WHERE u.id = ? AND u.ativo = 1");
            $stmtU->execute([$usuarioId]);
            $usuario = $stmtU->fetch();
            if ($usuario) {
                fazerLogin($usuario);
            }
            return;
        }
    }
}
