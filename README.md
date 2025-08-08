# 🚚 Sistema de Gestão de Avarias

[![Status do Projeto](https://img.shields.io/badge/status-em%20desenvolvimento-yellowgreen.svg)](https://github.com/Estoquelogistica/gestao-de-avarias)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Tecnologia](https://img.shields.io/badge/stack-XAMPP-orange.svg)]()
[![Banco de Dados](https://img.shields.io/badge/banco-MySQL-blue.svg)]()

---

## 📝 Descrição

**Contexto:**  
O controle de avarias em produtos durante o manuseio e transporte é um ponto crítico na logística. A falta de um registro formal e centralizado resultava em perdas financeiras, dificuldade em identificar as causas-raiz dos problemas e falta de dados para a melhoria contínua dos processos.

**Ação:**  
Foi desenvolvido o "Sistema de Gestão de Avarias", uma aplicação web para registrar, acompanhar e gerar relatórios sobre todas as ocorrências de danos em produtos. O sistema permite o cadastro detalhado de cada avaria, incluindo fotos, descrição, quantidade e motivo.

**Resultado:**  
A solução implementou um processo padronizado para o tratamento de avarias. Com um banco de dados centralizado, a gestão agora tem visibilidade total sobre as ocorrências, podendo filtrar por data, produto ou setor. A capacidade de gerar relatórios em Excel e PDF fornece as ferramentas necessárias para análises gerenciais, ajudando a reduzir perdas e a aprimorar a qualidade operacional.

---

## 🔧 Funcionalidades Principais

✅ **Autenticação Segura:** Sistema de login com diferentes níveis de acesso para usuários.  
✅ **Dashboard Intuitivo:** Painel inicial com gráficos e indicadores chave sobre as avarias.  
✅ **Registro Detalhado de Avarias:** Formulário para cadastrar novas ocorrências com upload de fotos.  
✅ **Listagem e Filtragem:** Tabela completa com todas as avarias, com filtros avançados (data, status, produto).  
✅ **Geração de Relatórios:** Exportação dos dados da tabela para formatos **Excel (XLSX)** e **PDF**.  
✅ **Gerenciamento:** Módulos para cadastrar e editar usuários, produtos, setores e motivos de avaria.

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