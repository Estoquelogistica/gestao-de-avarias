<?php
// Define o tipo de conteúdo como CSV e força o download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="template_importacao_produtos.csv"');

// Abre o fluxo de saída do PHP
$output = fopen('php://output', 'w');

// Define o cabeçalho do CSV
$header = [
    'codigo_produto', 'descricao', 'referencia', 'quantidade_estoque', 
    'endereco', 'tipo_embalagem', 'lastro_camada', 
    'codigo_barras_1', 'codigo_barras_2', 'codigo_barras_3', 
    'codigo_barras_4', 'codigo_barras_5', 'codigo_barras_6', 
    'codigo_barras_7', 'codigo_barras_8', 'codigo_barras_9', 
    'codigo_barras_10', 'codigo_barras_11'
];

// Escreve o cabeçalho no arquivo, usando ponto e vírgula como delimitador
fputcsv($output, $header, ';');

// Adiciona uma linha de exemplo para guiar o usuário
$example = [
    'PROD001', 'Produto Exemplo 1', 'REF-EX-01', 100, 
    'Corredor A, Prateleira 3', 'Caixa', '10x5', 
    '7890123456789', '7890123456780', '', '', '', '', '', '', '', '', ''
];
fputcsv($output, $example, ';');

fclose($output);
exit();
