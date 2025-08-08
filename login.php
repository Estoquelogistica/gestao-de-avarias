<?php
require_once __DIR__ . '/config/db.php';

$error_message = '';

// Processa o formulário apenas se o método for POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login = $_POST['login'];
    $senha = $_POST['senha'];

    if (empty($login) || empty($senha)) {
        $error_message = "Por favor, preencha o login e a senha.";
    } else {
        // Usar prepared statements para prevenir SQL Injection
        $stmt = $conn->prepare("SELECT id, nome, senha, nivel FROM usuarios WHERE login = ?");
        $stmt->bind_param("s", $login);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verificar a senha usando password_verify
            if (password_verify($senha, $user['senha'])) {
                // Senha correta, iniciar sessão
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario_nome'] = $user['nome'];
                $_SESSION['usuario_nivel'] = $user['nivel'];
                
                header("Location: dashboard.php");
                exit();
            } else {
                $error_message = "Login ou senha inválidos.";
            }
        } else {
            $error_message = "Login ou senha inválidos.";
        }
        $stmt->close();
    }
    // Fecha a conexão após o uso no POST
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gestão de Avarias</title>
    <link rel="shortcut icon" type="image/x-icon" href="img/favicon.ico"> <!-- Supondo que você tenha um favicon -->
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: url('img/background.png') no-repeat center center fixed; /* Você precisa ter essa imagem na pasta img/ */
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            color: #254c90;
        }
        .login-container {
            text-align: center;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
            width: 350px;
            position: relative;
        }
        .login-container img {
            width: 200px;
            max-width: 90%;
            margin-bottom: 20px;
        }
        .login-container h2 {
            color: #0052a5;
            margin-bottom: 20px;
            font-size: 24px;
        }
        .login-container input {
            width: calc(100% - 20px);
            padding: 15px;
            margin: 15px 0;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
            transition: all 0.3s ease;
        }
        .login-container input:focus {
            border-color: #0052a5;
            outline: none;
            box-shadow: 0 0 5px rgba(0, 82, 165, 0.5);
        }
        .login-container button {
            width: 100%;
            padding: 12px;
            background: #0052a5;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            transition: background 0.3s ease;
        }
        .login-container button:hover { background: #003d7a; }
        .login-container button:active { transform: scale(0.98); }
        .login-container .register-link { margin-top: 16px; display: block; color: #0052a5; text-decoration: underline; font-size: 15px; }
        .error { color: red; margin-bottom: 10px; }
        @media (max-width: 600px) {
            body { background-image: none; background: #254c90; }
            .login-container { width: 100%; max-width: 320px; padding: 16px; margin: 0 auto; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <img src="img/logo.svg" alt="Logo"> <!-- Certifique-se que seu logo está em img/logo.svg -->
        <h2>Entrar na Conta</h2>
        <?php if (!empty($error_message)): ?>
            <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <form action="login.php" method="post">
            <input type="text" name="login" placeholder="Nome de Usuário" required>
            <input type="password" name="senha" placeholder="Senha" required>
            <button type="submit">ENTRAR</button>
        </form>
        <a href="register.php" class="register-link">Criar nova conta</a>
    </div>
</body>
</html>
