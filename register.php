<?php
require_once __DIR__ . '/config/db.php';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $login = trim($_POST['login']);
    $senha = $_POST['senha'];

    if (empty($nome) || empty($login) || empty($senha)) {
        $error = "Todos os campos são obrigatórios.";
    } else {
        // Verifica se o login já existe
        $stmt_check = $conn->prepare("SELECT id FROM usuarios WHERE login = ?");
        $stmt_check->bind_param("s", $login);
        $stmt_check->execute();
        $result = $stmt_check->get_result();

        if ($result->num_rows > 0) {
            $error = "Já existe uma conta com este nome de usuário (login).";
        } else {
            // Insere o novo usuário
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt_insert = $conn->prepare("INSERT INTO usuarios (nome, login, senha) VALUES (?, ?, ?)");
            $stmt_insert->bind_param("sss", $nome, $login, $senha_hash);

            if ($stmt_insert->execute()) {
                $success = "Conta criada com sucesso! Você já pode fazer o login.";
            } else {
                $error = "Erro ao criar a conta. Tente novamente.";
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <title>Criar Nova Conta</title>
    <link rel="shortcut icon" type="image/x-icon" href="img/favicon.ico">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: url('img/background.png') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            color: #254c90;
        }
        .register-container {
            text-align: center;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
            width: 370px;
            position: relative;
        }
        .register-container img { width: 200px; max-width: 90%; margin-bottom: 20px; }
        .register-container h2 { color: #0052a5; margin-bottom: 20px; font-size: 24px; }
        .register-container input { width: calc(100% - 20px); padding: 15px; margin: 12px 0; border: 1px solid #ccc; border-radius: 8px; font-size: 16px; box-sizing: border-box; transition: all 0.3s ease; }
        .register-container input:focus { border-color: #0052a5; outline: none; box-shadow: 0 0 5px rgba(0, 82, 165, 0.5); }
        .register-container button { width: 100%; padding: 12px; background: #0052a5; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 18px; font-weight: bold; transition: background 0.3s ease; }
        .register-container button:hover { background: #003d7a; }
        .register-container .login-link { margin-top: 16px; display: block; color: #0052a5; text-decoration: underline; font-size: 15px; }
        .error { color: red; margin-bottom: 10px; }
        .success { color: green; margin-bottom: 10px; }
        @media (max-width: 600px) {
            body { background-image: none; background: #254c90; }
            .register-container { width: 100%; max-width: 320px; padding: 16px; margin: 0 auto; }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <img src="img/logo.svg" alt="Logo">
        <h2>Criar Nova Conta</h2>
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        <form method="POST" action="register.php">
            <input type="text" name="nome" placeholder="Nome Completo" required>
            <input type="text" name="login" placeholder="Nome de Usuário (para login)" required>
            <input type="password" name="senha" placeholder="Senha" required>
            <button type="submit">Criar Conta</button>
        </form>
        <a href="login.php" class="login-link">Já tem conta? Entrar</a>
    </div>
</body>
</html>