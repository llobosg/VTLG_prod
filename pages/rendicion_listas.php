<?php
require_once '../session_check.php';
require_once '../config.php';

$rol = $_SESSION['rol'] ?? '';
if ($rol !== 'admin' && $rol !== 'comercial') {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso denegado.');
}

// ✅ Inicializar SIEMPRE
$rendiciones = [];

// Cargar datos solo en contexto web
if (php_sapi_name() !== 'cli') {
    try {
        $pdo = getDBConnection();
        if ($pdo) {
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
                LEFT JOIN clientes c ON rm.cliente_rms = c.id_clt
                ORDER BY r.fecha_rendicion DESC, r.id_rendicion DESC
            ");
            $stmt->execute();
            $rendiciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Error al cargar lista de rendiciones: " . $e->getMessage());
        $rendiciones = []; // ✅ Asegura que sea array incluso en error
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Lista de Rendiciones - SIGA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem;">
        <h2 style="font-weight: bold;">
            <i class="fas fa-file-invoice"></i> Lista de Rendiciones de Gasto
        </h2>
        <a href="/pages/remesa_lista.php" class="btn-secondary" style="text-decoration: none; padding: 0.4rem 0.8rem;">
            <i class="fas fa-arrow-left"></i> Volver a Remesas
        </a>
    </div>

    <?php if (!empty($rendiciones)): ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Remesa</th>
                    <th>Cliente</th>
                    <th>Ref. Clte</th>
                    <th>Fecha Rend.</th>
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
                    <td><?= isset($r['monto_pago_rndcn']) ? number_format($r['monto_pago_rndcn'], 0, ',', '.') : '–' ?></td>
                    <td><?= isset($r['monto_gastos_agencia_rndcn']) ? number_format($r['monto_gastos_agencia_rndcn'], 0, ',', '.') : '–' ?></td>
                    <td>
                        <a href="/pages/rendicion_view.php?seleccionar=<?= (int)$r['id_rms'] ?>" class="btn-primary" title="Ver Rendición">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="/pages/generar_pdf_rendicion.php?id=<?= (int)$r['id_rendicion'] ?>" target="_blank" class="btn-comment" title="PDF">
                            <i class="fas fa-file-pdf"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="card" style="text-align: center; padding: 2rem;">
            <i class="fas fa-file-invoice" style="font-size: 3rem; color: #bdc3c7; margin-bottom: 1rem;"></i>
            <p>No hay rendiciones de gasto registradas.</p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>