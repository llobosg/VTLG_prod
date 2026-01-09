<?php
require_once '../config.php';
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT nombre_clt FROM clientes ORDER BY nombre_clt");
echo json_encode(array_column($stmt->fetchAll(), 'nombre_clt'));
?>