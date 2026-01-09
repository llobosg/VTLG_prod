<?php
require_once '../session_check.php';
require_once '../config.php';

$pdo = getDBConnection();
$rol = $_SESSION['rol'] ?? 'usuario';

// === KPIs ===
// Solicitudes por estado
$estados = ['Confeccion', 'solicitada', 'Transferida OK'];
$kpis_estado = [];
$total_transferir = 0;
foreach ($estados as $e) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total, COALESCE(SUM(total_transferir_rms), 0) as suma FROM remesa WHERE estado_rms = ?");
    $stmt->execute([$e]);
    $res = $stmt->fetch();
    $kpis_estado[$e] = [
        'count' => (int)$res['total'],
        'suma' => (float)$res['suma']
    ];
    if ($e === 'solicitada') {
        $total_transferir = $res['suma'];
    }
}

// Total rendido
$stmt = $pdo->prepare("SELECT COALESCE(SUM(monto_pago_rndcn), 0) as total FROM rendicion");
$stmt->execute();
$total_rendido = (float)$stmt->fetchColumn();

// Saldo pendiente
$saldo_pendiente = $total_transferir - $total_rendido;

// === Tabla de remesas (últimas 20) ===
$stmt = $pdo->prepare("
    SELECT 
        r.*,
        c.nombre_clt AS cliente_nombre,
        m.mercancia_mrcc AS mercancia_nombre
    FROM remesa r
    LEFT JOIN clientes c ON r.cliente_rms = c.id_clt
    LEFT JOIN mercancias m ON r.mercancia_rms = m.id_mrcc
    ORDER BY r.fecha_rms DESC
    LIMIT 20
");
$stmt->execute();
$remesas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard - SIGA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <link rel="stylesheet" href="/styles.css">
    <style>
        .kpi-card {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        .kpi-value {
            font-size: 1.8rem;
            font-weight: bold;
            margin: 0.5rem 0;
        }
        .kpi-label {
            color: #666;
            font-size: 0.9rem;
        }
        .busqueda-container {
            position: relative;
            margin: 1.2rem 0;
        }
        #resultados-busqueda {
            position: absolute;
            z-index: 3000;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            max-height: 300px;
            overflow-y: auto;
            width: 100%;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: none;
        }
        #resultados-busqueda div {
            padding: 0.7rem;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        #resultados-busqueda div:hover {
            background: #f0f0f0;
        }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="container">
    <h2 style="font-weight: bold; margin-bottom: 1.2rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-chart-line"></i> Dashboard Operativo
    </h2>

    <!-- === KPIs === -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.8rem;">
        <?php foreach ($estados as $e): ?>
        <div class="kpi-card">
            <div class="kpi-label"><?= htmlspecialchars($e) ?></div>
            <div class="kpi-value"><?= number_format($kpis_estado[$e]['count'], 0, ',', '.') ?></div>
            <div style="font-size: 0.85rem; color: #27ae60;">
                $<?= number_format($kpis_estado[$e]['suma'], 0, ',', '.') ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div class="kpi-card" style="background: #e8f4fc;">
            <div class="kpi-label">Total a Transferir</div>
            <div class="kpi-value" style="color: #2980b9;">
                $<?= number_format($total_transferir, 0, ',', '.') ?>
            </div>
        </div>
        
        <div class="kpi-card" style="background: #e8f5e9;">
            <div class="kpi-label">Total Rendido</div>
            <div class="kpi-value" style="color: #27ae60;">
                $<?= number_format($total_rendido, 0, ',', '.') ?>
            </div>
        </div>
        
        <div class="kpi-card" style="background: <?= $saldo_pendiente > 0 ? '#fef9e7' : '#f5b7b1' ?>;">
            <div class="kpi-label">Saldo Pendiente</div>
            <div class="kpi-value" style="color: <?= $saldo_pendiente > 0 ? '#d35400' : '#e74c3c' ?>;">
                $<?= number_format(abs($saldo_pendiente), 0, ',', '.') ?>
                <?= $saldo_pendiente > 0 ? '(por rendir)' : '(sobrante)' ?>
            </div>
        </div>
    </div>

    <!-- === BÚSQUEDA INTELIGENTE === -->
    <div class="busqueda-container">
        <input type="text" 
               id="busqueda-inteligente" 
               placeholder="Buscar por cliente, mercancía, ref.clte, mes, etc..." 
               style="width: 100%; height: 2.4rem; padding: 0.5rem; font-size: 0.95rem;">
        <div id="resultados-busqueda"></div>
    </div>

    <!-- === TABLA DE REMESAS === -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="margin: 0;">Solicitudes Recientes</h3>
            <?php if ($rol === 'admin'): ?>
            <a href="/pages/remesa_view.php" class="btn-primary" style="font-size: 0.9rem; padding: 0.4rem 0.8rem;">
                <i class="fas fa-plus"></i> Nueva Solicitud
            </a>
            <?php endif; ?>
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Mercancía</th>
                        <th>Ref.Clte.</th>
                        <th>Estado</th>
                        <th>Total Transferir</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tabla-reemesas">
                    <?php foreach ($remesas as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['fecha_rms']) ?></td>
                        <td><?= htmlspecialchars($r['cliente_nombre'] ?? 'ID: ' . $r['cliente_rms']) ?></td>
                        <td><?= htmlspecialchars($r['mercancia_nombre'] ?? 'ID: ' . $r['mercancia_rms']) ?></td>
                        <td><?= htmlspecialchars($r['ref_clte_rms'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['estado_rms']) ?></td>
                        <td><?= number_format($r['total_transferir_rms'] ?? 0, 0, ',', '.') ?></td>
                        <td>
                            <a href="/pages/generar_pdf.php?id=<?= $r['id_rms'] ?>" target="_blank" class="btn-comment" title="PDF">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                            <?php if ($rol === 'admin' && $r['estado_rms'] === 'solicitada'): ?>
                            <a href="/pages/rendicion_view.php?seleccionar=<?= $r['id_rms'] ?>" class="btn-warning" title="Rendición">
                                <i class="fas fa-receipt"></i>
                            </a>
                            <?php endif; ?>
                            <a href="/pages/remesa_view.php?editar=<?= $r['id_rms'] ?>" class="btn-edit" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// === BÚSQUEDA INTELIGENTE ===
document.getElementById('busqueda-inteligente')?.addEventListener('input', async function() {
    const term = this.value.trim();
    const div = document.getElementById('resultados-busqueda');
    div.style.display = 'none';
    if (!term) {
        // Si se borra la búsqueda, recargar tabla completa
        location.reload();
        return;
    }

    try {
        const res = await fetch(`/api/buscar_remesas_dashboard.php?term=${encodeURIComponent(term)}`);
        const data = await res.json();
        div.innerHTML = '';
        if (data.length > 0) {
            data.forEach(r => {
                const d = document.createElement('div');
                d.innerHTML = `<strong>${r.cliente_nombre || 'ID: ' + r.cliente_rms}</strong><br>
                              <small>
                                ${r.mercancia_nombre || '–'} | 
                                Ref: ${r.ref_clte_rms || '–'} | 
                                ${r.fecha_rms} | ${r.estado_rms}
                              </small>`;
                d.onclick = () => {
                    // Redirigir a la ficha de remesa (editar)
                    window.location.href = `/pages/remesa_view.php?editar=${r.id_rms}`;
                };
                div.appendChild(d);
            });
            div.style.display = 'block';
        }
    } catch (e) {
        console.error('Error en búsqueda:', e);
    }
});

// Cerrar resultados al hacer clic fuera
document.addEventListener('click', function(e) {
    const resultados = document.getElementById('resultados-busqueda');
    const input = document.getElementById('busqueda-inteligente');
    if (!resultados.contains(e.target) && e.target !== input) {
        resultados.style.display = 'none';
    }
});
</script>
</body>
</html>