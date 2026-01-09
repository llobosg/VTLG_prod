<?php
session_save_path('/tmp');
/**
 * Verificación segura de sesión.
 * Solo se ejecuta en contexto web real (no en CLI).
 */

// Salir inmediatamente si se ejecuta desde CLI (Railway build)
if (php_sapi_name() === 'cli') {
    return;
}

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación completa
$auth_ok = (
    isset($_SESSION['user_id']) && 
    isset($_SESSION['user']) && 
    isset($_SESSION['rol']) &&
    is_int($_SESSION['user_id']) && 
    $_SESSION['user_id'] > 0
);

if (!$auth_ok) {
    // Destruir sesión parcial
    session_destroy();
    if (!defined('SKIP_SESSION_REDIRECT')) {
        header('Location: /login.php');
        exit;
    }
}

// Validar rol
$roles_validos = ['admin', 'comercial', 'pricing', 'usuario'];
if (!in_array($_SESSION['rol'], $roles_validos, true)) {
    session_destroy();
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso denegado: rol no autorizado.');
}
?>