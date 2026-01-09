<?php
require_once '../session_check.php';
require_once '../config.php';

if (php_sapi_name() === 'cli') {
    http_response_code(403);
    exit('Acceso denegado.');
}

header('Content-Type: application/json');
$pdo = getDBConnection();
$action = $_POST['action'] ?? '';

try {
    if ($action === 'crear_usuario') {
        $required = ['nombre_usuario', 'usuario', 'password', 'rol'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                echo json_encode(['success' => false, 'message' => 'Campo requerido: ' . $field]);
                exit;
            }
        }

        $hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (nombre_usuario, usuario, password, rol, activo)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['nombre_usuario'],
            $_POST['usuario'],
            $hashed,
            $_POST['rol'],
            $_POST['activo'] ?? '1'
        ]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'actualizar_usuario') {
        if (!isset($_POST['id_usuario'])) {
            echo json_encode(['success' => false, 'message' => 'ID requerido.']);
            exit;
        }

        $fields = ['nombre_usuario', 'usuario', 'rol', 'activo'];
        $updates = [];
        $params = [];
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $updates[] = "$field = ?";
                $params[] = $_POST[$field];
            }
        }

        if (!empty($updates) && isset($_POST['password']) && !empty($_POST['password'])) {
            $updates[] = "password = ?";
            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        if (empty($updates)) {
            echo json_encode(['success' => false, 'message' => 'No hay datos para actualizar.']);
            exit;
        }

        $params[] = $_POST['id_usuario'];
        $sql = "UPDATE usuarios SET " . implode(', ', $updates) . " WHERE id_usuario = ?";
        $pdo->prepare($sql)->execute($params);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'eliminar_usuario') {
        if (!isset($_POST['id_usuario'])) {
            echo json_encode(['success' => false, 'message' => 'ID requerido.']);
            exit;
        }

        $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ?")->execute([$_POST['id_usuario']]);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Acción no válida.']);

} catch (Exception $e) {
    error_log("Error en usuarios_logic: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno.']);
}
?>