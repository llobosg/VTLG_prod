<?php
require_once '../session_check.php';
require_once '../config.php';

// 🔒 Protección: no ejecutar en CLI (Railway build)
if (php_sapi_name() === 'cli') {
    http_response_code(403);
    exit('Acceso denegado.');
}

header('Content-Type: application/json');
$pdo = getDBConnection();

// ✅ Leer acción desde $_POST (FormData, no JSON)
$action = $_POST['action'] ?? '';

try {
    /* ===============================
   CREAR DETALLE DE NOTA COBRANZA
    =============================== */
    if ($action === 'crear_detalle') {
        $required = ['id_cabecera', 'item_detalle', 'proveedor_detalle', 'montoneto_detalle'];
        foreach ($required as $field) {
            if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                echo json_encode(['success' => false, 'message' => 'Campo requerido: ' . $field]);
                exit;
            }
        }

        $id_cabecera = (int)$_POST['id_cabecera'];
        $item = trim($_POST['item_detalle']);
        $proveedor = trim($_POST['proveedor_detalle']);
        $nro_doc = trim($_POST['nro_doc_detalle'] ?? '');
        $montoneto = (float)$_POST['montoneto_detalle'];

        // Calcular IVA y total
        $montoiva = round($montoneto * 0.19, 2);
        $monto = $montoneto + $montoiva;

        try {
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
            $stmt->execute([
                $id_cabecera,
                $item,
                $proveedor,
                $nro_doc,
                $montoneto,
                $montoiva,
                $monto
            ]);

            // Actualizar total_monto_nc en la cabecera
            $stmt = $pdo->prepare("
                UPDATE notacobranza 
                SET total_monto_nc = (
                    SELECT COALESCE(SUM(monto_detalle), 0) 
                    FROM detalle_nc 
                    WHERE id_cabecera = ?
                )
                WHERE id_cabecera = ?
            ");
            $stmt->execute([$id_cabecera, $id_cabecera]);

            echo json_encode(['success' => true, 'message' => 'Ítem agregado correctamente.']);
            exit;

        } catch (Exception $e) {
            error_log("Error en crear_detalle: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error al guardar el ítem.']);
            exit;
        }
    }

    // === CREAR DETALLE ===
    if ($action === 'crear_detalle') {
        if (!isset($_POST['id_cabecera'])) {
            echo json_encode(['success' => false, 'message' => 'ID de cabecera requerido.']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO detalle_nc (
                id_cabecera, item_detalle, proveedor_detalle, nro_doc_detalle,
                montoneto_detalle, montoiva_detalle, monto_detalle
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['id_cabecera'],
            $_POST['item_detalle'] ?? '',
            $_POST['proveedor_detalle'] ?? '',
            $_POST['nro_doc_detalle'] ?? '',
            $_POST['montoneto_detalle'] ?? 0,
            $_POST['montoiva_detalle'] ?? 0,
            $_POST['monto_detalle'] ?? 0
        ]);

        actualizarTotalesCabecera($_POST['id_cabecera'], $pdo);
        echo json_encode(['success' => true]);
        exit;
    }

    /* ===============================
   ACTUALIZAR DETALLE DE NOTA COBRANZA
    =============================== */
    if ($action === 'actualizar_detalle') {
        $required = ['id_detalle', 'item_detalle', 'proveedor_detalle', 'montoneto_detalle'];
        foreach ($required as $field) {
            if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
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

        try {
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
            $stmt = $pdo->prepare("
                SELECT id_cabecera FROM detalle_nc WHERE id_detalle = ?
            ");
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

            echo json_encode(['success' => true, 'message' => 'Ítem actualizado correctamente.']);
            exit;

        } catch (Exception $e) {
            error_log("Error en actualizar_detalle: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error al actualizar el ítem.']);
            exit;
        }
    }

    /* ===============================
    ELIMINAR DETALLE DE NOTA COBRANZA
    =============================== */
    if ($action === 'eliminar_detalle') {
        if (empty($_POST['id_detalle']) || !is_numeric($_POST['id_detalle'])) {
            echo json_encode(['success' => false, 'message' => 'ID de ítem inválido.']);
            exit;
        }

        $id_detalle = (int)$_POST['id_detalle'];

        try {
            // Eliminar el detalle
            $pdo->prepare("DELETE FROM detalle_nc WHERE id_detalle = ?")->execute([$id_detalle]);

            // Actualizar total en cabecera
            $stmt = $pdo->prepare("
                UPDATE notacobranza 
                SET total_monto_nc = (
                    SELECT COALESCE(SUM(monto_detalle), 0) 
                    FROM detalle_nc 
                    WHERE id_cabecera = (
                        SELECT id_cabecera FROM detalle_nc WHERE id_detalle = ?
                    )
                )
                WHERE id_cabecera = (
                    SELECT id_cabecera FROM detalle_nc WHERE id_detalle = ?
                )
            ");
            $stmt->execute([$id_detalle, $id_detalle]);

            echo json_encode(['success' => true, 'message' => 'Ítem eliminado correctamente.']);
            exit;

        } catch (Exception $e) {
            error_log("Error en eliminar_detalle: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error al eliminar el ítem.']);
            exit;
        }
    }

    // === ACTUALIZAR CONCEPTO CABECERA ===
    if ($action === 'actualizar_concepto_cabecera') {
        if (!isset($_POST['id_cabecera']) || !isset($_POST['concepto_nc'])) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
            exit;
        }

        $pdo->prepare("UPDATE notacobranza SET concepto_nc = ? WHERE id_cabecera = ?")
            ->execute([$_POST['concepto_nc'], $_POST['id_cabecera']]);
        echo json_encode(['success' => true]);
        exit;
    }

    // === ELIMINAR CABECERA COMPLETA ===
    if ($action === 'eliminar_cabecera') {
        if (!isset($_POST['id_cabecera'])) {
            echo json_encode(['success' => false, 'message' => 'ID de cabecera requerido.']);
            exit;
        }

        $pdo->prepare("DELETE FROM notacobranza WHERE id_cabecera = ?")->execute([$_POST['id_cabecera']]);
        echo json_encode(['success' => true]);
        exit;
    }

    /* ===============================
   OBTENER DETALLE PARA EDICIÓN
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

    // === ACCIÓN NO RECONOCIDA ===
    error_log("Acción no válida en notacobranza_logic: " . $action);
    echo json_encode(['success' => false, 'message' => 'Acción no válida.']);

} catch (Exception $e) {
    error_log("Error en notacobranza_logic: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
}

// === FUNCIÓN AUXILIAR ===
function actualizarTotalesCabecera($id_cabecera, $pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM detalle_nc WHERE id_cabecera = ?");
    $stmt->execute([$id_cabecera]);
    $conteo = $stmt->fetchColumn();

    if ($conteo == 0) {
        $pdo->prepare("UPDATE notacobranza SET 
            total_neto_nc = 0, 
            total_iva_nc = 0, 
            total_monto_nc = 0,
            nro_nc = NULL,
            concepto_nc = NULL
        WHERE id_cabecera = ?")->execute([$id_cabecera]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(montoneto_detalle), 0),
                COALESCE(SUM(montoiva_detalle), 0),
                COALESCE(SUM(monto_detalle), 0)
            FROM detalle_nc WHERE id_cabecera = ?
        ");
        $stmt->execute([$id_cabecera]);
        [$total_neto, $total_iva, $total_monto] = $stmt->fetch(PDO::FETCH_NUM);

        $pdo->prepare("UPDATE notacobranza SET 
            total_neto_nc = ?, total_iva_nc = ?, total_monto_nc = ?
        WHERE id_cabecera = ?")->execute([$total_neto, $total_iva, $total_monto]);
    }
}
?>