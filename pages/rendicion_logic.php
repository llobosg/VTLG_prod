<?php
require_once '../session_check.php';
require_once '../config.php';

if (php_sapi_name() === 'cli') {
    http_response_code(403);
    exit('Acceso denegado.');
}

header('Content-Type: application/json');

// Conectar solo aquí
$pdo = getDBConnection();

$action = $_POST['action'] ?? '';

try {
    if ($action === 'crear_rendicion') {
        $required = ['id_rms', 'tipo_concepto', 'concepto_rendicion', 'monto_rendicion', 'item_rndcn'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                echo json_encode(['success' => false, 'message' => 'Campo requerido: ' . $field]);
                exit;
            }
        }

        $id_rms = (int)$_POST['id_rms'];
        $tipo = $_POST['tipo_concepto'];
        $concepto = trim($_POST['concepto_rendicion']);
        $monto = (float)$_POST['monto_rendicion'];
        $item = (int)$_POST['item_rndcn'];

        if ($tipo === 'cliente') {
            $stmt = $pdo->prepare("
                INSERT INTO rendicion (id_rms, item_rndcn, concepto_rndcn, monto_pago_rndcn, fecha_rndcn, tipo_concepto)
                VALUES (?, ?, ?, ?, CURDATE(), 'cliente')
            ");
            $stmt->execute([$id_rms, $item, $concepto, $monto]);
        } elseif ($tipo === 'agencia') {
            $stmt = $pdo->prepare("
                INSERT INTO rendicion (id_rms, item_rndcn, concepto_agencia_rndcn, monto_gastos_agencia_rndcn, fecha_rndcn, tipo_concepto)
                VALUES (?, ?, ?, ?, CURDATE(), 'agencia')
            ");
            $stmt->execute([$id_rms, $item, $concepto, $monto]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Tipo inválido.']);
            exit;
        }

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'eliminar_rendicion') {
        if (empty($_POST['id_rndcn']) || !is_numeric($_POST['id_rndcn'])) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }
        $pdo->prepare("DELETE FROM rendicion WHERE id_rndcn = ?")->execute([(int)$_POST['id_rndcn']]);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Acción no válida.']);

} catch (Exception $e) {
    error_log("Error en rendicion_logic: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno.']);
}
?>