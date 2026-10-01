<?php
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../includes/auth.php";
exigirLogin();
header("Content-Type: application/json; charset=utf-8");

$cacheArquivo = sys_get_temp_dir() . "/clima_sinergia_agro.json";

if (file_exists($cacheArquivo) && (time() - filemtime($cacheArquivo) < 1800)) {
    echo file_get_contents($cacheArquivo);
    exit;
}

$url = "https://api.openweathermap.org/data/2.5/weather?q=" . urlencode(CLIMA_CIDADE) . "&units=metric&lang=pt_br&appid=" . CLIMA_API_KEY;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$resposta = curl_exec($ch);
curl_close($ch);

if ($resposta === false) {
    echo json_encode(["erro" => true]);
    exit;
}

file_put_contents($cacheArquivo, $resposta);
echo $resposta;
