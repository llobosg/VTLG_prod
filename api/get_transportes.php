<?php
require_once '../config.php';
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT transporte_trnsprt FROM transporte ORDER BY transporte_trnsprt");
echo json_encode(array_column($stmt->fetchAll(), 'transporte_trnsprt'));
?>