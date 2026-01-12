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

// ✅ Cargar datos con JOIN correcto
$stmt = $pdo->prepare("
    SELECT 
        r.*,
        c.nombre_clt AS cliente_nombre,
        c.rut_clt,
        c.direccion_clt,
        c.ciudad_clt
    FROM remesa r
    LEFT JOIN clientes c ON r.id_clt_rms = c.id_clt  -- ✅ Usa id_clt_rms
    WHERE r.id_rms = ?
");
$stmt->execute([$id_rms]);
$remesa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$remesa) {
    die('Remesa no encontrada.');
}

// Cargar rendiciones
$stmt = $pdo->prepare("SELECT * FROM rendicion WHERE id_rms = ? ORDER BY id_rndcn");
$stmt->execute([$id_rms]);
$rendiciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales
$totalCliente = 0;
$totalAgencia = 0;
$clienteRendiciones = [];
$agenciaRendiciones = [];

foreach ($rendiciones as $r) {
    if ($r['concepto_rndcn']) {
        $clienteRendiciones[] = $r;
        $totalCliente += (float)$r['monto_pago_rndcn'];
    } else {
        $agenciaRendiciones[] = $r;
        $totalAgencia += (float)$r['monto_gastos_agencia_rndcn'];
    }
}

$netoAgencia = $totalAgencia;
$ivaAgencia = $netoAgencia * 0.19;
$totalGastosAgencia = $netoAgencia + $ivaAgencia;
$totalRendicion = $totalCliente + $totalGastosAgencia;
$saldo = (float)$remesa['total_transferir_rms'] - $totalRendicion;
$aFavor = $saldo > 0 ? 'cliente' : ($saldo < 0 ? 'agencia' : 'OK');

function fmt($val) {
    return number_format($val, 0, ',', '.');
}

$options = new Options();
$options->set('defaultFont', 'Arial');
$options->set('chroot', $_SERVER['DOCUMENT_ROOT'] ?? '/app');
$dompdf = new Dompdf($options);

$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 0; padding: 0; }
        .container { padding: 20px; }
        .section-box {
            border: 1px solid #000;
            padding: 10px;
            margin-bottom: 16px;
        }
        table { width: 100%; border-collapse: collapse; margin: 6px 0; }
        th, td { padding: 4px; vertical-align: top; }
        .section-title { font-weight: bold; margin: 12px 0 6px 0; font-size: 12px; }
        .label { font-weight: bold; }
        .text-right { text-align: right; }
        .totals { font-weight: bold; }
        h2 { text-align: center; margin: 0 0 16px 0; font-size: 16px; }
        .header-table th, .header-table td { border: none; }
        .detail-table thead tr { border-bottom: 2px solid #000; }
        .detail-table td:last-child, .detail-table th:last-child {
            border-left: 1px solid #000;
        }
        .totals-row td { border-top: 1px solid #000; }
        .separator { height: 1px; background: #000; margin: 8px 0; }
    </style>
</head>
<body>
<div class="container" style="margin-bottom: 40px;">
    <div style="font-size: 8px; margin-bottom: 6px; text-align: left;">Agencia de Aduanas Luis Galleguillos Valderrama</div>
    <h2>RENDICIÓN DE GASTOS</h2>
    <!-- SECCIÓN SUPERIOR -->
    <div class="section-box">
        <table class="header-table">
            <tr>
                <td class="label">SEÑOR(ES):</td>
                <td>' . htmlspecialchars($remesa['cliente_nombre'] ?? '') . '</td>
            </tr>
            <tr>
                <td class="label">RUT:</td>
                <td>' . htmlspecialchars($remesa['rut_clt'] ?? '') . '</td>
            </tr>
            <tr>
                <td class="label">DIRECCIÓN:</td>
                <td>' . htmlspecialchars($remesa['direccion_clt'] ?? '') . '</td>
            </tr>
            <tr>
                <td class="label">CIUDAD:</td>
                <td>' . htmlspecialchars($remesa['ciudad_clt'] ?? '') . '</td>
            </tr>
        </table>
    </div>

    <!-- SECCIÓN MEDIA -->
    <div class="section-box">
        <table class="detail-table">
            <thead>
                <tr>
                    <th>DETALLE DE GASTOS</th>
                    <th>Nro. Docto</th>
                    <th class="text-right">VALOR</th>
                </tr>
            </thead>
            <tbody>
                <!-- ✅ Label y valor en la misma celda -->
                <tr><td class="label" style="font-weight: bold;">DESPACHO: ' . htmlspecialchars($remesa['despacho_rms'] ?? '') . '</td><td></td><td></td></tr>
                <tr><td class="label" style="font-weight: bold;">REF.CLTE.: ' . htmlspecialchars($remesa['ref_clte_rms'] ?? '') . '</td><td></td><td></td></tr>
                <tr><td></td><td></td><td></td></tr>
                <tr><td></td><td></td><td></td></tr>
                
                <!-- ✅ Gastos cliente: alineado a la derecha de columna 2 -->
                <tr><td colspan="2" style="font-weight: bold;">GASTOS PAGADOS POR CUENTA CLIENTE:</td><td></td></tr>
                <tr><td class="label">Detalle (emp. emisora/recaudadora)</td><td></td><td></td></tr>
                
                ' . implode('', array_map(function($r) {
                    return '<tr>
                        <td>' . htmlspecialchars($r['concepto_rndcn']) . '</td>
                        <td>' . htmlspecialchars($r['nro_documento_rndcn'] ?? '') . '</td>
                        <td class="text-right">' . fmt($r['monto_pago_rndcn']) . '</td>
                    </tr>';
                }, $clienteRendiciones)) . '
                
                <tr><td></td><td></td><td></td></tr>
                <tr><td></td><td></td><td></td></tr>
                <tr><td></td><td></td><td></td></tr>
                <tr><td></td><td></td><td></td></tr>
                <tr><td></td><td></td><td></td></tr>
                
                <!-- ✅ Agenciamento: alineado a la derecha de columna 2 -->
                <tr><td colspan="2" style="font-weight: bold;">AGENCIAMIENTO ADUANERO:</td><td></td></tr>
                
                ' . implode('', array_map(function($r) {
                    return '<tr>
                        <td>' . htmlspecialchars($r['concepto_agencia_rndcn']) . '</td>
                        <td>' . htmlspecialchars($r['nro_documento_rndcn'] ?? '') . '</td>
                        <td class="text-right">' . fmt($r['monto_gastos_agencia_rndcn']) . '</td>
                    </tr>';
                }, $agenciaRendiciones)) . '
                
                <!-- Totales -->
                <tr class="totals-row">
                    <td></td>
                    <td class="text-right label">MONTO NETO:</td>
                    <td class="text-right">' . fmt($netoAgencia) . '</td>
                </tr>
                <tr class="totals-row">
                    <td></td>
                    <td class="text-right label">I.V.A.:</td>
                    <td class="text-right">' . fmt($ivaAgencia) . '</td>
                </tr>
                <tr class="totals-row">
                    <td></td>
                    <td class="text-right label">TOTAL FACT.:</td>
                    <td class="text-right totals">' . fmt($totalGastosAgencia) . '</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- SECCIÓN BAJA -->
    <table>
        <tr>
            <td class="label">REMESA DEPOSITADA: ' . htmlspecialchars($remesa['fecha_rms']) . '</td>
            <td class="text-right">' . fmt($remesa['total_transferir_rms']) . '</td>
        </tr>
        <tr><td colspan="2" style="height: 8px;"></td></tr>
        <tr>
            <td class="label">TOTAL LIQUIDACIÓN:</td>
            <td class="text-right">' . fmt($totalRendicion) . '</td>
        </tr>
        <tr>
            <td colspan="2" style="height: 8px; border-bottom: 1px solid #000;"></td>
        </tr>
        <tr>
            <td class="label">SALDO OPERACIÓN A FAVOR DE: ' . htmlspecialchars($aFavor) . '</td>
            <td class="text-right totals">' . fmt(abs($saldo)) . '</td>
        </tr>
    </table>
</div>
    <div style="position: absolute; bottom: 20px; right: 20px; font-size: 8px; color: #666;">
        Sistema Integrado Gestión Aduanera SIGA, powered by GLT_Comex
    </div>
</body>
</html>
';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Rendicion_{$id_rms}.pdf", ["Attachment" => true]);
exit;
?>