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

$action = $_POST['action'] ?? '';

try {
    // === CREAR REMESA ===
    if ($action === 'crear') {
        $required = ['cliente_rms', 'tipo_rms', 'fecha_rms'];
        foreach ($required as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                echo json_encode(['success' => false, 'message' => 'Campo requerido: ' . $field]);
                exit;
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO remesa (
                id_clt_rms, contacto_rms, ref_clte_rms, tipo_rms, 
                fecha_rms, mes_rms, estado_rms, total_transferir_rms
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['cliente_rms'],
            $_POST['contacto_rms'] ?? '',
            $_POST['ref_clte_rms'] ?? '',
            $_POST['tipo_rms'],
            $_POST['fecha_rms'],
            $_POST['mes_rms'] ?? '',
            $_POST['estado_rms'] ?? 'Solicitada',
            $_POST['total_transferir_rms'] ?? 0
        ]);

        $id_rms = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'id_rms' => (int)$id_rms]);
        exit;
    }

    // === ACTUALIZAR REMESA ===
    if ($action === 'actualizar') {
        if (!isset($_POST['id_rms'])) {
            echo json_encode(['success' => false, 'message' => 'ID de remesa requerido.']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE remesa SET
                id_clt_rms = ?,
                contacto_rms = ?,
                ref_clte_rms = ?,
                tipo_rms = ?,
                fecha_rms = ?,
                mes_rms = ?,
                estado_rms = ?
            WHERE id_rms = ?
        ");
        $stmt->execute([
            $_POST['cliente_rms'] ?? null,
            $_POST['contacto_rms'] ?? '',
            $_POST['ref_clte_rms'] ?? '',
            $_POST['tipo_rms'] ?? '',
            $_POST['fecha_rms'] ?? '',
            $_POST['mes_rms'] ?? '',
            $_POST['estado_rms'] ?? 'Solicitada',
            $_POST['id_rms']
        ]);

        echo json_encode(['success' => true]);
        exit;
    }

    // === ACCIÓN NO RECONOCIDA ===
    error_log("Acción no válida en remesa_logic: " . $action);
    echo json_encode(['success' => false, 'message' => 'Acción no válida.']);

} catch (Exception $e) {
    error_log("Error en remesa_logic: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
}
?>