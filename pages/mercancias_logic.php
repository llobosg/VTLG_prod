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
       CREAR MERCANCÍA (catálogo global)
    =============================== */
    if ($action === 'crear_mercancia_catalogo') {
        if (empty($_POST['mercancia_mrcc'])) {
            echo json_encode(['success' => false, 'message' => 'Nombre de mercancía requerido.']);
            exit;
        }

        // Verificar unicidad
        $stmt = $pdo->prepare("SELECT id_mrcc FROM mercancias WHERE mercancia_mrcc = ?");
        $stmt->execute([$_POST['mercancia_mrcc']]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Ya existe una mercancía con ese nombre.']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO mercancias (mercancia_mrcc) VALUES (?)");
        $stmt->execute([$_POST['mercancia_mrcc']]);
        echo json_encode(['success' => true, 'message' => 'Mercancía creada correctamente.']);
        exit;
    }

    /* ===============================
       OBTENER MERCANCÍA (para edición)
    =============================== */
    if ($action === 'obtener_mercancia_catalogo') {
        if (empty($_GET['id']) || !is_numeric($_GET['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }

        $id = (int)$_GET['id'];
        
        $stmt = $pdo->prepare("SELECT id_mrcc, mercancia_mrcc FROM mercancias WHERE id_mrcc = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data && isset($data['id_mrcc'])) {
            // Devolver exactamente los campos que espera el JavaScript
            echo json_encode([
                'id_mrcc' => (int)$data['id_mrcc'],
                'mercancia_mrcc' => $data['mercancia_mrcc']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Mercancía no encontrada.']);
        }
        exit;
    }

    /* ===============================
       ACTUALIZAR MERCANCÍA
    =============================== */
    if ($action === 'actualizar_mercancia_catalogo') {
        if (empty($_POST['id_mrcc']) || empty($_POST['mercancia_mrcc'])) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE mercancias SET mercancia_mrcc = ? WHERE id_mrcc = ?");
        $result = $stmt->execute([$_POST['mercancia_mrcc'], $_POST['id_mrcc']]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Mercancía actualizada correctamente.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar la mercancía.']);
        }
        exit;
    }

    /* ===============================
       ELIMINAR MERCANCÍA
    =============================== */
    if ($action === 'eliminar_mercancia_catalogo') {
        if (empty($_POST['id_mrcc'])) {
            echo json_encode(['success' => false, 'message' => 'ID requerido.']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM mercancias WHERE id_mrcc = ?");
        $result = $stmt->execute([$_POST['id_mrcc']]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Mercancía eliminada correctamente.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar la mercancía.']);
        }
        exit;
    }

    /* ===============================
       ACCIÓN NO VÁLIDA
    =============================== */
    echo json_encode(['success' => false, 'message' => 'Acción no válida.']);

} catch (Exception $e) {
    error_log("Error en mercancias_logic: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}
?>