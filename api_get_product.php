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
    echo json_encode(['error' => 'Código do produto não fornecido.']);
    exit();
}

// Prepara a consulta para buscar um único produto pelo seu código.
$sql = "SELECT id, codigo_produto, descricao, referencia FROM produtos WHERE codigo_produto = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Erro na preparação da consulta.']);
    exit();
}

$stmt->bind_param("s", $code);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

// Retorna o objeto do produto ou um objeto vazio se não for encontrado.
echo json_encode($product ?: new stdClass());

