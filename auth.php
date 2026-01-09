<?php
// auth.php
session_save_path('/tmp'); // ✅ Obligatorio en Railway
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

$conn = getDBConnection();
if (!$conn) {
    error_log("Error: conexión nula en auth.php");
    $_SESSION['error_login'] = 'Error de conexión. Intente más tarde.';
    header('Location: /login.php');
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT id_usuario, nombre_usuario, usuario, password, rol FROM usuarios WHERE usuario = ? AND activo = 1");
if (!$stmt) {
    error_log("Error prepare: " . mysqli_error($conn));
    $_SESSION['error_login'] = 'Error interno.';
    header('Location: /login.php');
    exit;
}

mysqli_stmt_bind_param($stmt, "s", $usuario);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if ($user && password_verify($clave, $user['password'])) {
    // ✅ Iniciar sesión correctamente
    $_SESSION['user_id'] = (int)$user['id_usuario'];
    $_SESSION['user'] = $user['nombre_usuario'];
    $_SESSION['rol'] = $user['rol'];

    error_log("✅ Login OK para: " . $user['usuario'] . " | Session ID: " . session_id());
    header('Location: /pages/dashboard.php');
    exit;
} else {
    $_SESSION['error_login'] = 'Usuario o contraseña incorrectos.';
    header('Location: /login.php');
    exit;
}
?>