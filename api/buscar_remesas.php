<?php
require_once '../config.php';
$pdo = getDBConnection();

$term = $_GET['term'] ?? '';
if (!$term) {
    echo json_encode([]);
    exit;
}

$term = "%$term%";

$stmt = $pdo->prepare("
    SELECT 
        r.id_rms,
        r.cliente_rms,
        r.mercancia_rms,
        r.ref_clte_rms,
        r.fecha_rms,
        r.estado_rms,
        c.nombre_clt AS cliente_nombre,
        m.mercancia_mrcc AS mercancia_nombre
    FROM remesa r
    LEFT JOIN clientes c ON r.cliente_rms = c.id_clt
    LEFT JOIN mercancias m ON r.mercancia_rms = m.id_mrcc
    WHERE r.estado_rms = 'solicitada'
      AND (
          c.nombre_clt LIKE ? OR
          m.mercancia_mrcc LIKE ? OR
          r.ref_clte_rms LIKE ? OR
          r.fecha_rms LIKE ? OR
          r.despacho_rms LIKE ?
      )
    ORDER BY r.fecha_rms DESC
    LIMIT 10
");
$stmt->execute([$term, $term, $term, $term, $term]);
$remesas = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($remesas);
?>