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
    if ($action === 'crear_cliente') {
        $required = ['nombre_clt', 'rut_clt'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                echo json_encode(['success' => false, 'message' => 'Campo requerido: ' . $field]);
                exit;
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO clientes (nombre_clt, rut_clt, direccion_clt, ciudad_clt, contacto_clt, email_clt)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['nombre_clt'],
            $_POST['rut_clt'],
            $_POST['direccion_clt'] ?? '',
            $_POST['ciudad_clt'] ?? '',
            $_POST['contacto_clt'] ?? '',
            $_POST['email_clt'] ?? ''
        ]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'actualizar_cliente') {
        if (!isset($_POST['id_clt'])) {
            echo json_encode(['success' => false, 'message' => 'ID requerido.']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE clientes SET
                nombre_clt = ?,
                rut_clt = ?,
                direccion_clt = ?,
                ciudad_clt = ?,
                contacto_clt = ?,
                email_clt = ?
            WHERE id_clt = ?
        ");
        $stmt->execute([
            $_POST['nombre_clt'] ?? '',
            $_POST['rut_clt'] ?? '',
            $_POST['direccion_clt'] ?? '',
            $_POST['ciudad_clt'] ?? '',
            $_POST['contacto_clt'] ?? '',
            $_POST['email_clt'] ?? '',
            $_POST['id_clt']
        ]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'eliminar_cliente') {
        if (!isset($_POST['id_clt'])) {
            echo json_encode(['success' => false, 'message' => 'ID requerido.']);
            exit;
        }

        $pdo->prepare("DELETE FROM clientes WHERE id_clt = ?")->execute([$_POST['id_clt']]);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Acción no válida.']);

} catch (Exception $e) {
    error_log("Error en clientes_logic: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno.']);
}
?>