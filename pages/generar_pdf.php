<?php
require_once '../session_check.php';
require_once '../config.php';
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$id_rms = $_GET['id'] ?? null;
if (!$id_rms || !is_numeric($id_rms)) {
    die('ID de remesa inválido.');
}

$pdo = getDBConnection();

$stmt = $pdo->prepare("
    SELECT 
        r.*,
        c.nombre_clt AS cliente_nombre,
        m.mercancia_mrcc AS mercancia_nombre,
        t.transporte_trnsprt AS transporte_nombre
    FROM remesa r
    LEFT JOIN clientes c ON r.cliente_rms = c.id_clt
    LEFT JOIN mercancias m ON r.mercancia_rms = m.id_mrcc
    LEFT JOIN transporte t ON r.cia_transp_rms = t.id_trnsprt
    WHERE r.id_rms = ?
");
$stmt->execute([$id_rms]);
$remesa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$remesa) {
    die('Remesa no encontrada.');
}

// Actualizar estado a "solicitada"
$pdo->prepare("UPDATE remesa SET estado_rms = 'solicitada' WHERE id_rms = ?")->execute([$id_rms]);
$stmt->execute([$id_rms]);
$remesa = $stmt->fetch(PDO::FETCH_ASSOC);

function fmt($val) { return number_format($val, 0, ',', '.'); }
function fmtDecimal($val) { return number_format($val, 2, ',', '.'); }

$options = new Options();
$options->set('defaultFont', 'Arial');
$options->set('isRemoteEnabled', false);
$options->set('isPhpEnabled', false);
$options->set('isJavascriptEnabled', false);
$options->set('isHtml5ParserEnabled', true);
$options->set('enableRemote', false);
$options->set('debugKeepTemp', false);

$dompdf = new Dompdf($options);

$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 0; padding: 0; }
        .container { padding: 15px; }
        .section-box {
            border: 1px solid #000;
            padding: 10px;
            margin-bottom: 16px;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 0; 
        }
        td { 
            padding: 3px 4px; 
            vertical-align: top; 
        }
        .text-right { text-align: right; }
        .label { font-weight: bold; }
        .section-title {
            text-align: center;
            font-weight: bold;
            font-size: 10px;
            padding: 3px;
            background: #333;
            color: white;
            margin: 0 0 8px 0;
        }
        .totals { font-weight: bold; }
        h2 { margin: 0 0 8px 0; font-size: 14px; text-align: center; }
    </style>
</head>
<body>
<div class="container">

    <!-- === SECCIÓN SUPERIOR (CABECERA + 4x4) === -->
    <div class="section-box">
        <div style="margin-bottom: 10px;">
            <div style="font-weight: bold; font-size: 16px; margin-bottom: 6px; text-align: left;">SIGA_LG</div>
            <h2>SOLICITUD DE REMESA</h2>
            <table style="margin-top: 8px;">
                <tr>
                    <td class="label">FECHA:</td><td>' . htmlspecialchars($remesa['fecha_rms']) . '</td>
                    <td class="label">MES:</td><td>' . htmlspecialchars($remesa['mes_rms']) . '</td>
                    <td class="label">ESTADO:</td><td>' . htmlspecialchars($remesa['estado_rms']) . '</td>
                    <td class="label">T/C:</td><td>' . fmtDecimal($remesa['tipo_cambio_rms']) . '</td>
                </tr>
            </table>
            <table style="margin-top: 6px;">
                <tr>
                    <td class="label" style="text-align: center;">SRES.:</td>
                    <td style="text-align: center;">' . htmlspecialchars($remesa['cliente_nombre'] ?? '') . '</td>
                    <td class="label" style="text-align: center;">ATN.:</td>
                    <td style="text-align: center;">' . htmlspecialchars($remesa['contacto_rms']) . '</td>
                </tr>
            </table>
        </div>

        <table style="margin-top: 12px;">
            <tr>
                <td class="label">DESPACHO</td><td>' . htmlspecialchars($remesa['despacho_rms']) . '</td>
                <td class="label">TRÁMITE</td><td>' . htmlspecialchars($remesa['tramite_rms']) . '</td>
            </tr>
            <tr>
                <td class="label">REF.CLTE.</td><td colspan="3">' . htmlspecialchars($remesa['ref_clte_rms']) . '</td>
            </tr>
            <tr>
                <td class="label">ADUANA</td><td>' . htmlspecialchars($remesa['aduana_rms']) . '</td>
                <td class="label">CIA.TRANSP./M/N</td><td>' . htmlspecialchars($remesa['transporte_nombre'] ?? '') . '</td>
            </tr>
            <tr>
                <td class="label">MERCANCÍA</td><td>' . htmlspecialchars($remesa['mercancia_nombre'] ?? '') . '</td>
                <td class="label">MOTONAVE</td><td>' . htmlspecialchars($remesa['motonave_rms']) . '</td>
            </tr>
        </table>
    </div>

    <!-- === SECCIÓN INTERMEDIA (6 columnas con proporción exacta) === -->
    <div class="section-box">
        <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
            <!-- Títulos: solo col 1+2 y col 4+5 -->
            <tr>
                <td colspan="2" class="section-title" style="width: 45%;">TESORERÍA GENERAL DE LA REPÚBLICA</td>
                <td style="width: 5%;"></td>
                <td colspan="2" class="section-title" style="width: 45%;">GASTOS OPERACIONALES</td>
                <td style="width: 5%;"></td>
            </tr>
            <!-- Fila vacía -->
            <tr><td colspan="6" style="height: 6px;"></td></tr>

            <!-- Filas de datos -->
            <tr>
                <td>Dº AD-VALOREM</td>
                <td class="text-right">' . fmt($remesa['d_ad_valores_rms']) . '</td>
                <td></td>
                <td>GASTOS AGA</td>
                <td class="text-right">' . fmt($remesa['gastos_aga_rms']) . '</td>
                <td></td>
            </tr>
            <tr>
                <td>IMPTO. ADICIONAL</td>
                <td class="text-right">' . fmt($remesa['impto_adicional_rms']) . '</td>
                <td></td>
                <td>HONORARIOS</td>
                <td class="text-right">' . fmt($remesa['honorarios_rms']) . '</td>
                <td></td>
            </tr>
            <tr>
                <td>ALMACENAJE</td>
                <td class="text-right">' . fmt($remesa['almacenaje_rms']) . '</td>
                <td></td>
                <td>TRANSM. EDI</td>
                <td class="text-right">' . fmt($remesa['transm_edi_rms']) . '</td>
                <td></td>
            </tr>
            <tr>
                <td>I.V.A.</td>
                <td class="text-right">' . fmt($remesa['iva_rms']) . '</td>
                <td></td>
                <td>GASTO LOCAL</td>
                <td class="text-right">' . fmt($remesa['gasto_local_rms']) . '</td>
                <td></td>
            </tr>
            <tr>
                <td class="totals">TOTAL TESORERÍA</td>
                <td class="text-right">' . fmt($remesa['total_tesoreria_rms']) . '</td>
                <td></td>
                <td>FLETE LOCAL</td>
                <td class="text-right">' . fmt($remesa['flete_local_rms']) . '</td>
                <td></td>
            </tr>
            <tr>
                <td></td><td></td><td></td>
                <td>GATE IN</td>
                <td class="text-right">' . fmt($remesa['gate_in_rms']) . '</td>
                <td></td>
            </tr>
            <tr>
                <td class="totals">VALOR CIF US$</td>
                <td class="text-right">' . fmtDecimal($remesa['valor_cif_rms']) . '</td>
                <td></td>
                <td>GASTOS OPERATIVOS</td>
                <td class="text-right">' . fmt($remesa['gastos_operativos_rms']) . '</td>
                <td></td>
            </tr>
            <tr>
                <td></td><td></td><td></td>
                <td>PÓLIZA CONTENEDOR</td>
                <td class="text-right">' . fmt($remesa['poliza_contenedor_rms']) . '</td>
                <td></td>
            </tr>
            <tr>
                <td></td><td></td><td></td>
                <td>SEGURO CARGA</td>
                <td class="text-right">' . fmt($remesa['seguro_carga_rms']) . '</td>
                <td></td>
            </tr>
            <tr>
                <td></td><td></td><td></td>
                <td>GICONA</td>
                <td class="text-right">' . fmt($remesa['gicona_rms']) . '</td>
                <td></td>
            </tr>
            <tr>
                <td class="totals">TOTAL FONDOS</td>
                <td></td>
                <td></td>
                <td>OTROS</td>
                <td class="text-right">' . fmt($remesa['otros_rms']) . '</td>
                <td></td>
            </tr>
            <tr><td colspan="6" style="height: 8px;"></td></tr>
            <tr>
                <td class="totals">TOTAL TESORERÍA</td>
                <td class="text-right">' . fmt($remesa['total_tesoreria2_rms']) . '</td>
                <td></td>
                <td>SUB-TOTAL</td>
                <td class="text-right">' . fmt($remesa['subtotal_gastos_operacionales_rms']) . '</td>
                <td></td>
            </tr>
            <tr>
                <td>GASTOS OPERACIONALES</td>
                <td class="text-right">' . fmt($remesa['total_gastos_operacionales2_rms']) . '</td>
                <td></td>
                <td>I.V.A.</td>
                <td class="text-right">' . fmt($remesa['iva_gastos_operacionales_rms']) . '</td>
                <td></td>
            </tr>
            <tr class="totals">
                <td>TOTAL A TRANSFERIR</td>
                <td class="text-right">' . fmt($remesa['total_transferir_rms']) . '</td>
                <td></td>
                <td>TOTAL</td>
                <td class="text-right">' . fmt($remesa['total_gastos_operacionales_rms']) . '</td>
                <td></td>
            </tr>
        </table>
    </div>

    <!-- === SECCIÓN INFERIOR === -->
    <div class="section-box">
        <table style="width: 100%; font-size: 10px;">
            <tr>
                <td colspan="2" style="font-weight: bold; font-size: 11px; text-align: center; padding-bottom: 6px;">
                    SOLICITO DEPOSITAR O TRANSFERIR A LA CTA.CTE.:
                </td>
            </tr>
            <tr>
                <td><strong>TITULAR:</strong> VTLG COMERCIO EXTERIOR LTDA.</td>
                <td><strong>CTA. CTE. NRO.:</strong> 82323058</td>
            </tr>
            <tr>
                <td><strong>RUT:</strong> 76.864.039-4</td>
                <td><strong>BANCO:</strong> SANTANDER</td>
            </tr>
        </table>
    </div>

</div>
</body>
</html>
';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("SolicitudRemesa_{$id_rms}.pdf", ["Attachment" => true]);
exit;
?>