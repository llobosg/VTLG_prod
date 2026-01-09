<?php
require_once '../config.php';
$pdo = getDBConnection();

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM notacobranza WHERE id_rms_nc = ? ORDER BY id_nc DESC");
$stmt->execute([$id]);
$conceptos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($conceptos);
?>