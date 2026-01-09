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
    if ($action === 'crear_mercancia') {
        $required = ['id_rms', 'nombre_merc'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                echo json_encode(['success' => false, 'message' => 'Campo requerido: ' . $field]);
                exit;
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO mercancias (id_rms, nombre_merc, bultos_merc, peso_merc, volumen_merc)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['id_rms'],
            $_POST['nombre_merc'],
            $_POST['bultos_merc'] ?? 0,
            $_POST['peso_merc'] ?? 0,
            $_POST['volumen_merc'] ?? 0
        ]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'actualizar_mercancia') {
        if (!isset($_POST['id_merc'])) {
            echo json_encode(['success' => false, 'message' => 'ID requerido.']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE mercancias SET
                nombre_merc = ?,
                bultos_merc = ?,
                peso_merc = ?,
                volumen_merc = ?
            WHERE id_merc = ?
        ");
        $stmt->execute([
            $_POST['nombre_merc'] ?? '',
            $_POST['bultos_merc'] ?? 0,
            $_POST['peso_merc'] ?? 0,
            $_POST['volumen_merc'] ?? 0,
            $_POST['id_merc']
        ]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'eliminar_mercancia') {
        if (!isset($_POST['id_merc'])) {
            echo json_encode(['success' => false, 'message' => 'ID requerido.']);
            exit;
        }

        $pdo->prepare("DELETE FROM mercancias WHERE id_merc = ?")->execute([$_POST['id_merc']]);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Acción no válida.']);

} catch (Exception $e) {
    error_log("Error en mercancias_logic: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno.']);
}
?>