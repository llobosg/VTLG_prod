<?php
// Iniciar sesión al inicio
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si ya hay sesión válida, redirigir al dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['rol'])) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'config.php';
    
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($usuario && $password) {
        try {
            $pdo = getDBConnection();
            // Ajusta los nombres de columna según tu tabla 'usuarios'
            $stmt = $pdo->prepare("SELECT id_usr, nombre_usr, rol_usr, password_usr FROM usuarios WHERE nombre_usr = ?");
            $stmt->execute([$usuario]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_usr'])) {
                // Establecer sesión
                $_SESSION['user_id'] = $user['id_usr'];
                $_SESSION['rol'] = $user['rol_usr'];
                $_SESSION['username'] = $user['nombre_usr'];
                
                // Regenerar ID de sesión
                session_regenerate_id(true);
                
                // Redirigir al dashboard
                header('Location: /dashboard.php');
                exit;
            } else {
                $error = 'Usuario o contraseña incorrectos.';
            }
        } catch (Exception $e) {
            error_log("Error en login: " . $e->getMessage());
            $error = 'Error interno. Intente más tarde.';
        }
    } else {
        $error = 'Complete todos los campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login - SIGA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <link rel="stylesheet" href="/styles.css">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 2.5rem;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            text-align: center;
            animation: fadeIn 0.6s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-logo {
            width: 80px;
            margin-bottom: 1rem;
            border-radius: 12px;
        }
        h2 {
            margin: 0 0 1.5rem 0;
            color: #3a4f63;
            font-size: 1.5rem;
        }
        .login-container input,
        .login-container button {
            width: 100%;
            padding: 0.9rem;
            margin: 0.6rem 0;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .login-container button {
            background: #0066cc;
            color: white;
            border: none;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }
        .login-container button:hover {
            background: #0055aa;
        }
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 0.7rem;
            border-radius: 6px;
            margin: 1rem 0;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
<div class="login-container">
    <img src="/includes/logo.png" alt="Logo VTLG" class="login-logo" onerror="this.style.display='none'">
    <h2><i class="fas fa-lock"></i> Acceso al Sistema</h2>
    <?php 
    // Mostrar error desde POST o GET
    $mensaje_error = '';
    if (!empty($_POST['error'])) {
        $mensaje_error = $_POST['error'];
    } elseif (!empty($_GET['error'])) {
        $mensaje_error = $_GET['error'];
    } elseif (!empty($error)) {
        $mensaje_error = $error;
    }
    ?>

    <?php if ($mensaje_error): ?>
        <div class="error"><?= htmlspecialchars($mensaje_error) ?></div>
    <?php endif; ?>
    <form method="POST">
        <input type="text" name="usuario" placeholder="Nombre de usuario" required value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>" />
        <input type="password" name="password" placeholder="Contraseña" required />
        <button type="submit">Ingresar</button>
    </form>
</div>
</body>
</html>