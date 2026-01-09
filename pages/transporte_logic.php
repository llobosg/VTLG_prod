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
    if ($action === 'crear_transporte') {
        $required = ['id_vehiculo_transporte', 'id_chofer_transporte', 'tipo_transporte'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                echo json_encode(['success' => false, 'message' => 'Campo requerido: ' . $field]);
                exit;
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO transporte (id_vehiculo_transporte, id_chofer_transporte, tipo_transporte)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $_POST['id_vehiculo_transporte'],
            $_POST['id_chofer_transporte'],
            $_POST['tipo_transporte']
        ]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'actualizar_transporte') {
        if (!isset($_POST['id_transporte'])) {
            echo json_encode(['success' => false, 'message' => 'ID requerido.']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE transporte SET
                id_vehiculo_transporte = ?,
                id_chofer_transporte = ?,
                tipo_transporte = ?
            WHERE id_transporte = ?
        ");
        $stmt->execute([
            $_POST['id_vehiculo_transporte'] ?? null,
            $_POST['id_chofer_transporte'] ?? null,
            $_POST['tipo_transporte'] ?? '',
            $_POST['id_transporte']
        ]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'eliminar_transporte') {
        if (!isset($_POST['id_transporte'])) {
            echo json_encode(['success' => false, 'message' => 'ID requerido.']);
            exit;
        }

        $pdo->prepare("DELETE FROM transporte WHERE id_transporte = ?")->execute([$_POST['id_transporte']]);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Acción no válida.']);

} catch (Exception $e) {
    error_log("Error en transporte_logic: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno.']);
}
?>