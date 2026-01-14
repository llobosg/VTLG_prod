<?php
/**
 * Verificación de sesión con almacenamiento en base de datos
 * Compatible con Railway (múltiples instancias)
 */

require_once 'config.php';

// Configuración segura de cookies
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);

// Inicializar sesión con base de datos
try {
    $pdo = getDBConnection();
    $handler = new DBSessionHandler($pdo);
    session_set_save_handler($handler, true);
    
    session_set_cookie_params([
        'lifetime' => 86400,   // 24 horas
        'path' => '/',
        'secure' => true,      // Solo HTTPS
        'httponly' => true,    // No accesible desde JS
        'samesite' => 'Strict'
    ]);
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
} catch (Exception $e) {
    error_log("Error al iniciar sesión: " . $e->getMessage());
    session_start(); // Fallback
}

// Validar contexto CLI (Railway build)
if (php_sapi_name() === 'cli') {
    return;
}

// No validar sesión en login.php
$current_page = basename($_SERVER['SCRIPT_NAME']);
if ($current_page === 'login.php') {
    return;
}

// Verificar sesión en páginas protegidas
if (!isset($_SESSION['user_id']) || !isset($_SESSION['rol'])) {
    session_destroy();
    header('Location: /login.php?error=' . urlencode('Sesión expirada. Por favor, inicie sesión nuevamente.'));
    exit;
}

// Validar tipos de datos
if (!is_numeric($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['admin', 'comercial', 'pricing', 'usuario'])) {
    session_destroy();
    header('Location: /login.php?error=' . urlencode('Datos de sesión inválidos.'));
    exit;
}

return;
?>