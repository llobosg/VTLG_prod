<?php
session_start();
require_once __DIR__ . '/config.php';

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Método no permitido.');
}

// Obtener credenciales
$usuario = trim($_POST['usuario'] ?? '');
$clave = $_POST['clave'] ?? '';

// Validar campos
if (empty($usuario) || empty($clave)) {
    $_SESSION['error_login'] = 'Usuario y contraseña son requeridos.';
    header('Location: /login.php');
    exit;
}

// Conexión a la base de datos
$conn = getDBConnection();
if (!$conn) {
    error_log("Error crítico: getDBConnection() devolvió null en auth.php");
    $_SESSION['error_login'] = 'Error temporal. Intente más tarde.';
    header('Location: /login.php');
    exit;
}

// Preparar consulta
$stmt = mysqli_prepare($conn, "SELECT id_usuario, nombre_usuario, usuario, password, rol FROM usuarios WHERE usuario = ? AND activo = 1");
if (!$stmt) {
    error_log("MySQLi prepare error en auth.php: " . mysqli_error($conn));
    $_SESSION['error_login'] = 'Error interno. Contacte al administrador.';
    header('Location: /login.php');
    exit;
}

// Vincular parámetro
mysqli_stmt_bind_param($stmt, "s", $usuario);

// Ejecutar
if (!mysqli_stmt_execute($stmt)) {
    error_log("MySQLi execute error en auth.php: " . mysqli_stmt_error($stmt));
    $_SESSION['error_login'] = 'Error al procesar la solicitud.';
    header('Location: /login.php');
    exit;
}

// Obtener resultado
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Verificar credenciales
if ($user && password_verify($clave, $user['password'])) {
    // Iniciar sesión
    $_SESSION['user_id'] = (int)$user['id_usuario'];
    $_SESSION['user'] = $user['nombre_usuario'];
    $_SESSION['rol'] = $user['rol']; // ✅ Corregido: era $username

    // Redirigir según rol
    header('Location: /pages/dashboard.php');
    exit;
} else {
    // Credenciales inválidas
    $_SESSION['error_login'] = 'Usuario o contraseña incorrectos.';
    header('Location: /login.php');
    exit;
}
?>