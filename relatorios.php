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
    <div class="row">
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
    <div class="content-section">
        <h3>Outro Relatório</h3>
        <p class="text-muted">Este é um espaço para um futuro relatório.</p>
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
