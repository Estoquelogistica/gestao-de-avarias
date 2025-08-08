<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config/db.php';

// Protege a API: se o usuário não estiver logado, nega o acesso.
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Acesso não autorizado.']);
    exit();
}

$searchTerm = $_GET['term'] ?? '';

// Retorna um array vazio se o termo de busca for muito curto, para não sobrecarregar o banco.
if (strlen($searchTerm) < 2) {
    echo json_encode([]);
    exit();
}

$searchLike = "%{$searchTerm}%";

// Lista de campos para a busca. Facilita a adição de novos campos no futuro.
$searchable_fields = ['codigo_produto', 'descricao', 'referencia'];
for ($i = 1; $i <= 11; $i++) {
    $searchable_fields[] = "codigo_barras_{$i}";
}

// Constrói a cláusula WHERE dinamicamente
$where_clauses = array_map(fn($field) => "{$field} LIKE ?", $searchable_fields);
$where_sql = "WHERE " . implode(" OR ", $where_clauses);

// Prepara a consulta para buscar em múltiplos campos.
$sql = "SELECT id, codigo_produto, descricao, referencia
        FROM produtos
        {$where_sql}
        LIMIT 20";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro na preparação da consulta: ' . $conn->error]);
    exit();
}

$params = array_fill(0, count($searchable_fields), $searchLike);
$stmt->bind_param(str_repeat('s', count($searchable_fields)), ...$params);
$stmt->execute();
$result = $stmt->get_result();

$products = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$conn->close();

echo json_encode($products);