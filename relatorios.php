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
$tipo_relatorio_selecionado = $_GET['tipo_relatorio'] ?? 'todos'; // 'todos', 'avaria', 'uso_e_consumo', 'recuperados'
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
                        SUM(CASE WHEN tipo = 'uso_e_consumo' THEN 1 ELSE 0 END) as total_consumo,
                        SUM(CASE WHEN tipo = 'recuperados' THEN 1 ELSE 0 END) as total_recuperado
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
$total_recuperado = (int)($dados_percentual['total_recuperado'] ?? 0);
$total_registros = $total_avarias + $total_consumo + $total_recuperado;
$percent_avarias = ($total_registros > 0) ? ($total_avarias / $total_registros) * 100 : 0;
$percent_consumo = ($total_registros > 0) ? ($total_consumo / $total_registros) * 100 : 0;
$percent_recuperado = ($total_registros > 0) ? ($total_recuperado / $total_registros) * 100 : 0;
$labels_grafico_percentual_json = json_encode(['Avarias', 'Uso e Consumo', 'Recuperados']);
$dados_grafico_percentual_json = json_encode([$total_avarias, $total_consumo, $total_recuperado]);

// --- Lógica para o Relatório de Valores (Perda x Consumo) ---
$sql_valores = "SELECT 
                    a.tipo,
                    SUM(a.quantidade * COALESCE(p.preco_venda, 0)) as total_valor
                FROM avarias a
                LEFT JOIN produtos p ON a.produto_id = p.id
                {$where_sql}
                GROUP BY a.tipo";
$stmt_valores = $conn->prepare($sql_valores);
$stmt_valores->bind_param($types, ...$params);
$stmt_valores->execute();
$result_valores = $stmt_valores->get_result();
$dados_valores = $result_valores->fetch_all(MYSQLI_ASSOC);
$stmt_valores->close();

$valor_total_avarias = (float) (array_values(array_filter($dados_valores, fn($v) => $v['tipo'] === 'avaria'))[0]['total_valor'] ?? 0);
$valor_total_consumo = (float) (array_values(array_filter($dados_valores, fn($v) => $v['tipo'] === 'uso_e_consumo'))[0]['total_valor'] ?? 0);
$valor_total_recuperado = (float) (array_values(array_filter($dados_valores, fn($v) => $v['tipo'] === 'recuperados'))[0]['total_valor'] ?? 0);
$labels_grafico_valores_json = json_encode(['Perdas (R$)', 'Uso e Consumo (R$)', 'Recuperados (R$)']);
$dados_grafico_valores = [$valor_total_avarias, $valor_total_consumo, $valor_total_recuperado];
$dados_grafico_valores_json = json_encode($dados_grafico_valores);

// Adiciona cálculo do valor máximo para o eixo Y para evitar que o rótulo de dados seja cortado no topo.
$max_valor = 0;
if (!empty($dados_grafico_valores)) {
    $max_valor = max($dados_grafico_valores);
}
$y_axis_max_valores = ceil($max_valor * 1.25); // Adiciona 25% de espaço no topo.
if ($y_axis_max_valores < 10) { $y_axis_max_valores = 10; } // Garante um valor mínimo para o eixo.

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
                    WHEN p.endereco IS NULL OR TRIM(p.endereco) = '' THEN 'Sem Endereço'
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
                COUNT(CASE WHEN a.tipo = 'avaria' THEN a.id ELSE NULL END) as total_avaria,
                COUNT(CASE WHEN a.tipo = 'uso_e_consumo' THEN a.id ELSE NULL END) as total_consumo,
                COUNT(CASE WHEN a.tipo = 'recuperados' THEN a.id ELSE NULL END) as total_recuperado
            FROM avarias a
            LEFT JOIN produtos p ON a.produto_id = p.id
            {$where_sql}
            GROUP BY rua
            ORDER BY (COUNT(CASE WHEN a.tipo = 'avaria' THEN a.id ELSE NULL END) + COUNT(CASE WHEN a.tipo = 'uso_e_consumo' THEN a.id ELSE NULL END) + COUNT(CASE WHEN a.tipo = 'recuperados' THEN a.id ELSE NULL END)) DESC";
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
$dados_grafico_ruas_recuperado = [];
$total_geral_ruas = 0;

foreach ($dados_ruas as $rua) {
    $total_rua = (int)$rua['total_avaria'] + (int)$rua['total_consumo'] + (int)$rua['total_recuperado'];
    // Adiciona a rua ao gráfico apenas se houver algum valor a ser mostrado
    if ($total_rua > 0) {
        if ($rua['rua'] === 'Sem Endereço') {
            $labels_grafico_ruas[] = 'Sem Endereço';
        } else {
            $labels_grafico_ruas[] = 'Rua ' . $rua['rua'];
        }
        $dados_grafico_ruas_avaria[] = (int)$rua['total_avaria'];
        $dados_grafico_ruas_consumo[] = (int)$rua['total_consumo'];
        $dados_grafico_ruas_recuperado[] = (int)$rua['total_recuperado'];
        $total_geral_ruas += $total_rua;
    }
}

// Calcula os totais para cada tipo de registro para usar no cálculo de percentual do gráfico de ruas.
$total_avarias_ruas = array_sum($dados_grafico_ruas_avaria);
$total_consumo_ruas = array_sum($dados_grafico_ruas_consumo);
$total_recuperado_ruas = array_sum($dados_grafico_ruas_recuperado);

// Calcula uma altura dinâmica para o gráfico para evitar que as barras fiquem espremidas.
$num_ruas = count($labels_grafico_ruas);
$altura_grafico_ruas = max(450, $num_ruas * 35 + 120); // Mínimo 450px, 35px por rua + 120px para eixos/legenda.

// Encontra o valor máximo para o eixo X (maior valor individual entre todas as barras)
$max_ruas_value = 0;
$all_ruas_values = array_merge($dados_grafico_ruas_avaria, $dados_grafico_ruas_consumo, $dados_grafico_ruas_recuperado);
if (!empty($all_ruas_values)) {
    $max_ruas_value = max($all_ruas_values);
}
// Adiciona uma margem de 45% ao valor máximo para garantir que os rótulos com percentual caibam.
// O fator pode ser ajustado se os rótulos ainda estiverem sendo cortados.
$x_axis_max_ruas = ceil($max_ruas_value * 1.45);
if ($x_axis_max_ruas < 5) { // Garante um valor mínimo para o eixo.
    $x_axis_max_ruas = 5;
}
$labels_grafico_ruas_json = json_encode($labels_grafico_ruas);
$dados_grafico_ruas_avaria_json = json_encode($dados_grafico_ruas_avaria);
$dados_grafico_ruas_consumo_json = json_encode($dados_grafico_ruas_consumo);
$dados_grafico_ruas_recuperado_json = json_encode($dados_grafico_ruas_recuperado);

// --- Lógica para o Relatório de Tendência por Produto ---
$produto_ids_tendencia = [];
if (isset($_GET['produto_ids_tendencia']) && is_array($_GET['produto_ids_tendencia'])) {
    // Garante que todos os valores são inteiros e positivos
    $produto_ids_tendencia = array_map('intval', $_GET['produto_ids_tendencia']);
    $produto_ids_tendencia = array_filter($produto_ids_tendencia, fn($id) => $id > 0);
    // Limita a seleção a no máximo 3 produtos e remove duplicados
    $produto_ids_tendencia = array_slice(array_unique($produto_ids_tendencia), 0, 3);
}

$agrupamento_tendencia = $_GET['tendencia_agrupamento'] ?? 'mes'; // 'dia', 'mes', 'ano'
$dados_grafico_tendencia_json = '{"labels":[], "datasets":[]}'; // Estrutura para múltiplos datasets
$produtos_selecionados_tendencia = []; // Para exibir os nomes dos produtos selecionados

if (!empty($produto_ids_tendencia)) {
    // 1. Build the IN clause for the SQL query
    $in_placeholders = implode(',', array_fill(0, count($produto_ids_tendencia), '?'));
    $types_for_in = str_repeat('i', count($produto_ids_tendencia));

    // 2. Buscar nomes dos produtos selecionados para a UI
    $sql_nomes = "SELECT id, descricao FROM produtos WHERE id IN ($in_placeholders)";
    $stmt_nome_tendencia = $conn->prepare($sql_nomes);
    $stmt_nome_tendencia->bind_param($types_for_in, ...$produto_ids_tendencia);
    $stmt_nome_tendencia->execute();
    $result_nome_tendencia = $stmt_nome_tendencia->get_result();
    while ($row = $result_nome_tendencia->fetch_assoc()) {
        $produtos_selecionados_tendencia[$row['id']] = $row['descricao'];
    }
    $stmt_nome_tendencia->close();

    // 3. Buscar dados da tendência (agrupados por dia, mês ou ano)
    $where_tendencia_sql = $where_sql . " AND a.produto_id IN ($in_placeholders)";
    $params_tendencia = array_merge($params, $produto_ids_tendencia);
    $types_tendencia = $types . $types_for_in;

    // Define os campos e agrupamentos da query com base na seleção do usuário
    $select_fields = '';
    $group_by_sql = '';
    $order_by_sql = '';
    $meses_nomes = ["Jan", "Fev", "Mar", "Abr", "Mai", "Jun", "Jul", "Ago", "Set", "Out", "Nov", "Dez"];

    switch ($agrupamento_tendencia) {
        case 'dia':
            $select_fields = "a.produto_id, DATE(a.data_ocorrencia) as data_agrupada, SUM(a.quantidade) as quantidade_total";
            $group_by_sql = "GROUP BY a.produto_id, data_agrupada";
            $order_by_sql = "ORDER BY data_agrupada ASC";
            break;
        case 'ano':
            $select_fields = "a.produto_id, YEAR(a.data_ocorrencia) as ano, SUM(a.quantidade) as quantidade_total";
            $group_by_sql = "GROUP BY a.produto_id, ano";
            $order_by_sql = "ORDER BY ano ASC";
            break;
        case 'mes':
        default:
            $select_fields = "a.produto_id, YEAR(a.data_ocorrencia) as ano, MONTH(a.data_ocorrencia) as mes, SUM(a.quantidade) as quantidade_total";
            $group_by_sql = "GROUP BY a.produto_id, ano, mes";
            $order_by_sql = "ORDER BY ano, mes ASC";
            break;
    }

    $sql_tendencia = "SELECT {$select_fields}
                      FROM avarias a
                      {$where_tendencia_sql} {$group_by_sql} {$order_by_sql}";

    $stmt_tendencia = $conn->prepare($sql_tendencia);
    $stmt_tendencia->bind_param($types_tendencia, ...$params_tendencia);
    $stmt_tendencia->execute();
    $result_tendencia = $stmt_tendencia->get_result();
    $dados_tendencia_raw = $result_tendencia->fetch_all(MYSQLI_ASSOC);
    error_log("Dados de tendência brutos para agrupamento {$agrupamento_tendencia}: " . json_encode($dados_tendencia_raw)); // NEW LOG
    $stmt_tendencia->close();

    // 4. Processar dados brutos para o formato do Chart.js
    $all_labels_map = [];
    $datasets_temp = [];

    foreach ($dados_tendencia_raw as $row) {
        $label = '';
        switch ($agrupamento_tendencia) {
            case 'dia': $label = date('d/m/y', strtotime($row['data_agrupada'])); break;
            case 'ano': $label = $row['ano']; break;
            case 'mes': default: $label = $meses_nomes[$row['mes'] - 1] . '/' . substr($row['ano'], -2); break;
        }
        $all_labels_map[$label] = true;
        $datasets_temp[$row['produto_id']][$label] = (int)$row['quantidade_total'];
    }

    $labels_grafico_tendencia = array_keys($all_labels_map);
    $datasets = [];
    $colors = ['rgba(37, 76, 144, %a)', 'rgba(220, 53, 69, %a)', 'rgba(25, 135, 84, %a)'];
    $color_index = 0;

    foreach ($produtos_selecionados_tendencia as $produto_id => $produto_nome) {
        $data = [];
        foreach ($labels_grafico_tendencia as $label) {
            $data[] = $datasets_temp[$produto_id][$label] ?? 0;
        }
        $color_base = $colors[$color_index % count($colors)];
        $datasets[] = [
            'label' => $produto_nome,
            'data' => $data,
            'fill' => true,
            'backgroundColor' => str_replace('%a', '0.2', $color_base),
            'borderColor' => str_replace('%a', '1', $color_base),
            'tension' => 0.1
        ];
        $color_index++;
    }

    $dados_grafico_tendencia_json = json_encode(['labels' => $labels_grafico_tendencia, 'datasets' => $datasets]);
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
    /* Esconde o corpo da página enquanto ela carrega para evitar o "salto" visual.
       O JavaScript removerá a classe 'is-loading' quando tudo estiver pronto. */
    body.is-loading {
        visibility: hidden;
    }
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
    .chart-container {
        position: relative;
    }
    /* Ajuste para o botão de cópia não sobrepor o título */
    .report-header {
        position: relative;
        padding-right: 40px; /* Espaço para o botão */
    }
    .btn-copy-report {
        position: absolute; top: 0; right: 0;
    }
    .btn-copy-chart {
        position: absolute; top: -5px; right: 0; z-index: 10;
    }
    .copy-feedback {
        position: absolute; top: 0px; right: 45px; z-index: 10; display: none; background-color: #28a745; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.8em;
    }
  </style>
</head>
<body class="is-loading">
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
            <div class="form-check form-switch">
                <input class="form-check-input report-toggle-checkbox" type="checkbox" role="switch" id="toggle-valores" data-target="#report-valores" checked>
                <label class="form-check-label" for="toggle-valores">Análise de Custo (Valor)</label>
            </div>
            <?php if ($nivel_usuario === 'admin'): ?>
            <div class="form-check form-switch">
                <input class="form-check-input report-toggle-checkbox" type="checkbox" role="switch" id="toggle-produtos-sem-preco" data-target="#report-produtos-sem-preco" checked>
                <label class="form-check-label" for="toggle-produtos-sem-preco">Produtos sem Preço de Venda</label>
            </div>
            <?php endif; ?>
            <!-- Adicione novos checkboxes para futuros relatórios aqui -->
        </div>
    </div>

    <!-- Seção de Filtros -->
    <div class="content-section">
        <h3 id="filtros">Filtros Gerais</h3>
        <form action="relatorios.php#filtros" method="GET" class="mt-3" id="form-relatorios-filtros">
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
                        <option value="todos" <?php if ($tipo_relatorio_selecionado === 'todos') echo 'selected'; ?>>Todos</option>
                        <option value="avaria" <?php if ($tipo_relatorio_selecionado === 'avaria') echo 'selected'; ?>>Avaria</option>
                        <option value="uso_e_consumo" <?php if ($tipo_relatorio_selecionado === 'uso_e_consumo') echo 'selected'; ?>>Uso e Consumo</option>
                        <option value="recuperados" <?php if ($tipo_relatorio_selecionado === 'recuperados') echo 'selected'; ?>>Recuperados</option>
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
            <div class="content-section" id="report-container-motivos">
                <div class="report-header">
                    <h3>Percentual por Motivo</h3>
                    <button class="btn btn-sm btn-outline-secondary btn-copy-report" data-container-id="report-container-motivos" data-feedback-id="feedback-motivos" title="Copiar relatório como imagem"><i class="fas fa-camera"></i></button>
                    <span class="copy-feedback" id="feedback-motivos">Copiado!</span>
                </div>
                <?php if (!empty($dados_motivos)): ?>
                    <div class="row align-items-center gx-5">
                        <div class="col-lg-6" style="position: relative; min-height: 300px;">
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
            <div class="content-section h-100" id="report-container-percentual">
                <div class="report-header">
                    <h3>Percentual por Tipo</h3>
                    <button class="btn btn-sm btn-outline-secondary btn-copy-report" data-container-id="report-container-percentual" data-feedback-id="feedback-percentual" title="Copiar relatório como imagem"><i class="fas fa-camera"></i></button>
                    <span class="copy-feedback" id="feedback-percentual">Copiado!</span>
                </div>
                <?php if ($total_registros > 0): ?>
                    <div class="row align-items-center h-100">
                        <div class="col-lg-6" style="position: relative; min-height: 250px;">
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
                            <p class="mb-0 percent-text">
                                <i class="fas fa-circle text-warning me-2"></i>
                                <strong>Recuperados:</strong> <?php echo $total_recuperado; ?> (<?php echo number_format($percent_recuperado, 1, ',', '.'); ?>%)
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
        <div class="report-header">
            <h3>Performance por Rua</h3>
            <button class="btn btn-sm btn-outline-secondary btn-copy-report" data-container-id="report-ruas" data-feedback-id="feedback-ruas" title="Copiar relatório como imagem"><i class="fas fa-camera"></i></button>
            <span class="copy-feedback" id="feedback-ruas">Copiado!</span>
        </div>
        <p class="text-muted">Gráfico de barras empilhadas mostrando a quantidade de registros por tipo em cada setor do depósito.</p>
        <?php if (!empty($dados_ruas)): ?>
            <div style="height: <?php echo $altura_grafico_ruas; ?>px; width: 100%;">
                <canvas id="graficoRuas"></canvas>
            </div>
        <?php else: ?>
            <p class="text-muted mt-3">Nenhum dado de endereço encontrado para o período e filtros selecionados.</p>
        <?php endif; ?>
    </div>

    <!-- Relatório de Valores (Perda x Consumo) -->
    <div class="content-section mt-4" id="report-valores">
        <div class="report-header">
            <h3>Análise de Custo</h3>
            <button class="btn btn-sm btn-outline-secondary btn-copy-report" data-container-id="report-valores" data-feedback-id="feedback-valores" title="Copiar relatório como imagem"><i class="fas fa-camera"></i></button>
            <span class="copy-feedback" id="feedback-valores">Copiado!</span>
        </div>
        <p class="text-muted">Comparativo do valor monetário total (baseado no `preco_venda`) entre os tipos de registro.</p>
        <?php if ($valor_total_avarias > 0 || $valor_total_consumo > 0 || $valor_total_recuperado > 0): ?>
            <div class="row">
                <div class="col-12" style="height: 350px;">
                    <canvas id="graficoValores"></canvas>
                </div>
            </div>
            <div class="row mt-4">
                <div class="col-lg-8 offset-lg-2">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Tipo de Custo</th>
                                    <th class="text-end">Valor Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td><i class="fas fa-circle text-danger me-2"></i>Perdas (Avarias)</td><td class="text-end"><strong><?php echo 'R$ ' . number_format($valor_total_avarias, 2, ',', '.'); ?></strong></td></tr>
                                <tr><td><i class="fas fa-circle text-success me-2"></i>Uso e Consumo</td><td class="text-end"><strong><?php echo 'R$ ' . number_format($valor_total_consumo, 2, ',', '.'); ?></strong></td></tr>
                                <tr><td><i class="fas fa-circle text-warning me-2"></i>Recuperados</td><td class="text-end"><strong><?php echo 'R$ ' . number_format($valor_total_recuperado, 2, ',', '.'); ?></strong></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <p class="text-muted mt-3">Nenhum valor encontrado para o período. Verifique se os produtos registrados possuem `preco_venda` cadastrado.</p>
        <?php endif; ?>
    </div>

    <!-- Novo Relatório: Produtos sem Preço de Venda -->
    <?php if ($nivel_usuario === 'admin'): ?>
    <div class="content-section mt-4" id="report-produtos-sem-preco">
        <div class="report-header">
            <h3>Produtos sem Preço de Venda</h3>
            <button class="btn btn-sm btn-outline-secondary btn-copy-report" data-container-id="report-produtos-sem-preco" data-feedback-id="feedback-produtos-sem-preco" title="Copiar relatório como imagem"><i class="fas fa-camera"></i></button>
            <span class="copy-feedback" id="feedback-produtos-sem-preco">Copiado!</span>
        </div>
        <p class="text-muted">Lista de produtos que não possuem um `preco_venda` cadastrado ou ele é zero, e que foram registrados no período selecionado.</p>
        <?php
            $sql_produtos_sem_preco = "SELECT DISTINCT p.codigo_produto, p.descricao, p.referencia
                                        FROM avarias a
                                        JOIN produtos p ON a.produto_id = p.id
                                        {$where_sql} AND (p.preco_venda IS NULL OR p.preco_venda = 0)
                                        ORDER BY p.descricao ASC";
            $stmt_produtos_sem_preco = $conn->prepare($sql_produtos_sem_preco);
            $stmt_produtos_sem_preco->bind_param($types, ...$params);
            $stmt_produtos_sem_preco->execute();
            $result_produtos_sem_preco = $stmt_produtos_sem_preco->get_result();
            $produtos_sem_preco = $result_produtos_sem_preco->fetch_all(MYSQLI_ASSOC);
            $stmt_produtos_sem_preco->close();
        ?>

        <?php if (!empty($produtos_sem_preco)): ?>
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Código do Produto</th>
                            <th>Descrição</th>
                            <th>Referência</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtos_sem_preco as $prod): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($prod['codigo_produto']); ?></td>
                                <td><?php echo htmlspecialchars($prod['descricao']); ?></td>
                                <td><?php echo htmlspecialchars($prod['referencia'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted mt-3">Nenhum produto sem preço de venda encontrado para o período e filtros selecionados.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Relatório de Tendência por Produto -->
    <div class="content-section mt-4" id="report-tendencia">
        <div class="report-header">
            <h3>Relatório de Tendência por Produto</h3>
            <button class="btn btn-sm btn-outline-secondary btn-copy-report" data-container-id="report-tendencia" data-feedback-id="feedback-tendencia" title="Copiar relatório como imagem"><i class="fas fa-camera"></i></button>
            <span class="copy-feedback" id="feedback-tendencia">Copiado!</span>
            <!-- New button here -->
            <button class="btn btn-sm btn-outline-danger ms-2" id="clear-tendencia-selection" title="Limpar seleção de produtos"><i class="fas fa-times-circle"></i> Limpar Seleção</button>
        </div>
        <p class="text-muted">Selecione até 3 produtos para comparar a tendência de registros ao longo do tempo.</p>
        
        <!-- Container para produtos selecionados e busca -->
        <div class="row align-items-start">
            <div class="col-12 mb-3">
                <div id="tendencia-selected-products" class="d-flex flex-wrap gap-2">
                    <?php foreach ($produtos_selecionados_tendencia as $id => $nome): ?>
                        <span class="badge bg-primary d-flex align-items-center p-2">
                            <span class="me-2"><?php echo htmlspecialchars($nome); ?></span>
                            <button type="button" class="btn-close btn-close-white remove-tendencia-product" data-id="<?php echo $id; ?>" aria-label="Remove"></button>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div id="tendencia-search-container" class="col-md-8 position-relative" <?php if (count($produto_ids_tendencia) >= 3) echo 'style="display:none;"'; ?>>
                <label for="produto_tendencia_search" class="form-label">Buscar Produto (<?php echo count($produto_ids_tendencia); ?>/3)</label>
                <input type="text" class="form-control" id="produto_tendencia_search" placeholder="Digite o código, descrição ou referência...">
                <div id="search-results-tendencia" class="list-group position-absolute" style="z-index: 1000; width: calc(100% - 1rem);"></div>
            </div>
        </div>

        <!-- Área do Gráfico -->
        <?php if (!empty($produto_ids_tendencia)): ?>
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
                <h4 class="mt-3 mb-0">Comparativo de Tendência</h4>
                <?php
                    // Constrói a URL base para os botões de agrupamento, mantendo os filtros atuais
                    $query_params_tendencia = $_GET;
                    unset($query_params_tendencia['tendencia_agrupamento']);
                    $base_url_tendencia = 'relatorios.php?' . http_build_query($query_params_tendencia);
                ?>
                <div class="btn-group mt-3" role="group">
                    <a href="<?php echo $base_url_tendencia . '&tendencia_agrupamento=dia#report-tendencia'; ?>" class="btn btn-sm btn-outline-primary <?php if ($agrupamento_tendencia === 'dia') echo 'active'; ?>">Dia</a>
                    <a href="<?php echo $base_url_tendencia . '&tendencia_agrupamento=mes#report-tendencia'; ?>" class="btn btn-sm btn-outline-primary <?php if ($agrupamento_tendencia === 'mes') echo 'active'; ?>">Mês</a>
                    <a href="<?php echo $base_url_tendencia . '&tendencia_agrupamento=ano#report-tendencia'; ?>" class="btn btn-sm btn-outline-primary <?php if ($agrupamento_tendencia === 'ano') echo 'active'; ?>">Ano</a>
                </div>
            </div>
            <div style="height: 350px; width: 100%;">
                <canvas id="graficoTendencia"></canvas>
            </div>
        <?php else: ?>
            <div class="alert alert-info mt-3"><i class="fas fa-search me-2"></i>Use o campo de busca acima para selecionar um produto e visualizar seu histórico.</div>
        <?php endif; ?>
    </div>

  </div>

  <!-- Modal para Detalhes da Rua -->
  <div class="modal fade" id="ruaDetailsModal" tabindex="-1" aria-labelledby="ruaDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="ruaDetailsModalLabel">Detalhes dos Itens</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="ruaDetailsModalBody">
          <!-- O conteúdo será preenchido via JavaScript -->
          <div class="text-center">
            <div class="spinner-border" role="status">
              <span class="visually-hidden">Carregando...</span>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
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
                        backgroundColor: ['#dc3545', '#198754', '#ffc107'],
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

        // Gráfico de Valores (Perda x Consumo)
        const ctxValores = document.getElementById('graficoValores')?.getContext('2d');
        if (ctxValores) {
            new Chart(ctxValores, {
                type: 'bar', // Alterado para 'bar'
                data: {
                    labels: <?php echo $labels_grafico_valores_json; ?>,
                    datasets: [{
                        label: 'Valor em R$',
                        data: <?php echo $dados_grafico_valores_json; ?>,
                        backgroundColor: ['#dc3545', '#198754', '#ffc107'],
                        borderColor: ['#dc3545', '#198754', '#ffc107'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            max: <?php echo $y_axis_max_valores; ?>,
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(tooltipItem) {
                                    return ' ' + new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(tooltipItem.raw);
                                }
                            }
                        },
                        datalabels: {
                            anchor: 'end',
                            align: 'top',
                            formatter: (value, context) => {
                                if (value > 0) { return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value); }
                                return null;
                            },
                            color: '#444',
                            font: { weight: 'bold' }
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
                        },
                        {
                            label: 'Recuperados',
                            data: <?php echo $dados_grafico_ruas_recuperado_json; ?>,
                            backgroundColor: 'rgba(255, 193, 7, 0.8)', // Amarelo Warning
                            borderColor: 'rgba(255, 193, 7, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    indexAxis: 'y', // Mantém o gráfico de barras horizontal
                    responsive: true,
                    layout: {
                        padding: {
                            left: 25 // Adiciona um preenchimento à esquerda para garantir que rótulos longos não sejam cortados.
                        }
                    },
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            stacked: false,
                            beginAtZero: true,
                            title: { display: true, text: 'Quantidade Total de Itens' },
                            max: <?php echo $x_axis_max_ruas; ?> // Define o valor máximo do eixo X para dar espaço aos rótulos
                        },
                        y: {
                            stacked: false,
                            title: { display: true, text: 'Setor/Rua' }
                        }
                    },
                    plugins: {
                        legend: { display: true, position: 'top' },
                        datalabels: {
                            formatter: (value, ctx) => {
                                if (value <= 0) return null;

                                // Define os totais de cada categoria (passados do PHP)
                                const totais = {
                                    'Avarias': <?php echo $total_avarias_ruas; ?>,
                                    'Uso e Consumo': <?php echo $total_consumo_ruas; ?>,
                                    'Recuperados': <?php echo $total_recuperado_ruas; ?>
                                };
                                
                                const totalCategoria = totais[ctx.dataset.label] || 0;

                                if (totalCategoria === 0) {
                                    return value; // Evita divisão por zero
                                }

                                const percentage = (value / totalCategoria) * 100;
                                return `${value} (${percentage.toFixed(1).replace('.', ',')}%)`;
                            },
                            color: '#444', // Cor escura para ser legível fora da barra
                            anchor: 'end',
                            align: 'right', // Alinha o rótulo à direita do final da barra
                            offset: 4, // Espaçamento para não colar na barra
                            font: { weight: 'bold', size: 10 }
                        }
                    },
                    // CORREÇÃO: O handler de clique deve estar dentro do objeto 'options'.
                    onClick: async (event, elements) => {
                        if (elements.length === 0) return; // Sai se o clique não foi em uma barra

                        const chart = event.chart;
                        const index = elements[0].index;
                        const ruaLabel = chart.data.labels[index];

                        // Pega os filtros atuais da página
                        const dataInicial = document.getElementById('data_inicial').value;
                        const dataFinal = document.getElementById('data_final').value;
                        const tipoRelatorio = document.getElementById('tipo_relatorio').value;

                        // Prepara e abre o modal
                        const modalElement = document.getElementById('ruaDetailsModal');
                        const detailsModal = new bootstrap.Modal(modalElement);
                        document.getElementById('ruaDetailsModalLabel').textContent = `Itens para: ${ruaLabel}`;
                        const modalBody = document.getElementById('ruaDetailsModalBody');
                        modalBody.innerHTML = '<div class="text-center p-4"><div class="spinner-border" role="status"><span class="visually-hidden">Carregando...</span></div></div>';
                        detailsModal.show();

                        // Busca os dados na nova API
                        try {
                            const url = `api_get_details_by_rua.php?rua=${encodeURIComponent(ruaLabel)}&data_inicial=${dataInicial}&data_final=${dataFinal}&tipo_relatorio=${tipoRelatorio}`;
                            const response = await fetch(url);
                            const items = await response.json();

                            if (items.error) {
                                throw new Error(items.error);
                            }

                            // Monta a tabela com os resultados
                            if (items.length > 0) {
                                let tableHtml = '<table class="table table-sm table-striped"><thead><tr><th>Produto</th><th class="text-center">Qtd.</th><th>Tipo</th><th>Data</th></tr></thead><tbody>';
                                items.forEach(item => {                                    
                                    let tipoBadge = '<span class="badge bg-secondary">N/D</span>';
                                    if (item.tipo === 'avaria') tipoBadge = '<span class="badge bg-danger">Avaria</span>';
                                    if (item.tipo === 'uso_e_consumo') tipoBadge = '<span class="badge bg-success">Uso/Consumo</span>';
                                    if (item.tipo === 'recuperados') tipoBadge = '<span class="badge bg-warning">Recuperados</span>';

                                    const dataFormatada = new Date(item.data_ocorrencia + 'T00:00:00').toLocaleDateString('pt-BR');

                                    tableHtml += `
                                        <tr>
                                            <td>${item.produto_nome}</td>
                                            <td class="text-center">${item.quantidade}</td>
                                            <td>${tipoBadge}</td>
                                            <td>${dataFormatada}</td>
                                        </tr>
                                    `;
                                });
                                tableHtml += '</tbody></table>';
                                modalBody.innerHTML = tableHtml;
                            } else {
                                modalBody.innerHTML = '<p class="text-center p-4">Nenhum item encontrado para esta seleção.</p>';
                            }

                        } catch (error) {
                            console.error('Erro ao buscar detalhes da rua:', error);
                            modalBody.innerHTML = `<div class="alert alert-danger">Erro ao carregar os dados: ${error.message}</div>`;
                        }
                    }
                }
            });
        }

        // --- LÓGICA PARA COPIAR CONTAINER DO RELATÓRIO PARA CLIPBOARD ---
        async function copyReportContainerToClipboard(containerId, feedbackId) {
            const reportElement = document.getElementById(containerId);
            const feedbackEl = document.getElementById(feedbackId);

            if (!reportElement || !feedbackEl) {
                console.error(`Elemento do container #${containerId} ou feedback #${feedbackId} não encontrado.`);
                return;
            }

            try {
                // Usa html2canvas para "fotografar" a área do relatório
                const canvas = await html2canvas(reportElement, {
                    scale: 2, // Renderiza com o dobro da resolução para melhor qualidade
                    useCORS: true,
                    backgroundColor: null // Permite fundo transparente se o elemento não tiver cor
                });

                // Converte o novo canvas para um Blob (formato de imagem)
                canvas.toBlob(async (blob) => {
                    await navigator.clipboard.write([
                        new ClipboardItem({ 'image/png': blob })
                    ]);

                    // Mostra feedback de sucesso
                    feedbackEl.textContent = 'Copiado!';
                    feedbackEl.style.backgroundColor = '#28a745';
                    feedbackEl.style.display = 'inline';
                    setTimeout(() => { feedbackEl.style.display = 'none'; }, 2000);
                }, 'image/png');

            } catch (err) {
                console.error('Falha ao copiar o relatório: ', err);
                // Mostra feedback de erro
                feedbackEl.textContent = 'Falha!';
                feedbackEl.style.backgroundColor = '#dc3545';
                feedbackEl.style.display = 'inline';
                setTimeout(() => { feedbackEl.style.display = 'none'; }, 2000);
            }
        }

        // Adiciona o evento de clique a todos os botões de cópia de relatório
        document.querySelectorAll('.btn-copy-report').forEach(button => {
            button.addEventListener('click', () => {
                const containerId = button.dataset.containerId;
                const feedbackId = button.dataset.feedbackId;
                copyReportContainerToClipboard(containerId, feedbackId);
            });
        });


        // --- LÓGICA PARA O RELATÓRIO DE TENDÊNCIA ---
        const searchInputTendencia = document.getElementById('produto_tendencia_search');
        const searchResultsTendencia = document.getElementById('search-results-tendencia');
        const selectedProductsContainer = document.getElementById('tendencia-selected-products');
        let searchTimeoutTendencia;

        // Helper: Recarrega a página com a lista de IDs de produtos atualizada.
        function reloadWithProductIds(productIds) {
            console.log('reloadWithProductIds called with productIds:', productIds); // Log 1
            const url = new URL(window.location.href);
            const newSearchParams = new URLSearchParams();

            // Get current 'tendencia_agrupamento' if it exists
            const currentAgrupamento = url.searchParams.get('tendencia_agrupamento');
            console.log('Current agrupamento before reload:', currentAgrupamento); // NEW LOG

            // Copia os parâmetros existentes, excluindo 'produto_ids_tendencia[]' e 'tendencia_agrupamento'
            url.searchParams.forEach((value, key) => {
                if (!key.startsWith('produto_ids_tendencia') && key !== 'tendencia_agrupamento') { // MODIFIED CONDITION
                    newSearchParams.append(key, value);
                }
            });

            // Adiciona os IDs de produtos atualizados
            productIds.forEach(id => {
                newSearchParams.append('produto_ids_tendencia[]', id);
            });

            // Re-add the 'tendencia_agrupamento' if it was present
            if (currentAgrupamento) {
                newSearchParams.append('tendencia_agrupamento', currentAgrupamento);
            }
            
            url.search = newSearchParams.toString();
            console.log('Final URL search params:', url.search); // Log 3
            console.log('Final URL:', url.toString()); // Log 4
            window.location.href = url.pathname + url.search + '#report-tendencia';
        }

        // Helper: Pega os IDs dos produtos já selecionados (lendo os botões de remover).
        function getCurrentSelectedIds() {
            return Array.from(document.querySelectorAll('.remove-tendencia-product')).map(btn => btn.dataset.id);
        }

        // Evento para ADICIONAR um produto da busca
        searchResultsTendencia.addEventListener('click', (e) => {
            e.preventDefault();
            const target = e.target.closest('a');
            if (target && target.dataset.productId) {
                const newProductId = target.dataset.productId;
                const currentIds = getCurrentSelectedIds();

                // Evita adicionar produtos duplicados e respeita o limite de 3
                if (!currentIds.includes(newProductId) && currentIds.length < 3) {
                    currentIds.push(newProductId);
                    reloadWithProductIds(currentIds);
                }
            }
        });

        // Evento para REMOVER um produto ao clicar no 'x'
        selectedProductsContainer.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-tendencia-product')) {
                const productIdToRemove = parseInt(e.target.dataset.id);
                let currentIds = getCurrentSelectedIds().map(id => parseInt(id));
                currentIds = currentIds.filter(id => id !== productIdToRemove);
                reloadWithProductIds(currentIds);
            }
        });

        // Lógica de busca com debounce (atraso para não sobrecarregar)
        searchInputTendencia.addEventListener('keyup', () => {
            clearTimeout(searchTimeoutTendencia);
            const searchTerm = searchInputTendencia.value.trim();
            if (searchTerm.length < 2) {
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
                    } else {
                        searchResultsTendencia.innerHTML = '<span class="list-group-item">Nenhum produto encontrado.</span>';
                    }
                    searchResultsTendencia.style.display = 'block';
                } catch (error) {
                    console.error('Erro na busca de tendência:', error);
                    searchResultsTendencia.innerHTML = '<span class="list-group-item text-danger">Erro ao buscar.</span>';
                    searchResultsTendencia.style.display = 'block';
                }
            }, 300);
        });

        // Esconde a lista de resultados se clicar fora da área de busca
        document.addEventListener('click', (event) => {
            if (!searchInputTendencia.contains(event.target) && !searchResultsTendencia.contains(event.target)) {
                searchResultsTendencia.style.display = 'none';
            }
        });

        // Gráfico de Tendência
        const ctxTendencia = document.getElementById('graficoTendencia')?.getContext('2d');
        if (ctxTendencia) {
            const tendenciaData = <?php echo $dados_grafico_tendencia_json; ?>;
            new Chart(ctxTendencia, {
                type: 'line',
                data: tendenciaData, // CORREÇÃO: Usa o objeto JSON completo gerado pelo PHP
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { 
                        y: { 
                            beginAtZero: true, 
                            title: { display: true, text: 'Quantidade Registrada' },
                            ticks: { precision: 0 } // Garante que o eixo Y só mostre números inteiros
                        } 
                    },
                    plugins: { 
                        legend: { 
                            display: tendenciaData.datasets.length > 1, // Mostra legenda apenas se houver mais de 1 produto
                            position: 'top' 
                        }, 
                        datalabels: {
                            formatter: (value) => (value > 0 ? value : null),
                            backgroundColor: (context) => context.dataset.borderColor, // Usa a cor da linha como fundo
                            color: 'white',
                            borderRadius: 4,
                            font: { weight: 'bold' }
                        }
                    }
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

        // --- LÓGICA PARA CORRIGIR O SCROLL APÓS FILTRAR ---
        // Força o scroll para a âncora na URL (#) após o recarregamento completo da página,
        // e só então exibe a página. Isso evita o "salto" visual.
        window.addEventListener('load', function() {
            const hash = window.location.hash;
            if (hash) {
                const elementId = hash.substring(1); // Remove o '#'
                const targetElement = document.getElementById(elementId);
                if (targetElement) {
                    // Rola a página para o elemento alvo instantaneamente.
                    targetElement.scrollIntoView({ behavior: 'auto', block: 'start' });
                }
            }
            // Remove a classe que esconde o corpo da página, tornando-o visível
            // já na posição correta e evitando o "salto".
            document.body.classList.remove('is-loading');
        });

        // Nova função para limpar a seleção de produtos da tendência
        document.getElementById('clear-tendencia-selection')?.addEventListener('click', () => {
            const url = new URL(window.location.href);
            const newSearchParams = new URLSearchParams();

            // Get current 'tendencia_agrupamento' if it exists
            const currentAgrupamento = url.searchParams.get('tendencia_agrupamento');

            // Copia todos os parâmetros existentes, exceto 'produto_ids_tendencia[]' e 'tendencia_agrupamento'
            url.searchParams.forEach((value, key) => {
                if (!key.startsWith('produto_ids_tendencia') && key !== 'tendencia_agrupamento') { // MODIFIED CONDITION
                    newSearchParams.append(key, value);
                }
            });
            
            // Re-add the 'tendencia_agrupamento' if it was present
            if (currentAgrupamento) {
                newSearchParams.append('tendencia_agrupamento', currentAgrupamento);
            }

            url.search = newSearchParams.toString();
            url.hash = '#report-tendencia'; // Mantém o scroll na seção
            window.location.href = url.toString();
        });
    });
  </script>
  <?php $conn->close(); ?>
</body>
</html>
