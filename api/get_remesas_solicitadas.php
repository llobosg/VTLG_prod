<?php
require_once '../config.php';
$pdo = getDBConnection();

$busqueda = $_GET['busqueda'] ?? '';
$mes = $_GET['mes'] ?? '';
$cliente = $_GET['cliente'] ?? '';
$mercancia = $_GET['mercancia'] ?? '';
$estado = $_GET['estado'] ?? '';

$where = "WHERE r.estado_rms = 'solicitada'";
$params = [];

if ($busqueda) {
    $where .= " AND (r.cliente_rms LIKE ? OR c.nombre_clt LIKE ? OR r.mercancia_rms LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}
if ($mes) {
    $where .= " AND r.mes_rms = ?";
    $params[] = $mes;
}
if ($cliente) {
    $where .= " AND c.id_clt = ?";
    $params[] = $cliente;
}
if ($mercancia) {
    $where .= " AND m.id_mrcc = ?";
    $params[] = $mercancia;
}
if ($estado) {
    $where .= " AND r.estado_rms = ?";
    $params[] = $estado;
}

$stmt = $pdo->prepare("
    SELECT 
        r.*,
        c.nombre_clt AS cliente_nombre,
        m.mercancia_mrcc AS mercancia_nombre
    FROM remesa r
    LEFT JOIN clientes c ON r.cliente_rms = c.id_clt
    LEFT JOIN mercancias m ON r.mercancia_rms = m.id_mrcc
    $where
    ORDER BY r.fecha_rms DESC
");
$stmt->execute($params);
$remesas = $stmt->fetchAll();

echo json_encode($remesas);
?>