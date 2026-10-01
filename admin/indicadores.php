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

                    if (empty($abas)) {
                        $erro = "Não encontrei nenhuma aba nessa planilha.";
                    } else {
                        foreach ($abas as $nomeIndicador) {
                            $linhas = $leitor->lerLinhas($nomeIndicador, 3);
                            $categorias = array_values(array_filter($linhas[0] ?? [], fn($v) => $v !== ""));
                            $valoresBrutos = $linhas[1] ?? [];
                            $valores = [];
                            foreach ($categorias as $indice => $categoria) {
                                $valores[] = (float) str_replace(",", ".", (string) ($valoresBrutos[$indice] ?? 0));
                            }
                            $tipo = (isset($linhas[2][0]) && strtolower(trim($linhas[2][0])) === "linha") ? "linha" : "barra";

                            $stmtExiste = $pdo->prepare("SELECT id FROM indicadores WHERE nome = ?");
                            $stmtExiste->execute([$nomeIndicador]);
                            $existente = $stmtExiste->fetch();

                            if ($existente) {
                                $stmt = $pdo->prepare("UPDATE indicadores SET tipo_grafico = ?, categorias = ?, valores = ?, data_inicio = ?, data_fim = ?, ativo = 1, usuario_id = ? WHERE id = ?");
                                $stmt->execute([$tipo, json_encode($categorias), json_encode($valores), $dataInicio, $dataFim, $_SESSION["usuario_id"], $existente["id"]]);
                            } else {
                                $stmt = $pdo->prepare("INSERT INTO indicadores (nome, tipo_grafico, categorias, valores, data_inicio, data_fim, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                $stmt->execute([$nomeIndicador, $tipo, json_encode($categorias), json_encode($valores), $dataInicio, $dataFim, $_SESSION["usuario_id"]]);
                            }
                        }
                        $pdo->prepare("INSERT INTO indicadores_arquivos (nome_arquivo, usuario_id) VALUES (?, ?)")->execute([$_FILES["arquivo"]["name"], $_SESSION["usuario_id"]]);
                        registrarLog("indicadores", "Enviou uma planilha atualizando " . count($abas) . " indicador(es)");
                        $mensagem = count($abas) . " indicador(es) criado(s)/atualizado(s) a partir da planilha.";
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

// --- Criar/editar indicador manualmente ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["salvar_indicador"])) {
    $nome = trim($_POST["nome"]);
    $tipo = $_POST["tipo_grafico"] === "linha" ? "linha" : "barra";
    $categorias = array_map("trim", explode(",", $_POST["categorias"]));
    $valores = array_map(fn($v) => (float) str_replace(",", ".", trim($v)), explode(",", $_POST["valores"]));
    $dataInicio = $_POST["data_inicio"];
    $dataFim = $_POST["data_fim"];
    $idIndicador = $_POST["id"] ?? null;

    if (count($categorias) !== count($valores) || $nome === "") {
        $erro = "Confira o nome e se a quantidade de categorias bate com a quantidade de valores.";
    } elseif ($idIndicador) {
        $stmt = $pdo->prepare("UPDATE indicadores SET nome = ?, tipo_grafico = ?, categorias = ?, valores = ?, data_inicio = ?, data_fim = ? WHERE id = ?");
        $stmt->execute([$nome, $tipo, json_encode($categorias), json_encode($valores), $dataInicio, $dataFim, $idIndicador]);
        registrarLog("indicadores", "Editou um indicador manualmente");
        $mensagem = "Indicador atualizado.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO indicadores (nome, tipo_grafico, categorias, valores, data_inicio, data_fim, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nome, $tipo, json_encode($categorias), json_encode($valores), $dataInicio, $dataFim, $_SESSION["usuario_id"]]);
        registrarLog("indicadores", "Criou um indicador manualmente");
        $mensagem = "Indicador criado.";
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

$indicadorEmEdicao = null;
if (!empty($_GET["editar"])) {
    $stmt = $pdo->prepare("SELECT * FROM indicadores WHERE id = ?");
    $stmt->execute([$_GET["editar"]]);
    $indicadorEmEdicao = $stmt->fetch();
}

$tituloPagina = "Indicadores";
require_once __DIR__ . "/../includes/layout_admin_topo.php";
?>
<h1>Indicadores de qualidade</h1>

<?php if ($mensagem): ?><p style="color:var(--verde-ok)"><?= htmlspecialchars($mensagem) ?></p><?php endif; ?>
<?php if ($erro): ?><p class="mensagem-erro"><?= htmlspecialchars($erro) ?></p><?php endif; ?>

<h2>Enviar planilha (.xlsx)</h2>
<p style="max-width:560px;font-size:13px;color:#666;">
  Uma aba por indicador: o nome da aba vira o nome do indicador (se já existir um indicador com
  esse nome, ele é atualizado — senão, é criado). Linha 1 = categorias, linha 2 = valores,
  linha 3 (opcional, só na primeira célula) = "linha" ou "barra".
</p>
<form class="formulario" method="post" enctype="multipart/form-data">
  <label>Planilha (.xlsx)</label>
  <input type="file" name="arquivo" accept=".xlsx" required>
  <label>Exibir de</label>
  <input type="date" name="data_inicio" value="<?= date("Y-m-d") ?>" required>
  <label>até</label>
  <input type="date" name="data_fim" value="<?= date("Y-m-d", strtotime("+30 days")) ?>" required>
  <button type="submit" name="enviar_planilha" value="1">Enviar planilha</button>
</form>

<h2 style="margin-top:32px;"><?= $indicadorEmEdicao ? "Editar indicador" : "Criar indicador manualmente" ?></h2>
<form class="formulario" method="post">
  <?php if ($indicadorEmEdicao): ?><input type="hidden" name="id" value="<?= $indicadorEmEdicao["id"] ?>"><?php endif; ?>
  <label>Nome do indicador</label>
  <input type="text" name="nome" value="<?= $indicadorEmEdicao ? htmlspecialchars($indicadorEmEdicao["nome"]) : "" ?>" required>
  <label>Tipo de gráfico</label>
  <select name="tipo_grafico">
    <option value="barra" <?= (!$indicadorEmEdicao || $indicadorEmEdicao["tipo_grafico"] === "barra") ? "selected" : "" ?>>Barra</option>
    <option value="linha" <?= ($indicadorEmEdicao && $indicadorEmEdicao["tipo_grafico"] === "linha") ? "selected" : "" ?>>Linha</option>
  </select>
  <label>Categorias (separadas por vírgula)</label>
  <input type="text" name="categorias" placeholder="Jan, Fev, Mar" value="<?= $indicadorEmEdicao ? htmlspecialchars(implode(", ", json_decode($indicadorEmEdicao["categorias"], true))) : "" ?>" required>
  <label>Valores (separados por vírgula, na mesma ordem)</label>
  <input type="text" name="valores" placeholder="12, 9, 15" value="<?= $indicadorEmEdicao ? htmlspecialchars(implode(", ", json_decode($indicadorEmEdicao["valores"], true))) : "" ?>" required>
  <label>Exibir de</label>
  <input type="date" name="data_inicio" value="<?= $indicadorEmEdicao ? $indicadorEmEdicao["data_inicio"] : date("Y-m-d") ?>" required>
  <label>até</label>
  <input type="date" name="data_fim" value="<?= $indicadorEmEdicao ? $indicadorEmEdicao["data_fim"] : date("Y-m-d", strtotime("+30 days")) ?>" required>
  <button type="submit" name="salvar_indicador" value="1"><?= $indicadorEmEdicao ? "Salvar edição" : "Criar indicador" ?></button>
</form>

<h2 style="margin-top:32px;">Indicadores <?= $mostrarTodos ? "(todos)" : "em exibição" ?></h2>
<p style="font-size:12px;"><a href="?<?= $mostrarTodos ? "" : "todos=1" ?>"><?= $mostrarTodos ? "Mostrar só os em exibição" : "Mostrar todos (inclusive inativos/expirados)" ?></a></p>
<table class="tabela-simples">
  <tr><th>Nome</th><th>Tipo</th><th>Período</th><th>Status</th><th>Ações</th></tr>
  <?php foreach ($indicadores as $i): ?>
  <tr>
    <td><?= htmlspecialchars($i["nome"]) ?></td>
    <td><?= $i["tipo_grafico"] ?></td>
    <td><?= date("d/m/Y", strtotime($i["data_inicio"])) ?> a <?= date("d/m/Y", strtotime($i["data_fim"])) ?></td>
    <td><?= $i["ativo"] ? "Ativo" : "Inativo" ?></td>
    <td class="acoes-linha">
      <a href="?editar=<?= $i["id"] ?>">Editar</a>
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
