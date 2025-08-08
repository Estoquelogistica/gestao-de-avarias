<?php
// Inclui a configuração que já inicia a sessão
require_once __DIR__ . '/config/db.php';

// Destrói todas as variáveis de sessão
session_destroy();

// Redireciona para a página de login
header("Location: login.php");
exit();
