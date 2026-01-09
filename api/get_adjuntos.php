<?php
require_once '../config.php';
$pdo = getDBConnection();
$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT id_adj, nombre_adj, fecha_adj FROM adjuntos WHERE id_rms = ? ORDER BY fecha_adj DESC");
$stmt->execute([$id]);
$adjuntos = [];
while ($row = $stmt->fetch()) {
    $adjuntos[] = [
        'id' => $row['id_adj'],
        'nombre' => $row['nombre_adj'],
        'fecha' => date('d/m/Y H:i', strtotime($row['fecha_adj']))
    ];
}
echo json_encode($adjuntos);
?>