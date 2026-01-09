<?php
    // Iniciar sesión y verificar autenticación
    require_once 'session_check.php';

    // Obtener rol desde la sesión (ya cargado por session_check.php)
    $rol = $_SESSION['rol'] ?? 'usuario';

    // Redirigir a dashboard para todos los roles
    header('Location: /pages/dashboard.php');
    exit;
?>