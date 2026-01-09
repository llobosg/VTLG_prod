<?php
require_once __DIR__ . '/../session_check.php';
require_once __DIR__ . '/../config.php';

$rol = $_SESSION['rol'] ?? 'usuario';
$remesas = [];

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Obtener todas las remesas que tengan al menos una rendición
    $stmt = $pdo->prepare("
        SELECT 
            r.id_rms,
            r.fecha_rms,
            r.despacho_rms,
            r.ref_clte_rms,
            r.total_transferir_rms,
            r.estado_rms,
            c.nombre_clt AS cliente_nombre,
            m.mercancia_mrcc AS mercancia_nombre,
            COALESCE(SUM(rend.monto_pago_rndcn), 0) AS total_cliente,
            COALESCE(SUM(rend.monto_gastos_agencia_rndcn), 0) AS total_agencia
        FROM remesa r
        LEFT JOIN clientes c ON r.cliente_rms = c.id_clt
        LEFT JOIN mercancias m ON r.mercancia_rms = m.id_mrcc
        LEFT JOIN rendicion rend ON r.id_rms = rend.id_rms
        WHERE r.estado_rms = 'solicitada'
        GROUP BY r.id_rms
        HAVING COUNT(rend.id_rndcn) > 0
        ORDER BY r.fecha_rms DESC
    ");
    $stmt->execute();
    $remesas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('Error rendicion_listas.php: ' . $e->getMessage());
    $remesas = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Rendición de Gastos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <link rel="stylesheet" href="/styles.css">
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.2rem;">
        <h2 style="font-weight:bold; display:flex; align-items:center; gap:0.5rem;">
            <i class="fas fa-receipt"></i> Rendición de Gastos
        </h2>
        <a href="/pages/rendicion_view.php" class="btn-primary">
            <i class="fas fa-plus"></i> Agregar Rendición
        </a>
    </div>

    <div class="card">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Despacho</th>
                        <th>Ref.Clte.</th>
                        <th>Mercancía</th>
                        <th>Fondos Transferidos</th>
                        <th>Total Liquidación</th>
                        <th>Saldo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($remesas)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;">No hay rendiciones registradas.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($remesas as $r):
                        $totalCliente = (float)$r['total_cliente'];
                        $totalAgencia = (float)$r['total_agencia'];
                        $ivaAgencia = $totalAgencia * 0.19;
                        $totalLiquidacion = $totalCliente + $totalAgencia + $ivaAgencia;
                        $saldo = (float)$r['total_transferir_rms'] - $totalLiquidacion;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($r['cliente_nombre'] ?? '–') ?></td>
                        <td><?= htmlspecialchars($r['despacho_rms'] ?? '–') ?></td>
                        <td><?= htmlspecialchars($r['ref_clte_rms'] ?? '–') ?></td>
                        <td><?= htmlspecialchars($r['mercancia_nombre'] ?? '–') ?></td>
                        <td><?= number_format($r['total_transferir_rms'], 0, ',', '.') ?></td>
                        <td><?= number_format($totalLiquidacion, 0, ',', '.') ?></td>
                        <td style="color:<?= $saldo > 0 ? '#2980b9' : '#e74c3c' ?>;">
                            <?= number_format(abs($saldo), 0, ',', '.') ?>
                            <?= $saldo > 0 ? ' (cliente)' : ' (agencia)' ?>
                        </td>
                        <td>
                            <a href="/pages/rendicion_view.php?seleccionar=<?= (int)$r['id_rms'] ?>" class="btn-primary">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="/pages/generar_pdf_rendicion.php?id=<?= (int)$r['id_rms'] ?>" target="_blank" class="btn-comment">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
