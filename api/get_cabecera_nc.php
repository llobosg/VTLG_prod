<?php
require_once '../config.php';
header('Content-Type: application/json');

$pdo = getDBConnection();
$id = $_GET['id'] ?? 0;

if (!$id || !is_numeric($id)) {
    echo json_encode(null);
    exit;
}

$stmt = $pdo->prepare("SELECT nro_nc, concepto_nc, total_monto_nc FROM notacobranza WHERE id_cabecera = ?");
$stmt->execute([$id]);
echo json_encode($stmt->fetch(PDO::FETCH_ASSOC) ?: null);
?>