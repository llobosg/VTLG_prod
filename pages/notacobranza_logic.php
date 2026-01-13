<?php
require_once '../session_check.php';
require_once '../config.php';

// Protección contra ejecución en CLI (Railway build)
if (php_sapi_name() === 'cli') {
    http_response_code(403);
    exit('Acceso denegado.');
}

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();

    // Leer acción desde POST o GET
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    /* ===============================
       CREAR CABECERA DE NOTA COBRANZA
    =============================== */
    if ($action === 'crear_cabecera') {
        $required = ['id_rms', 'nro_nc', 'fecha_vence_nc', 'concepto_nc'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                echo json_encode(['success' => false, 'message' => 'Campo requerido: ' . $field]);
                exit;
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO notacobranza (
                id_rms_nc,
                nro_nc,
                fecha_vence_nc,
                concepto_nc,
                total_monto_nc
            ) VALUES (?, ?, ?, ?, 0)
        ");
        $stmt->execute([
            (int)$_POST['id_rms'],
            $_POST['nro_nc'],
            $_POST['fecha_vence_nc'],
            $_POST['concepto_nc']
        ]);

        echo json_encode([
            'success' => true,
            'id_cabecera' => $pdo->lastInsertId(),
            'message' => 'Cabecera de nota de cobranza creada.'
        ]);
        exit;
    }

    /* ===============================
       OBTENER DETALLE PARA EDICIÓN (GET)
    =============================== */
    if ($action === 'obtener_detalle') {
        if (empty($_GET['id']) || !is_numeric($_GET['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM detalle_nc WHERE id_detalle = ?");
        $stmt->execute([(int)$_GET['id']]);
        $detalle = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($detalle) {
            echo json_encode($detalle);
        } else {
            echo json_encode(['success' => false, 'message' => 'Ítem no encontrado.']);
        }
        exit;
    }

    /* ===============================
       CREAR DETALLE
    =============================== */
    if ($action === 'crear_detalle') {
        $required = ['id_cabecera', 'item_detalle', 'proveedor_detalle', 'montoneto_detalle'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                echo json_encode(['success' => false, 'message' => 'Campo requerido: ' . $field]);
                exit;
            }
        }

        $id_cabecera = (int)$_POST['id_cabecera'];
        $item = trim($_POST['item_detalle']);
        $proveedor = trim($_POST['proveedor_detalle']);
        $nro_doc = trim($_POST['nro_doc_detalle'] ?? '');
        $montoneto = (float)$_POST['montoneto_detalle'];

        $montoiva = round($montoneto * 0.19, 2);
        $monto = $montoneto + $montoiva;

        $stmt = $pdo->prepare("
            INSERT INTO detalle_nc (
                id_cabecera,
                item_detalle,
                proveedor_detalle,
                nro_doc_detalle,
                montoneto_detalle,
                montoiva_detalle,
                monto_detalle
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$id_cabecera, $item, $proveedor, $nro_doc, $montoneto, $montoiva, $monto]);

        // Actualizar total en cabecera
        $pdo->prepare("
            UPDATE notacobranza 
            SET total_monto_nc = (
                SELECT COALESCE(SUM(monto_detalle), 0) 
                FROM detalle_nc 
                WHERE id_cabecera = ?
            )
            WHERE id_cabecera = ?
        ")->execute([$id_cabecera, $id_cabecera]);

        echo json_encode(['success' => true, 'message' => 'Ítem agregado correctamente.']);
        exit;
    }

    /* ===============================
       ACTUALIZAR DETALLE
    =============================== */
    if ($action === 'actualizar_detalle') {
        $required = ['id_detalle', 'item_detalle', 'proveedor_detalle', 'montoneto_detalle'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                echo json_encode(['success' => false, 'message' => 'Campo requerido: ' . $field]);
                exit;
            }
        }

        $id_detalle = (int)$_POST['id_detalle'];
        $item = trim($_POST['item_detalle']);
        $proveedor = trim($_POST['proveedor_detalle']);
        $nro_doc = trim($_POST['nro_doc_detalle'] ?? '');
        $montoneto = (float)$_POST['montoneto_detalle'];

        $montoiva = round($montoneto * 0.19, 2);
        $monto = $montoneto + $montoiva;

        $stmt = $pdo->prepare("
            UPDATE detalle_nc SET
                item_detalle = ?,
                proveedor_detalle = ?,
                nro_doc_detalle = ?,
                montoneto_detalle = ?,
                montoiva_detalle = ?,
                monto_detalle = ?
            WHERE id_detalle = ?
        ");
        $stmt->execute([$item, $proveedor, $nro_doc, $montoneto, $montoiva, $monto, $id_detalle]);

        // Actualizar total en cabecera
        $stmt = $pdo->prepare("SELECT id_cabecera FROM detalle_nc WHERE id_detalle = ?");
        $stmt->execute([$id_detalle]);
        $id_cabecera = $stmt->fetchColumn();

        if ($id_cabecera) {
            $pdo->prepare("
                UPDATE notacobranza 
                SET total_monto_nc = (
                    SELECT COALESCE(SUM(monto_detalle), 0) 
                    FROM detalle_nc 
                    WHERE id_cabecera = ?
                )
                WHERE id_cabecera = ?
            ")->execute([$id_cabecera, $id_cabecera]);
        }

        mostrarNotificacion('✅ Ítem agregado.', 'success');
        exit;
    }

    /* ===============================
       ELIMINAR DETALLE
    =============================== */
    if ($action === 'eliminar_detalle') {
        if (empty($_POST['id_detalle']) || !is_numeric($_POST['id_detalle'])) {
            echo json_encode(['success' => false, 'message' => 'ID de ítem inválido.']);
            exit;
        }

        $id_detalle = (int)$_POST['id_detalle'];

        // Obtener id_cabecera antes de eliminar
        $stmt = $pdo->prepare("SELECT id_cabecera FROM detalle_nc WHERE id_detalle = ?");
        $stmt->execute([$id_detalle]);
        $id_cabecera = $stmt->fetchColumn();

        // Eliminar detalle
        $pdo->prepare("DELETE FROM detalle_nc WHERE id_detalle = ?")->execute([$id_detalle]);

        // Actualizar total en cabecera
        if ($id_cabecera) {
            $pdo->prepare("
                UPDATE notacobranza 
                SET total_monto_nc = (
                    SELECT COALESCE(SUM(monto_detalle), 0) 
                    FROM detalle_nc 
                    WHERE id_cabecera = ?
                )
                WHERE id_cabecera = ?
            ")->execute([$id_cabecera, $id_cabecera]);
        }

        echo json_encode(['success' => true, 'message' => 'Ítem eliminado correctamente.']);
        exit;
    }

    /* ===============================
       ACCIÓN NO VÁLIDA
    =============================== */
    echo json_encode(['success' => false, 'message' => 'Acción no válida.']);

} catch (Throwable $e) {
    error_log("Error en notacobranza_logic: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}

/* ===============================
   ELIMINAR NOTA DE COBRANZA COMPLETA (cabecera + detalles)
=============================== */
if ($action === 'eliminar_nota_cobranza') {
    if (empty($_POST['id_cabecera']) || !is_numeric($_POST['id_cabecera'])) {
        echo json_encode(['success' => false, 'message' => 'ID de nota inválido.']);
        exit;
    }

    $id_cabecera = (int)$_POST['id_cabecera'];

    try {
        // Iniciar transacción
        $pdo->beginTransaction();

        // Verificar si existen detalles
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM detalle_nc WHERE id_cabecera = ?");
        $stmt->execute([$id_cabecera]);
        $tiene_detalles = $stmt->fetchColumn() > 0;

        // Eliminar detalles primero
        $pdo->prepare("DELETE FROM detalle_nc WHERE id_cabecera = ?")->execute([$id_cabecera]);

        // Eliminar cabecera
        $pdo->prepare("DELETE FROM notacobranza WHERE id_cabecera = ?")->execute([$id_cabecera]);

        // Confirmar transacción
        $pdo->commit();

        $mensaje = $tiene_detalles 
            ? '✅ Nota de cobranza y sus ítems eliminados.' 
            : '✅ Nota de cobranza eliminada.';

        echo json_encode(['success' => true, 'message' => $mensaje]);
        exit;

    } catch (Exception $e) {
        $pdo->rollback();
        error_log("Error al eliminar nota de cobranza: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al eliminar la nota de cobranza.']);
        exit;
    }
}
?>