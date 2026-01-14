<?php
require_once '../session_check.php';
require_once '../config.php';
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$id_cabecera = $_GET['id'] ?? null;
if (!$id_cabecera || !is_numeric($id_cabecera)) {
    die('ID de nota inválido.');
}

$pdo = getDBConnection();

// Cargar cabecera
$stmt = $pdo->prepare("
    SELECT 
        nc.*,
        r.cliente_rms,
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
    die('Nota no encontrada.');
}

// Cargar detalles
$stmt = $pdo->prepare("SELECT * FROM detalle_nc WHERE id_cabecera = ? ORDER BY id_detalle");
$stmt->execute([$id_cabecera]);
$detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        .container { padding: 20px; margin-bottom: 50px; }
        .header { position: relative; margin-bottom: 15px; }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .header-table td {
            vertical-align: middle;
            padding: 2px;
        }
        .col1 { width: 50%; text-align: left; }
        .col2 { width: 10%; }
        .col3 { width: 40%; text-align: center; }
        .box-right {
            position: absolute;
            right: 20px;
            top: 0;
            width: 40%;
            height: calc(5 * 1.4em);
            border: 1px solid #000;
            box-sizing: border-box;
        }
        .section-box {
            border: 1px solid #000;
            padding: 10px;
            margin: 15px 0;
        }
        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
        }
        .detail-table thead th {
            background-color: #f2f2f2; /* ✅ Fondo gris suave */
            padding: 4px;
            border: 1px solid #000;
            text-align: center;
        }
        .detail-table tbody td {
            padding: 4px;
            border: 1px solid #000;
            text-align: left;
        }
        .detail-table tbody td:last-child {
            text-align: right;
        }
        .totals-row td {
            border-top: 2px solid #000; /* ✅ Solo borde superior grueso */
            border-left: none;
            border-right: none;
            border-bottom: none; /* ✅ Sin borde inferior */
            font-weight: bold;
            padding: 4px;
            text-align: right;
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
        .separator { height: 8px; }
    </style>
</head>
<body>
<div class="container">
    <!-- SECCIÓN SUPERIOR NUEVA -->
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="col1"></td>
                <td class="col2"></td>
                <td class="col3"></td>
            </tr>
            <tr>
                <td class="col1"></td>
                <td class="col2"></td>
                <td class="col3">R.U.T. 13.979.734-6</td>
            </tr>
            <tr>
                <td class="col1"><strong>Agencia de Aduana Luis Galleguillos Valderrama</strong></td>
                <td class="col2"></td>
                <td class="col3">NOTA DE COBRANZA</td>
            </tr>
            <tr>
                <td class="col1">Casa matriz: Blanco 1623 of 1203 Valparaíso - Valparaíso</td>
                <td class="col2"></td>
                <td class="col3">Nº: ' . htmlspecialchars($cabecera['nro_nc'] ?? '') . '</td>
            </tr>
        </table>
        <!-- Cuadro derecho CORREGIDO -->
        <div class="box-right">
            <div style="display: flex; flex-direction: column; justify-content: center; align-items: center; height: 100%; text-align: center; font-weight: bold; border-top: 1px solid #000; border-bottom: 1px solid #000;">
                R.U.T.<br>
                NOTA DE COBRANZA<br>
                Nº: ' . htmlspecialchars($cabecera['nro_nc'] ?? '') . '
            </div>
        </div>
    </div>

    <!-- Separación -->
    <div class="separator"></div>
    <div class="separator"></div>
    <div class="separator"></div>
    <div class="separator"></div>

    <!-- SECCIÓN SUPERIOR ACTUAL (CLIENTE) -->
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="col1"><strong>Fecha:</strong> ' . htmlspecialchars($cabecera['fecha_nc']) . '</td>
                <td class="col2"></td>
                <td class="col3" style="text-align: left;"><strong>Despacho:</strong> ' . htmlspecialchars($cabecera['despacho_rms'] ?? '') . '</td>      
            </tr>
            <tr>
                <td class="col1"><strong>Señores:</strong> ' . htmlspecialchars($cabecera['cliente_nombre'] ?? '') . '</td>
                <td class="col2"></td>
                <td class="col3" style="text-align: left;"><strong>Referencia:</strong> ' . htmlspecialchars($cabecera['ref_clte_rms'] ?? '') . '</td>
            </tr>
            <tr>
                <td class="col1"><strong>Rut:</strong> ' . htmlspecialchars($cabecera['rut_clt'] ?? '') . '</td>
                <td class="col2"></td> 
                <td class="col3" style="text-align: left;"><strong>Aduana:</strong> ' . htmlspecialchars($cabecera['aduana_rms'] ?? '') . '</td>       
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
    </div>
    <!-- Cuadro derecho para sección cliente -->
    <div style="position: relative; margin-top: -100px;">
        <div style="position: absolute; right: 20px; top: 0; width: 40%; height: 70px; border: 1px solid #000; box-sizing: border-box;"></div>
    </div>

    <!-- Separación -->
    <div class="separator"></div>
    <div class="separator"></div>
    <div class="separator"></div>
    <div class="separator"></div>
    <div class="separator"></div>
    <div class="separator"></div>
    <div class="separator"></div>
    <div class="separator"></div>
    <div class="separator"></div>

    <!-- DOCUMENTO NO TRIBUTARIO (justo debajo del cuadro) -->
    <div style="position: relative; margin: 8px 0;">
        <div style="position: absolute; right: 20px; width: 40%; text-align: center; font-size: 9px; font-weight: bold; top: 0;">
            DOCUMENTO NO TRIBUTARIO
        </div>
    </div>

    <div class="separator"></div>
    <div class="separator"></div>
    <div class="separator"></div>
    <div class="separator"></div>
    <div class="separator"></div>

    <!-- DETALLE DE LA NOTA -->
    <table class="detail-table">
        <thead>
            <tr>
                <th style="text-align: center;">Ítem</th>
                <th style="text-align: center;">Proveedor</th>
                <th style="text-align: center;">Nro. Docto.</th>
                <th style="text-align: center;">Monto Neto</th>
                <th style="text-align: center;">Monto Iva</th>
                <th style="text-align: center;">Monto</th>
            </tr>
        </thead>
        <tbody>
            ' . implode('', array_map(function($d) {
                return '<tr>
                    <td>' . htmlspecialchars($d['item_detalle']) . '</td>
                    <td>' . htmlspecialchars($d['proveedor_detalle']) . '</td>
                    <td>' . htmlspecialchars($d['nro_doc_detalle']) . '</td>
                    <td style="text-align: right;">' . fmt($d['montoneto_detalle']) . '</td>
                    <td style="text-align: right;">' . fmt($d['montoiva_detalle']) . '</td>
                    <td style="text-align: right;">' . fmt($d['monto_detalle']) . '</td>
                </tr>';
            }, $detalles)) . '
            <tr class="totals-row">
                <td></td>
                <td></td>
                <td></td>
                <td>' . fmt($cabecera['total_neto_nc']) . '</td>
                <td>' . fmt($cabecera['total_iva_nc']) . '</td>
                <td>' . fmt($cabecera['total_monto_nc']) . '</td>
            </tr>
        </tbody>
    </table>

    <!-- PIE DE PÁGINA -->
    <div class="footer">
        Este es un documento no tributario, emitido exclusivamente con fines de cobranza<br>
        por aquellos gastos por cuenta de terceros asociados a la operación indicada.<br>
        AGENCIA ADUANA LUIS GALLEGUILLOS
    </div>
</div>
</body>
</html>
';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("NotaCobranza_{$id_cabecera}.pdf", ["Attachment" => true]);
exit;
?>