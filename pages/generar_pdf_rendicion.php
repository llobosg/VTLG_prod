<?php
if (php_sapi_name() === 'cli') {
    exit;
}

require_once '../session_check.php';
require_once '../config.php';

$id_rendicion = $_GET['id'] ?? null;
if (!$id_rendicion) {
    http_response_code(400);
    exit('ID de rendición requerido.');
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT 
            r.*, rm.ref_clte_rms, rm.fecha_rms,
            c.nombre_clt, c.rut_clt
        FROM rendicion r
        LEFT JOIN remesa rm ON r.id_rms = rm.id_rms
        LEFT JOIN clientes c ON rm.id_clt_rms = c.id_clt
        WHERE r.id_rendicion = ?
    ");
    $stmt->execute([$id_rendicion]);
    $rendicion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rendicion) {
        http_response_code(404);
        exit('Rendición no encontrada.');
    }

    require_once '../libs/tcpdf/tcpdf.php';

    class PDF extends TCPDF {
        public function Header() {
            $this->SetFont('helvetica', 'B', 12);
            $this->Cell(0, 10, 'RENDICION DE GASTOS - SIGA', 0, 1, 'C');
            $this->Ln(5);
        }
    }

    $pdf = new PDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10);

    // Datos del cliente
    $pdf->Cell(0, 6, 'Cliente: ' . $rendicion['nombre_clt'], 0, 1);
    $pdf->Cell(0, 6, 'Ref. Cliente: ' . $rendicion['ref_clte_rms'], 0, 1);
    $pdf->Ln(5);

    // Tipo de rendición
    if ($rendicion['tipo_concepto'] === 'cliente') {
        $pdf->Cell(0, 6, 'Concepto Cliente: ' . $rendicion['concepto_rndcn'], 0, 1);
        $pdf->Cell(0, 6, 'Monto Cliente: $' . number_format($rendicion['monto_pago_rndcn'], 0, ',', '.'), 0, 1);
    } else {
        $pdf->Cell(0, 6, 'Concepto Agencia: ' . $rendicion['concepto_agencia_rndcn'], 0, 1);
        $monto_neto = $rendicion['monto_gastos_agencia_rndcn'];
        $iva = round($monto_neto * 0.19);
        $total = $monto_neto + $iva;
        $pdf->Cell(0, 6, 'Monto Neto Agencia: $' . number_format($monto_neto, 0, ',', '.'), 0, 1);
        $pdf->Cell(0, 6, 'IVA (19%): $' . number_format($iva, 0, ',', '.'), 0, 1);
        $pdf->Cell(0, 6, 'Total Agencia: $' . number_format($total, 0, ',', '.'), 0, 1);
    }

    $pdf->Output('rendicion_' . $id_rendicion . '.pdf', 'I');

} catch (Exception $e) {
    error_log("Error en generar_pdf_rendicion: " . $e->getMessage());
    http_response_code(500);
    echo "Error al generar el PDF.";
}
?>