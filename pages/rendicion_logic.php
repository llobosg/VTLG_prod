<?php
require_once '../session_check.php';
require_once '../config.php';

// 🔒 Protección: no ejecutar en CLI (Railway build)
if (php_sapi_name() === 'cli') {
    http_response_code(403);
    exit('Acceso denegado.');
}

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();

    // === ACCIÓN: OBTENER UN CONCEPTO (para edición) ===
    if (isset($_GET['action']) && $_GET['action'] === 'obtener' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM rendicion WHERE id_rndcn = ?");
        $stmt->execute([(int)$_GET['id']]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data) {
            echo json_encode($data);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registro no encontrado.']);
        }
        exit;
    }

    // === LEER ACCIÓN DE $_POST ===
    $action = $_POST['action'] ?? '';

    // === CREAR RENDICIÓN ===
    if ($action === 'crear_rendicion') {
        $required = ['id_rms', 'tipo_concepto', 'concepto_rendicion', 'monto_rendicion'];
        foreach ($required as $field) {
            if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                echo json_encode(['success' => false, 'message' => 'Campo requerido: ' . $field]);
                exit;
            }
        }

        $id_rms = (int)$_POST['id_rms'];
        $tipo = $_POST['tipo_concepto'];
        $concepto = trim($_POST['concepto_rendicion']);
        $monto = (float)$_POST['monto_rendicion'];
        $nro_doc = trim($_POST['nro_documento_rndcn'] ?? '');
        $fecha = !empty($_POST['fecha_rndcn']) ? $_POST['fecha_rndcn'] : date('Y-m-d');

        if ($tipo === 'cliente') {
            $stmt = $pdo->prepare("
                INSERT INTO rendicion (
                    id_rms,
                    concepto_rndcn,
                    monto_pago_rndcn,
                    nro_documento_rndcn,
                    fecha_rndcn
                ) VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$id_rms, $concepto, $monto, $nro_doc, $fecha]);
        } elseif ($tipo === 'agencia') {
            $stmt = $pdo->prepare("
                INSERT INTO rendicion (
                    id_rms,
                    concepto_agencia_rndcn,
                    monto_gastos_agencia_rndcn,
                    nro_documento_rndcn,
                    fecha_rndcn
                ) VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$id_rms, $concepto, $monto, $nro_doc, $fecha]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Tipo de concepto inválido.']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Concepto creado correctamente.']);
        exit;
    }

    // === ACTUALIZAR RENDICIÓN ===
    if ($action === 'actualizar_rendicion') {
        if (empty($_POST['id_rndcn']) || !is_numeric($_POST['id_rndcn'])) {
            echo json_encode(['success' => false, 'message' => 'ID de concepto inválido.']);
            exit;
        }

        // Determinar tipo desde los campos existentes
        $stmt = $pdo->prepare("SELECT concepto_rndcn, concepto_agencia_rndcn FROM rendicion WHERE id_rndcn = ?");
        $stmt->execute([(int)$_POST['id_rndcn']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Concepto no encontrado.']);
            exit;
        }

        $tipo = $row['concepto_rndcn'] !== null ? 'cliente' : 'agencia';
        $id_rndcn = (int)$_POST['id_rndcn'];
        $concepto = trim($_POST['concepto_rendicion']);
        $monto = (float)$_POST['monto_rendicion'];
        $nro_doc = trim($_POST['nro_documento_rndcn'] ?? '');
        $fecha = !empty($_POST['fecha_rndcn']) ? $_POST['fecha_rndcn'] : date('Y-m-d');

        if ($tipo === 'cliente') {
            $stmt = $pdo->prepare("
                UPDATE rendicion SET
                    concepto_rndcn = ?,
                    monto_pago_rndcn = ?,
                    nro_documento_rndcn = ?,
                    fecha_rndcn = ?
                WHERE id_rndcn = ?
            ");
            $stmt->execute([$concepto, $monto, $nro_doc, $fecha, $id_rndcn]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE rendicion SET
                    concepto_agencia_rndcn = ?,
                    monto_gastos_agencia_rndcn = ?,
                    nro_documento_rndcn = ?,
                    fecha_rndcn = ?
                WHERE id_rndcn = ?
            ");
            $stmt->execute([$concepto, $monto, $nro_doc, $fecha, $id_rndcn]);
        }

        echo json_encode(['success' => true, 'message' => 'Concepto actualizado correctamente.']);
        exit;
    }

    // === ELIMINAR RENDICIÓN ===
    if ($action === 'eliminar_rendicion') {
        if (empty($_POST['id_rndcn']) || !is_numeric($_POST['id_rndcn'])) {
            echo json_encode(['success' => false, 'message' => 'ID de concepto inválido.']);
            exit;
        }

        $pdo->prepare("DELETE FROM rendicion WHERE id_rndcn = ?")->execute([(int)$_POST['id_rndcn']]);
        echo json_encode(['success' => true, 'message' => 'Concepto eliminado correctamente.']);
        exit;
    }

    // === ACCIÓN NO RECONOCIDA ===
    echo json_encode(['success' => false, 'message' => 'Acción no válida.']);

} catch (Throwable $e) {
    error_log("Error en rendicion_logic.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}
?>