<?php
require_once __DIR__ . '/../session_check.php';
require_once __DIR__ . '/../config.php';

$pdo = getDBConnection();

try {

    $stmt = $pdo->prepare("
        SELECT 
            r.id_rms,
            r.fecha_rms,
            r.despacho_rms,
            r.ref_clte_rms,
            r.total_transferir_rms,
            c.nombre_clt AS cliente,
            m.mercancia_mrcc AS mercancia,

            SUM(
                COALESCE(rd.monto_pago_rndcn,0) +
                COALESCE(rd.monto_gastos_agencia_rndcn,0) +
                COALESCE(rd.monto_iva_rndcn,0)
            ) AS total_liquidacion

        FROM rendicion rd
        INNER JOIN remesa r ON r.id_rms = rd.id_rms
        LEFT JOIN clientes c ON c.id_clt = r.cliente_rms
        LEFT JOIN mercancias m ON m.id_mrcc = r.mercancia_rms

        GROUP BY r.id_rms
        ORDER BY r.fecha_rms DESC
    ");
    $stmt->execute();
    $rendiciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('[RENDICION_LISTAS] ' . $e->getMessage());
    $rendiciones = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Rendición de Gastos</title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body>

<h1>Rendición de Gastos</h1>

<div class="toolbar">
    <a href="/pages/rendicion_view.php?modo=insert" class="btn-primary">
        ➕ Agregar Rendición
    </a>
</div>

<table class="tabla">
    <thead>
        <tr>
            <th>Cliente</th>
            <th>Despacho</th>
            <th>Ref. Cliente</th>
            <th>Mercancía</th>
            <th>Fondos Transferidos</th>
            <th>Total Liquidación</th>
            <th>Saldo</th>
            <th>Acción</th>
        </tr>
    </thead>
    <tbody>

    <?php if (!$rendiciones): ?>
        <tr>
            <td colspan="8">No hay rendiciones registradas</td>
        </tr>
    <?php endif; ?>

    <?php foreach ($rendiciones as $r): 
        $total = (float)$r['total_liquidacion'];
        $fondos = (float)$r['total_transferir_rms'];
        $saldo = $fondos - $total;
    ?>
        <tr>
            <td><?= htmlspecialchars($r['cliente']) ?></td>
            <td><?= htmlspecialchars($r['despacho_rms']) ?></td>
            <td><?= htmlspecialchars($r['ref_clte_rms']) ?></td>
            <td><?= htmlspecialchars($r['mercancia']) ?></td>
            <td>$<?= number_format($fondos,0,',','.') ?></td>
            <td>$<?= number_format($total,0,',','.') ?></td>
            <td class="<?= $saldo < 0 ? 'negativo' : 'positivo' ?>">
                $<?= number_format($saldo,0,',','.') ?>
            </td>
            <td>
                <a href="/pages/rendicion_view.php?modo=edit&id_rms=<?= (int)$r['id_rms'] ?>"
                   class="btn-secondary">
                    ✏️ Editar
                </a>
            </td>
        </tr>
    <?php endforeach; ?>

    </tbody>
</table>

</body>
</html>