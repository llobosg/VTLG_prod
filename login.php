<?php
// login.php
session_save_path('/tmp');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si ya hay sesión, redirigir al dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: /pages/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login - SIGA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .login-container h2 {
            text-align: center;
            margin-bottom: 1.5rem;
            color: #2c3e50;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.4rem;
            font-weight: bold;
            color: #34495e;
        }
        .form-group input {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        .btn-login {
            width: 100%;
            padding: 0.7rem;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
        }
        .btn-login:hover {
            background: #2980b9;
        }
        .error {
            color: #e74c3c;
            background: #fdf2f2;
            padding: 0.7rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            border: 1px solid #fadbd8;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2><i class="fas fa-lock"></i> Iniciar Sesión</h2>

        <?php if (!empty($_SESSION['error_login'])): ?>
            <div class="error">
                <?= htmlspecialchars($_SESSION['error_login']) ?>
            </div>
            <?php unset($_SESSION['error_login']); ?>
        <?php endif; ?>

        <form method="POST" action="/auth.php">
            <div class="form-group">
                <label for="usuario">Usuario</label>
                <input type="text" id="usuario" name="usuario" required autofocus>
            </div>
            <div class="form-group">
                <label for="clave">Contraseña</label>
                <input type="password" id="clave" name="clave" required>
            </div>
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Entrar
            </button>
        </form>
    </div>
</body>
</html>