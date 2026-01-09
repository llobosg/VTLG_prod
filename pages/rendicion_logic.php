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
    if ($action === 'crear_rendicion') {
        $required = ['id_rms', 'tipo_concepto', 'item_rendicion', 'concepto_rendicion', 'monto_rendicion'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                echo json_encode(['success' => false, 'message' => 'Campo requerido: ' . $field]);
                exit;
            }
        }

        if ($_POST['tipo_concepto'] === 'cliente') {
            $stmt = $pdo->prepare("
                INSERT INTO rendicion (id_rms, tipo_concepto, item_rendicion, concepto_rndcn, monto_pago_rndcn, fecha_rendicion)
                VALUES (?, ?, ?, ?, ?, CURDATE())
            ");
            $stmt->execute([
                $_POST['id_rms'],
                'cliente',
                $_POST['item_rendicion'],
                $_POST['concepto_rendicion'],
                $_POST['monto_rendicion']
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO rendicion (id_rms, tipo_concepto, item_rendicion, concepto_agencia_rndcn, monto_gastos_agencia_rndcn, fecha_rendicion)
                VALUES (?, ?, ?, ?, ?, CURDATE())
            ");
            $stmt->execute([
                $_POST['id_rms'],
                'agencia',
                $_POST['item_rendicion'],
                $_POST['concepto_rendicion'],
                $_POST['monto_rendicion']
            ]);
        }

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'actualizar_rendicion') {
        // Implementar si es necesario
        echo json_encode(['success' => false, 'message' => 'Actualización no implementada.']);
        exit;
    }

    if ($action === 'eliminar_rendicion') {
        if (!isset($_POST['id_rendicion'])) {
            echo json_encode(['success' => false, 'message' => 'ID requerido.']);
            exit;
        }

        $pdo->prepare("DELETE FROM rendicion WHERE id_rendicion = ?")->execute([$_POST['id_rendicion']]);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Acción no válida.']);

} catch (Exception $e) {
    error_log("Error en rendicion_logic: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno.']);
}
?>