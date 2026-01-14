<?php
require_once '../config.php';

header('Content-Type: application/json');

$term = $_GET['term'] ?? '';
if (strlen($term) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT id_mrcc, mercancia_mrcc 
        FROM mercancias 
        WHERE mercancia_mrcc LIKE ? 
        ORDER BY mercancia_mrcc
        LIMIT 10
    ");
    $stmt->execute(["%{$term}%"]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    error_log("Error en get_mercancias_busqueda: " . $e->getMessage());
    echo json_encode([]);
}
?>