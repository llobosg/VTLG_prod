<?php
require_once '../config.php';
$pdo = getDBConnection();

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM rendicion WHERE id_rms = ? ORDER BY fecha_rndcn DESC");
$stmt->execute([$id]);
$rendiciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rendiciones);
?>