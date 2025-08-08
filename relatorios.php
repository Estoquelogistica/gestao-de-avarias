<?php
require_once __DIR__ . '/config/db.php';

// Protege a página: se o usuário não estiver logado, redireciona para o login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$nome_usuario = $_SESSION['usuario_nome'];
$nivel_usuario = $_SESSION['usuario_nivel'] ?? 'user';

// --- Lógica para os Filtros (Exemplo) ---
$data_inicial = isset($_GET['data_inicial']) && !empty($_GET['data_inicial']) ? $_GET['data_inicial'] : date('Y-m-01');
$data_final = isset($_GET['data_final']) && !empty($_GET['data_final']) ? $_GET['data_final'] : date('Y-m-d');
$tipo_relatorio_selecionado = $_GET['tipo_relatorio'] ?? 'todos'; // 'todos', 'avaria', 'uso_e_consumo'
// --- Lógica para construção da query dinâmica com base nos filtros ---
$where_conditions = ["data_ocorrencia BETWEEN ? AND ?"];
$params = [$data_inicial, $data_final];
$types = 'ss';
if ($tipo_relatorio_selecionado !== 'todos') {
    $where_conditions[] = "tipo = ?";
    $params[] = $tipo_relatorio_selecionado;
    $types .= 's';
}
$where_sql = "WHERE " . implode(' AND ', $where_conditions);

// --- Lógica para o Relatório de Percentual por Tipo ---
$sql_percentual = "SELECT 
                        SUM(CASE WHEN tipo = 'avaria' THEN 1 ELSE 0 END) as total_avarias,
                        SUM(CASE WHEN tipo = 'uso_e_consumo' THEN 1 ELSE 0 END) as total_consumo
                   FROM avarias 
                   {$where_sql}";
$stmt_percentual = $conn->prepare($sql_percentual);
$stmt_percentual->bind_param($types, ...$params);
$stmt_percentual->execute();
$result_percentual = $stmt_percentual->get_result();
$dados_percentual = $result_percentual->fetch_assoc();
$stmt_percentual->close();

$total_avarias = (int)($dados_percentual['total_avarias'] ?? 0);
$total_consumo = (int)($dados_percentual['total_consumo'] ?? 0);
$total_registros = $total_avarias + $total_consumo;
$percent_avarias = ($total_registros > 0) ? ($total_avarias / $total_registros) * 100 : 0;
$percent_consumo = ($total_registros > 0) ? ($total_consumo / $total_registros) * 100 : 0;
$labels_grafico_percentual_json = json_encode(['Avarias', 'Uso e Consumo']);
$dados_grafico_percentual_json = json_encode([$total_avarias, $total_consumo]);

// --- Lógica para o Relatório de Motivos ---

$sql_motivos = "SELECT 
                    COALESCE(NULLIF(TRIM(motivo), ''), 'Não especificado') as motivo_tratado, 
                    COUNT(id) as total_ocorrencias
                FROM avarias 
                {$where_sql}
                GROUP BY motivo_tratado
                ORDER BY total_ocorrencias DESC";

$stmt_motivos = $conn->prepare($sql_motivos);
$stmt_motivos->bind_param($types, ...$params);
$stmt_motivos->execute();
$result_motivos = $stmt_motivos->get_result();
$dados_motivos = $result_motivos->fetch_all(MYSQLI_ASSOC);
$stmt_motivos->close();

// Preparar dados para o gráfico
$labels_grafico_motivos = array_column($dados_motivos, 'motivo_tratado');
$dados_grafico_motivos = array_column($dados_motivos, 'total_ocorrencias');
$labels_grafico_motivos_json = json_encode($labels_grafico_motivos);
$dados_grafico_motivos_json = json_encode($dados_grafico_motivos);

// --- Lógica para o Relatório de Performance por Rua (Setor) ---
$sql_ruas = "SELECT
                CASE
                    WHEN UPPER(SUBSTRING(p.endereco, 1, 1)) = 'A' THEN '01'
                    WHEN UPPER(SUBSTRING(p.endereco, 1, 1)) = 'B' THEN '02'
                    WHEN UPPER(SUBSTRING(p.endereco, 1, 1)) = 'C' THEN '03'
                    WHEN UPPER(SUBSTRING(p.endereco, 1, 1)) = 'D' THEN '04'
                    WHEN UPPER(SUBSTRING(p.endereco, 1, 1)) = 'E' THEN '05'
                    WHEN UPPER(SUBSTRING(p.endereco, 1, 1)) = 'F' THEN '06'
                    WHEN UPPER(SUBSTRING(p.endereco, 1, 1)) = 'G' THEN '07'
                    WHEN UPPER(SUBSTRING(p.endereco, 1, 1)) = 'H' THEN '08'
                    WHEN UPPER(SUBSTRING(p.endereco, 1, 1)) = 'I' THEN '09'
                    WHEN UPPER(SUBSTRING(p.endereco, 1, 1)) = 'K' THEN '11'
                    ELSE SUBSTRING(p.endereco, 1, 2)
                END as rua,                
                SUM(CASE WHEN a.tipo = 'avaria' THEN a.quantidade ELSE 0 END) as total_avaria,
                SUM(CASE WHEN a.tipo = 'uso_e_consumo' THEN a.quantidade ELSE 0 END) as total_consumo
            FROM avarias a
            LEFT JOIN produtos p ON a.produto_id = p.id
            {$where_sql} AND p.endereco IS NOT NULL AND p.endereco != ''
            GROUP BY rua
            ORDER BY (SUM(CASE WHEN a.tipo = 'avaria' THEN a.quantidade ELSE 0 END) + SUM(CASE WHEN a.tipo = 'uso_e_consumo' THEN a.quantidade ELSE 0 END)) DESC";
$stmt_ruas = $conn->prepare($sql_ruas);
$stmt_ruas->bind_param($types, ...$params);
$stmt_ruas->execute();
$result_ruas = $stmt_ruas->get_result();
$dados_ruas = $result_ruas->fetch_all(MYSQLI_ASSOC);
$stmt_ruas->close();

// --- Preparar dados para o gráfico de Ruas ---
$labels_grafico_ruas = [];
$dados_grafico_ruas_avaria = [];
$dados_grafico_ruas_consumo = [];
$total_geral_ruas = 0;

foreach ($dados_ruas as $rua) {
    $total_rua = (int)$rua['total_avaria'] + (int)$rua['total_consumo'];
    // Adiciona a rua ao gráfico apenas se houver algum valor a ser mostrado
    if ($total_rua > 0) {
        $labels_grafico_ruas[] = 'Rua ' . $rua['rua'];
        $dados_grafico_ruas_avaria[] = (int)$rua['total_avaria'];
        $dados_grafico_ruas_consumo[] = (int)$rua['total_consumo'];
        $total_geral_ruas += $total_rua;
    }
}
$labels_grafico_ruas_json = json_encode($labels_grafico_ruas);
$dados_grafico_ruas_avaria_json = json_encode($dados_grafico_ruas_avaria);
$dados_grafico_ruas_consumo_json = json_encode($dados_grafico_ruas_consumo);

// --- Lógica para o Relatório de Tendência por Produto ---
$produto_id_tendencia = isset($_GET['produto_id_tendencia']) ? (int)$_GET['produto_id_tendencia'] : 0;
$agrupamento_tendencia = $_GET['tendencia_agrupamento'] ?? 'mes'; // 'dia', 'mes', 'ano'
$produto_tendencia_nome = '';
$labels_grafico_tendencia_json = '[]';
$dados_grafico_tendencia_json = '[]';

if ($produto_id_tendencia > 0) {
    // 1. Buscar nome do produto para o título
    $stmt_nome_tendencia = $conn->prepare("SELECT descricao FROM produtos WHERE id = ?");
    $stmt_nome_tendencia->bind_param("i", $produto_id_tendencia);
    $stmt_nome_tendencia->execute();
    $result_nome_tendencia = $stmt_nome_tendencia->get_result();
    if ($row_nome_tendencia = $result_nome_tendencia->fetch_assoc()) {
        $produto_tendencia_nome = $row_nome_tendencia['descricao'];
    }
    $stmt_nome_tendencia->close();

    // 2. Buscar dados da tendência (agrupados por dia, mês ou ano)
    $where_tendencia_sql = $where_sql . " AND a.produto_id = ?";
    $params_tendencia = array_merge($params, [$produto_id_tendencia]);
    $types_tendencia = $types . 'i';

    // Define os campos e agrupamentos da query com base na seleção do usuário
    $select_fields = '';
    $group_by_sql = '';
    $order_by_sql = '';

    switch ($agrupamento_tendencia) {
        case 'dia':
            $select_fields = "DATE(a.data_ocorrencia) as data_agrupada, SUM(a.quantidade) as quantidade_total";
            $group_by_sql = "GROUP BY data_agrupada";
            $order_by_sql = "ORDER BY data_agrupada ASC";
            break;
        case 'ano':
            $select_fields = "YEAR(a.data_ocorrencia) as ano, SUM(a.quantidade) as quantidade_total";
            $group_by_sql = "GROUP BY ano";
            $order_by_sql = "ORDER BY ano ASC";
            break;
        case 'mes':
        default:
            $select_fields = "YEAR(a.data_ocorrencia) as ano, MONTH(a.data_ocorrencia) as mes, SUM(a.quantidade) as quantidade_total";
            $group_by_sql = "GROUP BY ano, mes";
            $order_by_sql = "ORDER BY ano, mes ASC";
            break;
    }

    $sql_tendencia = "SELECT {$select_fields}
                      FROM avarias a
                      {$where_tendencia_sql}
                      {$group_by_sql} {$order_by_sql}";
    
    $stmt_tendencia = $conn->prepare($sql_tendencia);
    $stmt_tendencia->bind_param($types_tendencia, ...$params_tendencia);
    $stmt_tendencia->execute();
    $result_tendencia = $stmt_tendencia->get_result();
    $dados_tendencia_raw = $result_tendencia->fetch_all(MYSQLI_ASSOC);
    $stmt_tendencia->close();

    // 3. Preparar dados para o gráfico, formatando os rótulos de acordo com o agrupamento
    $labels_grafico_tendencia = [];
    $dados_grafico_tendencia = [];

    switch ($agrupamento_tendencia) {
        case 'dia':
            foreach ($dados_tendencia_raw as $row) {
                $labels_grafico_tendencia[] = date('d/m/y', strtotime($row['data_agrupada']));
                $dados_grafico_tendencia[] = (int)$row['quantidade_total'];
            }
            break;
        case 'ano':
            foreach ($dados_tendencia_raw as $row) {
                $labels_grafico_tendencia[] = $row['ano'];
                $dados_grafico_tendencia[] = (int)$row['quantidade_total'];
            }
            break;
        case 'mes':
        default:
            $meses_nomes = ["Jan", "Fev", "Mar", "Abr", "Mai", "Jun", "Jul", "Ago", "Set", "Out", "Nov", "Dez"];
            foreach ($dados_tendencia_raw as $row) {
                $labels_grafico_tendencia[] = $meses_nomes[$row['mes'] - 1] . '/' . substr($row['ano'], -2);
                $dados_grafico_tendencia[] = (int)$row['quantidade_total'];
            }
            break;
    }
    
    $labels_grafico_tendencia_json = json_encode($labels_grafico_tendencia);
    $dados_grafico_tendencia_json = json_encode($dados_grafico_tendencia);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <base target="_top">
  <meta charset="UTF-8">
  <title>Relatórios - Gestão de Avarias</title>
  <link rel="icon" href="img/favicon.ico" type="image/x-icon">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
  <style>
    /* Copiando os estilos do dashboard.php para manter a consistência visual */
    body { font-family: 'Inter', Arial, sans-serif; background-color: #f8f9fb; display: flex; min-height: 100vh; margin: 0; }
    .sidebar { width: 250px; background-color: #254c90; color: white; padding: 0; box-shadow: 2px 0 5px rgba(0,0,0,0.1); display: flex; flex-direction: column; flex-shrink: 0; }
    .sidebar-header { padding: 20px; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
    .logo-container { background-color: white; border-radius: 8px; width: 130px; padding: 0,5px; display: flex; justify-content: center; align-items: center; margin-bottom: 15px; overflow: hidden; }
    .logo-container img { max-width: 100%; height: auto; }
    .sidebar h2 { font-size: 1.2em; margin-bottom: 5px; }
    .sidebar h3 { font-size: 0.9em; opacity: 0.8; }
    .sidebar-menu { flex-grow: 1; list-style: none; padding: 15px 0; margin: 0; }
    .sidebar-menu .nav-item { padding: 0 10px; }
    .sidebar-menu .nav-link { display: block; padding: 12px 15px; color: white; text-decoration: none; transition: background-color 0.2s ease; font-size: 1em; border-radius: 0.5rem; border: none; margin-bottom: 5px; }
    .sidebar-menu .nav-link:hover { background-color: #1d3870; color: white; }
    .sidebar-menu .nav-link.active { background-color: #1d3870; color: white; font-weight: 500; }
    .sidebar-menu .nav-link i { margin-right: 10px; width: 20px; text-align: center; }
    .main-content { flex-grow: 1; padding: 25px; background-color: #f8f9fb; overflow-y: auto; }
    .main-header { margin-bottom: 25px; }
    .main-header h1 { color: #254c90; font-weight: 700; font-size: 1.5rem; }
    .content-section { background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 8px 16px rgba(0,0,0,0.1); margin-bottom: 20px; }
    .content-section h3 { font-size: 1.15rem; }
    .content-section .table {
        font-size: 0.875rem;
    }
    .text-danger { color: #dc3545 !important; }
    .text-success { color: #198754 !important; }
    .percent-text { font-size: 1.1rem; }
    .position-absolute {
        position: absolute !important;
    }
    #search-results-tendencia.list-group {
        display: none; /* Escondido por padrão */
        max-height: 300px; overflow-y: auto;
    }
  </style>
</head>
<body>
  <div class="sidebar">
    <div class="sidebar-header">
      <div class="logo-container">
        <img src="img/logo.svg" alt="Logo da Empresa">
      </div>
      <h2>Gestão de Avarias</h2>
      <h3><?php echo htmlspecialchars($nome_usuario); ?></h3>
    </div>
    <ul class="sidebar-menu nav flex-column">
      <li class="nav-item"><a class="nav-link" href="dashboard.php#painel"><i class="fas fa-tachometer-alt"></i> Painel</a></li>
      <li class="nav-item"><a class="nav-link" href="dashboard.php#registrar"><i class="fas fa-plus-circle"></i> Registrar Avaria</a></li>
      <li class="nav-item"><a class="nav-link" href="dashboard.php#lista-produtos"><i class="fas fa-list-ul"></i> Lista de Produtos</a></li>
      <li class="nav-item"><a class="nav-link" href="dashboard.php#historico"><i class="fas fa-history"></i> Histórico</a></li>
      <li class="nav-item"><a class="nav-link active" href="relatorios.php"><i class="fas fa-chart-pie"></i> Relatórios</a></li>
      <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
    </ul>
  </div>

  <div class="main-content">
    <header class="main-header">
        <h1 id="pageTitle" class="h2">Relatórios</h1>
    </header>

    <!-- Seção de Seleção de Relatórios -->
    <div class="content-section">
        <h3 class="mb-3">Visualizar Relatórios</h3>
        <div id="report-selector" class="d-flex flex-wrap gap-3">
            <div class="form-check form-switch">
                <input class="form-check-input report-toggle-checkbox" type="checkbox" role="switch" id="toggle-geral" data-target="#report-geral" checked>
                <label class="form-check-label" for="toggle-geral">Análise Geral (Motivos e Tipos)</label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input report-toggle-checkbox" type="checkbox" role="switch" id="toggle-ruas" data-target="#report-ruas" checked>
                <label class="form-check-label" for="toggle-ruas">Performance por Rua</label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input report-toggle-checkbox" type="checkbox" role="switch" id="toggle-tendencia" data-target="#report-tendencia" checked>
                <label class="form-check-label" for="toggle-tendencia">Tendência por Produto</label>
            </div>
            <!-- Adicione novos checkboxes para futuros relatórios aqui -->
        </div>
    </div>

    <!-- Seção de Filtros -->
    <div class="content-section">
        <h3>Filtros Gerais</h3>
        <form action="relatorios.php" method="GET" class="mt-3" id="form-relatorios-filtros">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="data_inicial" class="form-label">Data Inicial</label>
                    <input type="date" name="data_inicial" id="data_inicial" class="form-control" value="<?php echo htmlspecialchars($data_inicial); ?>">
                </div>
                <div class="col-md-4">
                    <label for="data_final" class="form-label">Data Final</label>
                    <input type="date" name="data_final" id="data_final" class="form-control" value="<?php echo htmlspecialchars($data_final); ?>">
                </div>
                <div class="col-md-2">
                    <label for="tipo_relatorio" class="form-label">Tipo</label>
                    <select name="tipo_relatorio" id="tipo_relatorio" class="form-select">
                        <option value="todos" <?php if ($tipo_relatorio_selecionado === 'todos') echo 'selected'; ?>>Ambos</option>
                        <option value="avaria" <?php if ($tipo_relatorio_selecionado === 'avaria') echo 'selected'; ?>>Avaria</option>
                        <option value="uso_e_consumo" <?php if ($tipo_relatorio_selecionado === 'uso_e_consumo') echo 'selected'; ?>>Uso e Consumo</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="d-grid">
                        <a href="relatorios.php" class="btn btn-outline-secondary">
                            <i class="fas fa-eraser"></i> Limpar
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Seção para o primeiro relatório -->
    <div class="row" id="report-geral">
        <div class="col-md-7">
            <div class="content-section">
                <h3>Percentual por Motivo</h3>
                <?php if (!empty($dados_motivos)): ?>
                    <div class="row align-items-center gx-5">
                        <div class="col-lg-6" style="min-height: 300px;">
                            <canvas id="graficoMotivos"></canvas>
                        </div>
                        <div class="col-lg-6">
                            <div class="table-responsive" style="max-height: 350px;">
                                <table class="table table-sm table-striped table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Motivo</th>
                                            <th class="text-center">Nº de Registros</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dados_motivos as $motivo): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($motivo['motivo_tratado']); ?></td>
                                                <td class="text-center"><?php echo $motivo['total_ocorrencias']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-muted mt-3">Nenhum dado de motivo encontrado para o período selecionado.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-5">
            <div class="content-section h-100">
                <h3>Percentual por Tipo (Avaria/Uso e Consumo)</h3>
                <?php if ($total_registros > 0): ?>
                    <div class="row align-items-center h-100">
                        <div class="col-lg-6" style="min-height: 250px;">
                            <canvas id="graficoPercentual"></canvas>
                        </div>
                        <div class="col-lg-6">
                            <p class="mb-3 percent-text">
                                <i class="fas fa-circle text-danger me-2"></i>
                                <strong>Avarias:</strong> <?php echo $total_avarias; ?> (<?php echo number_format($percent_avarias, 1, ',', '.'); ?>%)
                            </p>
                            <p class="mb-0 percent-text">
                                <i class="fas fa-circle text-success me-2"></i>
                                <strong>Uso/Consumo:</strong> <?php echo $total_consumo; ?> (<?php echo number_format($percent_consumo, 1, ',', '.'); ?>%)
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-muted mt-3">Nenhum dado encontrado para o período selecionado.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Adicione mais seções de relatórios aqui -->
    <div class="content-section mt-4" id="report-ruas">
        <h3>Performance por Rua (Avaria vs. Consumo)</h3>
        <p class="text-muted">Gráfico de barras empilhadas mostrando o volume de itens por tipo em cada setor do depósito.</p>
        <?php if (!empty($dados_ruas)): ?>
            <div style="position: relative; height: 450px; width: 100%;">
                <canvas id="graficoRuas"></canvas>
            </div>
        <?php else: ?>
            <p class="text-muted mt-3">Nenhum dado de endereço encontrado para o período e filtros selecionados.</p>
        <?php endif; ?>
    </div>

    <!-- Relatório de Tendência por Produto -->
    <div class="content-section mt-4" id="report-tendencia">
        <h3>Relatório de Tendência por Produto</h3>
        <p class="text-muted">Selecione um produto para visualizar a tendência de registros ao longo do tempo, de acordo com os filtros gerais.</p>
        
        <!-- Formulário de Busca -->
        <div class="row align-items-end">
            <div class="col-md-8 mb-3 position-relative">
                <label for="produto_tendencia_search" class="form-label">Buscar Produto</label>
                <input type="text" class="form-control" id="produto_tendencia_search" placeholder="Digite o código, descrição ou referência do produto..." value="<?php echo htmlspecialchars($produto_tendencia_nome); ?>">
                <div id="search-results-tendencia" class="list-group position-absolute" style="z-index: 1000; width: calc(100% - 1rem);"></div>
            </div>
        </div>

        <!-- Área do Gráfico -->
        <?php if ($produto_id_tendencia > 0): ?>
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                <h4 class="mt-3 mb-0">Tendência para: <span class="text-primary"><?php echo htmlspecialchars($produto_tendencia_nome); ?></span></h4>
                <?php
                    // Constrói a URL base para os botões de agrupamento, mantendo os filtros atuais
                    $query_params_tendencia = $_GET;
                    unset($query_params_tendencia['tendencia_agrupamento']);
                    $base_url_tendencia = 'relatorios.php?' . http_build_query($query_params_tendencia);
                ?>
                <div class="btn-group" role="group">
                    <a href="<?php echo $base_url_tendencia . '&tendencia_agrupamento=dia'; ?>" class="btn btn-sm btn-outline-primary <?php if ($agrupamento_tendencia === 'dia') echo 'active'; ?>">Dia</a>
                    <a href="<?php echo $base_url_tendencia . '&tendencia_agrupamento=mes'; ?>" class="btn btn-sm btn-outline-primary <?php if ($agrupamento_tendencia === 'mes') echo 'active'; ?>">Mês</a>
                    <a href="<?php echo $base_url_tendencia . '&tendencia_agrupamento=ano'; ?>" class="btn btn-sm btn-outline-primary <?php if ($agrupamento_tendencia === 'ano') echo 'active'; ?>">Ano</a>
                </div>
            </div>
            <div style="position: relative; height: 350px; width: 100%;">
                <canvas id="graficoTendencia"></canvas>
            </div>
        <?php else: ?>
            <div class="alert alert-info mt-3"><i class="fas fa-search me-2"></i>Use o campo de busca acima para selecionar um produto e visualizar seu histórico.</div>
        <?php endif; ?>
    </div>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
        // REGISTRA O PLUGIN DE RÓTULOS GLOBALMENTE PARA TODOS OS GRÁFICOS
        Chart.register(ChartDataLabels);

        // Gráfico de Motivos
        const ctxMotivos = document.getElementById('graficoMotivos')?.getContext('2d');
        if (ctxMotivos) {
            new Chart(ctxMotivos, {
                type: 'doughnut',
                data: {
                    labels: <?php echo $labels_grafico_motivos_json; ?>,
                    datasets: [{
                        label: 'Ocorrências por Motivo',
                        data: <?php echo $dados_grafico_motivos_json; ?>,
                        backgroundColor: [
                            '#4e79a7', '#f28e2c', '#e15759', '#76b7b2', '#59a14f',
                            '#edc949', '#af7aa1', '#ff9da7', '#9c755f', '#bab0ab'
                        ],
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }, // A tabela ao lado já serve como legenda
                        datalabels: {
                            formatter: (value, ctx) => {
                                const datapoints = ctx.chart.data.datasets[0].data;
                                const total = datapoints.reduce((total, datapoint) => total + datapoint, 0);
                                const percentage = (value / total) * 100;
                                return percentage.toFixed(1) + '%';
                            },
                            color: '#fff',
                            font: { weight: 'bold', size: 12 }
                        }
                    }
                }
            });
        }

        // Gráfico de Percentual
        const ctxPercentual = document.getElementById('graficoPercentual')?.getContext('2d');
        if (ctxPercentual) {
            new Chart(ctxPercentual, {
                type: 'doughnut',
                data: {
                    labels: <?php echo $labels_grafico_percentual_json; ?>,
                    datasets: [{
                        label: 'Registros por Tipo',
                        data: <?php echo $dados_grafico_percentual_json; ?>,
                        backgroundColor: ['#dc3545', '#198754'],
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        datalabels: {
                            formatter: (value, ctx) => {
                                const datapoints = ctx.chart.data.datasets[0].data;
                                const total = datapoints.reduce((total, datapoint) => total + datapoint, 0);
                                const percentage = (value / total) * 100;
                                return percentage.toFixed(1) + '%';
                            },
                            color: '#fff',
                            font: { weight: 'bold', size: 14 }
                        }
                    }
                }
            });
        }

        // Gráfico de Performance por Rua
        const ctxRuas = document.getElementById('graficoRuas')?.getContext('2d');
        if (ctxRuas) {
            new Chart(ctxRuas, {
                type: 'bar',
                data: {
                    labels: <?php echo $labels_grafico_ruas_json; ?>,
                    datasets: [
                        {
                            label: 'Avarias',
                            data: <?php echo $dados_grafico_ruas_avaria_json; ?>,
                            backgroundColor: 'rgba(220, 53, 69, 0.8)', // Vermelho
                            borderColor: 'rgba(220, 53, 69, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Uso e Consumo',
                            data: <?php echo $dados_grafico_ruas_consumo_json; ?>,
                            backgroundColor: 'rgba(25, 135, 84, 0.8)', // Verde
                            borderColor: 'rgba(25, 135, 84, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    indexAxis: 'y', // Mantém o gráfico de barras horizontal
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            beginAtZero: true,
                            title: { display: true, text: 'Quantidade Total de Itens' }
                        },
                        y: {
                            // No stacking for grouped bars
                        }
                    },
                    plugins: {
                        legend: { display: true, position: 'top' },
                        datalabels: {
                            formatter: (value, ctx) => {
                                if (value <= 0) return null;
                                const totalGeral = <?php echo $total_geral_ruas; ?>;
                                if (totalGeral === 0) return value;
                                const percentage = (value / totalGeral) * 100;
                                // Retorna o valor e a porcentagem formatada (ex: 50 (15,5%))
                                return `${value} (${percentage.toFixed(1).replace('.', ',')}%)`;
                            },
                            color: '#444', // Cor escura para ser legível fora da barra
                            anchor: 'end',
                            align: 'right', // Alinha o rótulo à direita do final da barra
                            offset: 4, // Espaçamento para não colar na barra
                            font: { weight: 'bold', size: 10 }
                        }
                    }
                }
            });
        }

        // --- LÓGICA PARA O RELATÓRIO DE TENDÊNCIA ---
        const searchInputTendencia = document.getElementById('produto_tendencia_search');
        const searchResultsTendencia = document.getElementById('search-results-tendencia');
        let searchTimeoutTendencia;

        if (searchInputTendencia) {
            searchInputTendencia.addEventListener('keyup', () => {
                clearTimeout(searchTimeoutTendencia);
                const searchTerm = searchInputTendencia.value.trim();
                if (searchTerm.length < 2) {
                    searchResultsTendencia.innerHTML = '';
                    searchResultsTendencia.style.display = 'none';
                    return;
                }
                searchTimeoutTendencia = setTimeout(async () => {
                    try {
                        const response = await fetch(`api_search_products.php?term=${encodeURIComponent(searchTerm)}`);
                        const products = await response.json();
                        
                        searchResultsTendencia.innerHTML = '';
                        if (products.length > 0) {
                            products.forEach(product => {
                                const item = document.createElement('a');
                                item.href = '#';
                                item.classList.add('list-group-item', 'list-group-item-action');
                                item.innerHTML = `<strong>${product.codigo_produto}</strong> - ${product.descricao}`;
                                item.dataset.productId = product.id;
                                searchResultsTendencia.appendChild(item);
                            });
                            searchResultsTendencia.style.display = 'block';
                        } else {
                            searchResultsTendencia.innerHTML = '<span class="list-group-item">Nenhum produto encontrado.</span>';
                            searchResultsTendencia.style.display = 'block';
                        }
                    } catch (error) {
                        console.error('Erro na busca de tendência:', error);
                        searchResultsTendencia.innerHTML = '<span class="list-group-item text-danger">Erro ao buscar.</span>';
                        searchResultsTendencia.style.display = 'block';
                    }
                }, 300);
            });

            searchResultsTendencia.addEventListener('click', (e) => {
                e.preventDefault();
                const target = e.target.closest('a');
                if (target && target.dataset.productId) {
                    const productId = target.dataset.productId;
                    const url = new URL(window.location.href);
                    url.searchParams.set('produto_id_tendencia', productId);
                    window.location.href = url.toString();
                }
            });

            // Esconde a lista de resultados se clicar fora
            document.addEventListener('click', function(event) {
                if (!searchInputTendencia.contains(event.target)) {
                    searchResultsTendencia.style.display = 'none';
                }
            });
        }

        // Gráfico de Tendência
        const ctxTendencia = document.getElementById('graficoTendencia')?.getContext('2d');
        if (ctxTendencia) {
            new Chart(ctxTendencia, {
                type: 'line',
                data: {
                    labels: <?php echo $labels_grafico_tendencia_json; ?>,
                    datasets: [{
                        label: 'Quantidade Total',
                        data: <?php echo $dados_grafico_tendencia_json; ?>,
                        fill: true,
                        backgroundColor: 'rgba(37, 76, 144, 0.2)',
                        borderColor: 'rgba(37, 76, 144, 1)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true, title: { display: true, text: 'Quantidade Registrada' } } },
                    plugins: { legend: { display: false }, datalabels: { display: false } }
                }
            });
        }

        // --- LÓGICA PARA SELEÇÃO DE RELATÓRIOS ---
        const reportCheckboxes = document.querySelectorAll('.report-toggle-checkbox');

        function toggleReportVisibility(checkbox) {
            const targetId = checkbox.dataset.target;
            const reportElement = document.querySelector(targetId);
            if (reportElement) {
                reportElement.style.display = checkbox.checked ? '' : 'none';
            }
        }

        reportCheckboxes.forEach(checkbox => {
            // Ao carregar, verifica o estado salvo no localStorage
            const savedState = localStorage.getItem(checkbox.id);
            if (savedState === 'false') {
                checkbox.checked = false;
            }
            toggleReportVisibility(checkbox); // Aplica o estado inicial

            // Adiciona o evento de mudança
            checkbox.addEventListener('change', () => {
                localStorage.setItem(checkbox.id, checkbox.checked);
                toggleReportVisibility(checkbox);
            });
        });

        // --- LÓGICA PARA ATUALIZAÇÃO AUTOMÁTICA DOS FILTROS ---
        const formFiltros = document.getElementById('form-relatorios-filtros');
        if (formFiltros) {
            const inputs = formFiltros.querySelectorAll('input, select');
            inputs.forEach(input => {
                input.addEventListener('change', function() {
                    formFiltros.submit();
                });
            });
        }
    });
  </script>
  <?php $conn->close(); ?>
</body>
</html>
