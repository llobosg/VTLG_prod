<?php
require_once '../config.php';
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT mercancia_mrcc FROM mercancias ORDER BY mercancia_mrcc");
echo json_encode(array_column($stmt->fetchAll(), 'mercancia_mrcc'));
?>