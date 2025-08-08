<?php
require_once __DIR__ . '/config/db.php';

// 1. VERIFICAÇÃO DE PERMISSÃO E SEGURANÇA
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_nivel'] ?? 'user') !== 'admin') {
    $_SESSION['lista_produto_erro'] = "Você não tem permissão para realizar esta ação.";
    header("Location: dashboard.php#lista-produtos");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['arquivo_produtos'])) {
    $_SESSION['lista_produto_erro'] = "Nenhum arquivo foi enviado.";
    header("Location: dashboard.php#lista-produtos");
    exit();
}

$arquivo = $_FILES['arquivo_produtos'];

if ($arquivo['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['lista_produto_erro'] = "Erro no upload do arquivo. Código: " . $arquivo['error'];
    header("Location: dashboard.php#lista-produtos");
    exit();
}

$extensao = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
if (strtolower($extensao) !== 'csv') {
    $_SESSION['lista_produto_erro'] = "Formato de arquivo inválido. Por favor, envie um arquivo .csv";
    header("Location: dashboard.php#lista-produtos");
    exit();
}

// 2. SALVA O ARQUIVO TEMPORARIAMENTE
$target_dir = __DIR__ . '/uploads/';
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0755, true);
}
$temp_filename = uniqid('import_', true) . '.csv';
$temp_filepath = $target_dir . $temp_filename;

if (!move_uploaded_file($arquivo['tmp_name'], $temp_filepath)) {
    $_SESSION['lista_produto_erro'] = "Falha ao mover o arquivo para o diretório temporário.";
    header("Location: dashboard.php#lista-produtos");
    exit();
}

// 3. LÊ OS DADOS PARA A PRÉ-VISUALIZAÇÃO
$handle = fopen($temp_filepath, "r");
$header = fgetcsv($handle, 1000, ";"); // Alterado para usar ponto e vírgula
$preview_rows = [];
$total_rows = 0;
$preview_limit = 10;

while (($row = fgetcsv($handle, 1000, ";")) !== false) { // Alterado para usar ponto e vírgula
    $total_rows++;
    if ($total_rows <= $preview_limit) {
        $preview_rows[] = $row;
    }
}
fclose($handle);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Pré-visualização da Importação</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .preview-container { max-width: 1200px; margin: 40px auto; }
        .table-responsive { max-height: 500px; }
    </style>
</head>
<body>
    <div class="container preview-container">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">Pré-visualização da Importação de Produtos</h3>
            </div>
            <div class="card-body">
                <div class="alert alert-secondary">
                    Arquivo <strong><?php echo htmlspecialchars($arquivo['name']); ?></strong> carregado.
                    Total de <strong><?php echo $total_rows; ?></strong> linhas de dados encontradas.
                    <br>
                    Abaixo estão as primeiras <strong><?php echo min($total_rows, $preview_limit); ?></strong> linhas para sua conferência.
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover">
                        <thead class="table-dark">
                            <tr>
                                <?php foreach ($header as $col): ?>
                                    <th><?php echo htmlspecialchars($col); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preview_rows as $row): ?>
                                <tr>
                                    <?php foreach ($row as $cell): ?>
                                        <td><?php echo htmlspecialchars($cell); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-end">
                <form action="import_produtos.php" method="POST" style="display: inline;">
                    <input type="hidden" name="temp_file" value="<?php echo htmlspecialchars($temp_filename); ?>">
                    <a href="dashboard.php#lista-produtos" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Confirmar e Importar
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
