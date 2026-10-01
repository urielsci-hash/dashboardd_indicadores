<?php
// Leitor mínimo de arquivos .xlsx (sem depender de bibliotecas externas / Composer).
// Um .xlsx é um ZIP com arquivos XML dentro — aqui lemos só o essencial: nomes das abas e as
// primeiras linhas de cada uma (o suficiente para o nosso modelo de indicador: categorias + valores).
class LeitorXlsx
{
    private ZipArchive $zip;
    private array $sharedStrings = [];
    private array $abas = [];

    public function __construct(string $caminhoArquivo)
    {
        $this->zip = new ZipArchive();
        if ($this->zip->open($caminhoArquivo) !== true) {
            throw new RuntimeException("Não foi possível abrir o arquivo .xlsx.");
        }
        $this->carregarSharedStrings();
        $this->carregarListaDeAbas();
    }

    private function xml(string $caminhoInterno): ?SimpleXMLElement
    {
        $conteudo = $this->zip->getFromName($caminhoInterno);
        if ($conteudo === false) {
            return null;
        }
        return new SimpleXMLElement($conteudo);
    }

    private function carregarSharedStrings(): void
    {
        $xml = $this->xml("xl/sharedStrings.xml");
        if (!$xml) {
            return;
        }
        foreach ($xml->si as $si) {
            if (isset($si->t)) {
                $this->sharedStrings[] = (string) $si->t;
            } else {
                $texto = "";
                foreach ($si->r as $r) {
                    $texto .= (string) $r->t;
                }
                $this->sharedStrings[] = $texto;
            }
        }
    }

    private function carregarListaDeAbas(): void
    {
        $workbook = $this->xml("xl/workbook.xml");
        $rels = $this->xml("xl/_rels/workbook.xml.rels");
        if (!$workbook || !$rels) {
            return;
        }
        $idParaCaminho = [];
        foreach ($rels->Relationship as $rel) {
            $idParaCaminho[(string) $rel["Id"]] = "xl/" . ltrim((string) $rel["Target"], "/");
        }
        $ns = $workbook->getNamespaces(true);
        $nsRelacionamento = $ns["r"] ?? null;
        foreach ($workbook->sheets->sheet as $sheet) {
            $atributosRid = $sheet->attributes($nsRelacionamento);
            $rId = (string) $atributosRid["id"];
            $nome = (string) $sheet["name"];
            if (isset($idParaCaminho[$rId])) {
                $this->abas[$nome] = $idParaCaminho[$rId];
            }
        }
    }

    public function nomesDasAbas(): array
    {
        return array_keys($this->abas);
    }

    private function letraParaColuna(string $referenciaCelula): int
    {
        preg_match("/[A-Z]+/", $referenciaCelula, $m);
        $letras = $m[0] ?? "A";
        $coluna = 0;
        foreach (str_split($letras) as $letra) {
            $coluna = $coluna * 26 + (ord($letra) - 64);
        }
        return $coluna - 1;
    }

    // Retorna as primeiras $maxLinhas linhas da aba, cada uma como um array indexado por coluna (0, 1, 2...).
    public function lerLinhas(string $nomeAba, int $maxLinhas = 5): array
    {
        if (!isset($this->abas[$nomeAba])) {
            return [];
        }
        $xml = $this->xml($this->abas[$nomeAba]);
        if (!$xml) {
            return [];
        }
        $linhas = [];
        $contador = 0;
        foreach ($xml->sheetData->row as $row) {
            if ($contador >= $maxLinhas) {
                break;
            }
            $valores = [];
            foreach ($row->c as $celula) {
                $indiceColuna = $this->letraParaColuna((string) $celula["r"]);
                $tipo = (string) $celula["t"];
                if ($tipo === "s") {
                    $valor = $this->sharedStrings[(int) $celula->v] ?? "";
                } elseif ($tipo === "inlineStr") {
                    $valor = (string) $celula->is->t;
                } else {
                    $valor = (string) $celula->v;
                }
                $valores[$indiceColuna] = $valor;
            }
            if (!empty($valores)) {
                $maiorIndice = max(array_keys($valores));
                $linhaCompleta = [];
                for ($i = 0; $i <= $maiorIndice; $i++) {
                    $linhaCompleta[$i] = $valores[$i] ?? "";
                }
                $linhas[] = $linhaCompleta;
            }
            $contador++;
        }
        return $linhas;
    }
}
