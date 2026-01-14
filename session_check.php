<?php
/**
 * Verificación de sesión para SIGA
 * Compatible con Railway (HTTPS, múltiples instancias)
 */

// Configuración segura de sesiones
ini_set('session.cookie_secure', 1);        // Solo enviar en HTTPS
ini_set('session.cookie_httponly', 1);      // No accesible desde JS
ini_set('session.cookie_samesite', 'Strict'); // Protección CSRF
ini_set('session.use_strict_mode', 1);      // Evitar session fixation
ini_set('session.use_only_cookies', 1);     // No usar parámetros URL

// Configurar cookie de sesión
session_set_cookie_params([
    'lifetime' => 86400,   // 24 horas
    'path' => '/',
    'domain' => '',        // Usar dominio actual
    'secure' => true,      // Solo HTTPS
    'httponly' => true,    // No accesible desde JavaScript
    'samesite' => 'Strict'
]);

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Función auxiliar para destruir sesión y redirigir
function destroySessionAndRedirect($message = '') {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = []; // Limpiar variables
        session_destroy(); // Destruir datos del servidor
    }
    
    // Limpiar cookies de sesión
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/', '', true, true);
    }
    
    // Redirigir a login
    $loginUrl = '/login.php';
    if ($message) {
        $loginUrl .= '?error=' . urlencode($message);
    }
    header('Location: ' . $loginUrl);
    exit;
}

// Validar contexto CLI (Railway build)
if (php_sapi_name() === 'cli') {
    return; // Permitir inclusión sin error en build
}

// Verificar que la sesión contenga los campos mínimos
$requiredFields = ['user_id', 'rol'];
foreach ($requiredFields as $field) {
    if (!isset($_SESSION[$field]) || empty($_SESSION[$field])) {
        destroySessionAndRedirect('Sesión inválida. Por favor, inicie sesión nuevamente.');
    }
}

// Verificar tipos de datos
if (!is_numeric($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['admin', 'comercial', 'pricing', 'usuario'])) {
    destroySessionAndRedirect('Datos de sesión corruptos.');
}

// Regenerar ID de sesión periódicamente (cada 30 minutos)
if (!isset($_SESSION['last_regenerated'])) {
    $_SESSION['last_regenerated'] = time();
}
if (time() - $_SESSION['last_regenerated'] > 1800) { // 30 minutos
    session_regenerate_id(true);
    $_SESSION['last_regenerated'] = time();
}

// Todo OK: sesión válida
return;
?>