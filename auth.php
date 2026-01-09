<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /login.php");
    exit;
}

$usuario = trim($_POST['usuario'] ?? '');
$password = $_POST['password'] ?? '';

if (!$usuario || !$password) {
    header("Location: /login.php?error=1");
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id_usr, nombre_usr, rol_usr, password_usr FROM usuarios WHERE nombre_usr = ? AND password_usr = ?");
    $stmt->execute([$usuario, $password]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user_id'] = $user['id_usr'];
        $_SESSION['nombre_usr'] = $user['nombre_usr'];
        $_SESSION['rol_usr'] = $user['rol_usr'];
        header("Location: /pages/dashboard.php");
        
    } else {
        header("Location: /login.php?error=1");
        exit;
    }
} catch (Exception $e) {
    error_log("Error en auth: " . $e->getMessage());
    header("Location: /login.php?error=1");
    exit;
}
?>