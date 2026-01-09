<?php
require_once __DIR__ . '/../session_check.php';
require_once __DIR__ . '/../config.php';

$rol = $_SESSION['rol'] ?? '';
if (!in_array($rol, ['admin', 'comercial'], true)) {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso denegado.');
}

$rendiciones = [];

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("
        SELECT 
            r.id_rendicion,
            r.id_rms,
            r.monto_pago_rndcn,
            r.monto_gastos_agencia_rndcn,
            r.fecha_rendicion,
            rm.ref_clte_rms,
            rm.fecha_rms,
            c.nombre_clt AS cliente_nombre
        FROM rendicion r
        LEFT JOIN remesa rm ON r.id_rms = rm.id_rms
        LEFT JOIN clientes c ON rm.id_clt_rms = c.id_clt
        ORDER BY r.fecha_rendicion DESC, r.id_rendicion DESC
    ");
    $stmt->execute();
    $rendiciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('Error lista rendiciones: ' . $e->getMessage());
    $rendiciones = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Lista de Rendiciones - SIGA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <link rel="stylesheet" href="/styles.css">

    <style>
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.2rem;
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="container">
    <div class="card-header">
        <h2 style="font-weight: bold;">
            <i class="fas fa-file-invoice"></i> Lista de Rendiciones de Gasto
        </h2>
        <a href="/pages/remesa_lista.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver a Remesas
        </a>
    </div>

<?php if ($rendiciones): ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Remesa</th>
                    <th>Cliente</th>
                    <th>Ref. Cliente</th>
                    <th>Fecha Rendición</th>
                    <th>Monto Cliente</th>
                    <th>Monto Agencia</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rendiciones as $r): ?>
                <tr>
                    <td><?= (int)$r['id_rendicion'] ?></td>
                    <td><?= (int)$r['id_rms'] ?></td>
                    <td><?= htmlspecialchars($r['cliente_nombre'] ?? '–') ?></td>
                    <td><?= htmlspecialchars($r['ref_clte_rms'] ?? '–') ?></td>
                    <td><?= htmlspecialchars($r['fecha_rendicion'] ?? '–') ?></td>
                    <td><?= number_format((float)$r['monto_pago_rndcn'], 0, ',', '.') ?></td>
                    <td><?= number_format((float)$r['monto_gastos_agencia_rndcn'], 0, ',', '.') ?></td>
                    <td>
                        <a href="/pages/rendicion_view.php?id=<?= (int)$r['id_rms'] ?>" class="btn-primary" title="Ver">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="/pages/generar_pdf_rendicion.php?id=<?= (int)$r['id_rms'] ?>" target="_blank" class="btn-comment" title="PDF">
                            <i class="fas fa-file-pdf"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="card" style="text-align:center; padding:2rem;">
        <i class="fas fa-file-invoice" style="font-size:3rem; color:#ccc;"></i>
        <p>No hay rendiciones registradas.</p>
    </div>
<?php endif; ?>

</div>
</body>
</html>