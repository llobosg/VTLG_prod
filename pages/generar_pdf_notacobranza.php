<?php
require_once '../config.php';
require_once '../vendor/autoload.php'; // TCPDF

use TCPDF;

// Validar parámetro
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    exit('ID de nota de cobranza inválido.');
}

$id_cabecera = (int)$_GET['id'];

try {
    $pdo = getDBConnection();

    // Cargar datos de la nota de cobranza
    $stmt = $pdo->prepare("
        SELECT 
            nc.*,
            r.fecha_rms,
            r.mes_rms,
            r.ref_clte_rms,
            r.despacho_rms,
            c.nombre_clt AS cliente_nombre,
            c.rut_clt,
            c.direccion_clt,
            c.ciudad_clt
        FROM notacobranza nc
        LEFT JOIN remesa r ON nc.id_rms_nc = r.id_rms
        LEFT JOIN clientes c ON r.cliente_rms = c.id_clt
        WHERE nc.id_cabecera = ?
    ");
    $stmt->execute([$id_cabecera]);
    $nota = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$nota) {
        exit('Nota de cobranza no encontrada.');
    }

    // Cargar detalles
    $stmt = $pdo->prepare("SELECT * FROM detalle_nc WHERE id_cabecera = ? ORDER BY item_detalle");
    $stmt->execute([$id_cabecera]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular totales
    $total_neto = 0;
    $total_iva = 0;
    $total_monto = 0;
    foreach ($detalles as $d) {
        $total_neto += (float)$d['montoneto_detalle'];
        $total_iva += (float)$d['montoiva_detalle'];
        $total_monto += (float)$d['monto_detalle'];
    }

    // Crear PDF
    $pdf = new TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('SIGA');
    $pdf->SetTitle('Nota de Cobranza');
    $pdf->SetSubject('Nota de Cobranza');
    $pdf->SetKeywords('Nota, Cobranza, SIGA');

    // Configuración de márgenes
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(0);
    $pdf->SetAutoPageBreak(TRUE, 15);

    // Sin encabezado/pie por defecto
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10);

    // === ENCABEZADO EMPRESA ===
    $html = '
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <div style="font-weight: bold; font-size: 14px;">Agencia de Aduanas Luis Galleguillos Valderrama</div>
                <div style="font-size: 12px;">RUT: 76.985.432-1</div>
                <div style="font-size: 12px;">Av. Los Carrera 0875, Of. 502, Santiago</div>
                <div style="font-size: 12px;">Fono: +56 2 2345 6789</div>
            </td>
            <td style="width: 40%; vertical-align: top;">
                <!-- MARCO CENTRADO -->
                <table style="width: 100%; border: 1px solid #000; border-collapse: collapse; height: 60px;">
                    <tr>
                        <td style="text-align: center; vertical-align: middle; font-weight: bold; font-size: 12px; border-top: 1px solid #000; border-bottom: 1px solid #000;">
                            RUT<br>NOTA DE COBRANZA<br>' . htmlspecialchars($nota['nro_nc']) . '
                        </td>
                    </tr>
                </table>
                <!-- DOCUMENTO NO TRIBUTARIO -->
                <div style="text-align: center; font-size: 10px; margin-top: 2px;">DOCUMENTO NO TRIBUTARIO</div>
            </td>
        </tr>
    </table>';

    // === DATOS DEL CLIENTE ===
    $html .= '
    <table style="width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 15px;">
        <tr>
            <td style="width: 20%; font-weight: bold;">CLIENTE:</td>
            <td style="width: 40%;">' . htmlspecialchars($nota['cliente_nombre'] ?? '') . '</td>
            <td style="width: 20%; font-weight: bold;">FECHA:</td>
            <td style="width: 20%;">' . htmlspecialchars($nota['fecha_rms']) . '</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">RUT:</td>
            <td>' . htmlspecialchars($nota['rut_clt'] ?? '') . '</td>
            <td style="font-weight: bold;">MES:</td>
            <td>' . htmlspecialchars($nota['mes_rms']) . '</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">DIRECCIÓN:</td>
            <td colspan="3">' . htmlspecialchars($nota['direccion_clt'] ?? '') . ', ' . htmlspecialchars($nota['ciudad_clt'] ?? '') . '</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">REF. CLTE.:</td>
            <td colspan="3">' . htmlspecialchars($nota['ref_clte_rms'] ?? '') . '</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">DESPACHO:</td>
            <td colspan="3">' . htmlspecialchars($nota['despacho_rms'] ?? '') . '</td>
        </tr>
    </table>';

    // === CONCEPTO ===
    $html .= '
    <div style="margin: 10px 0; font-weight: bold;">CONCEPTO:</div>
    <div style="margin-bottom: 15px; padding: 5px; border: 1px solid #000;">' . htmlspecialchars($nota['concepto_nc'] ?? '') . '</div>';

    // === DETALLE DE LA NOTA ===
    $html .= '
    <table style="width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 10px;">
        <thead>
            <tr style="background-color: #f2f2f2;">
                <th style="border: 1px solid #000; padding: 4px; text-align: center;">ÍTEM</th>
                <th style="border: 1px solid #000; padding: 4px; text-align: center;">PROVEEDOR</th>
                <th style="border: 1px solid #000; padding: 4px; text-align: center;">NRO. DOCTO.</th>
                <th style="border: 1px solid #000; padding: 4px; text-align: center;">NETO</th>
                <th style="border: 1px solid #000; padding: 4px; text-align: center;">IVA</th>
                <th style="border: 1px solid #000; padding: 4px; text-align: center;">MONTO</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($detalles as $d) {
        $html .= '
        <tr>
            <td style="border: 1px solid #000; padding: 4px; text-align: center;">' . htmlspecialchars($d['item_detalle']) . '</td>
            <td style="border: 1px solid #000; padding: 4px;">' . htmlspecialchars($d['proveedor_detalle']) . '</td>
            <td style="border: 1px solid #000; padding: 4px; text-align: center;">' . htmlspecialchars($d['nro_doc_detalle']) . '</td>
            <td style="border: 1px solid #000; padding: 4px; text-align: right;">' . number_format((float)$d['montoneto_detalle'], 0, ',', '.') . '</td>
            <td style="border: 1px solid #000; padding: 4px; text-align: right;">' . number_format((float)$d['montoiva_detalle'], 0, ',', '.') . '</td>
            <td style="border: 1px solid #000; padding: 4px; text-align: right;">' . number_format((float)$d['monto_detalle'], 0, ',', '.') . '</td>
        </tr>';
    }

    // Rellenar si hay menos de 10 filas
    $filas_faltantes = max(0, 10 - count($detalles));
    for ($i = 0; $i < $filas_faltantes; $i++) {
        $html .= '<tr>
            <td style="border: 1px solid #000; padding: 4px;">&nbsp;</td>
            <td style="border: 1px solid #000; padding: 4px;">&nbsp;</td>
            <td style="border: 1px solid #000; padding: 4px;">&nbsp;</td>
            <td style="border: 1px solid #000; padding: 4px;">&nbsp;</td>
            <td style="border: 1px solid #000; padding: 4px;">&nbsp;</td>
            <td style="border: 1px solid #000; padding: 4px;">&nbsp;</td>
        </tr>';
    }

    $html .= '
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" style="height: 8px; border-top: 2px solid #000; border-bottom: none;"></td>
            </tr>
            <tr>
                <td colspan="4" style="text-align: right; font-weight: bold; padding: 4px 8px;">TOTAL NETO:</td>
                <td colspan="2" style="text-align: right; padding: 4px 8px; border: none;">' . number_format($total_neto, 0, ',', '.') . '</td>
            </tr>
            <tr>
                <td colspan="4" style="text-align: right; font-weight: bold; padding: 4px 8px;">TOTAL IVA:</td>
                <td colspan="2" style="text-align: right; padding: 4px 8px; border: none;">' . number_format($total_iva, 0, ',', '.') . '</td>
            </tr>
            <tr>
                <td colspan="4" style="text-align: right; font-weight: bold; padding: 4px 8px;">TOTAL NOTA DE COBRANZA:</td>
                <td colspan="2" style="text-align: right; padding: 4px 8px; border: none;">' . number_format($total_monto, 0, ',', '.') . '</td>
            </tr>
        </tfoot>
    </table>';

    // === AFECTA A FAVOR ===
    $html .= '
    <div style="margin-top: 20px; font-weight: bold;">
        AFECTA A FAVOR DE: ' . htmlspecialchars($nota['afavor_nc'] ?? 'cliente') . '
    </div>';

    // Salida
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('NotaCobranza_' . $nota['nro_nc'] . '.pdf', 'I');

} catch (Exception $e) {
    error_log("Error en generar_pdf_notacobranza: " . $e->getMessage());
    exit('Error al generar el PDF.');
}
?>