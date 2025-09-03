<?php
require_once __DIR__ . '/config/db.php';

// Protege a página: se o usuário não estiver logado, redireciona para o login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$nome_usuario = $_SESSION['usuario_nome'];
$nivel_usuario = $_SESSION['usuario_nivel'] ?? 'user'; // Pega o nível do usuário da sessão

/**
 * Verifica se o usuário atual é um administrador. Se não for, define uma mensagem de erro
 * na sessão, redireciona para o dashboard e encerra o script.
 */
function check_admin_or_exit(): void {
    if (($_SESSION['usuario_nivel'] ?? 'user') !== 'admin') {
        $_SESSION['lista_produto_erro'] = "Você não tem permissão para realizar esta ação.";
        header("Location: dashboard.php#lista-produtos");
        exit();
    }
}

// --- Lógica para exibir mensagens da sessão (após redirecionamento) ---
$registro_sucesso = $_SESSION['message_success'] ?? null;
$registro_erro = $_SESSION['message_error'] ?? null;
$lista_produto_sucesso = $_SESSION['lista_produto_sucesso'] ?? null;
$lista_produto_erro = $_SESSION['lista_produto_erro'] ?? null;
$lista_produto_log_erros = $_SESSION['lista_produto_log_erros'] ?? null;

// Limpa as mensagens da sessão para que não apareçam novamente
unset($_SESSION['message_success'], $_SESSION['message_error']);
unset($_SESSION['lista_produto_sucesso'], $_SESSION['lista_produto_erro'], $_SESSION['lista_produto_log_erros']);

// --- Listas pré-definidas para o formulário de registro ---
$motivos_avaria = ['PRODUTO VENCIDO', 'EMBALAGEM DANIFICADA', 'ERRO DE MANUSEIO', 'PROBLEMA DE QUALIDADE', 'AVARIA NO TRANSPORTE', 'OUTROS'];
$motivos_consumo = ['USO INTERNO (ESCRITÓRIO)', 'AMOSTRA PARA CLIENTE', 'MATERIAL DE LIMPEZA', 'DESCARTE PARA TESTE', 'DOAÇÃO', 'OUTROS'];
$motivos_recuperados = ['REPARO INTERNO', 'DEVOLUÇÃO DO FORNECEDOR', 'RECLASSIFICAÇÃO DE ESTOQUE', 'OUTROS'];


// --- Lógica para Registrar Nova Avaria/Consumo ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_avaria'])) {
    // Coleta e valida os dados do formulário
    $produto_id = filter_input(INPUT_POST, 'produto_id', FILTER_VALIDATE_INT);
    $produto_nome = trim($_POST['produto_nome']); // Nome vem do campo oculto
    $quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_INT);
    $lote = trim($_POST['lote']);
    $validade = trim($_POST['validade']);
    $motivo = trim($_POST['motivo']);
    $tipo_embalagem = trim($_POST['tipo_embalagem_avaria']);
    $tipo_avaria = $_POST['tipo_avaria'];
    $data_ocorrencia = $_POST['data_ocorrencia'];
    $usuario_id = $_SESSION['usuario_id'];

    // Validação dos campos obrigatórios
    if (empty($produto_id) || empty($produto_nome) || $quantidade === false || $quantidade <= 0 || empty($lote) || empty($validade) || empty($tipo_embalagem) || !in_array($tipo_avaria, ['avaria', 'uso_e_consumo', 'recuperados']) || empty($data_ocorrencia)) {
        $_SESSION['message_error'] = "Por favor, preencha todos os campos obrigatórios.";
    } else {
        // Prepara e executa a inserção no banco de dados
        $sql_insert = "INSERT INTO avarias (produto_id, produto_nome, lote, validade, quantidade, tipo_embalagem, motivo, tipo, data_ocorrencia, registrado_por_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("isssissssi", $produto_id, $produto_nome, $lote, $validade, $quantidade, $tipo_embalagem, $motivo, $tipo_avaria, $data_ocorrencia, $usuario_id);
        if ($stmt_insert->execute()) {
            $_SESSION['message_success'] = "Registro adicionado com sucesso!";
        } else {
            $_SESSION['message_error'] = "Erro ao registrar a avaria. Por favor, tente novamente."; // Evita expor detalhes do erro.
        }
        $stmt_insert->close();
    }
    // Redireciona para a mesma página para evitar reenvio do formulário
    header("Location: dashboard.php#registrar");
    exit();
}

// --- Lógica para Cadastrar Novo Produto ---
/**
 * Processa uma string de códigos de barras (separados por nova linha) e retorna um array
 * com exatamente 11 elementos, preenchido com os códigos ou null.
 * @param string $barcodes_string A string de entrada da textarea.
 * @return array<string|null>
 */
function process_barcodes_from_input(string $barcodes_string): array {
    $barcodes = array_fill(0, 11, null);
    if (!empty(trim($barcodes_string))) {
        $input_barcodes = explode("\n", trim($barcodes_string));
        $input_barcodes = array_filter(array_map('trim', $input_barcodes));
        $limited_barcodes = array_slice($input_barcodes, 0, 11);
        array_splice($barcodes, 0, count($limited_barcodes), $limited_barcodes);
    }
    return $barcodes;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cadastrar_produto'])) {
    check_admin_or_exit();
    // Coleta dos dados do formulário
    $codigo_produto = trim($_POST['codigo_produto']);
    $descricao = trim($_POST['descricao']);
    $referencia = trim($_POST['referencia']);
    $quantidade_estoque = filter_input(INPUT_POST, 'quantidade_estoque', FILTER_VALIDATE_INT);
    $endereco = trim($_POST['endereco']);
    $tipo_embalagem = $_POST['tipo_embalagem'];
    $lastro_camada = trim($_POST['lastro_camada']);

    $barcodes = process_barcodes_from_input($_POST['codigos_barras'] ?? '');

    // Validação simples
    if (empty($codigo_produto) || empty($descricao) || $quantidade_estoque === false) {
        $_SESSION['lista_produto_erro'] = "Ao cadastrar: Código do Produto, Descrição e Quantidade são obrigatórios.";
    } else {
        $sql_insert_produto = "INSERT INTO produtos (codigo_produto, descricao, referencia, quantidade_estoque, endereco, tipo_embalagem, lastro_camada, codigo_barras_1, codigo_barras_2, codigo_barras_3, codigo_barras_4, codigo_barras_5, codigo_barras_6, codigo_barras_7, codigo_barras_8, codigo_barras_9, codigo_barras_10, codigo_barras_11) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_produto = $conn->prepare($sql_insert_produto);
        $insert_params = array_merge([$codigo_produto, $descricao, $referencia, $quantidade_estoque, $endereco, $tipo_embalagem, $lastro_camada], $barcodes);
        $stmt_produto->bind_param("sssissssssssssssss", ...$insert_params);
        if ($stmt_produto->execute()) {
            $_SESSION['lista_produto_sucesso'] = "Produto '".htmlspecialchars($descricao)."' cadastrado com sucesso!";
        } else {
            $_SESSION['lista_produto_erro'] = "Erro ao cadastrar o produto. Verifique se o código já existe.";
        }
        $stmt_produto->close();
    }
    header("Location: dashboard.php#lista-produtos");
    exit();
}

// --- Lógica para a Lista de Produtos (CRUD) ---

// Atualizar Produto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    check_admin_or_exit();
    $id = filter_input(INPUT_POST, 'edit_produto_id', FILTER_VALIDATE_INT);
    $codigo_produto = trim($_POST['edit_codigo_produto']);
    $descricao = trim($_POST['edit_descricao']);
    $referencia = trim($_POST['edit_referencia']);
    $quantidade_estoque = filter_input(INPUT_POST, 'edit_quantidade_estoque', FILTER_VALIDATE_INT);
    $endereco = trim($_POST['edit_endereco']);
    $tipo_embalagem = $_POST['edit_tipo_embalagem'];
    $lastro_camada = trim($_POST['edit_lastro_camada']);
    $preco_venda = filter_input(INPUT_POST, 'edit_preco_venda', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE); // Novo campo

    $barcodes = process_barcodes_from_input($_POST['edit_codigos_barras'] ?? '');

    if ($id && !empty($codigo_produto) && !empty($descricao) && $quantidade_estoque !== false) {
        $sql_update = "UPDATE produtos SET codigo_produto = ?, descricao = ?, referencia = ?, quantidade_estoque = ?, endereco = ?, tipo_embalagem = ?, lastro_camada = ?, preco_venda = ?, codigo_barras_1 = ?, codigo_barras_2 = ?, codigo_barras_3 = ?, codigo_barras_4 = ?, codigo_barras_5 = ?, codigo_barras_6 = ?, codigo_barras_7 = ?, codigo_barras_8 = ?, codigo_barras_9 = ?, codigo_barras_10 = ?, codigo_barras_11 = ? WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        // Adiciona preco_venda aos parâmetros e 'd' aos tipos
        $update_params = array_merge([$codigo_produto, $descricao, $referencia, $quantidade_estoque, $endereco, $tipo_embalagem, $lastro_camada, $preco_venda], $barcodes, [$id]);
        $stmt_update->bind_param("sssisssdsisssssssssi", ...$update_params); // 'd' para double/float
        if ($stmt_update->execute()) {
            $_SESSION['lista_produto_sucesso'] = "Produto atualizado com sucesso!";
        } else {
            $_SESSION['lista_produto_erro'] = "Erro ao atualizar o produto. Verifique se o código já não está em uso por outro item.";
        }
        $stmt_update->close();
    } else {
        $_SESSION['lista_produto_erro'] = "Dados inválidos para atualização.";
    }
    header("Location: dashboard.php#lista-produtos");
    exit();
}

// Excluir Produto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    check_admin_or_exit();
    $id = filter_input(INPUT_POST, 'delete_produto_id', FILTER_VALIDATE_INT);
    if ($id) {
        // Opcional: Verificar se o produto está sendo usado em 'avarias' antes de excluir.
        // Por simplicidade, vamos excluir diretamente.
        $sql_delete = "DELETE FROM produtos WHERE id = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("i", $id);
        if ($stmt_delete->execute()) {
            $_SESSION['lista_produto_sucesso'] = "Produto excluído com sucesso!";
        } else {
            $_SESSION['lista_produto_erro'] = "Erro ao excluir o produto. Pode estar em uso em algum registro de avaria.";
        }
        $stmt_delete->close();
    }
    header("Location: dashboard.php#lista-produtos");
    exit();
}

// Busca e Paginação da Lista de Produtos
$p_search_term = isset($_GET['p_search']) ? trim($_GET['p_search']) : '';
$p_page = isset($_GET['p_page']) && is_numeric($_GET['p_page']) ? (int)$_GET['p_page'] : 1;
$p_records_per_page = 10;
$p_offset = ($p_page - 1) * $p_records_per_page;

$p_search_sql = '';
$p_search_params = [];
if (!empty($p_search_term)) {
    $p_search_like = "%{$p_search_term}%";

    // Lista de campos para a busca. Facilita a adição de novos campos no futuro.
    $searchable_fields = ['codigo_produto', 'descricao', 'referencia', 'endereco'];
    for ($i = 1; $i <= 11; $i++) {
        $searchable_fields[] = "codigo_barras_{$i}";
    }

    // Constrói a cláusula WHERE dinamicamente
    $where_clauses = array_map(fn($field) => "{$field} LIKE ?", $searchable_fields);
    $p_search_sql = "WHERE " . implode(" OR ", $where_clauses);

    // Preenche os parâmetros para o bind
    $p_search_params = array_fill(0, count($searchable_fields), $p_search_like);
}

$p_sql_count = "SELECT COUNT(id) as total FROM produtos {$p_search_sql}";
$p_stmt_count = $conn->prepare($p_sql_count);
if (!empty($p_search_term)) { $p_stmt_count->bind_param(str_repeat('s', count($p_search_params)), ...$p_search_params); }
$p_stmt_count->execute();
$p_total_records = $p_stmt_count->get_result()->fetch_assoc()['total'];
$p_total_pages = ceil($p_total_records / $p_records_per_page);
$p_stmt_count->close();

$p_sql_lista = "SELECT * FROM produtos {$p_search_sql} ORDER BY descricao ASC LIMIT ?, ?";
$p_stmt_lista = $conn->prepare($p_sql_lista);
$p_lista_params = array_merge($p_search_params, [$p_offset, $p_records_per_page]);
$p_types = str_repeat('s', count($p_search_params)) . 'ii';
$p_stmt_lista->bind_param($p_types, ...$p_lista_params);
$p_stmt_lista->execute();
$result_lista_produtos = $p_stmt_lista->get_result();
$p_stmt_lista->close();

// --- Lógica para o Histórico de Avarias (Paginação e Busca) ---
// Pega os parâmetros do GET
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$data_inicial = isset($_GET['data_inicial']) && !empty($_GET['data_inicial']) ? $_GET['data_inicial'] : date('Y-m-d', strtotime('-3 days'));
$data_final = isset($_GET['data_final']) && !empty($_GET['data_final']) ? $_GET['data_final'] : date('Y-m-d');
$tipo_historico = isset($_GET['tipo_historico']) ? $_GET['tipo_historico'] : 'todos'; // 'todos', 'avaria', 'uso_e_consumo', 'recuperados'
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 15;
$offset = ($page - 1) * $records_per_page;

// Base da query e parâmetros para a busca
$where_conditions = [];
$params = [];
$types = '';

// Adiciona condição de data (sempre presente)
$where_conditions[] = "a.data_ocorrencia BETWEEN ? AND ?";
if (!empty($search_term)) {
    $search_like = "%{$search_term}%";
    // Busca no nome do produto (registrado na avaria) ou no código do produto (na tabela de produtos)
    $where_conditions[] = "(a.produto_nome LIKE ? OR p.codigo_produto LIKE ? OR p.referencia LIKE ?)";
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
    $types .= 'sss';
}
if ($tipo_historico !== 'todos') {
    $where_conditions[] = "a.tipo = ?";
    $params[] = $tipo_historico;
    $types .= 's';
}

$where_sql = "WHERE " . implode(' AND ', $where_conditions);

// 1. Contar o total de registros para a paginação
$sql_count = "SELECT COUNT(a.id) as total 
              FROM avarias a 
              LEFT JOIN produtos p ON a.produto_id = p.id 
              {$where_sql}";
$stmt_count = $conn->prepare($sql_count);
$count_params = array_merge([$data_inicial, $data_final], $params);
$stmt_count->bind_param('ss' . $types, ...$count_params);
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);
$stmt_count->close();

// 2. Buscar os registros para a página atual
$sql_historico = "SELECT a.id, a.data_registro, a.data_ocorrencia, a.produto_nome, p.codigo_produto, a.lote, a.validade, a.quantidade, a.tipo_embalagem, a.motivo, a.tipo, u.nome as nome_usuario
                  FROM avarias a
                  LEFT JOIN usuarios u ON a.registrado_por_id = u.id
                  LEFT JOIN produtos p ON a.produto_id = p.id
                  {$where_sql} ORDER BY a.data_ocorrencia DESC, a.id DESC LIMIT ?, ?";
$stmt_historico = $conn->prepare($sql_historico);

$historico_params = array_merge([$data_inicial, $data_final], $params, [$offset, $records_per_page]);

$stmt_historico->bind_param('ss' . $types . 'ii', ...$historico_params);
$stmt_historico->execute();
$result_historico = $stmt_historico->get_result();
$stmt_historico->close();

// --- Lógica para os Filtros ---
$ano_selecionado = isset($_GET['ano']) ? intval($_GET['ano']) : date('Y');
$mes_selecionado = isset($_GET['mes']) ? intval($_GET['mes']) : 0;
$dia_selecionado = isset($_GET['dia']) && $mes_selecionado > 0 ? intval($_GET['dia']) : 0; // Dia só é válido se um mês for selecionado
$tipo_selecionado = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos'; // 'avaria', 'uso_e_consumo', 'recuperados', 'todos'

// Busca os anos disponíveis para o filtro
$query_anos = "SELECT DISTINCT YEAR(data_ocorrencia) as ano FROM avarias ORDER BY ano DESC";
$anos_disponiveis = [];
$result_anos = $conn->query($query_anos);
if ($result_anos) {
    while($row_ano = $result_anos->fetch_assoc()) {
        $anos_disponiveis[] = $row_ano['ano'];
    }
}
// Garante que o ano atual esteja sempre disponível para seleção, mesmo que não haja registros
if (!in_array(date('Y'), $anos_disponiveis)) { array_unshift($anos_disponiveis, (int)date('Y')); }

// Nomes dos meses para o filtro
$meses_nomes_filtro = [
    1 => "Janeiro", 2 => "Fevereiro", 3 => "Março", 4 => "Abril", 5 => "Maio", 6 => "Junho",
    7 => "Julho", 8 => "Agosto", 9 => "Setembro", 10 => "Outubro", 11 => "Novembro", 12 => "Dezembro"
];

// --- Lógica de Construção de Query Dinâmica ---
$where_conditions = ["YEAR(data_ocorrencia) = ?"];
$params = [$ano_selecionado];
$types = "i";

$kpi_titulo_avarias = "Avarias em " . $ano_selecionado;
$kpi_titulo_consumo = "Uso/Consumo em " . $ano_selecionado;
$kpi_titulo_recuperados = "Recuperados em " . $ano_selecionado;

if ($mes_selecionado > 0) {
    $where_conditions[] = "MONTH(data_ocorrencia) = ?";
    $params[] = $mes_selecionado;
    $types .= "i";
    $kpi_titulo_avarias = "Avarias em " . $meses_nomes_filtro[$mes_selecionado] . "/" . $ano_selecionado;
    $kpi_titulo_consumo = "Uso/Consumo em " . $meses_nomes_filtro[$mes_selecionado] . "/" . $ano_selecionado;
    $kpi_titulo_recuperados = "Recuperados em " . $meses_nomes_filtro[$mes_selecionado] . "/" . $ano_selecionado;

    if ($dia_selecionado > 0) {
        if ($dia_selecionado == 101) { // 1ª Quinzena
            $where_conditions[] = "DAY(data_ocorrencia) <= 15";
            $kpi_titulo_avarias = "Avarias na 1ª Quinzena de " . $meses_nomes_filtro[$mes_selecionado] . "/" . $ano_selecionado;
            $kpi_titulo_consumo = "Uso/Consumo na 1ª Quinzena de " . $meses_nomes_filtro[$mes_selecionado] . "/" . $ano_selecionado;
            $kpi_titulo_recuperados = "Recuperados na 1ª Quinzena de " . $meses_nomes_filtro[$mes_selecionado] . "/" . $ano_selecionado;
        } elseif ($dia_selecionado == 102) { // 2ª Quinzena
            $where_conditions[] = "DAY(data_ocorrencia) > 15";
            $kpi_titulo_avarias = "Avarias na 2ª Quinzena de " . $meses_nomes_filtro[$mes_selecionado] . "/" . $ano_selecionado;
            $kpi_titulo_consumo = "Uso/Consumo na 2ª Quinzena de " . $meses_nomes_filtro[$mes_selecionado] . "/" . $ano_selecionado;
            $kpi_titulo_recuperados = "Recuperados na 2ª Quinzena de " . $meses_nomes_filtro[$mes_selecionado] . "/" . $ano_selecionado;
        } else { // Dia específico
            $where_conditions[] = "DAY(data_ocorrencia) = ?";
            $params[] = $dia_selecionado;
            $types .= "i";
            $data_formatada = $dia_selecionado . "/" . $mes_selecionado . "/" . $ano_selecionado;
            $kpi_titulo_avarias = "Avarias em " . $data_formatada;
            $kpi_titulo_consumo = "Uso/Consumo em " . $data_formatada;
            $kpi_titulo_recuperados = "Recuperados em " . $data_formatada;
        }
    }
}

$where_sql = "WHERE " . implode(" AND ", $where_conditions);

// --- Buscando dados para o Painel ---

// 1. KPIs (Avarias e Uso/Consumo) - Estes usam apenas o filtro de data (ano/mês)
$sql_kpi = "SELECT 
                SUM(CASE WHEN tipo = 'avaria' THEN 1 ELSE 0 END) AS total_avarias,
                SUM(CASE WHEN tipo = 'uso_e_consumo' THEN 1 ELSE 0 END) AS total_consumo,
                SUM(CASE WHEN tipo = 'recuperados' THEN 1 ELSE 0 END) AS total_recuperados
            FROM avarias a {$where_sql}";
$stmt_kpi = $conn->prepare($sql_kpi);
$stmt_kpi->bind_param($types, ...$params);
$stmt_kpi->execute();
$result_kpi = $stmt_kpi->get_result();
$kpi_counts = $result_kpi->fetch_assoc();
$kpi_total_avarias = $kpi_counts['total_avarias'] ?? 0;
$kpi_total_consumo = $kpi_counts['total_consumo'] ?? 0;
$kpi_total_recuperados = $kpi_counts['total_recuperados'] ?? 0;
$stmt_kpi->close();

// Ajusta os KPIs com base no filtro de tipo. Se 'avaria' for selecionado, zera 'consumo', e vice-versa.
if ($tipo_selecionado === 'avaria') {
    $kpi_total_consumo = 0;
    $kpi_total_recuperados = 0;
} elseif ($tipo_selecionado === 'uso_e_consumo') {
    $kpi_total_avarias = 0;
    $kpi_total_recuperados = 0;
} elseif ($tipo_selecionado === 'recuperados') {
    $kpi_total_avarias = 0;
    $kpi_total_consumo = 0;
}

// 2. Construção da query para dados que usam TODOS os filtros (incluindo 'tipo')
$data_where_conditions = $where_conditions;
$data_params = $params;
$data_types = $types;
if ($tipo_selecionado !== 'todos') {
    $data_where_conditions[] = "a.tipo = ?";
    $data_params[] = $tipo_selecionado;
    $data_types .= "s";
}
$data_where_sql = "WHERE " . implode(" AND ", $data_where_conditions);

// 3. Dados para o Card de Volume por Embalagem (agora usa todos os filtros)
$volume_titulo = "Volume por Embalagem";
if ($tipo_selecionado === 'avaria') {
    $volume_titulo .= " (Avarias)";
} elseif ($tipo_selecionado === 'uso_e_consumo') {
    $volume_titulo .= " (Uso e Consumo)";
} elseif ($tipo_selecionado === 'recuperados') {
    $volume_titulo .= " (Recuperados)";
}

$sql_volume = "SELECT tipo_embalagem, SUM(quantidade) as total_volume FROM avarias a {$data_where_sql} GROUP BY tipo_embalagem ORDER BY total_volume DESC";
$stmt_volume = $conn->prepare($sql_volume);
if ($stmt_volume) {
    $stmt_volume->bind_param($data_types, ...$data_params);
    $stmt_volume->execute();
    $result_volume = $stmt_volume->get_result();
    $dados_volume = $result_volume->fetch_all(MYSQLI_ASSOC);
    $stmt_volume->close();
} else {
    $dados_volume = [];
}

// 4. Dados para o Gráfico e Top 10 (já usavam a query correta)
$grafico_titulo = "Registros";
if($tipo_selecionado === 'avaria') $grafico_titulo = "Avarias";
if($tipo_selecionado === 'uso_e_consumo') $grafico_titulo = "Uso e Consumo";
if($tipo_selecionado === 'recuperados') $grafico_titulo = "Recuperados";

// A query para o Top 10 é a mesma para visão anual ou mensal, apenas o título e os dados do gráfico mudam.
$sql_top10 = "SELECT a.produto_id, a.produto_nome, p.referencia, COUNT(a.id) as total
              FROM avarias a
              LEFT JOIN produtos p ON a.produto_id = p.id
              {$data_where_sql}
              GROUP BY a.produto_id, a.produto_nome, p.referencia
              ORDER BY total DESC
              LIMIT 10";
if ($mes_selecionado > 0 && $dia_selecionado > 0) { // Visão Diária ou Quinzena
    if ($dia_selecionado >= 101) { // Quinzenas
        if ($dia_selecionado == 101) {
            $top10_titulo = "Top 10 {$grafico_titulo} (1ª Quinzena de {$meses_nomes_filtro[$mes_selecionado]}/{$ano_selecionado})";
            $labels_grafico = range(1, 15);
            $dados_grafico = array_fill(0, 15, 0);
        } else { // 102
            $dias_no_mes = cal_days_in_month(CAL_GREGORIAN, $mes_selecionado, $ano_selecionado);
            $top10_titulo = "Top 10 {$grafico_titulo} (2ª Quinzena de {$meses_nomes_filtro[$mes_selecionado]}/{$ano_selecionado})";
            $labels_grafico = range(16, $dias_no_mes);
            $dados_grafico = array_fill(0, count($labels_grafico), 0);
        }

        $sql_grafico = "SELECT DAY(a.data_ocorrencia) as dia, COUNT(a.id) as total FROM avarias a {$data_where_sql} GROUP BY DAY(a.data_ocorrencia) ORDER BY dia ASC";
        $stmt_grafico = $conn->prepare($sql_grafico);
        $stmt_grafico->bind_param($data_types, ...$data_params);
        $stmt_grafico->execute();
        $result_grafico = $stmt_grafico->get_result();
        if ($result_grafico) {
            while ($row = $result_grafico->fetch_assoc()) {
                $dia = intval($row['dia']);
                $index = -1;
                if ($dia_selecionado == 101 && $dia >= 1 && $dia <= 15) {
                    $index = $dia - 1;
                } elseif ($dia_selecionado == 102 && $dia > 15) {
                    $index = $dia - 16;
                }
                if ($index !== -1 && isset($dados_grafico[$index])) {
                    $dados_grafico[$index] = intval($row['total']);
                }
            }
        }
        $stmt_grafico->close();
    } else { // Visão Diária
        $top10_titulo = "Top 10 {$grafico_titulo} ({$dia_selecionado}/{$mes_selecionado}/{$ano_selecionado})";
        // Gráfico para um único dia
        $labels_grafico = ["Dia $dia_selecionado"];
        $dados_grafico = [0];
        $sql_grafico = "SELECT COUNT(a.id) as total FROM avarias a {$data_where_sql}"; // Sem GROUP BY
        $stmt_grafico = $conn->prepare($sql_grafico);
        $stmt_grafico->bind_param($data_types, ...$data_params);
        $stmt_grafico->execute();
        $result_grafico = $stmt_grafico->get_result();
        if ($result_grafico) {
            $row = $result_grafico->fetch_assoc();
            $dados_grafico[0] = intval($row['total'] ?? 0);
        }
        $stmt_grafico->close();
    }
} elseif ($mes_selecionado > 0) { // Visão Mensal
    $top10_titulo = "Top 10 {$grafico_titulo} ({$meses_nomes_filtro[$mes_selecionado]}/{$ano_selecionado})";
    // Gráfico por dia
    $dias_no_mes = cal_days_in_month(CAL_GREGORIAN, $mes_selecionado, $ano_selecionado);
    $labels_grafico = range(1, $dias_no_mes);
    $dados_grafico = array_fill(0, $dias_no_mes, 0);
    $sql_grafico = "SELECT DAY(a.data_ocorrencia) as dia, COUNT(a.id) as total FROM avarias a {$data_where_sql} GROUP BY DAY(a.data_ocorrencia) ORDER BY dia ASC";
    $stmt_grafico = $conn->prepare($sql_grafico);
    $stmt_grafico->bind_param($data_types, ...$data_params);
    $stmt_grafico->execute();
    $result_grafico = $stmt_grafico->get_result();
    if ($result_grafico) { while ($row = $result_grafico->fetch_assoc()) { $dados_grafico[intval($row['dia']) - 1] = intval($row['total']); } }
    $stmt_grafico->close();
} else { // Visão Anual
    $top10_titulo = "Top 10 {$grafico_titulo} ({$ano_selecionado})";
    // Gráfico por mês
    $sql_grafico = "SELECT MONTH(a.data_ocorrencia) as mes, COUNT(a.id) as total FROM avarias a {$data_where_sql} GROUP BY MONTH(a.data_ocorrencia) ORDER BY mes ASC";
    $labels_grafico = ["Jan", "Fev", "Mar", "Abr", "Mai", "Jun", "Jul", "Ago", "Set", "Out", "Nov", "Dez"];
    $dados_grafico = array_fill(0, 12, 0);
    $stmt_grafico = $conn->prepare($sql_grafico);
    $stmt_grafico->bind_param($data_types, ...$data_params);
    $stmt_grafico->execute();
    $result_grafico = $stmt_grafico->get_result();
    if ($result_grafico) { while ($row = $result_grafico->fetch_assoc()) { $dados_grafico[intval($row['mes']) - 1] = intval($row['total']); } }
    $stmt_grafico->close();
}
$stmt_top10 = $conn->prepare($sql_top10);
$stmt_top10->bind_param($data_types, ...$data_params);

// --- Lógica para o eixo Y do gráfico ---
$max_grafico = 0;
if (!empty($dados_grafico)) {
    $max_grafico = max($dados_grafico);
}
// Adiciona uma margem no topo do gráfico para os rótulos caberem.
// A margem é de 20% do valor máximo, com um mínimo de 5 unidades.
$padding = $max_grafico * 0.2;
$y_axis_max = ceil($max_grafico + ($padding < 5 ? 5 : $padding));

$dados_grafico_json = json_encode($dados_grafico);
$labels_grafico_json = json_encode($labels_grafico);
$stmt_top10->execute();
$result_top10 = $stmt_top10->get_result();
$stmt_top10->close();

// --- Lógica para Colunas de Exportação ---
$colunas_disponiveis = [
    'data_registro' => 'Data Registro',
    'data_ocorrencia' => 'Data Ocorr.',
    'codigo_produto' => 'Código',
    'produto_nome' => 'Produto',
    'referencia' => 'Referência',
    'lote' => 'Lote',
    'validade' => 'Validade',
    'quantidade' => 'Qtd.',
    'tipo_embalagem' => 'Embalagem',
    'motivo' => 'Motivo',
    'tipo' => 'Tipo',
    'nome_usuario' => 'Registrado Por'
];
// Colunas que virão marcadas por padrão
$colunas_selecionadas_default = ['data_ocorrencia', 'codigo_produto', 'produto_nome', 'referencia', 'lote', 'quantidade', 'tipo_embalagem', 'tipo', 'nome_usuario'];

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <base target="_top">
  <meta charset="UTF-8">
  <title>Dashboard - Gestão de Avarias</title>
  <link rel="icon" href="img/favicon.ico" type="image/x-icon">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
  <style>
    /* Estilos extraídos do seu arquivo de referência */
    body {
        font-family: 'Inter', Arial, sans-serif;
        background-color: #f8f9fb;
        display: flex;
        min-height: 100vh;
        margin: 0;
    }
    .sidebar {
        width: 250px;
        background-color: #254c90;
        color: white;
        padding: 0;
        box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        display: flex;
        flex-direction: column;
        flex-shrink: 0;
    }
    .sidebar-header {
        padding: 20px;
        text-align: left;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    .logo-container {
        background-color: white;
        border-radius: 8px;
        width: 130px;
        padding: 0,5px;
        display: flex;
        justify-content: center;
        align-items: center;
        margin-bottom: 15px;
        overflow: hidden;
    }
    .logo-container img {
        max-width: 100%;
        height: auto;
    }
    .sidebar h2 {
        font-size: 1.2em;
        margin-bottom: 5px;
    }
    .sidebar h3 {
        font-size: 0.9em;
        opacity: 0.8;
    }
    .sidebar-menu {
        flex-grow: 1;
        list-style: none;
        padding: 15px 0;
        margin: 0;
    }
    .sidebar-menu .nav-item {
        padding: 0 10px;
    }
    .sidebar-menu .nav-link {
        display: block;
        padding: 12px 15px;
        color: white;
        text-decoration: none;
        transition: background-color 0.2s ease;
        font-size: 1em;
        border-radius: 0.5rem;
        border: none;
        margin-bottom: 5px;
    }
    .sidebar-menu .nav-link:hover {
        background-color: #1d3870;
        color: white;
    }
    .sidebar-menu .nav-link.active {
        background-color: #1d3870;
        color: white;
        font-weight: 500;
    }
    .sidebar-menu .nav-link i {
        margin-right: 10px;
        width: 20px;
        text-align: center;
    }
    .main-content {
        flex-grow: 1;
        padding: 25px;
        background-color: #f8f9fb;
        overflow-y: auto;
    }
    .main-header {
        margin-bottom: 25px;
    }
    .main-header h1 {
        color: #254c90;
        font-weight: 700;
    }
    .content-section {
        background-color: white;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    .content-section h3.header-verde {
        background-color: #198754; /* Verde do Bootstrap (success) */
        color: white;
        padding: 1rem 1.5rem;
        margin: -30px -30px 25px -30px; /* Anula o padding do content-section e adiciona margem inferior */
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
        font-size: 1.5rem;
    }
    /* Estilo para o cabeçalho da tabela de histórico */
    #historico .table thead th {
        background-color: #647adbff; /* Verde do Bootstrap (success) */
        color: white;
        border-color: #647adbff; /* Um tom mais escuro para a borda, para um acabamento melhor */
    }

    /* Ajustes de tamanho de fonte para uma interface mais compacta e legível em 100% de zoom */
    .main-header h1 {
        font-size: 1.5rem; /* Reduz o título principal da página (era ~2rem) */
    }
    .card .fs-1 {
        font-size: 2.2rem !important; /* Reduz os números grandes dos KPIs (era ~2.5rem) */
    }
    .content-section h3 {
        font-size: 1.15rem; /* Reduz o tamanho dos títulos das seções */
    }
    .table {
        font-size: 0.875rem; /* Reduz o tamanho da fonte em todas as tabelas (equivalente a 14px) */
    }
    .form-label {
        font-size: 0.875rem;
    }
    .top-10-list .list-group-item {
        padding: 0.4rem 0.75rem;
    }
    .top-10-list .fw-bold {
        font-size: 0.8rem !important; /* Ajustado para caber melhor */
    }
    .top-10-list small {
        font-size: 0.7rem !important; /* Referência um pouco menor */
    }
    .top-10-list .badge {
        font-size: 0.7rem !important; /* Badge menor */
        padding: 0.3em 0.5em;
    }
    /* Estilos para o novo botão de cópia do painel */
    .main-header {
        position: relative;
        padding-right: 50px; /* Espaço para o botão */
    }
    .btn-copy-main {
        position: absolute; top: 0; right: 0;
    }
    .copy-feedback-main {
        position: absolute; top: 5px; right: 45px; z-index: 10; display: none;
        background-color: #28a745; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.8em;
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
    <ul class="sidebar-menu nav nav-tabs flex-column" id="sidebarTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <a class="nav-link active" id="painel-tab" data-bs-toggle="tab" href="#painel" role="tab" aria-controls="painel" aria-selected="true">
          <i class="fas fa-tachometer-alt"></i> Painel
        </a>
      </li>
      <li class="nav-item" role="presentation">
        <a class="nav-link" id="registrar-tab" data-bs-toggle="tab" href="#registrar" role="tab" aria-controls="registrar" aria-selected="false">
          <i class="fas fa-plus-circle"></i> Registrar Avaria
        </a>
      </li>
      <li class="nav-item" role="presentation">
        <a class="nav-link" id="lista-produtos-tab" data-bs-toggle="tab" href="#lista-produtos" role="tab" aria-controls="lista-produtos" aria-selected="false">
          <i class="fas fa-list-ul"></i> Lista de Produtos
        </a>
      </li>
      <li class="nav-item" role="presentation">
        <a class="nav-link" id="historico-tab" data-bs-toggle="tab" href="#historico" role="tab" aria-controls="historico" aria-selected="false">
          <i class="fas fa-history"></i> Histórico
        </a>
      </li>
      <li class="nav-item" role="presentation">
        <a class="nav-link" href="relatorios.php">
          <i class="fas fa-chart-pie"></i> Relatórios
        </a>
      </li>
      <li class="nav-item" role="presentation">
        <a class="nav-link" href="logout.php">
          <i class="fas fa-sign-out-alt"></i> Sair
        </a>
      </li>
    </ul>
  </div>

  <div class="main-content tab-content" id="nav-tabContent">
    <header class="main-header">
        <h1 id="pageTitle" class="h2">Painel</h1>
        <div id="painel-header-buttons" style="display: none;">
            <button class="btn btn-sm btn-outline-secondary btn-copy-report btn-copy-main" data-container-id="report-container-painel" data-feedback-id="feedback-painel" title="Copiar painel como imagem"><i class="fas fa-camera"></i></button>
            <span class="copy-feedback-main" id="feedback-painel">Copiado!</span>
        </div>
    </header>

    <div class="tab-pane fade show active" id="painel" role="tabpanel" aria-labelledby="painel-tab">
      <div id="report-container-painel">
      <!-- Cards de KPIs -->
      <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-danger h-100">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-box-open"></i> <?php echo $kpi_titulo_avarias; ?></h5>
                    <p class="card-text fs-1 fw-bold"><?php echo $kpi_total_avarias; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success h-100">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-shopping-cart"></i> <?php echo $kpi_titulo_consumo; ?></h5>
                    <p class="card-text fs-1 fw-bold"><?php echo $kpi_total_consumo; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning h-100">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-recycle"></i> <?php echo $kpi_titulo_recuperados; ?></h5>
                    <p class="card-text fs-1 fw-bold"><?php echo $kpi_total_recuperados; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-primary h-100">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-boxes"></i> <?php echo htmlspecialchars($volume_titulo); ?></h5>
                    <?php if (!empty($dados_volume)): ?>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <?php foreach ($dados_volume as $volume): ?>
                                <div class="bg-light text-dark rounded-pill px-3 py-1 d-flex align-items-center" style="font-size: 0.85rem;">
                                    <strong class="me-2"><?php echo htmlspecialchars(strtoupper($volume['tipo_embalagem'] ?? 'N/D')); ?>:</strong>
                                    <span><?php echo $volume['total_volume']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="card-text mt-3">Nenhum volume registrado no período selecionado.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
      </div>

      <!-- Gráfico de Avarias -->
      <div class="row mb-4">
        <div class="col-lg-8 mb-4 mb-lg-0">
            <div class="content-section h-100">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                    <h3 class="mb-0 me-3"><?php echo $grafico_titulo; ?></h3>
                    <form id="filtroForm" action="dashboard.php" method="GET" class="d-flex">
                        <select name="tipo" id="tipo" class="form-select form-select-sm me-2" style="width: auto;">
                            <option value="todos" <?php if ($tipo_selecionado == 'todos') echo 'selected'; ?>>Todos os Tipos</option>
                            <option value="avaria" <?php if ($tipo_selecionado == 'avaria') echo 'selected'; ?>>Avarias</option>
                            <option value="uso_e_consumo" <?php if ($tipo_selecionado == 'uso_e_consumo') echo 'selected'; ?>>Uso e Consumo</option>
                            <option value="recuperados" <?php if ($tipo_selecionado == 'recuperados') echo 'selected'; ?>>Recuperados</option>
                        </select>
                        <select name="ano" id="ano" class="form-select form-select-sm me-2" style="width: auto;">
                            <?php foreach ($anos_disponiveis as $ano): ?>
                                <option value="<?php echo $ano; ?>" <?php if ($ano == $ano_selecionado) echo 'selected'; ?>>
                                    Ano de <?php echo $ano; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="mes" id="mes" class="form-select form-select-sm me-2" style="width: auto;">
                            <option value="0" <?php if ($mes_selecionado == 0) echo 'selected'; ?>>Todos os Meses</option>
                            <?php foreach ($meses_nomes_filtro as $num => $nome): ?>
                                <option value="<?php echo $num; ?>" <?php if ($num == $mes_selecionado) echo 'selected'; ?>><?php echo $nome; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="dia" id="dia" class="form-select form-select-sm" style="width: auto;">
                            <option value="0" <?php if ($dia_selecionado == 0) echo 'selected'; ?>>Todos os Dias</option>
                            <?php
                            if ($mes_selecionado > 0) {
                                echo '<option value="101" ' . ($dia_selecionado == 101 ? 'selected' : '') . '>1ª Quinzena</option>';
                                echo '<option value="102" ' . ($dia_selecionado == 102 ? 'selected' : '') . '>2ª Quinzena</option>';
                                $dias_no_mes = cal_days_in_month(CAL_GREGORIAN, $mes_selecionado, $ano_selecionado);
                                for ($d = 1; $d <= $dias_no_mes; $d++) {
                                    echo "<option value=\"{$d}\" " . ($d == $dia_selecionado ? 'selected' : '') . ">{$d}</option>";
                                }
                            }
                            ?>
                        </select>
                    </form>
                </div>
                <div style="position: relative; height: 350px; width: 100%;">
                    <canvas id="graficoAvarias"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="content-section h-100">
                <h3><?php echo $top10_titulo; ?></h3>
                <ol class="list-group list-group-numbered top-10-list">
                    <?php if ($result_top10 && $result_top10->num_rows > 0): ?>
                        <?php while($item = $result_top10->fetch_assoc()): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-start"
                                data-bs-toggle="modal" 
                                data-bs-target="#productDetailsModal" 
                                data-product-id="<?php echo $item['produto_id']; ?>" 
                                data-product-name="<?php echo htmlspecialchars($item['produto_nome']); ?>"
                                style="cursor: pointer;"
                                title="Clique para ver os detalhes">
                                <div class="ms-2 me-auto">
                                    <div class="fw-bold text-truncate" title="<?php echo htmlspecialchars($item['produto_nome']); ?>">
                                        <?php echo htmlspecialchars($item['produto_nome']); ?>
                                    </div>
                                    <small class="text-muted"><?php echo htmlspecialchars($item['referencia'] ?? 'Sem ref.'); ?></small>
                                </div>
                                <span class="badge bg-danger rounded-pill"><?php echo $item['total']; ?></span>
                            </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li class="list-group-item">Nenhum dado para este ano.</li>
                    <?php endif; ?>
                </ol>
            </div>
        </div>
      </div>
      </div>

    </div>

    <div class="tab-pane fade" id="registrar" role="tabpanel" aria-labelledby="registrar-tab">
      <div class="content-section">
        <?php if ($registro_sucesso): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($registro_sucesso); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($registro_erro): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($registro_erro); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <h3>Registrar Nova Avaria ou Consumo</h3>
        <form id="form-registrar-avaria" action="dashboard.php#registrar" method="POST" class="mt-4">
            <!-- Campos ocultos para enviar os dados do produto selecionado -->
            <input type="hidden" id="produto_id" name="produto_id">
            <input type="hidden" id="produto_nome" name="produto_nome">

            <div class="row">
                <div class="col-md-8 mb-3">
                    <label for="codigo_produto_avaria" class="form-label">Código do Produto</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="codigo_produto_avaria" placeholder="Digite o código, busque na lupa ou leia com a câmera">
                        <button class="btn btn-outline-secondary" type="button" id="btn-open-search-modal" title="Buscar produto">
                            <i class="fas fa-search"></i>
                        </button>
                        <button class="btn btn-outline-primary" type="button" id="btn-open-scanner-modal" title="Ler código de barras">
                            <i class="fas fa-camera"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="lote" class="form-label">Lote</label>
                    <input type="text" class="form-control" id="lote" name="lote" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="quantidade" class="form-label">Quantidade</label>
                    <input type="number" class="form-control" id="quantidade" name="quantidade" min="1" value="1" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="tipo_embalagem_avaria" class="form-label">Embalagem</label>
                    <select class="form-select" id="tipo_embalagem_avaria" name="tipo_embalagem_avaria" required><option value="" disabled selected>Selecione...</option><option value="UNIDADE">UNIDADE</option><option value="CAIXA">CAIXA</option><option value="PACOTE">PACOTE</option><option value="FARDO">FARDO</option><option value="DP">DP (Display)</option></select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="validade" class="form-label">Validade</label>
                    <input type="date" class="form-control" id="validade" name="validade" required>
                </div>
            </div>

            <div class="mb-3"><label for="descricao_avaria" class="form-label">Descrição do Produto</label><input type="text" class="form-control bg-light" id="descricao_avaria" readonly></div>
            <div class="mb-3"><label for="referencia_avaria" class="form-label">Referência</label><input type="text" class="form-control bg-light" id="referencia_avaria" readonly></div>

            <div class="mb-3"> <!-- Campo de Motivo agora é um dropdown -->
                <label for="motivo" class="form-label">Motivo da Avaria/Consumo</label>
                <select class="form-select" id="motivo" name="motivo" required>
                    <option value="" selected disabled>Selecione o tipo de registro primeiro...</option>
                </select>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="tipo_avaria" class="form-label">Tipo de Registro</label>
                    <select class="form-select" id="tipo_avaria" name="tipo_avaria" required>
                        <option value="avaria" selected>Avaria (Quebra, defeito, etc)</option>
                        <option value="uso_e_consumo">Uso e Consumo (Material de escritório, etc)</option>
                        <option value="recuperados">Recuperados (Retorno ao estoque)</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="data_ocorrencia" class="form-label">Data da Ocorrência</label>
                    <input type="date" class="form-control" id="data_ocorrencia" name="data_ocorrencia" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>

            <input type="hidden" name="registrar_avaria" value="1">
            <button type="submit" class="btn btn-primary" id="btn-registrar-avaria" disabled><i class="fas fa-save"></i> Registrar</button>
        </form>
      </div>
    </div>

    <div class="tab-pane fade" id="lista-produtos" role="tabpanel" aria-labelledby="lista-produtos-tab">
      <div class="content-section">
        <?php if ($lista_produto_sucesso): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $lista_produto_sucesso; // Pode conter HTML (<strong>), então não escapamos ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($lista_produto_erro): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($lista_produto_erro); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($lista_produto_log_erros): ?>
            <div class="alert alert-warning" role="alert">
                <h4 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Ocorreram erros durante a importação</h4>
                <p>Algumas linhas do seu arquivo CSV não puderam ser processadas. Veja os detalhes abaixo:</p>
                <hr>
                <ul class="mb-0">
                    <?php foreach ($lista_produto_log_erros as $log_erro): ?>
                        <li><small><?php echo htmlspecialchars($log_erro); ?></small></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <h3>Lista de Produtos Cadastrados</h3>

        <!-- Formulário de Busca de Produtos -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <form action="dashboard.php#lista-produtos" method="GET" id="form-lista-produtos" class="input-group" style="max-width: 500px;">
                <input type="text" name="p_search" id="p_search_input" class="form-control" placeholder="Buscar por código, descrição, referência, endereço..." value="<?php echo htmlspecialchars($p_search_term); ?>">
                <button class="btn btn-primary" type="submit" title="Buscar"><i class="fas fa-search"></i></button>
                <button class="btn btn-outline-primary" type="button" id="btn-open-scanner-list-modal" title="Ler código de barras para buscar">
                    <i class="fas fa-camera"></i>
                </button>
                <?php if (!empty($p_search_term)): ?>
                    <a href="dashboard.php#lista-produtos" class="btn btn-outline-secondary">Limpar</a>
                <?php endif; ?>
            </form>
            <?php if ($nivel_usuario === 'admin'): // Mostra o botão apenas para admin ?>
            <div class="d-flex gap-2">
                <button class="btn btn-info text-white" data-bs-toggle="modal" data-bs-target="#importProductsModal">
                    <i class="fas fa-file-import"></i> Importar CSV
                </button>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addProductModal" title="Adicionar um novo produto manualmente">
                    <i class="fas fa-plus"></i> Adicionar Produto
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- Tabela de Produtos -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Código</th>
                        <th>Descrição</th>
                        <th>Estoque</th>
                        <th>Endereço</th>
                        <?php if ($nivel_usuario === 'admin'): ?>
                        <th class="text-center">Ações</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="product-list-table">
                    <?php if ($result_lista_produtos && $result_lista_produtos->num_rows > 0): ?>
                        <?php while($prod = $result_lista_produtos->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($prod['codigo_produto']); ?></td>
                                <td class="text-truncate" style="max-width: 300px;" title="<?php echo htmlspecialchars($prod['descricao']); ?>"><?php echo htmlspecialchars($prod['descricao']); ?></td>
                                <td><?php echo htmlspecialchars($prod['quantidade_estoque']); ?></td>
                                <td><?php echo htmlspecialchars($prod['endereco'] ?? '-'); ?></td>
                                <?php if ($nivel_usuario === 'admin'): // Mostra os botões apenas para admin ?>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-warning btn-edit-product" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editProductModal"
                                            data-product='<?php echo htmlspecialchars(json_encode($prod), ENT_QUOTES, 'UTF-8'); ?>'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger btn-delete-product" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteProductModal"
                                            data-product-id="<?php echo $prod['id']; ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="<?php echo ($nivel_usuario === 'admin') ? '5' : '4'; ?>" class="text-center py-4">Nenhum produto encontrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginação da Lista de Produtos -->
        <?php if ($p_total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php
                    $p_query_string = http_build_query(['p_search' => $p_search_term]);
                    $window = 2; // Número de links em cada lado da página atual
                ?>
                <!-- Botão Anterior -->
                <li class="page-item <?php if ($p_page <= 1) { echo 'disabled'; } ?>">
                    <a class="page-link" href="?p_page=<?php echo $p_page - 1; ?>&<?php echo $p_query_string; ?>#lista-produtos">Anterior</a>
                </li>

                <?php
                    // Lógica para exibir os números de página de forma inteligente
                    $start = max(1, $p_page - $window);
                    $end = min($p_total_pages, $p_page + $window);

                    if ($start > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?p_page=1&' . $p_query_string . '#lista-produtos">1</a></li>';
                        if ($start > 2) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
                    }

                    for ($i = $start; $i <= $end; $i++) {
                        echo '<li class="page-item ' . ($p_page == $i ? 'active' : '') . '"><a class="page-link" href="?p_page=' . $i . '&' . $p_query_string . '#lista-produtos">' . $i . '</a></li>';
                    }

                    if ($end < $p_total_pages) {
                        if ($end < $p_total_pages - 1) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
                        echo '<li class="page-item"><a class="page-link" href="?p_page=' . $p_total_pages . '&' . $p_query_string . '#lista-produtos">' . $p_total_pages . '</a></li>';
                    }
                ?>

                <!-- Botão Próximo -->
                <li class="page-item <?php if ($p_page >= $p_total_pages) { echo 'disabled'; } ?>">
                    <a class="page-link" href="?p_page=<?php echo $p_page + 1; ?>&<?php echo $p_query_string; ?>#lista-produtos">Próximo</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
      </div>
    </div>

    <div class="tab-pane fade" id="historico" role="tabpanel" aria-labelledby="historico-tab">
      <div class="content-section">
        <h3 class="header-verde">Histórico de Avarias</h3>
        
        <!-- Formulário de Busca -->
        <form action="dashboard.php#historico" method="GET" class="mb-4">
            <input type="hidden" name="page" value="1"> <!-- Reseta para a página 1 em nova busca -->
            <div class="row g-3 align-items-end">
                <div class="col-md-3"><label for="data_inicial" class="form-label">Data Inicial</label><input type="date" name="data_inicial" id="data_inicial" class="form-control" value="<?php echo htmlspecialchars($data_inicial); ?>"></div>
                <div class="col-md-3"><label for="data_final" class="form-label">Data Final</label><input type="date" name="data_final" id="data_final" class="form-control" value="<?php echo htmlspecialchars($data_final); ?>"></div>
                <div class="col-md-3"><label for="search" class="form-label">Produto</label><input type="text" name="search" id="search" class="form-control" placeholder="Buscar por nome, código ou referência..." value="<?php echo htmlspecialchars($search_term); ?>"></div>
                <div class="col-md-2"><label for="tipo_historico" class="form-label">Tipo</label>
                    <select name="tipo_historico" id="tipo_historico" class="form-select">
                        <option value="todos" <?php if ($tipo_historico === 'todos') echo 'selected'; ?>>Todos</option>
                        <option value="avaria" <?php if ($tipo_historico === 'avaria') echo 'selected'; ?>>Avaria</option>
                        <option value="uso_e_consumo" <?php if ($tipo_historico === 'uso_e_consumo') echo 'selected'; ?>>Uso e Consumo</option>
                        <option value="recuperados" <?php if ($tipo_historico === 'recuperados') echo 'selected'; ?>>Recuperados</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                    </div>
                </div>
            </div>
            <div class="row g-3 mt-2">
                <div class="col-md-auto ms-auto">
                    <div class="d-flex gap-2">
                        <?php $query_string_base = http_build_query(['data_inicial' => $data_inicial, 'data_final' => $data_final, 'search' => $search_term, 'tipo_historico' => $tipo_historico]); ?>
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#colunasExportModal">
                            <i class="fas fa-columns"></i> Colunas
                        </button>
                        <a id="exportXlsxLink" href="export_xlsx.php?<?php echo $query_string_base; ?>" target="_blank" class="btn btn-success"><i class="fas fa-file-excel"></i> Exportar XLSX</a>
                        <a id="exportPdfLink" href="export_pdf.php?<?php echo $query_string_base; ?>" target="_blank" class="btn btn-danger" title="Gera um PDF moderno a partir do HTML"><i class="fas fa-file-pdf"></i> Exportar PDF</a>
                        <a href="dashboard.php#historico" class="btn btn-outline-secondary" title="Limpar Filtros"><i class="fas fa-eraser"></i> Limpar
                        </a>
                    </div>
                </div>
            </div>
        </form>

        <!-- Tabela de Histórico -->
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>                        
                        <th>Data Reg.</th>
                        <th>Data Ocorr.</th>
                        <th>Código</th>
                        <th>Produto</th>
                        <th>Lote</th>
                        <th>Validade</th>
                        <th>Qtd.</th>
                        <th>Embalagem</th>
                        <th>Motivo</th>
                        <th>Tipo</th>
                        <th>Registrado por</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_historico && $result_historico->num_rows > 0): ?>
                        <?php while($reg = $result_historico->fetch_assoc()): ?>
                            <tr>                                
                                <td class="text-nowrap"><?php echo date('d/m/Y H:i', strtotime($reg['data_registro'])); ?></td>
                                <td class="text-nowrap"><?php echo date('d/m/Y', strtotime($reg['data_ocorrencia'])); ?></td>
                                <td><?php echo htmlspecialchars($reg['codigo_produto'] ?? '-'); ?></td>
                                <td class="text-truncate" style="max-width: 250px;" title="<?php echo htmlspecialchars($reg['produto_nome']); ?>"><?php echo htmlspecialchars($reg['produto_nome']); ?></td>
                                <td><?php echo htmlspecialchars($reg['lote'] ?? '-'); ?></td>
                                <td class="text-nowrap"><?php echo $reg['validade'] ? date('d/m/Y', strtotime($reg['validade'])) : '-'; ?></td>
                                <td><?php echo htmlspecialchars($reg['quantidade']); ?></td>
                                <td><?php echo htmlspecialchars($reg['tipo_embalagem'] ?? '-'); ?></td>
                                <td class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($reg['motivo'] ?? ''); ?>"><?php echo htmlspecialchars($reg['motivo'] ?? '-'); ?></td>
                                <td>
                                    <?php
                                        $badge_class = 'bg-secondary';
                                        if ($reg['tipo'] === 'avaria') $badge_class = 'bg-danger';
                                        if ($reg['tipo'] === 'uso_e_consumo') $badge_class = 'bg-success';
                                        if ($reg['tipo'] === 'recuperados') $badge_class = 'bg-warning';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $reg['tipo'] ?? 'N/D')); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($reg['nome_usuario'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" class="text-center py-4">Nenhum registro encontrado para os filtros aplicados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Controles de Paginação -->
        <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php
                    $query_string = http_build_query(['data_inicial' => $data_inicial, 'data_final' => $data_final, 'search' => $search_term, 'tipo_historico' => $tipo_historico]);
                    $window = 2; // Número de links em cada lado da página atual
                ?>
                <!-- Botão Anterior -->
                <li class="page-item <?php if ($page <= 1) { echo 'disabled'; } ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo $query_string; ?>#historico">Anterior</a>
                </li>

                <?php
                    // Lógica para exibir os números de página de forma inteligente
                    $start = max(1, $page - $window);
                    $end = min($total_pages, $page + $window);

                    if ($start > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=1&' . $query_string . '#historico">1</a></li>';
                        if ($start > 2) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
                    }

                    for ($i = $start; $i <= $end; $i++) {
                        echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '"><a class="page-link" href="?page=' . $i . '&' . $query_string . '#historico">' . $i . '</a></li>';
                    }

                    if ($end < $total_pages) {
                        if ($end < $total_pages - 1) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
                        echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&' . $query_string . '#historico">' . $total_pages . '</a></li>';
                    }
                ?>

                <!-- Botão Próximo -->
                <li class="page-item <?php if ($page >= $total_pages) { echo 'disabled'; } ?>">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo $query_string; ?>#historico">Próximo</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Modal de Detalhes do Produto (Top 10) -->
  <div class="modal fade" id="productDetailsModal" tabindex="-1" aria-labelledby="productDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="productDetailsModalLabel">Detalhes do Produto</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="productDetailsModalBody">
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

  <!-- Modal de Seleção de Colunas para Exportação -->
  <div class="modal fade" id="colunasExportModal" tabindex="-1" aria-labelledby="colunasExportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="colunasExportModalLabel">Selecionar Colunas para Exportação</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p>Escolha as colunas que aparecerão nos relatórios CSV e PDF.</p>
          <form id="formColunasExport">
              <div class="row">
              <?php foreach ($colunas_disponiveis as $key => $label): ?>
                  <div class="col-md-6">
                      <div class="form-check mb-2">
                          <input class="form-check-input" type="checkbox" value="<?php echo $key; ?>" id="col_<?php echo $key; ?>"
                              <?php if (in_array($key, $colunas_selecionadas_default)) echo 'checked'; ?>>
                          <label class="form-check-label" for="col_<?php echo $key; ?>">
                              <?php echo htmlspecialchars($label); ?>
                          </label>
                      </div>
                  </div>
              <?php endforeach; ?>
              </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Confirmar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal de Adição de Produto -->
  <div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addProductModalLabel">Adicionar Novo Produto</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="dashboard.php#lista-produtos" method="POST">
          <div class="modal-body">
            <div class="row">
                <div class="col-md-6 mb-3"><label for="codigo_produto" class="form-label">Código do Produto <span class="text-danger">*</span></label><input type="text" class="form-control" id="codigo_produto" name="codigo_produto" required></div>
                <div class="col-md-6 mb-3"><label for="referencia" class="form-label">Referência</label><input type="text" class="form-control" id="referencia" name="referencia"></div>
            </div>
            <div class="mb-3"><label for="descricao" class="form-label">Descrição <span class="text-danger">*</span></label><textarea class="form-control" id="descricao" name="descricao" rows="2" required></textarea></div>
            <div class="row">
                <div class="col-md-4 mb-3"><label for="quantidade_estoque" class="form-label">Qtd. Inicial <span class="text-danger">*</span></label><input type="number" class="form-control" id="quantidade_estoque" name="quantidade_estoque" min="0" value="0" required></div>
                <div class="col-md-4 mb-3"><label for="tipo_embalagem" class="form-label">Embalagem</label><select class="form-select" id="tipo_embalagem" name="tipo_embalagem"><option value="Unidade" selected>Unidade</option><option value="Caixa">Caixa</option><option value="Pacote">Pacote</option><option value="Fardo">Fardo</option><option value="Palete">Palete</option></select></div>
                <div class="col-md-4 mb-3"><label for="lastro_camada" class="form-label">Lastro x Camada</label><input type="text" class="form-control" id="lastro_camada" name="lastro_camada" placeholder="Ex: 10x5"></div>
            </div>
            <div class="mb-3"><label for="endereco" class="form-label">Endereço (Localização)</label><input type="text" class="form-control" id="endereco" name="endereco" placeholder="Ex: Corredor A, Prateleira 3"></div>
            <div class="mb-3"><label for="codigos_barras" class="form-label">Códigos de Barras (um por linha)</label>
                <textarea class="form-control" id="codigos_barras" name="codigos_barras" rows="3" placeholder="Digite um código de barras por linha..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" name="cadastrar_produto" class="btn btn-primary">Salvar Produto</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal de Edição de Produto -->
  <div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editProductModalLabel">Editar Produto</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="dashboard.php#lista-produtos" method="POST">
          <div class="modal-body">
            <input type="hidden" name="edit_produto_id" id="edit_produto_id">
            <div class="row">
                <div class="col-md-6 mb-3"><label for="edit_codigo_produto" class="form-label">Código do Produto</label><input type="text" class="form-control" id="edit_codigo_produto" name="edit_codigo_produto" required></div>
                <div class="col-md-6 mb-3"><label for="edit_referencia" class="form-label">Referência</label><input type="text" class="form-control" id="edit_referencia" name="edit_referencia"></div>
            </div>
            <div class="mb-3"><label for="edit_descricao" class="form-label">Descrição</label><textarea class="form-control" id="edit_descricao" name="edit_descricao" rows="2" required></textarea></div>
            <div class="row">
                <div class="col-md-4 mb-3"><label for="edit_quantidade_estoque" class="form-label">Qtd. Estoque</label><input type="number" class="form-control" id="edit_quantidade_estoque" name="edit_quantidade_estoque" min="0" required></div>
                <div class="col-md-4 mb-3"><label for="edit_tipo_embalagem" class="form-label">Embalagem</label><select class="form-select" id="edit_tipo_embalagem" name="edit_tipo_embalagem"><option value="Unidade">Unidade</option><option value="Caixa">Caixa</option><option value="Pacote">Pacote</option><option value="Fardo">Fardo</option><option value="Palete">Palete</option></select></div>
                <div class="col-md-4 mb-3"><label for="edit_lastro_camada" class="form-label">Lastro x Camada</label><input type="text" class="form-control" id="edit_lastro_camada" name="edit_lastro_camada"></div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3"><label for="edit_preco_venda" class="form-label">Preço de Venda</label><input type="number" step="0.01" class="form-control" id="edit_preco_venda" name="edit_preco_venda" min="0"></div>
            </div>
            <div class="mb-3"><label for="edit_endereco" class="form-label">Endereço</label><input type="text" class="form-control" id="edit_endereco" name="edit_endereco"></div>
            <div class="mb-3"><label for="edit_codigos_barras" class="form-label">Códigos de Barras (um por linha)</label>
                <textarea class="form-control" id="edit_codigos_barras" name="edit_codigos_barras" rows="3" placeholder="Digite um código de barras por linha..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" name="update_product" class="btn btn-primary">Salvar Alterações</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal de Exclusão de Produto -->
  <div class="modal fade" id="deleteProductModal" tabindex="-1" aria-labelledby="deleteProductModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="deleteProductModalLabel">Confirmar Exclusão</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="dashboard.php#lista-produtos" method="POST">
          <div class="modal-body">
            <p>Você tem certeza que deseja excluir este produto?</p>
            <p>Esta ação não pode ser desfeita.</p>
            <input type="hidden" name="delete_produto_id" id="delete_produto_id">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" name="delete_product" class="btn btn-danger">Excluir Produto</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal de Importação de Produtos -->
  <div class="modal fade" id="importProductsModal" tabindex="-1" aria-labelledby="importProductsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="importProductsModalLabel">Importar Produtos via CSV</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="preview_import.php" method="POST" enctype="multipart/form-data">
          <div class="modal-body">
            <div class="alert alert-info">
                <p class="mb-1"><strong>Instruções:</strong></p>
                <p>O arquivo deve ser no formato CSV e a primeira linha deve ser o cabeçalho. O sistema irá <strong>atualizar</strong> produtos com códigos existentes ou <strong>criar novos</strong> se o código não for encontrado.</p>
                <p>As colunas devem estar na seguinte ordem:</p>
                <small>
                    <code>codigo_produto, descricao, referencia, quantidade_estoque, endereco, tipo_embalagem, lastro_camada, codigo_barras</code>
                </small>
            </div>
            <div class="mb-3">
                <label for="arquivo_produtos" class="form-label">Selecione o arquivo CSV</label>
                <input class="form-control" type="file" id="arquivo_produtos" name="arquivo_produtos" accept=".csv" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-upload"></i> Enviar e Processar
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal de Busca de Produto -->
  <div class="modal fade" id="searchProductModal" tabindex="-1" aria-labelledby="searchProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="searchProductModalLabel">Buscar Produto</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
              <input type="text" class="form-control" id="modal_product_search_input" placeholder="Digite para buscar por código, descrição, referência ou código de barras...">
          </div>
          <div class="table-responsive" style="max-height: 400px;">
              <table class="table table-hover">
                  <thead>
                      <tr>
                          <th>Código</th>
                          <th>Descrição</th>
                          <th>Referência</th>
                          <th>Ação</th>
                      </tr>
                  </thead>
                  <tbody id="modal_search_results"></tbody>
              </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal do Scanner de Código de Barras -->
  <div class="modal fade" id="scannerModal" tabindex="-1" aria-labelledby="scannerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="scannerModalLabel">Scanner de Código de Barras</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="reader" style="width: 100%;"></div>
          <div id="scanner-status" class="mt-2 text-center"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    
    // Script para atualizar o título da página ao trocar de aba
    document.addEventListener('DOMContentLoaded', function() {
        // REGISTRA O PLUGIN DE RÓTULOS GLOBALMENTE PARA TODOS OS GRÁFICOS
        Chart.register(ChartDataLabels);

        const pageTitle = document.getElementById('pageTitle');
        const sidebarTabs = document.querySelectorAll('#sidebarTabs .nav-link[data-bs-toggle="tab"]');
        const painelHeaderButtons = document.getElementById('painel-header-buttons');

        function updateTitle(tab) {
            if (tab && pageTitle) {
                pageTitle.textContent = tab.textContent.trim();
                // Mostra o botão de cópia apenas se a aba "Painel" estiver ativa
                painelHeaderButtons.style.display = (tab.id === 'painel-tab') ? 'block' : 'none';
            }
        }

        const activeTab = document.querySelector('#sidebarTabs .nav-link.active');
        updateTitle(activeTab);

        sidebarTabs.forEach(tab => {
            // Adiciona o evento para quando uma nova aba é mostrada
            tab.addEventListener('shown.bs.tab', function(event) {
                updateTitle(event.target);
            });
        });

        // Ativa a aba correta com base na URL hash (ex: #registrar)
        const hash = window.location.hash;
        if (hash) {
            const tabTrigger = document.querySelector('.nav-link[href="' + hash + '"]');
            if (tabTrigger) {
                new bootstrap.Tab(tabTrigger).show();
                // Limpa o hash da URL para que o F5 não o mantenha, preservando os filtros (query string)
                history.replaceState(null, '', window.location.pathname + window.location.search);
            }
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
                    backgroundColor: '#f8f9fb' // Cor de fundo do body para evitar transparência
                });

                // Converte o novo canvas para um Blob (formato de imagem)
                canvas.toBlob(async (blob) => {
                    await navigator.clipboard.write([
                        new ClipboardItem({ 'image/png': blob })
                    ]);

                    // Mostra feedback de sucesso
                    feedbackEl.textContent = 'Copiado!';
                    feedbackEl.style.display = 'inline';
                    setTimeout(() => { feedbackEl.style.display = 'none'; }, 2000);
                }, 'image/png');

            } catch (err) {
                console.error('Falha ao copiar o relatório: ', err);
                // Mostra feedback de erro
                feedbackEl.textContent = 'Falha!';
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

        // Script para submeter o formulário de filtro
        const filtroForm = document.getElementById('filtroForm');
        const anoSelect = document.getElementById('ano');
        const mesSelect = document.getElementById('mes');
        const diaSelect = document.getElementById('dia');

        // Mostra ou esconde o filtro de dia na carga da página
        if (mesSelect.value === '0') {
            diaSelect.style.display = 'none';
        }

        // Adiciona os eventos de 'change' para submeter o formulário
        document.getElementById('tipo').addEventListener('change', () => filtroForm.submit());
        
        anoSelect.addEventListener('change', () => {
            // Ao mudar o ano, reseta o dia para evitar datas inválidas (ex: 29/02) e submete
            diaSelect.value = '0';
            filtroForm.submit();
        });

        mesSelect.addEventListener('change', () => {
            // Ao mudar o mês, reseta o dia para "Todos" e submete
            diaSelect.value = '0';
            filtroForm.submit();
        });

        diaSelect.addEventListener('change', () => filtroForm.submit());

        // Script para o Gráfico de Barras
        const ctx = document.getElementById('graficoAvarias').getContext('2d');
        const graficoAvarias = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo $labels_grafico_json; ?>,
                datasets: [{
                    label: 'Nº de Avarias',
                    data: <?php echo $dados_grafico_json; ?>,
                    backgroundColor: 'rgba(37, 76, 144, 0.7)', // Cor do preenchimento da barra
                    borderColor: 'rgba(37, 76, 144, 1)',     // Cor da borda da barra
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: <?php echo $y_axis_max; ?>,
                        ticks: {
                            // Garante que o eixo Y só mostre números inteiros
                            callback: function(value) { if (Number.isInteger(value)) { return value; } },
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    datalabels: {
                        formatter: (value, context) => {
                            // Só mostra o rótulo se o valor for maior que 0
                            return value > 0 ? value : null;
                        },
                        color: '#000', // Cor do texto do rótulo
                        anchor: 'end', // Ancorar o rótulo no topo da barra
                        align: 'top', // Alinhar o texto acima da barra
                        offset: 4, // Distância em pixels acima da barra
                        font: { weight: 'bold' }
                    }
                }
            }
        });

        // Se houver uma mensagem de sucesso/erro para avaria, muda para a aba de registro
        <?php if ($registro_sucesso || $registro_erro): ?>
            new bootstrap.Tab(document.getElementById('registrar-tab')).show();
        <?php endif; ?>
        // Se houver uma mensagem de sucesso/erro/log para a lista de produtos, muda para a aba correspondente
        <?php if ($lista_produto_sucesso || $lista_produto_erro || $lista_produto_log_erros): ?>
            new bootstrap.Tab(document.getElementById('lista-produtos-tab')).show();
        <?php endif; ?>

        // --- LÓGICA PARA O DROPDOWN DE MOTIVOS DINÂMICOS ---
        const tipoAvariaSelect = document.getElementById('tipo_avaria');
        const motivoSelect = document.getElementById('motivo');
        const motivos = {
            avaria: <?php echo json_encode($motivos_avaria); ?>,
            uso_e_consumo: <?php echo json_encode($motivos_consumo); ?>,
            recuperados: <?php echo json_encode($motivos_recuperados); ?>
        };

        function updateMotivosDropdown(tipo) {
            motivoSelect.innerHTML = ''; // Limpa opções existentes
            const listaMotivos = motivos[tipo] || [];

            if (listaMotivos.length === 0) {
                motivoSelect.add(new Option('Selecione o tipo de registro primeiro...', ''));
                motivoSelect.disabled = true;
                return;
            }

            motivoSelect.disabled = false;
            motivoSelect.add(new Option('Selecione um motivo...', ''));
            listaMotivos.forEach(motivo => {
                motivoSelect.add(new Option(motivo, motivo));
            });
        }

        // Adiciona o evento e chama a função na inicialização
        if (tipoAvariaSelect) {
            tipoAvariaSelect.addEventListener('change', () => updateMotivosDropdown(tipoAvariaSelect.value));
            updateMotivosDropdown(tipoAvariaSelect.value); // Inicializa o dropdown com os valores corretos
        }

        // --- LÓGICA PARA BUSCA DE PRODUTO NA TELA DE REGISTRO ---
        const formAvaria = document.getElementById('form-registrar-avaria');
        const inputCodigo = document.getElementById('codigo_produto_avaria');
        const inputDescricao = document.getElementById('descricao_avaria');
        const inputReferencia = document.getElementById('referencia_avaria');
        const inputHiddenId = document.getElementById('produto_id');
        const inputHiddenNome = document.getElementById('produto_nome');
        const btnRegistrar = document.getElementById('btn-registrar-avaria');
        
        const searchModal = new bootstrap.Modal(document.getElementById('searchProductModal'));
        const btnOpenModal = document.getElementById('btn-open-search-modal');
        const modalSearchInput = document.getElementById('modal_product_search_input');
        const modalResultsContainer = document.getElementById('modal_search_results');

        // Limpa o formulário e desabilita o botão de salvar
        function resetProductFields() {
            inputDescricao.value = '';
            inputReferencia.value = '';
            inputHiddenId.value = '';
            inputHiddenNome.value = '';
            btnRegistrar.disabled = true;
        }

        // Preenche o formulário com os dados do produto
        function populateProductFields(product) {
            inputCodigo.value = product.codigo_produto || '';
            inputDescricao.value = product.descricao || '';
            inputReferencia.value = product.referencia || ''; // Garante que não seja 'null' ou 'undefined'
            inputHiddenId.value = product.id || '';
            inputHiddenNome.value = product.descricao || '';
            btnRegistrar.disabled = false; // Habilita o botão de registro
        }

        // Função para buscar produto pelo código digitado
        async function fetchProductByCode(code) {
            if (!code) {
                resetProductFields();
                return;
            }
            try {
                const response = await fetch(`api_get_product.php?code=${encodeURIComponent(code)}`);
                if (!response.ok) {
                    throw new Error('Falha na busca do produto.');
                }
                const product = await response.json();
                if (product && product.id) {
                    populateProductFields(product);
                } else {
                    resetProductFields();
                    inputDescricao.value = 'Produto não encontrado.';
                }
            } catch (error) {
                console.error('Erro:', error);
                resetProductFields();
                inputDescricao.value = 'Erro ao buscar produto.';
            }
        }

        // Evento para buscar ao sair do campo de código (blur) ou ao pressionar Enter
        inputCodigo.addEventListener('blur', () => fetchProductByCode(inputCodigo.value.trim()));
        inputCodigo.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault(); // Impede o envio do formulário
                fetchProductByCode(inputCodigo.value.trim());
            }
        });
        
        // Evento para limpar campos se o código for apagado
        inputCodigo.addEventListener('input', () => {
            if(inputCodigo.value.trim() === '') {
                resetProductFields();
            }
        });

        // Abre o modal de busca
        btnOpenModal.addEventListener('click', () => searchModal.show());

        // Função para buscar produtos para o modal
        let searchTimeout;
        async function searchProductsForModal(term) {
            modalResultsContainer.innerHTML = '<tr><td colspan="3" class="text-center">Buscando...</td></tr>';
            if (term.length < 2) {
                modalResultsContainer.innerHTML = '<tr><td colspan="3" class="text-center">Digite ao menos 2 caracteres.</td></tr>';
                return;
            }
            try {
                const response = await fetch(`api_search_products.php?term=${encodeURIComponent(term)}`);
                const products = await response.json();
                
                modalResultsContainer.innerHTML = ''; // Limpa resultados anteriores
                if (products.length > 0) {
                    products.forEach(product => {
                        const row = document.createElement('tr');
                        // Armazena os dados do produto no próprio botão para fácil acesso
                        row.innerHTML = `
                            <td>${product.codigo_produto || ''}</td>
                            <td class="text-truncate" style="max-width: 250px;" title="${product.descricao || ''}">${product.descricao || ''}</td>
                            <td>${product.referencia || '-'}</td>
                            <td><button type="button" class="btn btn-sm btn-primary btn-select-product" data-product='${JSON.stringify(product)}'>Selecionar</button></td>
                        `;
                        modalResultsContainer.appendChild(row);
                    });
                } else {
                    modalResultsContainer.innerHTML = '<tr><td colspan="3" class="text-center">Nenhum produto encontrado.</td></tr>';
                }
            } catch (error) {
                console.error('Erro na busca do modal:', error);
                modalResultsContainer.innerHTML = '<tr><td colspan="3" class="text-center">Erro ao carregar produtos.</td></tr>';
            }
        }

        // Evento de digitação no campo de busca do modal (com debounce)
        modalSearchInput.addEventListener('keyup', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                searchProductsForModal(modalSearchInput.value.trim());
            }, 300); // Espera 300ms após o usuário parar de digitar
        });

        // Evento para selecionar um produto da lista do modal
        modalResultsContainer.addEventListener('click', (e) => {
            if (e.target && e.target.classList.contains('btn-select-product')) {
                const productData = JSON.parse(e.target.dataset.product);
                populateProductFields(productData);
                searchModal.hide();
                modalSearchInput.value = ''; // Limpa o campo de busca do modal
                modalResultsContainer.innerHTML = ''; // Limpa os resultados
            }
        });

        // --- LÓGICA PARA O SCANNER DE CÓDIGO DE BARRAS (Refatorado) ---
        const scannerModalEl = document.getElementById('scannerModal');
        let html5QrCode;
        let onScanSuccessCallback = null; // Variável para armazenar o callback de sucesso

        if (scannerModalEl) {
            const scannerModal = new bootstrap.Modal(scannerModalEl);
            const scannerStatusEl = document.getElementById('scanner-status');

            // Função genérica chamada quando um código de barras é lido com sucesso
            const onScanSuccess = (decodedText, decodedResult) => {
                if (html5QrCode && html5QrCode.isScanning) {
                    html5QrCode.stop().catch(err => console.error("Falha ao parar o scanner.", err));
                }
                scannerModal.hide();

                // Executa o callback específico que foi definido
                if (typeof onScanSuccessCallback === 'function') {
                    onScanSuccessCallback(decodedText);
                }
            };

            const onScanFailure = (error) => { /* Ignorar */ };

            // Função para iniciar o scanner com um callback específico
            function startScanner(successCallback) {
                onScanSuccessCallback = successCallback;
                scannerModal.show();
            }

            // Eventos do modal para iniciar e parar o scanner
            scannerModalEl.addEventListener('shown.bs.modal', () => {
                scannerStatusEl.textContent = 'Iniciando câmera...';
                html5QrCode = new Html5Qrcode("reader");
                const config = { fps: 10, qrbox: { width: 250, height: 250 } };

                html5QrCode.start({ facingMode: "environment" }, config, onScanSuccess, onScanFailure)
                    .catch(err => {
                        scannerStatusEl.innerHTML = `<div class="alert alert-danger">Erro ao iniciar o scanner: ${err}. Verifique as permissões da câmera.</div>`;
                    });
            });

            scannerModalEl.addEventListener('hide.bs.modal', () => {
                if (html5QrCode && html5QrCode.isScanning) {
                    html5QrCode.stop();
                }
                onScanSuccessCallback = null; // Limpa o callback ao fechar
            });

            // --- Scanner para a tela de REGISTRAR AVARIA ---
            const btnOpenScannerAvaria = document.getElementById('btn-open-scanner-modal');
            if (btnOpenScannerAvaria) {
                btnOpenScannerAvaria.addEventListener('click', () => {
                    startScanner((decodedText) => {
                        // Callback para a tela de avaria
                        inputCodigo.value = decodedText;
                        inputCodigo.dispatchEvent(new Event('blur')); // Dispara a busca do produto
                    });
                });
            }

            // --- Scanner para a tela de LISTA DE PRODUTOS ---
            const btnOpenScannerLista = document.getElementById('btn-open-scanner-list-modal');
            const pSearchInput = document.getElementById('p_search_input');
            const pSearchForm = document.getElementById('form-lista-produtos');
            if (btnOpenScannerLista && pSearchInput && pSearchForm) {
                btnOpenScannerLista.addEventListener('click', () => {
                    startScanner((decodedText) => {
                        // Callback para a lista de produtos
                        pSearchInput.value = decodedText;
                        pSearchForm.submit(); // Submete o formulário de busca
                    });
                });
            }
        }

        // --- LÓGICA PARA A LISTA DE PRODUTOS (MODAIS) ---
        const productListTable = document.getElementById('product-list-table');
        if (productListTable) {
            productListTable.addEventListener('click', function(e) {
                const editButton = e.target.closest('.btn-edit-product');
                const deleteButton = e.target.closest('.btn-delete-product');

                if (editButton) {
                    const product = JSON.parse(editButton.dataset.product);
                    document.getElementById('edit_produto_id').value = product.id;
                    document.getElementById('edit_codigo_produto').value = product.codigo_produto;
                    document.getElementById('edit_descricao').value = product.descricao;
                    document.getElementById('edit_referencia').value = product.referencia;
                    document.getElementById('edit_quantidade_estoque').value = product.quantidade_estoque;
                    document.getElementById('edit_endereco').value = product.endereco;
                    document.getElementById('edit_tipo_embalagem').value = product.tipo_embalagem;
                    document.getElementById('edit_lastro_camada').value = product.lastro_camada;
                    document.getElementById('edit_preco_venda').value = product.preco_venda; // Novo campo

                    // Junta todos os códigos de barras em uma string, um por linha
                    const barcodes = [];
                    for (let i = 1; i <= 11; i++) {
                        if (product[`codigo_barras_${i}`]) {
                            barcodes.push(product[`codigo_barras_${i}`]);
                        }
                    }
                    document.getElementById('edit_codigos_barras').value = barcodes.join('\n');
                } else if (deleteButton) {
                    document.getElementById('delete_produto_id').value = deleteButton.dataset.productId;
                }
            });
        }

        // --- LÓGICA PARA ATUALIZAR LINKS DE EXPORTAÇÃO ---
        const formColunasExport = document.getElementById('formColunasExport');
        const exportXlsxLink = document.getElementById('exportXlsxLink');
        const exportPdfLink = document.getElementById('exportPdfLink');
        
        // Armazena as URLs base dos links de exportação quando a página carrega
        const baseXlsxHref = exportXlsxLink ? exportXlsxLink.href : '';
        const basePdfHref = exportPdfLink ? exportPdfLink.href : '';

        function updateExportLinks() {
            if (!exportXlsxLink || !exportPdfLink) return;
            
            const selectedColumns = [];
            formColunasExport.querySelectorAll('input[type="checkbox"]:checked').forEach(checkbox => {
                selectedColumns.push(`columns[]=${encodeURIComponent(checkbox.value)}`);
            });

            const columnsQuery = selectedColumns.join('&');

            // Anexa a query das colunas à URL base
            exportXlsxLink.href = `${baseXlsxHref}&${columnsQuery}`;
            exportPdfLink.href = `${basePdfHref}&${columnsQuery}`;
        }

        if (formColunasExport) formColunasExport.addEventListener('change', updateExportLinks);
        updateExportLinks(); // Chamada inicial para configurar os links com os padrões
    });

    // --- LÓGICA PARA O MODAL DE DETALHES DO TOP 10 ---
    const productDetailsModal = document.getElementById('productDetailsModal');
    if (productDetailsModal) {
        productDetailsModal.addEventListener('show.bs.modal', async function (event) {
            const listItem = event.relatedTarget; // O item <li> que acionou o modal
            const productId = listItem.dataset.productId;
            const productName = listItem.dataset.productName;

            const modalTitle = productDetailsModal.querySelector('.modal-title');
            const modalBody = productDetailsModal.querySelector('.modal-body');

            modalTitle.textContent = `Registros para: ${productName}`;
            modalBody.innerHTML = '<div class="text-center p-4"><div class="spinner-border" role="status"><span class="visually-hidden">Carregando...</span></div></div>';

            // Pega os filtros atuais do painel
            const ano = document.getElementById('ano').value;
            const mes = document.getElementById('mes').value;
            const dia = document.getElementById('dia').value;
            const tipo = document.getElementById('tipo').value;

            try {
                const url = `api_get_details_by_product.php?product_id=${productId}&ano=${ano}&mes=${mes}&dia=${dia}&tipo=${tipo}`;
                const response = await fetch(url);
                const items = await response.json();

                if (items.error) {
                    throw new Error(items.error);
                }

                if (items.length > 0) {
                    let tableHtml = `
                        <div class="table-responsive">
                            <table class="table table-sm table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Código</th>
                                        <th>Referência</th>
                                        <th>Qtd</th>
                                        <th>Motivo</th>
                                        <th>Tipo</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    items.forEach(item => {
                        const dataOcorrencia = new Date(item.data_ocorrencia + 'T00:00:00').toLocaleDateString('pt-BR');
                        
                        let tipoBadge = '<span class="badge bg-secondary">N/D</span>';
                        if (item.tipo === 'avaria') tipoBadge = '<span class="badge bg-danger">Avaria</span>';
                        if (item.tipo === 'uso_e_consumo') tipoBadge = '<span class="badge bg-success">Uso/Consumo</span>';
                        if (item.tipo === 'recuperados') tipoBadge = '<span class="badge bg-warning text-dark">Recuperados</span>';

                        tableHtml += `
                            <tr>
                                <td class="text-nowrap">${dataOcorrencia}</td>
                                <td>${item.codigo_produto || '-'}</td>
                                <td>${item.referencia || '-'}</td>
                                <td>${item.quantidade}</td>
                                <td class="text-truncate" style="max-width: 150px;" title="${item.motivo || ''}">${item.motivo || '-'}</td>
                                <td>${tipoBadge}</td>
                            </tr>
                        `;
                    });
                    tableHtml += '</tbody></table></div>';
                    modalBody.innerHTML = tableHtml;
                } else {
                    modalBody.innerHTML = '<p class="text-center">Nenhum registro detalhado encontrado para este item no período selecionado.</p>';
                }

            } catch (error) {
                modalBody.innerHTML = `<div class="alert alert-danger">Erro ao carregar detalhes: ${error.message}</div>`;
            }
        });
    }
  </script>
  <?php $conn->close(); // Fecha a conexão com o banco de dados no final do script ?>
</body>
</html>
