<?php
// 1. Carrega as dependências
require_once __DIR__ . '/lib/dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 2. Define o conteúdo HTML do procedimento
$html = <<<HTML
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Procedimento de Gestão de Avaria</title>
    <style>
        @page {
            margin: 4cm 2cm 3cm 2cm;
        }
        body {
            font-family: "Helvetica", sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
        }
        .header, .footer {
            position: fixed;
            left: 0;
            right: 0;
            color: #888;
            text-align: center;
        }
        .header {
            top: -3.5cm;
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
        }
        .footer {
            bottom: -2.5cm;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
        .footer .page-number:after {
            content: counter(page);
        }
        .logo {
            width: 150px;
            margin-bottom: 15px;
        }
        h1, h2, h3 {
            color: #254c90;
            font-family: "Helvetica", sans-serif;
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 10px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
        h1 { font-size: 22px; }
        h2 { font-size: 18px; }
        h3 { font-size: 14px; border-bottom: none; }
        ul, ol {
            padding-left: 20px;
        }
        li {
            margin-bottom: 8px;
        }
        strong {
            font-weight: bold;
        }
        code {
            background-color: #f0f0f0;
            padding: 2px 4px;
            border-radius: 3px;
            font-family: "Courier New", monospace;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .procedure-image {
            max-width: 100%;
            border: 1px solid #ccc;
            margin-top: 15px;
            margin-bottom: 15px;
        }
        .summary {
            border: 1px solid #ccc;
            padding: 15px;
            margin-bottom: 25px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        .summary h3 {
            margin-top: 0;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .summary ul {
            list-style-type: none;
            padding-left: 0;
        }
        .summary ul li {
            margin-bottom: 5px;
        }
        .summary a {
            text-decoration: none;
            color: #254c90;
        }
    </style>
</head>
<body>
    <div class="header">
        <!-- O cabeçalho fixo agora contém apenas a logo, que se repetirá em todas as páginas -->
        <img src="imagens/logo.png" alt="Logo" class="logo" style="width: 120px;">
    </div>

    <div class="footer">
        <span class="page-number">Página </span>
    </div>

    <main>
        <!-- Título e informações do documento, agora no corpo principal para aparecer apenas na primeira página -->
        <h1 style="text-align: center; border-bottom: none;">Procedimento de Gestão de Avaria</h1>
        <p style="text-align: center; font-size: 11px; margin-top: -10px; margin-bottom: 25px;">
            <strong>Código:</strong> CS-PRO-GA-01 | <strong>Versão:</strong> v1-08/2025 | <strong>Emissão:</strong> 12/08/2025
        </p>

        <div class="summary">
            <h3>Sumário</h3>
            <ul>
                <li><a href="#objetivo">1. Objetivo</a></li>
                <li><a href="#aplicacao">2. Campo de Aplicação</a></li>
                <li><a href="#referencias">3. Referências</a></li>
                <li><a href="#definicoes">4. Definições</a></li>
                <li><a href="#responsabilidades">5. Responsabilidades</a></li>
                <li><a href="#procedimento">6. Descrição do Procedimento</a></li>
                <li><a href="#registros">7. Registros</a></li>
                <li><a href="#anexos">8. Anexos - Caminhos de Acesso</a></li>
                <li><a href="#revisoes">9. Controle de Revisões</a></li>
            </ul>
        </div>

        <div style="page-break-after: always;"></div>

        <h2 id="objetivo">1. Objetivo</h2>
        <p>Estabelecer as diretrizes e os passos para a identificação, registro e tratamento de produtos avariados, garantindo o controle preciso do estoque, a minimização de perdas e a destinação correta dos itens danificados.</p>

        <h2 id="aplicacao">2. Campo de Aplicação</h2>
        <p>Este procedimento se aplica a todo o processo de gestão de avaria. O <strong>Auxiliar de Estoque</strong> é o principal responsável pela sua execução, enquanto o <strong>Administrador do Sistema</strong> é responsável pela gestão do catálogo de produtos.</p>

        <h2 id="referencias">3. Referências</h2>
        <p>Não aplicável.</p>

        <h2 id="definicoes">4. Definições</h2>
        <ul>
            <li><strong>Avaria:</strong> Produto registrado como perda.</li>
            <li><strong>Uso e Consumo:</strong> Itens destinados a uso interno da empresa.</li>
            <li><strong>Recuperados:</strong> Produtos que foram reparados ou reclassificados e retornaram ao estoque.</li>
        </ul>

        <h2 id="responsabilidades">5. Responsabilidades</h2>
        <h3>5.1. Auxiliar de Estoque:</h3>
        <ul>
            <li>Identificar a avaria e separar o produto danificado.</li>
            <li>Classificar os itens em <code>Avaria</code>, <code>Uso e Consumo</code> ou <code>Recuperados</code>.</li>
            <li>Registrar a ocorrência no sistema, preenchendo os detalhes do produto e o motivo.</li>
            <li>Consultar o histórico de registros e exportar relatórios, se necessário.</li>
        </ul>
        <h3>5.2. Administrador do Sistema:</h3>
        <ul>
            <li>Gerenciar o catálogo de produtos (adicionar, editar e excluir).</li>
            <li>Realizar a importação de novos produtos em massa via arquivo CSV.</li>
            <li>Monitorar todos os registros e relatórios para análise gerencial.</li>
        </ul>

        <h2 id="procedimento">6. Descrição do Procedimento</h2>
        <h3>6.1. Identificação e Classificação:</h3>
        <ol>
            <li>O Auxiliar de Estoque identifica um produto avariado, com embalagem danificada ou para uso interno.</li>
            <li>Separa e classifica o item em uma das seguintes categorias: <code>Avaria</code>, <code>Uso e Consumo</code> ou <code>Recuperados</code>.</li>
        </ol>
        <h3>6.2. Registro no Sistema:</h3>
        <ol>
            <li>O Auxiliar de Estoque acessa a tela <strong>"Registrar Avaria"</strong>.</li>
            <li>Busca o produto usando o código de barras, o código interno ou a busca detalhada (lupa).</li>
            <li>Preenche os detalhes da ocorrência: lote, quantidade, embalagem e validade.</li>
            <li>Seleciona o "Tipo de Registro" e o "Motivo" correspondente.</li>
            <li>Por fim, confirma a data da ocorrência e clica em <strong>"Registrar"</strong>.</li>
        </ol>
        <img src="imagens/registrar.png" alt="Tela de Registro" class="procedure-image">

        <h3>6.3. Análise e Monitoramento (Painel e Relatórios):</h3>
        <ul>
            <li>
                <strong>Painel:</strong> Utilizado para uma visão rápida e resumida da situação, com indicadores chave e filtros dinâmicos por período.
                <img src="imagens/dashboard.png" alt="Tela do Painel" class="procedure-image">
            </li>
            <li>
                <strong>Relatórios:</strong> Ferramenta de Business Intelligence para uma análise aprofundada, permitindo análises de Custo, Performance por Rua, Tendência de Produtos e Motivos.
                <img src="imagens/relatorio1.png" alt="Tela de Relatórios - Análise Geral" class="procedure-image">
                <img src="imagens/relatorio2.png" alt="Tela de Relatórios - Performance por Rua" class="procedure-image">
                <img src="imagens/relatorio3.png" alt="Tela de Relatórios - Análise de Custo" class="procedure-image">
                <img src="imagens/relatorio4.png" alt="Tela de Relatórios - Tendência por Produto" class="procedure-image">
            </li>
        </ul>
        <h3>6.4. Gestão do Catálogo de Produtos (Administrador):</h3>
        <ol>
            <li>O Administrador acessa a tela <strong>"Lista de Produtos"</strong> para gerenciar o catálogo.</li>
            <li><strong>Adição Manual:</strong> Clica em "Adicionar Produto" e preenche os dados no formulário.</li>
            <li><strong>Edição/Exclusão:</strong> Utiliza os ícones de ação na lista para editar ou remover um produto existente.</li>
            <li><strong>Importação em Massa (CSV):</strong>
                <ul>
                    <li>Clica em <strong>"Importar CSV"</strong>.</li>
                    <li>Seleciona o arquivo <code>.csv</code> (utilizando o modelo padrão, se necessário).</li>
                    <li>O sistema exibe uma pré-visualização dos dados.</li>
                    <li>Após a conferência, clica em <strong>"Confirmar e Importar"</strong> para criar ou atualizar os produtos.</li>
                </ul>
            </li>
        </ol>
        <img src="imagens/consulta.png" alt="Tela de Consulta de Produtos" class="procedure-image">

        <h2 id="registros">7. Registros</h2>
        <p>Os registros das avarias, uso e consumo e recuperados ficam armazenados na tela de <strong>"Histórico"</strong>. É possível utilizar os filtros de data, produto e tipo para localizar registros específicos. Antes de exportar, o usuário pode clicar em <strong>"Colunas"</strong> para selecionar quais informações deseja incluir no arquivo final em formato <strong>PDF</strong> ou <strong>XLSX</strong>.</p>
        <img src="imagens/historico.png" alt="Tela de Histórico" class="procedure-image">

        <h2 id="anexos">8. Anexos - Caminhos de Acesso</h2>
        <ul>
            <li><strong>Acesso ao Sistema de Gestão de Avaria:</strong>
                <ul>
                    <li>O acesso às telas é feito através da interface do sistema, cujo URL é <code>http://localhost/gestao-de-avarias/dashboard.php</code>.</li>
                </ul>
            </li>
        </ul>

        <h2 id="revisoes">9. Controle de Revisões</h2>
        <table>
            <thead>
                <tr>
                    <th>Versão/Revisão</th>
                    <th>Data da Revisão</th>
                    <th>Descrição da Alteração</th>
                    <th>Responsável pela Alteração</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>V1-08/2025</td>
                    <td>12/08/2025</td>
                    <td>Emissão inicial</td>
                    <td>Saulo Sampaio</td>
                </tr>
            </tbody>
        </table>
    </main>
</body>
</html>
HTML;

// 3. Instancia e usa o Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true); // Necessário para carregar a imagem do logo
// Define o diretório raiz do projeto para permitir o acesso a arquivos locais (imagens, CSS)
$options->set('chroot', __DIR__);
$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// 4. Envia o PDF para o navegador
$dompdf->stream("procedimento_gestao_avaria.pdf", ["Attachment" => false]);
exit();
