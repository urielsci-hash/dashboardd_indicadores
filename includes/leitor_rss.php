<?php
// Leitor simples de RSS/Atom (sem depender de bibliotecas externas). Faz cache em disco por
// 15 minutos para não sobrecarregar o feed de origem a cada atualização do dashboard.
class LeitorRss
{
    public static function buscarItens(array $urls, int $limite = 15): array
    {
        $todos = [];
        foreach ($urls as $url) {
            $todos = array_merge($todos, self::buscarFeed($url));
        }
        usort($todos, fn($a, $b) => strtotime($b["data"]) <=> strtotime($a["data"]));
        return array_slice($todos, 0, $limite);
    }

    private static function buscarFeed(string $url): array
    {
        $cacheArquivo = sys_get_temp_dir() . "/rss_sinergia_" . md5($url) . ".xml";
        $xmlTexto = null;

        if (file_exists($cacheArquivo) && (time() - filemtime($cacheArquivo) < 900)) {
            $xmlTexto = file_get_contents($cacheArquivo);
        } else {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            // Alguns sites bloqueiam requisições sem um User-Agent reconhecível.
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; PainelSinergiaAgro/1.0; +https://www.vinceassessorios.com.br)");
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Accept: application/rss+xml, application/xml, text/xml, */*"]);
            $resposta = curl_exec($ch);
            $codigoErroCurl = curl_errno($ch);
            $codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($resposta && $codigoHttp >= 200 && $codigoHttp < 300) {
                $xmlTexto = $resposta;
                file_put_contents($cacheArquivo, $resposta);
            } else {
                // Não derruba a página por isso — só deixa um rastro no log de erros do PHP
                // (visível no painel da hospedagem) para dar pra saber o motivo se persistir.
                error_log(sprintf(
                    "[noticias_rss] Falha ao buscar %s — curl_errno=%d http=%d",
                    $url,
                    $codigoErroCurl,
                    $codigoHttp
                ));
                if (file_exists($cacheArquivo)) {
                    // Feed fora do ar agora: usa o cache antigo em vez de deixar a seção vazia.
                    $xmlTexto = file_get_contents($cacheArquivo);
                }
            }
        }

        if (!$xmlTexto) {
            return [];
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlTexto);
        if (!$xml) {
            return [];
        }

        $listaItens = [];
        if (isset($xml->channel->item)) {
            $listaItens = $xml->channel->item;
        } elseif (isset($xml->entry)) {
            $listaItens = $xml->entry;
        }

        $itens = [];
        foreach ($listaItens as $item) {
            $titulo = trim((string) $item->title);
            $link = trim((string) ($item->link ?? ""));
            if ($link === "" && isset($item->link["href"])) {
                $link = (string) $item->link["href"];
            }
            $descricaoBruta = (string) ($item->description ?? $item->summary ?? "");
            $resumo = trim(strip_tags($descricaoBruta));
            if (strlen($resumo) > 160) {
                $resumo = substr($resumo, 0, 160) . "…";
            }
            $dataBruta = (string) ($item->pubDate ?? $item->updated ?? "");
            $itens[] = [
                "titulo" => $titulo,
                "link" => $link,
                "resumo" => $resumo,
                "data" => $dataBruta ? date("Y-m-d H:i:s", strtotime($dataBruta)) : date("Y-m-d H:i:s"),
            ];
        }
        return $itens;
    }
}
