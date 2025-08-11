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
$rua_label = $_GET['rua'] ?? '';
$data_inicial = $_GET['data_inicial'] ?? '';
$data_final = $_GET['data_final'] ?? '';
$tipo_relatorio = $_GET['tipo_relatorio'] ?? 'todos';

if (empty($rua_label) || empty($data_inicial) || empty($data_final)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetros insuficientes.']);
    exit();
}

// --- Montagem da Query ---
$where_conditions = ["a.data_ocorrencia BETWEEN ? AND ?"];
$params = [$data_inicial, $data_final];
$types = 'ss';

if ($tipo_relatorio !== 'todos') {
    $where_conditions[] = "a.tipo = ?";
    $params[] = $tipo_relatorio;
    $types .= 's';
}

// Lógica para traduzir o rótulo do gráfico de volta para uma condição SQL
$rua_map_letra = [
    '01' => 'A', '02' => 'B', '03' => 'C', '04' => 'D', '05' => 'E',
    '06' => 'F', '07' => 'G', '08' => 'H', '09' => 'I', '11' => 'K'
];

if ($rua_label === 'Sem Endereço') {
    $where_conditions[] = "(p.endereco IS NULL OR TRIM(p.endereco) = '')";
} else {
    $rua_numero = str_replace('Rua ', '', $rua_label);
    if (isset($rua_map_letra[$rua_numero])) {
        $letra = $rua_map_letra[$rua_numero];
        // CORREÇÃO: A query em relatorios.php agrupa tanto por letra (ex: 'A') quanto por número (ex: '01').
        // A API precisa replicar essa lógica para encontrar todos os itens.
        $where_conditions[] = "(UPPER(SUBSTRING(p.endereco, 1, 1)) = ? OR SUBSTRING(p.endereco, 1, 2) = ?)";
        $params[] = $letra;
        $params[] = $rua_numero;
        $types .= 'ss';
    } else {
        // Para casos não mapeados (ex: '10', '12'), assume que é o início do endereço
        $where_conditions[] = "SUBSTRING(p.endereco, 1, 2) = ?";
        $params[] = $rua_numero;
        $types .= 's';
    }
}

$where_sql = "WHERE " . implode(' AND ', $where_conditions);

$sql = "SELECT 
            a.produto_nome, 
            a.quantidade, 
            a.tipo,
            a.data_ocorrencia
        FROM avarias a
        LEFT JOIN produtos p ON a.produto_id = p.id
        {$where_sql}
        ORDER BY a.data_ocorrencia DESC, a.produto_nome ASC";

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