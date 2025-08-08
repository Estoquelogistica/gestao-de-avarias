# 🚚 Sistema de Gestão de Avarias

[![Status do Projeto](https://img.shields.io/badge/status-em%20desenvolvimento-yellowgreen.svg)](https://github.com/Estoquelogistica/gestao-de-avarias)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Tecnologia](https://img.shields.io/badge/stack-XAMPP-orange.svg)]()
[![Banco de Dados](https://img.shields.io/badge/banco-MySQL-blue.svg)]()

---

## 📝 Descrição

**Contexto:**  
O controle de mercadorias avariadas **dentro do depósito** é um desafio operacional e financeiro. A falta de um registro formal para danos ocorridos durante a movimentação e armazenagem interna resultava em perdas de inventário, dificuldade em identificar os setores com maior incidência de problemas e falta de dados para a melhoria contínua.

**Ação:**  
Foi desenvolvido o "Sistema de Gestão de Avarias", uma aplicação web para **controlar todas as ocorrências de danos em mercadorias dentro do depósito**. O sistema permite o cadastro detalhado de cada avaria, incluindo fotos, descrição, quantidade e motivo, fornecendo rastreabilidade completa.

**Resultado:**  
A solução implementou um processo padronizado para o tratamento de avarias internas. Com um banco de dados centralizado, a gestão do depósito agora tem visibilidade total sobre as ocorrências, podendo filtrar por data, produto ou setor. A capacidade de gerar relatórios em Excel e PDF fornece as ferramentas necessárias para análises gerenciais, ajudando a reduzir perdas e a aprimorar a qualidade operacional do armazém.

---

## 🔧 Funcionalidades Principais

✅ **Autenticação Segura:** Sistema de login com diferentes níveis de acesso para usuários.
✅ **Dashboard Intuitivo:** Painel inicial com KPIs, gráficos de ocorrências e ranking de produtos mais avariados.
✅ **Registro Detalhado:** Formulário inteligente para registrar avarias e consumo, com busca de produtos e campos dinâmicos.
✅ **Gerenciamento de Produtos:** CRUD completo de produtos, incluindo importação em massa via CSV.
✅ **Histórico Completo:** Tabela de registros com filtros avançados (data, produto, tipo) e exportação para **Excel (XLSX)** e **PDF** com colunas selecionáveis.
✅ **Relatórios Avançados e Interativos:**
    -   Painel de relatórios com seletor de visualização para uma interface limpa e focada.
    -   **Análise Geral:** Gráficos de pizza para visualizar a proporção de avarias por motivo e tipo.
    -   **Performance por Rua:** Gráfico de barras que identifica os setores do depósito com maior volume de perdas.
    -   **Tendência por Produto:** Ferramenta de análise com busca de produto e gráfico de linha que mostra a evolução dos registros por dia, mês ou ano.

---

## 📁 Estrutura do Projeto

```
gestao-de-avarias/
├── config/               # Configuração da conexão com o banco de dados (db.php)
├── css/                  # Folhas de estilo (CSS)
├── img/                  # Recursos visuais (logo, background, ícones)
├── js/                   # Scripts JavaScript para interatividade
├── lib/                  # Bibliotecas manuais (dompdf para PDFs)
├── uploads/              # Pasta para armazenamento das fotos de avarias
├── vendor/               # Dependências do Composer (PhpSpreadsheet para Excel)
├── .gitignore            # Arquivos e pastas ignorados pelo Git
├── composer.json         # Declaração das dependências do Composer
├── login.php             # Tela de autenticação
├── dashboard.php         # Painel principal do sistema
├── registrar_avaria.php  # Formulário de registro
├── listar_avarias.php    # Tabela de visualização das avarias
└── README.md             # Esta documentação
```

---

## 🛠️ Como Executar (Ambiente Local)

1.  Instale o **XAMPP** (ou um ambiente similar com PHP e MySQL).
2.  Copie a pasta `gestao-de-avarias/` para o diretório `C:/xampp/htdocs/`.
3.  Inicie os módulos **Apache** e **MySQL** no painel de controle do XAMPP.
4.  Crie um banco de dados no **phpMyAdmin** (ex: `gestao_avarias`).
5.  Importe o arquivo `.sql` com a estrutura das tabelas para o banco de dados criado.
6.  Configure a conexão com o banco no arquivo `config/db.php`.
7.  Abra um terminal na pasta do projeto (`C:/xampp/htdocs/gestao-de-avarias`) e execute `composer install` para baixar as dependências.
8.  Acesse no seu navegador:
    ```
    http://localhost/gestao-de-avarias/login.php
    ```

---

## 🔐 Usuários e Permissões

- **Autenticação:** Os usuários são validados contra a tabela `usuarios` no banco de dados.
- **Segurança:** As senhas devem ser armazenadas de forma segura usando `password_hash()` e verificadas com `password_verify()`.
- **Sessão:** Após o login, os dados do usuário (ID, nome, nível) são guardados na sessão PHP para controlar o acesso às funcionalidades.

---

## 📸 Capturas de Tela (Exemplos)

*A seguir, adicione as capturas de tela reais do seu projeto. Substitua os links de exemplo.*

### 1. 🔐 Tela de Login
*Interface de entrada do sistema.*
`!Tela de Login`

### 2. 📊 Dashboard
*Painel com os principais indicadores de avarias.*
`!Dashboard`

### 3. 📝 Formulário de Registro
*Tela para cadastrar uma nova avaria com todos os detalhes.*
`!Formulário de Registro`

### 4. 📜 Listagem de Avarias
*Tabela com todas as ocorrências, filtros e opções de exportação.*
`!Listagem de Avarias`

---

## 👨‍💻 Autor

**Saulo Sampaio**  
Sistema desenvolvido para otimizar a gestão de ativos logísticos.

---

## 📄 Licença

Projeto de uso interno.  
Livre para adaptar conforme a necessidade da empresa.