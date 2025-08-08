<?php
require_once __DIR__ . '/config/db.php';

// 1. VERIFICAÇÃO DE PERMISSÃO E SEGURANÇA
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_nivel'] ?? 'user') !== 'admin') {
    $_SESSION['lista_produto_erro'] = "Você não tem permissão para realizar esta ação.";
    header("Location: dashboard.php#lista-produtos");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['temp_file'])) {
    $_SESSION['lista_produto_erro'] = "Nenhum arquivo temporário especificado para importação.";
    header("Location: dashboard.php#lista-produtos");
    exit();
}

// Pega o nome do arquivo temporário do POST
$temp_filename = basename($_POST['temp_file']); // basename() para segurança
$caminho_temporario = __DIR__ . '/uploads/' . $temp_filename;

// Valida se o arquivo temporário realmente existe
if (!file_exists($caminho_temporario)) {
    $_SESSION['lista_produto_erro'] = "O arquivo de importação não foi encontrado no servidor. Tente novamente.";
    header("Location: dashboard.php#lista-produtos");
    exit();
}

// 2. PROCESSAMENTO DO ARQUIVO CSV
$handle = fopen($caminho_temporario, "r");
if ($handle === false) {
    $_SESSION['lista_produto_erro'] = "Não foi possível abrir o arquivo enviado.";
    header("Location: dashboard.php#lista-produtos");
    exit();
}

$inseridos = 0;
$atualizados = 0;
$erros = 0;
$linha_atual = 0;
$log_erros = []; // Array para armazenar as mensagens de erro

// Inicia uma transação para garantir a integridade dos dados
$conn->begin_transaction();

try {
    // Prepara as consultas para reutilização dentro do loop
    $stmt_select = $conn->prepare("SELECT id FROM produtos WHERE codigo_produto = ?");
    $stmt_update = $conn->prepare("UPDATE produtos SET descricao = ?, referencia = ?, quantidade_estoque = ?, endereco = ?, tipo_embalagem = ?, lastro_camada = ?, codigo_barras_1 = ?, codigo_barras_2 = ?, codigo_barras_3 = ?, codigo_barras_4 = ?, codigo_barras_5 = ?, codigo_barras_6 = ?, codigo_barras_7 = ?, codigo_barras_8 = ?, codigo_barras_9 = ?, codigo_barras_10 = ?, codigo_barras_11 = ? WHERE codigo_produto = ?");
    $stmt_insert = $conn->prepare("INSERT INTO produtos (codigo_produto, descricao, referencia, quantidade_estoque, endereco, tipo_embalagem, lastro_camada, codigo_barras_1, codigo_barras_2, codigo_barras_3, codigo_barras_4, codigo_barras_5, codigo_barras_6, codigo_barras_7, codigo_barras_8, codigo_barras_9, codigo_barras_10, codigo_barras_11) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    // Pula a primeira linha (cabeçalho)
    fgetcsv($handle, 1000, ";"); // Alterado para usar ponto e vírgula

    while (($data = fgetcsv($handle, 1000, ";")) !== false) { // Alterado para usar ponto e vírgula
        $linha_atual++;
        // Garante que a linha tenha o número esperado de colunas
        if (count($data) < 7) { // Pelo menos as 7 colunas de dados do produto
            $log_erros[] = "Linha " . ($linha_atual + 1) . ": Número incorreto de colunas. Esperado: 7 ou mais, Encontrado: " . count($data);
            continue; // Pula para a próxima linha
        }

        // Atribui os dados a variáveis para clareza
        $codigo_produto     = trim($data[0]);
        $descricao          = trim($data[1]);
        $referencia         = trim($data[2]);
        $quantidade_estoque = (int)trim($data[3]);
        $endereco           = trim($data[4]);
        $tipo_embalagem     = trim($data[5]);
        $lastro_camada      = trim($data[6]);

        // Coleta os 11 códigos de barras das colunas do CSV
        $barcodes = [];
        for ($i = 7; $i <= 17; $i++) { // Loop para as 11 colunas de código de barras
            $barcode = isset($data[$i]) ? trim($data[$i]) : null;
            // Salva como NULL se estiver vazio para evitar problemas com chaves únicas
            $barcodes[] = !empty($barcode) ? $barcode : null;
        }

        // Validação mínima: código e descrição não podem ser vazios
        if (empty($codigo_produto) || empty($descricao)) {
            $log_erros[] = "Linha " . ($linha_atual + 1) . " (Código: " . ($codigo_produto ?: 'Vazio') . "): Código do produto ou descrição estão vazios.";
            continue;
        }

        // Verifica se o produto já existe
        $stmt_select->bind_param("s", $codigo_produto);
        $stmt_select->execute();
        $result = $stmt_select->get_result();

        if ($result->num_rows > 0) {
            // Produto existe, então ATUALIZA
            $update_params = array_merge(
                [$descricao, $referencia, $quantidade_estoque, $endereco, $tipo_embalagem, $lastro_camada],
                $barcodes,
                [$codigo_produto]
            );
            if ($stmt_update->bind_param("ssisssssssssssssss", ...$update_params) && $stmt_update->execute()) {
                $atualizados++;
            } else {
                $log_erros[] = "Linha " . ($linha_atual + 1) . " (Código: {$codigo_produto}): Erro ao ATUALIZAR - " . $stmt_update->error;
                continue;
            }
        } else {
            // Produto não existe, então INSERE
            $insert_params = array_merge(
                [$codigo_produto, $descricao, $referencia, $quantidade_estoque, $endereco, $tipo_embalagem, $lastro_camada],
                $barcodes
            );
            if ($stmt_insert->bind_param("sssissssssssssssss", ...$insert_params) && $stmt_insert->execute()) {
                $inseridos++;
            } else {
                $log_erros[] = "Linha " . ($linha_atual + 1) . " (Código: {$codigo_produto}): Erro ao INSERIR - " . $stmt_insert->error;
                continue;
            }
        }
    }

    // Se tudo correu bem, confirma as alterações no banco
    $conn->commit();
    $_SESSION['lista_produto_sucesso'] = "Importação concluída! <strong>{$inseridos}</strong> produtos cadastrados e <strong>{$atualizados}</strong> atualizados.";
    if (!empty($log_erros)) {
        $_SESSION['lista_produto_log_erros'] = $log_erros;
    }

} catch (Exception $e) {
    // Se algo deu errado, desfaz todas as alterações
    $conn->rollback();
    $_SESSION['lista_produto_erro'] = "Ocorreu um erro durante a importação e nenhuma alteração foi salva. Erro: " . $e->getMessage();
}

fclose($handle);
// APAGA O ARQUIVO TEMPORÁRIO APÓS O USO
unlink($caminho_temporario);

header("Location: dashboard.php#lista-produtos");
exit();
