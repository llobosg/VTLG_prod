<?php
if (php_sapi_name() === 'cli') {
    exit;
}

require_once '../session_check.php';
require_once '../config.php';

$id_cabecera = $_GET['id'] ?? null;
if (!$id_cabecera) {
    http_response_code(400);
    exit('ID de nota de cobranza requerido.');
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT 
            nc.*, r.ref_clte_rms, r.fecha_rms,
            c.nombre_clt, c.rut_clt
        FROM notacobranza nc
        LEFT JOIN remesa r ON nc.id_rms_nc = r.id_rms
        LEFT JOIN clientes c ON r.id_clt_rms = c.id_clt
        WHERE nc.id_cabecera = ?
    ");
    $stmt->execute([$id_cabecera]);
    $cabecera = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cabecera) {
        http_response_code(404);
        exit('Nota de cobranza no encontrada.');
    }

    // Cargar conceptos
    $stmt = $pdo->prepare("SELECT * FROM detalle_nc WHERE id_cabecera = ? ORDER BY item_detalle");
    $stmt->execute([$id_cabecera]);
    $conceptos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    require_once '../libs/tcpdf/tcpdf.php';

    class PDF extends TCPDF {
        public function Header() {
            $this->SetFont('helvetica', 'B', 12);
            $this->Cell(0, 10, 'NOTA DE COBRANZA - SIGA', 0, 1, 'C');
            $this->Ln(5);
        }
    }

    $pdf = new PDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10);

    // Cabecera
    $pdf->Cell(0, 6, 'Cliente: ' . $cabecera['nombre_clt'], 0, 1);
    $pdf->Cell(0, 6, 'Ref. Cliente: ' . $cabecera['ref_clte_rms'], 0, 1);
    $pdf->Cell(0, 6, 'Nro. Nota: ' . $cabecera['nro_nc'], 0, 1);
    $pdf->Cell(0, 6, 'Concepto: ' . $cabecera['concepto_nc'], 0, 1);
    $pdf->Ln(5);

    // Tabla de conceptos
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(20, 6, 'Ítem', 1);
    $pdf->Cell(60, 6, 'Proveedor', 1);
    $pdf->Cell(30, 6, 'Docto', 1);
    $pdf->Cell(25, 6, 'Neto', 1);
    $pdf->Cell(25, 6, 'Iva', 1);
    $pdf->Cell(25, 6, 'Total', 1);
    $pdf->Ln();

    $pdf->SetFont('helvetica', '', 9);
    foreach ($conceptos as $c) {
        $pdf->Cell(20, 6, $c['item_detalle'], 1);
        $pdf->Cell(60, 6, $c['proveedor_detalle'], 1);
        $pdf->Cell(30, 6, $c['nro_doc_detalle'], 1);
        $pdf->Cell(25, 6, number_format($c['montoneto_detalle'], 0, ',', '.'), 1);
        $pdf->Cell(25, 6, number_format($c['montoiva_detalle'], 0, ',', '.'), 1);
        $pdf->Cell(25, 6, number_format($c['monto_detalle'], 0, ',', '.'), 1);
        $pdf->Ln();
    }

    // Totales
    $pdf->Ln(3);
    $pdf->Cell(135, 6, 'TOTAL NOTA:', 0, 0, 'R');
    $pdf->Cell(25, 6, number_format($cabecera['total_monto_nc'], 0, ',', '.'), 0, 1, 'R');

    $pdf->Output('nota_cobranza_' . $id_cabecera . '.pdf', 'I');

} catch (Exception $e) {
    error_log("Error en generar_pdf_notacobranza: " . $e->getMessage());
    http_response_code(500);
    echo "Error al generar el PDF.";
}
?>