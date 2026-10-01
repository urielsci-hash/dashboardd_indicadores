<?php
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../includes/auth.php";
exigirLogin();
header("Content-Type: application/json; charset=utf-8");

$pdo = getConexao();
$pastaUploads = __DIR__ . "/../uploads/mural/";

$stmt = $pdo->prepare("SELECT titulo, conteudo, tipo, imagem_path FROM mural_posts WHERE ativo = 1 AND CURDATE() BETWEEN data_inicio AND data_fim ORDER BY criado_em DESC");
$stmt->execute();

$mural = [];
foreach ($stmt->fetchAll() as $post) {
    // Corrige a causa raiz do "erro ao carregar imagem": só manda pro dashboard uma imagem
    // que realmente existe em disco agora. Se sumiu/nunca chegou a salvar, cai para texto.
    if ($post["tipo"] === "imagem") {
        $existeNoDisco = $post["imagem_path"] && file_exists($pastaUploads . $post["imagem_path"]);
        if (!$existeNoDisco) {
            $post["tipo"] = "texto";
            $post["conteudo"] = $post["conteudo"] ?: "";
        }
    }
    $mural[] = $post;
}

echo json_encode(["mural" => $mural], JSON_UNESCAPED_UNICODE);
