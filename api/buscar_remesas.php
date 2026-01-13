<?php
require_once '../session_check.php';
require_once '../config.php';

if (php_sapi_name() === 'cli') exit;

header('Content-Type: application/json');

$term = $_GET['term'] ?? '';
if (strlen($term) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT 
            r.id_rms,
            r.fecha_rms,
            r.ref_clte_rms,
            c.nombre_clt AS cliente_nombre,
            m.mercancia_mrcc AS mercancia_nombre
        FROM remesa r
        LEFT JOIN clientes c ON r.cliente_rms = c.id_clt
        LEFT JOIN mercancias m ON r.mercancia_rms = m.id_mrcc
        WHERE r.estado_rms IN ('Confeccion', 'solicitada', 'transferencia OK', 'Rendida')
        AND (
            c.nombre_clt LIKE ? OR
            r.ref_clte_rms LIKE ? OR
            m.mercancia_mrcc LIKE ?
        )
        ORDER BY r.fecha_rms DESC
        LIMIT 10
    ");
    $like = "%{$term}%";
    $stmt->execute([$like, $like, $like]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    error_log("API búsqueda error: " . $e->getMessage());
    echo json_encode([]);
}
?>