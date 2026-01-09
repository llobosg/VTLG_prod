<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', '/tmp/php_errors.log');

    // Forzar sesiones en /tmp
    session_save_path('/tmp');

    // Iniciar sesión y verificar autenticación
    require_once 'session_check.php';

    // Obtener rol desde la sesión (ya cargado por session_check.php)
    $rol = $_SESSION['rol'] ?? 'usuario';

    // Redirigir a dashboard para todos los roles
    header('Location: /pages/dashboard.php');
    exit;
?>