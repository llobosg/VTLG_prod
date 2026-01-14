<?php
declare(strict_types=1);

// ==================================================
// Seguridad básica
// ==================================================
require_once __DIR__ . '/../session_check.php';
require_once __DIR__ . '/../config.php';

// ==================================================
// Autoload Composer (robusto)
// ==================================================
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    die('Error crítico: autoload.php no encontrado');
}
require_once $autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

// ==================================================
// Validación de entrada
// ==================================================
$id_cabecera = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id_cabecera <= 0) {
    http_response_code(400);
    die('ID de nota inválido.');
}

// ==================================================
// Conexión BD
// ==================================================
$pdo = getDBConnection();

// ==================================================
// Cabecera
// ==================================================
$stmt = $pdo->prepare("
    SELECT 
        nc.*,
        r.despacho_rms,
        r.ref_clte_rms,
        r.aduana_rms,
        c.nombre_clt AS cliente_nombre,
        c.rut_clt,
        c.direccion_clt,
        c.ciudad_clt
    FROM notacobranza nc
    LEFT JOIN remesa r ON nc.id_rms_nc = r.id_rms
    LEFT JOIN clientes c ON r.id_clt_rms = c.id_clt
    WHERE nc.id_cabecera = ?
");
$stmt->execute([$id_cabecera]);
$cabecera = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cabecera) {
    http_response_code(404);
    die('Nota de cobranza no encontrada.');
}

// ==================================================
// Detalle
// ==================================================
$stmt = $pdo->prepare("
    SELECT *
    FROM detalle_nc
    WHERE id_cabecera = ?
    ORDER BY id_detalle
");
$stmt->execute([$id_cabecera]);
$detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==================================================
// Helpers
// ==================================================
function fmt($val): string {
    return number_format((float)$val, 0, ',', '.');
}

// ==================================================
// Dompdf
// ==================================================
$options = new Options();
$options->set('defaultFont', 'Arial');
$options->set('chroot', realpath(__DIR__ . '/..'));

$dompdf = new Dompdf($options);

// ==================================================
// HTML
// ==================================================
$html = '
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: Arial, sans-serif; font-size: 10px; margin: 0; padding: 0; }
    .container { padding: 20px; margin-bottom: 50px; }

    .header-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }
    .header-table td { padding: 2px; vertical-align: middle; }

    .col1 { width: 50%; text-align: left; }
    .col2 { width: 10%; }
    .col3 { width: 40%; text-align: center; }

    .box-right {
        position: absolute;
        right: 20px;
        top: 0;
        width: 40%;
        height: 70px;
        border: 1px solid #000;
    }

    .detail-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 12px;
    }
    .detail-table th,
    .detail-table td {
        border: 1px solid #000;
        padding: 4px;
    }
    .detail-table th { text-align: center; }
    .detail-table td:last-child,
    .detail-table th:last-child { text-align: right; }

    .totals-row td {
        border-top: 2px solid #000;
        font-weight: bold;
    }

    .footer {
        position: absolute;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        font-size: 8px;
        color: #666;
        text-align: center;
        width: 100%;
    }

    .spacer { height: 8px; }
</style>
</head>
<body>

<div class="container">

// === ENCABEZADO EMPRESA ===
$html .= '
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
<div class="box-right"></div>
</div>

<div class="spacer"></div><div class="spacer"></div><div class="spacer"></div>

<!-- DATOS CLIENTE -->
<table class="header-table">
<tr>
    <td class="col1"><strong>Fecha:</strong> ' . htmlspecialchars($cabecera['fecha_nc'] ?? '') . '</td>
    <td class="col2"></td>
    <td class="col3" style="text-align:left;"><strong>Despacho:</strong> ' . htmlspecialchars($cabecera['despacho_rms'] ?? '') . '</td>
</tr>
<tr>
    <td class="col1"><strong>Señores:</strong> ' . htmlspecialchars($cabecera['cliente_nombre'] ?? '') . '</td>
    <td class="col2"></td>
    <td class="col3" style="text-align:left;"><strong>Referencia:</strong> ' . htmlspecialchars($cabecera['ref_clte_rms'] ?? '') . '</td>
</tr>
<tr>
    <td class="col1"><strong>RUT:</strong> ' . htmlspecialchars($cabecera['rut_clt'] ?? '') . '</td>
    <td class="col2"></td>
    <td class="col3" style="text-align:left;"><strong>Aduana:</strong> ' . htmlspecialchars($cabecera['aduana_rms'] ?? '') . '</td>
</tr>
<tr>
    <td class="col1"><strong>Dirección:</strong> ' . htmlspecialchars($cabecera['direccion_clt'] ?? '') . '</td>
    <td class="col2"></td>
    <td class="col3"></td>
</tr>
<tr>
    <td class="col1"><strong>Ciudad:</strong> ' . htmlspecialchars($cabecera['ciudad_clt'] ?? '') . '</td>
    <td class="col2"></td>
    <td class="col3"></td>
</tr>
</table>

<div class="spacer"></div><div class="spacer"></div>
<div class="spacer"></div><div class="spacer"></div>

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
';

foreach ($detalles as $d) {
    $html .= '
<tr>
    <td>' . htmlspecialchars($d['item_detalle']) . '</td>
    <td>' . htmlspecialchars($d['proveedor_detalle']) . '</td>
    <td>' . htmlspecialchars($d['nro_doc_detalle']) . '</td>
    <td style="text-align:right;">' . fmt($d['montoneto_detalle']) . '</td>
    <td style="text-align:right;">' . fmt($d['montoiva_detalle']) . '</td>
    <td style="text-align:right;">' . fmt($d['monto_detalle']) . '</td>
</tr>';
}

$html .= '
<tr class="totals-row">
    <td colspan="3"></td>
    <td>' . fmt($cabecera['total_neto_nc']) . '</td>
    <td>' . fmt($cabecera['total_iva_nc']) . '</td>
    <td>' . fmt($cabecera['total_monto_nc']) . '</td>
</tr>
</tbody>
</table>

</div>

<div class="footer">
Este es un documento no tributario, emitido exclusivamente con fines de cobranza.<br>
AGENCIA DE ADUANA LUIS GALLEGUILLOS
</div>

</body>
</html>
';

// ==================================================
// Render PDF
// ==================================================
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("NotaCobranza_{$id_cabecera}.pdf", ['Attachment' => true]);
exit;
