<?php
require_once __DIR__ . "/../includes/auth.php";
exigirDepartamento(["Qualidade"]);
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/leitor_xlsx.php";

$pdo = getConexao();
$mensagem = null;
$erro = null;

// --- Upload em lote (.xlsx): cada aba cria OU atualiza um indicador (casado pelo nome) ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["enviar_planilha"]) && !empty($_FILES["arquivo"]["name"])) {
    $erroUpload = $_FILES["arquivo"]["error"] ?? UPLOAD_ERR_NO_FILE;
    if ($erroUpload !== UPLOAD_ERR_OK) {
        $erro = "Falha no envio do arquivo (código " . $erroUpload . "). Verifique o tamanho do arquivo.";
    } else {
        $extensao = strtolower(pathinfo($_FILES["arquivo"]["name"], PATHINFO_EXTENSION));
        if ($extensao !== "xlsx") {
            $erro = "Envie o arquivo no formato .xlsx (Excel 2007 ou mais recente).";
        } else {
            $pastaTemp = sys_get_temp_dir() . "/" . uniqid("indicadores_") . ".xlsx";
            if (!move_uploaded_file($_FILES["arquivo"]["tmp_name"], $pastaTemp)) {
                $erro = "Não foi possível salvar o arquivo enviado no servidor.";
            } else {
                try {
                    $leitor = new LeitorXlsx($pastaTemp);
                    $abas = $leitor->nomesDasAbas();
                    $dataInicio = $_POST["data_inicio"];
                    $dataFim = $_POST["data_fim"];

                    $tipo_indicador = $_POST["tipo_indicador"];

                    if (empty($abas)) {
                        $erro = "Não encontrei nenhuma aba nessa planilha.";
                    } else {
                        $linhas = $leitor->lerLinhas($abas[0], 30);
                        $dados = ["tipo" => $tipo_indicador];
                        $nome_formatado = "";

                        if ($tipo_indicador === "manutencao") {
                            $nome_formatado = "Manutenção";
                            $dados["data"] = [];
                            $dados["acoes"] = [];
                            for($c=5; $c<=16; $c++) {
                                $val = isset($linhas[5][$c]) ? (float) str_replace(",", ".", $linhas[5][$c]) : 0;
                                $dados["data"][] = $val;
                            }
                            for($r=8; $r<=19; $r++) {
                                $dados["acoes"][] = $linhas[$r][3] ?? "";
                            }
                        } elseif ($tipo_indicador === "producao_pcp") {
                            $nome_formatado = "Produção PCP";
                            $dados["op"] = [];
                            $dados["of"] = [];
                            $dados["entrega"] = [];
                            $dados["acoes"] = [];
                            for($c=5; $c<=16; $c++) {
                                $dados["op"][] = isset($linhas[7][$c]) ? (float) str_replace(",", ".", $linhas[7][$c]) : 0;
                                $dados["of"][] = isset($linhas[8][$c]) ? (float) str_replace(",", ".", $linhas[8][$c]) : 0;
                                $dados["entrega"][] = isset($linhas[9][$c]) ? (float) str_replace(",", ".", $linhas[9][$c]) : 0;
                            }
                            for($r=12; $r<=23; $r++) {
                                $dados["acoes"][] = $linhas[$r][3] ?? "";
                            }
                        } elseif ($tipo_indicador === "qualidade") {
                            $nome_formatado = "Qualidade";
                            $dados["refugo"] = [
                                isset($linhas[19][4]) ? (float) str_replace(",", ".", $linhas[19][4]) : 0,
                                isset($linhas[19][10]) ? (float) str_replace(",", ".", $linhas[19][10]) : 0
                            ];
                            $dados["reprocesso"] = [
                                isset($linhas[20][4]) ? (float) str_replace(",", ".", $linhas[20][4]) : 0,
                                isset($linhas[20][10]) ? (float) str_replace(",", ".", $linhas[20][10]) : 0
                            ];
                            $dados["total"] = [
                                isset($linhas[21][4]) ? (float) str_replace(",", ".", $linhas[21][4]) : 0,
                                isset($linhas[21][10]) ? (float) str_replace(",", ".", $linhas[21][10]) : 0
                            ];
                        } elseif ($tipo_indicador === "perdas") {
                            $nome_formatado = "Perdas";
                            $dados["datasets"] = [];
                            for($r=3; $r<=9; $r++) {
                                if(!isset($linhas[$r][4])) continue;
                                $ds = ["label" => $linhas[$r][4], "data" => []];
                                for($c=5; $c<=10; $c++) {
                                    $ds["data"][] = isset($linhas[$r][$c]) && trim($linhas[$r][$c]) !== "" ? (float) str_replace(",", ".", $linhas[$r][$c]) : null;
                                }
                                $dados["datasets"][] = $ds;
                            }
                        }

                        $stmtExiste = $pdo->prepare("SELECT id FROM indicadores WHERE nome = ?");
                        $stmtExiste->execute([$nome_formatado]);
                        $existente = $stmtExiste->fetch();

                        if ($existente) {
                            $stmt = $pdo->prepare("UPDATE indicadores SET tipo_grafico = ?, categorias = ?, valores = ?, data_inicio = ?, data_fim = ?, ativo = 1, usuario_id = ? WHERE id = ?");
                            $stmt->execute([$tipo_indicador, json_encode([]), json_encode($dados, JSON_UNESCAPED_UNICODE), $dataInicio, $dataFim, $_SESSION["usuario_id"], $existente["id"]]);
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO indicadores (nome, tipo_grafico, categorias, valores, data_inicio, data_fim, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$nome_formatado, $tipo_indicador, json_encode([]), json_encode($dados, JSON_UNESCAPED_UNICODE), $dataInicio, $dataFim, $_SESSION["usuario_id"]]);
                        }

                        $pdo->prepare("INSERT INTO indicadores_arquivos (nome_arquivo, usuario_id) VALUES (?, ?)")->execute([$_FILES["arquivo"]["name"], $_SESSION["usuario_id"]]);
                        registrarLog("indicadores", "Enviou planilha e atualizou indicador: $nome_formatado");
                        $mensagem = "Indicador $nome_formatado atualizado a partir da planilha.";
                    }
                } catch (Exception $e) {
                    $erro = "Não foi possível ler o arquivo: " . $e->getMessage();
                } finally {
                    @unlink($pastaTemp);
                }
            }
        }
    }
}



if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["alternar_ativo"])) {
    $pdo->prepare("UPDATE indicadores SET ativo = 1 - ativo WHERE id = ?")->execute([$_POST["id"]]);
    registrarLog("indicadores", "Ativou/desativou um indicador");
    $mensagem = "Indicador atualizado.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["excluir_indicador"])) {
    $pdo->prepare("DELETE FROM indicadores WHERE id = ?")->execute([$_POST["id"]]);
    registrarLog("indicadores", "Excluiu um indicador");
    $mensagem = "Indicador excluído.";
}

$mostrarTodos = isset($_GET["todos"]);
if ($mostrarTodos) {
    $indicadores = $pdo->query("SELECT * FROM indicadores ORDER BY ativo DESC, nome")->fetchAll();
} else {
    $indicadores = $pdo->query("SELECT * FROM indicadores WHERE ativo = 1 AND CURDATE() BETWEEN data_inicio AND data_fim ORDER BY nome")->fetchAll();
}



$tituloPagina = "Indicadores";
require_once __DIR__ . "/../includes/layout_admin_topo.php";
?>
<h1>Indicadores de qualidade</h1>

<?php if ($mensagem): ?><p style="color:var(--verde-ok)"><?= htmlspecialchars($mensagem) ?></p><?php endif; ?>
<?php if ($erro): ?><p class="mensagem-erro"><?= htmlspecialchars($erro) ?></p><?php endif; ?>

<h2>Enviar planilha (.xlsx)</h2>
<p style="max-width:560px;font-size:13px;color:#666;">
  Selecione qual indicador da ISO 9001 está enviando. O sistema lerá exatamente a estrutura da planilha esperada para aquele indicador.
</p>
<form class="formulario" method="post" enctype="multipart/form-data">
  <label>Tipo de Indicador</label>
  <select name="tipo_indicador" required>
    <option value="manutencao">Manutenção (Indicador_Manutenção_2026.xlsx)</option>
    <option value="producao_pcp">Produção PCP (Indicador_Produção_PCP_2026.xlsx)</option>
    <option value="qualidade">Qualidade / Não Conforme (Indicador_Qualidade_Prod.Não Conforme_2026.xlsx)</option>
    <option value="perdas">Perdas (Ind_Monitoramento_Produção_Tipo Perdas 2026.xlsx)</option>
  </select>
  <label>Planilha (.xlsx)</label>
  <input type="file" name="arquivo" accept=".xlsx" required>
  <label>Exibir de</label>
  <input type="date" name="data_inicio" value="<?= date("Y-m-d") ?>" required>
  <label>até</label>
  <input type="date" name="data_fim" value="<?= date("Y-m-d", strtotime("+30 days")) ?>" required>
  <button type="submit" name="enviar_planilha" value="1">Processar planilha</button>
</form>



<h2 style="margin-top:32px;">Indicadores <?= $mostrarTodos ? "(todos)" : "em exibição" ?></h2>
<p style="font-size:12px;"><a href="?<?= $mostrarTodos ? "" : "todos=1" ?>"><?= $mostrarTodos ? "Mostrar só os em exibição" : "Mostrar todos (inclusive inativos/expirados)" ?></a></p>
<table class="tabela-simples">
  <tr><th>Nome</th><th>Tipo</th><th>Período</th><th>Status</th><th>Ações</th></tr>
  <?php foreach ($indicadores as $i): ?>
  <tr>
    <td><?= htmlspecialchars($i["nome"]) ?></td>
    <td>ISO 9001</td>
    <td><?= date("d/m/Y", strtotime($i["data_inicio"])) ?> a <?= date("d/m/Y", strtotime($i["data_fim"])) ?></td>
    <td><?= $i["ativo"] ? "Ativo" : "Inativo" ?></td>
    <td class="acoes-linha">
      <form method="post" style="display:inline;">
        <input type="hidden" name="id" value="<?= $i["id"] ?>">
        <button type="submit" name="alternar_ativo" value="1" class="botao-link"><?= $i["ativo"] ? "Desativar" : "Ativar" ?></button>
      </form>
      <form method="post" onsubmit="return confirm(&quot;Excluir este indicador? Essa ação não pode ser desfeita.&quot;);" style="display:inline;">
        <input type="hidden" name="id" value="<?= $i["id"] ?>">
        <button type="submit" name="excluir_indicador" value="1" class="botao-link-perigo">Excluir</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$indicadores): ?><tr><td colspan="5">Nenhum indicador <?= $mostrarTodos ? "cadastrado" : "em exibição no momento" ?>.</td></tr><?php endif; ?>
</table>
<?php require_once __DIR__ . "/../includes/layout_admin_rodape.php"; ?>
