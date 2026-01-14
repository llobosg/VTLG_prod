<?php
require_once '../session_check.php';
require_once '../config.php';

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
       CREAR TRANSPORTE (catálogo global)
    =============================== */
    if ($action === 'crear_transporte_catalogo') {
        if (empty($_POST['transporte_trnsprt'])) {
            echo json_encode(['success' => false, 'message' => 'Nombre de transporte requerido.']);
            exit;
        }

        // Verificar unicidad
        $stmt = $pdo->prepare("SELECT id_trnsprt FROM transporte WHERE transporte_trnsprt = ?");
        $stmt->execute([$_POST['transporte_trnsprt']]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Ya existe un transporte con ese nombre.']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO transporte (
                transporte_trnsprt, 
                contacto_trnsprt, 
                fono_trnsprt, 
                direccion_trnsprt, 
                email_trnsprt
            ) VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['transporte_trnsprt'],
            $_POST['contacto_trnsprt'] ?? null,
            $_POST['fono_trnsprt'] ?? null,
            $_POST['direccion_trnsprt'] ?? null,
            $_POST['email_trnsprt'] ?? null
        ]);
        echo json_encode(['success' => true, 'message' => 'Transporte creado correctamente.']);
        exit;
    }

    /* ===============================
       OBTENER TRANSPORTE (para edición)
    =============================== */
    if ($action === 'obtener_transporte_catalogo') {
        if (empty($_GET['id']) || !is_numeric($_GET['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }

        $id = (int)$_GET['id'];
        
        $stmt = $pdo->prepare("
            SELECT 
                id_trnsprt, 
                transporte_trnsprt, 
                contacto_trnsprt, 
                fono_trnsprt, 
                direccion_trnsprt, 
                email_trnsprt 
            FROM transporte 
            WHERE id_trnsprt = ?
        ");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data && isset($data['id_trnsprt'])) {
            echo json_encode($data);
        } else {
            echo json_encode(['success' => false, 'message' => 'Transporte no encontrado.']);
        }
        exit;
    }

    /* ===============================
       ACTUALIZAR TRANSPORTE
    =============================== */
    if ($action === 'actualizar_transporte_catalogo') {
        if (empty($_POST['id_trnsprt']) || empty($_POST['transporte_trnsprt'])) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE transporte SET 
                transporte_trnsprt = ?,
                contacto_trnsprt = ?,
                fono_trnsprt = ?,
                direccion_trnsprt = ?,
                email_trnsprt = ?
            WHERE id_trnsprt = ?
        ");
        $result = $stmt->execute([
            $_POST['transporte_trnsprt'],
            $_POST['contacto_trnsprt'] ?? null,
            $_POST['fono_trnsprt'] ?? null,
            $_POST['direccion_trnsprt'] ?? null,
            $_POST['email_trnsprt'] ?? null,
            $_POST['id_trnsprt']
        ]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Transporte actualizado correctamente.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar el transporte.']);
        }
        exit;
    }

    /* ===============================
       ELIMINAR TRANSPORTE
    =============================== */
    if ($action === 'eliminar_transporte_catalogo') {
        if (empty($_POST['id_trnsprt'])) {
            echo json_encode(['success' => false, 'message' => 'ID requerido.']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM transporte WHERE id_trnsprt = ?");
        $result = $stmt->execute([$_POST['id_trnsprt']]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Transporte eliminado correctamente.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar el transporte.']);
        }
        exit;
    }

    /* ===============================
       ACCIÓN NO VÁLIDA
    =============================== */
    echo json_encode(['success' => false, 'message' => 'Acción no válida.']);

} catch (Exception $e) {
    error_log("Error en transporte_logic: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}
?>