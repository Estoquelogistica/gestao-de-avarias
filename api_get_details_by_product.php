<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config/db.php';

// Protege a API: se o usuário não estiver logado, nega o acesso.
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso não autorizado.']);
    exit();
}

// --- Validação dos Parâmetros ---
$product_id = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);
$ano = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
$mes = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT);
$dia = filter_input(INPUT_GET, 'dia', FILTER_VALIDATE_INT);
$tipo = $_GET['tipo'] ?? 'todos';

if (empty($product_id) || empty($ano)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetros insuficientes ou inválidos.']);
    exit();
}

// --- Montagem da Query com base nos filtros do painel ---
$where_conditions = ["a.produto_id = ?", "YEAR(a.data_ocorrencia) = ?"];
$params = [$product_id, $ano];
$types = "ii";

if ($mes > 0) {
    $where_conditions[] = "MONTH(a.data_ocorrencia) = ?";
    $params[] = $mes;
    $types .= "i";
}

if ($dia > 0 && $mes > 0) { // Dia só é válido se um mês for selecionado
    if ($dia == 101) { // 1ª Quinzena
        $where_conditions[] = "DAY(a.data_ocorrencia) <= 15";
    } elseif ($dia == 102) { // 2ª Quinzena
        $where_conditions[] = "DAY(a.data_ocorrencia) > 15";
    } else { // Dia específico
        $where_conditions[] = "DAY(a.data_ocorrencia) = ?";
        $params[] = $dia;
        $types .= "i";
    }
}

if ($tipo !== 'todos') {
    $where_conditions[] = "a.tipo = ?";
    $params[] = $tipo;
    $types .= "s";
}

$where_sql = "WHERE " . implode(' AND ', $where_conditions);

$sql = "SELECT 
            a.data_ocorrencia, 
            a.quantidade, 
            a.lote,
            a.motivo,
            a.tipo,
            u.nome as nome_usuario,
            p.codigo_produto,
            p.referencia
        FROM avarias a
        LEFT JOIN usuarios u ON a.registrado_por_id = u.id
        LEFT JOIN produtos p ON a.produto_id = p.id
        {$where_sql}
        ORDER BY a.data_ocorrencia DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro na preparação da consulta: ' . $conn->error]);
    exit();
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$details = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$conn->close();

echo json_encode($details);
?>

