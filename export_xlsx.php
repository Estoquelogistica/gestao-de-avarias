<?php
require_once __DIR__ . '/config/db.php';

// 1. VERIFICAÇÃO DE ROBUSTEZ: Checa se o autoloader do Composer existe
$autoloader = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloader)) {
    http_response_code(500);
    die("ERRO: O autoloader do Composer não foi encontrado. Execute 'composer install' na pasta do projeto.");
}
require_once $autoloader;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// Protege a página
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo "Acesso negado.";
    exit();
}

// --- Definição das colunas disponíveis ---
$colunas_disponiveis = [
    'data_registro' => 'Data Registro', 'data_ocorrencia' => 'Data Ocorrência', 'codigo_produto' => 'Código',
    'produto_nome' => 'Produto', 'referencia' => 'Referência', 'lote' => 'Lote', 'validade' => 'Validade', 'quantidade' => 'Qtd.',
    'tipo_embalagem' => 'Embalagem', 'motivo' => 'Motivo', 'tipo' => 'Tipo', 'nome_usuario' => 'Registrado Por'
];
$colunas_selecionadas_keys = $_GET['columns'] ?? array_keys($colunas_disponiveis);
$colunas_selecionadas_keys = array_intersect($colunas_selecionadas_keys, array_keys($colunas_disponiveis));

// --- Lógica para buscar os dados com base nos filtros (idêntica à anterior) ---
$search_term = $_GET['search'] ?? '';
$data_inicial = $_GET['data_inicial'] ?? date('Y-m-d', strtotime('-3 days'));
$data_final = $_GET['data_final'] ?? date('Y-m-d');
$tipo_historico = $_GET['tipo_historico'] ?? 'todos';

$where_conditions = ["a.data_ocorrencia BETWEEN ? AND ?"];
$params = [$data_inicial, $data_final];
$types = 'ss';

if (!empty($search_term)) {
    $search_like = "%{$search_term}%";
    $where_conditions[] = "(a.produto_nome LIKE ? OR p.codigo_produto LIKE ? OR p.referencia LIKE ?)";
    array_push($params, $search_like, $search_like, $search_like);
    $types .= 'sss';
}
if ($tipo_historico !== 'todos') {
    $where_conditions[] = "a.tipo = ?";
    $params[] = $tipo_historico;
    $types .= 's';
}
$where_sql = "WHERE " . implode(' AND ', $where_conditions);

$sql = "SELECT a.data_registro, a.data_ocorrencia, p.codigo_produto, p.referencia, a.produto_nome, a.lote, a.validade, a.quantidade, a.tipo_embalagem, a.motivo, a.tipo, u.nome as nome_usuario
        FROM avarias a
        LEFT JOIN usuarios u ON a.registrado_por_id = u.id
        LEFT JOIN produtos p ON a.produto_id = p.id
        {$where_sql} ORDER BY a.data_ocorrencia DESC, a.id DESC";

// 2. VERIFICAÇÃO DE ROBUSTEZ: Checa se a preparação da query falhou
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    die("ERRO: Falha ao preparar a consulta SQL: " . $conn->error);
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// --- Geração do XLSX ---
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// 1. Cabeçalho
$header = [];
foreach ($colunas_selecionadas_keys as $key) {
    $header[] = $colunas_disponiveis[$key];
}
$sheet->fromArray($header, null, 'A1');

// Adiciona um estilo para o cabeçalho e rodapé
const HEADER_STYLE = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '647ADB']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
];
$sheet->getStyle('A1:' . Coordinate::stringFromColumnIndex(count($header)) . '1')->applyFromArray(HEADER_STYLE);

// 3. MELHORIA DE PERFORMANCE: Processa linha por linha para economizar memória
$rowNum = 2;
// 4. NOVA FUNCIONALIDADE: Array para somar os totais por embalagem
$totais_por_embalagem = [];

while ($reg = $result->fetch_assoc()) {
    // Acumula o total por tipo de embalagem, se as colunas existirem no resultado
    if (isset($reg['quantidade']) && isset($reg['tipo_embalagem'])) {
        $embalagem = strtoupper($reg['tipo_embalagem'] ?? 'N/D');
        $quantidade = (int)$reg['quantidade'];
        if (!isset($totais_por_embalagem[$embalagem])) {
            $totais_por_embalagem[$embalagem] = 0;
        }
        $totais_por_embalagem[$embalagem] += $quantidade;
    }

    $colIndex = 0;
    $rowData = [];
    foreach ($colunas_selecionadas_keys as $key) {
        $value = $reg[$key] ?? '-';
        // Formatação básica dos dados para melhor leitura
        switch ($key) {
            case 'data_registro': $value = $reg[$key] ? date('d/m/Y H:i', strtotime($reg[$key])) : '-'; break;
            case 'data_ocorrencia': case 'validade': $value = $reg[$key] ? date('d/m/Y', strtotime($reg[$key])) : '-'; break;
            case 'tipo':
                $value = ucfirst(str_replace('_', ' ', $reg[$key] ?? ''));
                // Aplica estilo condicional para a coluna 'tipo'
                $cellCoordinate = Coordinate::stringFromColumnIndex($colIndex + 1) . $rowNum;
                $cellStyle = $sheet->getStyle($cellCoordinate);
                $cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $cellStyle->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
                
                if ($reg['tipo'] === 'avaria') {
                    $cellStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DC3545'); // Vermelho
                } elseif ($reg['tipo'] === 'uso_e_consumo') {
                    $cellStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('198754'); // Verde
                } elseif ($reg['tipo'] === 'recuperados') {
                    $cellStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFC107'); // Amarelo Warning
                    $cellStyle->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK));
                }
                break;
        }
        $rowData[] = $value;
        $colIndex++;
    }
    $sheet->fromArray($rowData, null, 'A' . $rowNum);
    $rowNum++;
}
$stmt->close();

// 4. FUNCIONALIDADE MELHORADA: Adiciona o rodapé com os totais por tipo de embalagem
$qty_col_index = array_search('quantidade', $colunas_selecionadas_keys);
if ($result->num_rows > 0 && $qty_col_index !== false && !empty($totais_por_embalagem)) {
    $total_start_row = $rowNum + 1; // Pula uma linha para a tabela de totais

    // Ordena os totais por nome da embalagem
    ksort($totais_por_embalagem);

    // Cabeçalho da tabela de totais
    $sheet->setCellValue("A{$total_start_row}", 'Resumo por Embalagem');
    $sheet->getStyle("A{$total_start_row}")->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0E0E0']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    $sheet->mergeCells("A{$total_start_row}:B{$total_start_row}");

    $total_row_num = $total_start_row + 1;
    foreach ($totais_por_embalagem as $embalagem => $total) {
        $sheet->setCellValue("A{$total_row_num}", $embalagem);
        $sheet->setCellValue("B{$total_row_num}", $total);
        $sheet->getStyle("A{$total_row_num}")->getFont()->setBold(true);
        $sheet->getStyle("B{$total_row_num}")->getFont()->setBold(true);
        $sheet->getStyle("B{$total_row_num}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $total_row_num++;
    }
}

// Ajusta a largura das colunas automaticamente para o conteúdo caber
foreach (range(1, count($header)) as $col) {
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
}
// Ajusta a largura das colunas da tabela de resumo também
$sheet->getColumnDimension('A')->setAutoSize(true);
$sheet->getColumnDimension('B')->setAutoSize(true);


// Define o nome da planilha
$sheet->setTitle('Histórico de Avarias');

// Define o nome do arquivo com base no filtro de tipo
$filename_prefix = 'historico'; // Padrão
if ($tipo_historico === 'avaria') {
    $filename_prefix = 'avarias';
} elseif ($tipo_historico === 'uso_e_consumo') {
    $filename_prefix = 'consumo';
} elseif ($tipo_historico === 'recuperados') {
    $filename_prefix = 'recuperados';
}
$filename = $filename_prefix . '_' . date('Y-m-d') . '.xlsx';

// Envia o arquivo para o navegador
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

// 5. CORREÇÃO DE LÓGICA: Fecha a conexão com o banco apenas no final do script
$conn->close();
exit();
