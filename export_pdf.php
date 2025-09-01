<?php
// Aumenta o limite de memória para gerar PDFs grandes
ini_set('memory_limit', '512M');

// 1. Carrega as dependências
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 2. Protege a página
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo "Acesso negado.";
    exit();
}

// --- Definição das colunas disponíveis para exportação ---
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

// Pega as colunas selecionadas do GET ou usa um padrão
$colunas_selecionadas_keys = $_GET['columns'] ?? array_keys($colunas_disponiveis);
// Garante que apenas colunas válidas sejam usadas
$colunas_selecionadas_keys = array_intersect($colunas_selecionadas_keys, array_keys($colunas_disponiveis));

// 3. Busca os dados (mesma lógica dos outros exports)
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$data_inicial = isset($_GET['data_inicial']) && !empty($_GET['data_inicial']) ? $_GET['data_inicial'] : date('Y-m-d', strtotime('-3 days'));
$data_final = isset($_GET['data_final']) && !empty($_GET['data_final']) ? $_GET['data_final'] : date('Y-m-d');
$tipo_historico = isset($_GET['tipo_historico']) ? $_GET['tipo_historico'] : 'todos';

$where_conditions = ["a.data_ocorrencia BETWEEN ? AND ?"];
$params = [$data_inicial, $data_final];
$types = 'ss';

if (!empty($search_term)) {
    $search_like = "%{$search_term}%";
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

// A query busca todas as colunas possíveis, a filtragem das colunas a serem exibidas é feita no PHP.
$sql = "SELECT a.data_registro, a.data_ocorrencia, p.codigo_produto, p.referencia, a.produto_nome, a.lote, a.validade, a.quantidade, a.tipo_embalagem, a.motivo, a.tipo, u.nome as nome_usuario
        FROM avarias a
        LEFT JOIN usuarios u ON a.registrado_por_id = u.id
        LEFT JOIN produtos p ON a.produto_id = p.id
        {$where_sql} ORDER BY a.data_ocorrencia DESC, a.id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$registros = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Calcula o total de quantidades para o rodapé
$totais_por_embalagem = [];
foreach ($registros as $reg) {
    $embalagem = strtoupper($reg['tipo_embalagem'] ?? 'N/D');
    $quantidade = (int)$reg['quantidade'];
    if (!isset($totais_por_embalagem[$embalagem])) {
        $totais_por_embalagem[$embalagem] = 0;
    }
    $totais_por_embalagem[$embalagem] += $quantidade;
}
ksort($totais_por_embalagem); // Ordena por nome da embalagem

// 4. Monta o conteúdo HTML para o PDF
$html = '
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Histórico</title>
    <style>
        @page { margin: 25px; }
        body { font-family: "Helvetica", sans-serif; color: #333; }
        .logo { width: 120px; margin-bottom: 10px; float: left; }
        .header { text-align: center; margin-bottom: 20px; overflow: hidden; }
        .header h1 { margin: 0; font-size: 22px; color: #254c90; }
        .header p { margin: 5px 0; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        thead th {
            background-color: #647adb; /* Cor azul do cabeçalho da sua tabela */
            color: white;
            text-align: center;
        }
        tbody tr:nth-child(even) { background-color: #f2f2f2; }
        .badge {
            color: white;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 10px;
            text-align: center;
            display: inline-block;
            min-width: 70px;
        }
        .bg-danger { background-color: #dc3545; }
        .bg-success { background-color: #198754; }
        .bg-info { background-color: #0dcaf0; }
        .bg-warning { background-color: #ffc107; color: #000 !important; } /* Adicionado */
        .text-center { text-align: center; }
        .text-nowrap { white-space: nowrap; }
        tfoot td {
            background-color: #f8f9fa;
            font-weight: bold;
            border-top: 2px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="imagens/logo.png" alt="Logo" class="logo">
        <h1>Histórico de Avarias, Consumo e Recuperados</h1>
        <p>Período: ' . date('d/m/Y', strtotime($data_inicial)) . ' a ' . date('d/m/Y', strtotime($data_final)) . '</p>
    </div>
    <table>
        <thead>
            <tr>
';

foreach ($colunas_selecionadas_keys as $key) {
    $html .= '<th>' . htmlspecialchars($colunas_disponiveis[$key]) . '</th>';
}

$html .= '
            </tr>
        </thead>
        <tbody>';

if (count($registros) > 0) {
    foreach ($registros as $reg) {
        $html .= '<tr>';
        foreach ($colunas_selecionadas_keys as $key) {
            $value = $reg[$key] ?? '-';
            $class = '';

            // Formatação e estilo por coluna
            switch ($key) {
                case 'data_registro':
                    $value = $reg[$key] ? date('d/m/Y H:i', strtotime($reg[$key])) : '-';
                    $class = 'text-center text-nowrap';
                    break;
                case 'data_ocorrencia':
                case 'validade':
                    $value = $reg[$key] ? date('d/m/Y', strtotime($reg[$key])) : '-';
                    $class = 'text-center text-nowrap';
                    break;
                case 'tipo':
                    $tipo_classe = 'bg-secondary';
                    if ($reg['tipo'] === 'avaria') {
                        $tipo_classe = 'bg-danger';
                    } elseif ($reg['tipo'] === 'uso_e_consumo') {
                        $tipo_classe = 'bg-success';
                    } elseif ($reg['tipo'] === 'recuperados') {
                        $tipo_classe = 'bg-warning';
                    }
                    $tipo_texto = ucfirst(str_replace('_', ' ', $reg['tipo']));
                    $value = '<span class="badge ' . $tipo_classe . '">' . $tipo_texto . '</span>';
                    $class = 'text-center';
                    break;
                case 'codigo_produto':
                case 'lote':
                case 'quantidade':
                case 'tipo_embalagem':
                    $class = 'text-center';
                    break;
            }

            // Escapa o valor se não for HTML (como o badge)
            if (strpos($value, '<') === false) {
                $value = htmlspecialchars($value);
            }
            $html .= '<td class="' . $class . '">' . $value . '</td>';
        }
        $html .= '</tr>';
    }
} else {
    $html .= '<tr><td colspan="' . count($colunas_selecionadas_keys) . '" class="text-center">Nenhum registro encontrado para os filtros aplicados.</td></tr>';
}

$html .= '
        </tbody>';

// Adiciona o rodapé com o total apenas se a coluna 'quantidade' foi selecionada
$qty_col_index = array_search('quantidade', $colunas_selecionadas_keys);
if (count($registros) > 0 && $qty_col_index !== false && !empty($totais_por_embalagem)) {
    $total_string_parts = [];
    foreach ($totais_por_embalagem as $embalagem => $total) {
        $total_string_parts[] = "<strong>" . htmlspecialchars($total) . "</strong> " . htmlspecialchars($embalagem);
    }
    $total_string = implode(' &nbsp; | &nbsp; ', $total_string_parts);

    $html .= '
        <tfoot>
            <tr>
                <td colspan="' . count($colunas_selecionadas_keys) . '" style="text-align: right; padding-right: 10px;">
                    <strong>Totais:</strong> ' . $total_string . '
                </td>
            </tr>
        </tfoot>';
}

$html .= '</table></body></html>';

// 5. Instancia e usa o Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true); // Permite carregar imagens externas e locais
$options->set('chroot', __DIR__); // Define o diretório raiz para acesso a arquivos locais
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

// Define o nome do arquivo com base no filtro de tipo
$filename_prefix = 'historico'; // Padrão
if ($tipo_historico === 'avaria') {
    $filename_prefix = 'avarias';
} elseif ($tipo_historico === 'uso_e_consumo') {
    $filename_prefix = 'consumo';
} elseif ($tipo_historico === 'recuperados') {
    $filename_prefix = 'recuperados';
}
$filename = $filename_prefix . '_' . date('Y-m-d') . '.pdf';

$dompdf->stream($filename, ["Attachment" => false]);
exit();
