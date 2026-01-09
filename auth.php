<?php
// auth.php (versión con PDO)
session_save_path('/tmp');
session_start();

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Método no permitido.');
}

$usuario = trim($_POST['usuario'] ?? '');
$clave = $_POST['clave'] ?? '';

if (empty($usuario) || empty($clave)) {
    $_SESSION['error_login'] = 'Usuario y contraseña son requeridos.';
    header('Location: /login.php');
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id_usuario, nombre_usuario, usuario, password, rol FROM usuarios WHERE usuario = ? AND activo = 1");
    $stmt->execute([$usuario]);
    $user = $stmt->fetch();

    if ($user && password_verify($clave, $user['password'])) {
        $_SESSION['user_id'] = (int)$user['id_usuario'];
        $_SESSION['user'] = $user['nombre_usuario'];
        $_SESSION['rol'] = $user['rol'];

        header('Location: /pages/dashboard.php');
        exit;
    } else {
        $_SESSION['error_login'] = 'Usuario o contraseña incorrectos.';
        header('Location: /login.php');
        exit;
    }
} catch (Exception $e) {
    error_log("Error en auth.php: " . $e->getMessage());
    $_SESSION['error_login'] = 'Error interno. Intente más tarde.';
    header('Location: /login.php');
    exit;
}
?>