<?php
// 🔒 Protección: no ejecutar en CLI (Railway build)
if (php_sapi_name() === 'cli') {
    exit;
}

require_once '../session_check.php';
require_once '../config.php';

$id_rms = $_GET['id'] ?? null;
if (!$id_rms) {
    http_response_code(400);
    exit('ID de remesa requerido.');
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT 
            r.*, 
            c.nombre_clt, c.rut_clt, c.direccion_clt, c.ciudad_clt
        FROM remesa r
        LEFT JOIN clientes c ON r.id_clt_rms = c.id_clt
        WHERE r.id_rms = ?
    ");
    $stmt->execute([$id_rms]);
    $remesa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$remesa) {
        http_response_code(404);
        exit('Remesa no encontrada.');
    }

    // === Generar PDF ===
    require_once '../libs/tcpdf/tcpdf.php';

    class PDF extends TCPDF {
        public function Header() {
            // Logo y título
            $this->SetFont('helvetica', 'B', 12);
            $this->Cell(0, 10, 'REPORTE DE REMESA - SIGA', 0, 1, 'C');
            $this->Ln(5);
        }
    }

    $pdf = new PDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10);

    // Datos del cliente
    $pdf->Cell(0, 6, 'Cliente: ' . $remesa['nombre_clt'], 0, 1);
    $pdf->Cell(0, 6, 'RUT: ' . $remesa['rut_clt'], 0, 1);
    $pdf->Cell(0, 6, 'Dirección: ' . $remesa['direccion_clt'], 0, 1);
    $pdf->Cell(0, 6, 'Ciudad: ' . $remesa['ciudad_clt'], 0, 1);
    $pdf->Ln(5);

    // Datos de la remesa
    $pdf->Cell(0, 6, 'Tipo: ' . $remesa['tipo_rms'], 0, 1);
    $pdf->Cell(0, 6, 'Fecha: ' . $remesa['fecha_rms'], 0, 1);
    $pdf->Cell(0, 6, 'Estado: ' . $remesa['estado_rms'], 0, 1);
    $pdf->Cell(0, 6, 'Total Transferido: $' . number_format($remesa['total_transferir_rms'], 0, ',', '.'), 0, 1);

    $pdf->Output('remesa_' . $id_rms . '.pdf', 'I');

} catch (Exception $e) {
    error_log("Error en generar_pdf: " . $e->getMessage());
    http_response_code(500);
    echo "Error al generar el PDF.";
}
?>