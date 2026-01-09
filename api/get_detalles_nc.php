<?php
require_once '../config.php';
$pdo = getDBConnection();

$id_cabecera = $_GET['id_cabecera'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM detalle_nc WHERE id_cabecera = ? ORDER BY id_detalle");
$stmt->execute([$id_cabecera]);
$detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($detalles);
?>