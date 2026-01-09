<?php
require_once '../config.php';

header('Content-Type: application/json');

$pdo = getDBConnection();
$id = $_GET['id'] ?? 0;

if (!$id || !is_numeric($id)) {
    echo json_encode(null);
    exit;
}

try {
    // Consulta con JOINs y TODOS los campos necesarios
    $stmt = $pdo->prepare("
        SELECT 
            r.id_rms,
            r.id_clt_rms,
            r.cliente_rms,
            r.mercancia_rms,
            r.estado_rms,
            r.fecha_rms,
            r.mes_rms,
            r.despacho_rms,
            r.ref_clte_rms,
            r.total_transferir_rms,
            r.contacto_rms,
            
            -- Datos del cliente (para RUT, dirección, etc.)
            c.nombre_clt AS cliente_nombre,
            c.rut_clt,
            c.direccion_clt,
            c.ciudad_clt,
            
            -- Datos de la mercancía
            m.mercancia_mrcc AS mercancia_nombre
            
        FROM remesa r
        LEFT JOIN clientes c ON r.cliente_rms = c.id_clt
        LEFT JOIN mercancias m ON r.mercancia_rms = m.id_mrcc
        WHERE r.id_rms = ?
        LIMIT 1
    ");
    
    $stmt->execute([(int)$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($data) {
        // Asegurar que los campos numéricos sean float
        $data['total_transferir_rms'] = (float)($data['total_transferir_rms'] ?? 0);
    }
    
    echo json_encode($data ?: null);
    
} catch (Exception $e) {
    error_log("Error en get_remesa.php: " . $e->getMessage());
    echo json_encode(null);
}
?>