<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Cargar datos del usuario si no están en sesión
if (!isset($_SESSION['user']) || !isset($_SESSION['rol'])) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT nombre_usr, rol_usr FROM usuarios WHERE id_usr = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['user'] = $user['nombre_usr'];
        $_SESSION['rol'] = $user['rol_usr'];
    } else {
        session_destroy();
        header('Location: /login.php');
        exit;
    }
}
?>