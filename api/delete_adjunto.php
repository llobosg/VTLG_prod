<?php
require_once '../config.php';
$pdo = getDBConnection();
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? 0;

if ($id) {
    // Opcional: eliminar archivo físico
    $stmt = $pdo->prepare("SELECT nombre_adj FROM adjuntos WHERE id_adj = ?");
    $stmt->execute([$id]);
    $archivo = $stmt->fetchColumn();
    if ($archivo) {
        $ruta = __DIR__ . "/../uploads/$archivo";
        if (file_exists($ruta)) unlink($ruta);
    }
    $pdo->prepare("DELETE FROM adjuntos WHERE id_adj = ?")->execute([$id]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
}
?>