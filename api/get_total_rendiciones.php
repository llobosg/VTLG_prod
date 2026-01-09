<?php
require_once '../config.php';
header('Content-Type: application/json');

$pdo = getDBConnection();
$id_rms = $_GET['id_rms'] ?? 0;

if (!$id_rms || !is_numeric($id_rms)) {
    echo json_encode(['total_rendicion' => 0]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN concepto_rndcn IS NOT NULL THEN monto_pago_rndcn ELSE 0 END), 0) AS total_cliente,
            COALESCE(SUM(CASE WHEN concepto_agencia_rndcn IS NOT NULL THEN monto_gastos_agencia_rndcn ELSE 0 END), 0) AS total_agencia
        FROM rendicion 
        WHERE id_rms = ?
    ");
    $stmt->execute([$id_rms]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    $totalCliente = (float)($data['total_cliente'] ?? 0);
    $totalAgencia = (float)($data['total_agencia'] ?? 0);
    $netoAgencia = $totalAgencia;
    $ivaAgencia = $netoAgencia * 0.19;
    $totalGastosAgencia = $netoAgencia + $ivaAgencia;
    $totalRendicion = $totalCliente + $totalGastosAgencia;

    echo json_encode(['total_rendicion' => $totalRendicion]);
} catch (Exception $e) {
    error_log("Error en get_total_rendiciones: " . $e->getMessage());
    echo json_encode(['total_rendicion' => 0]);
}
?>