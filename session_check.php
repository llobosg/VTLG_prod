<?php
// session_check.php

// Salir en CLI (build de Railway)
if (php_sapi_name() === 'cli') {
    return;
}

// ✅ Guardar sesiones en /tmp (único lugar persistente en Railway)
session_save_path('/tmp');

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
    session_destroy();
    if (!defined('SKIP_SESSION_REDIRECT')) {
        header('Location: /login.php');
        exit;
    }
    return;
}

// Validar rol
$roles_validos = ['admin', 'comercial', 'pricing', 'usuario'];
if (!in_array($_SESSION['rol'], $roles_validos, true)) {
    session_destroy();
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso denegado: rol no autorizado.');
}
?>