<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config/db.php';

// Protege a API: se o usuário não estiver logado, nega o acesso.
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso não autorizado.']);
    exit();
}

$code = $_GET['code'] ?? '';

if (empty($code)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Código do produto ou de barras não fornecido.']);
    exit();
}

// Constrói a query para buscar pelo código do produto ou por qualquer um dos códigos de barras.
$barcode_fields = [];
for ($i = 1; $i <= 11; $i++) {
    $barcode_fields[] = "codigo_barras_{$i} = ?";
}
$barcode_sql = implode(' OR ', $barcode_fields);

$sql = "SELECT id, codigo_produto, descricao, referencia 
        FROM produtos 
        WHERE codigo_produto = ? OR {$barcode_sql}
        LIMIT 1";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Erro na preparação da consulta: ' . $conn->error]);
    exit();
}

// Prepara os parâmetros para o bind. O código é usado 12 vezes (1 para codigo_produto + 11 para barcodes).
$params = array_fill(0, 12, $code);
$stmt->bind_param(str_repeat('s', 12), ...$params);

$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

// Retorna o objeto do produto ou um objeto vazio se não for encontrado.
echo json_encode($product ?: new stdClass());
