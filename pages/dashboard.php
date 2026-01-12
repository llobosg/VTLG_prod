<?php
require_once '../session_check.php';
require_once '../config.php';

if (php_sapi_name() === 'cli') {
    exit;
}

$pdo = getDBConnection();

// === 1. Totales por estado ===
$estados_validos = [
    'confección', 'solicitada', 'transferencia OK', 'Rendida',
    'Nota Cobranza enviada', 'Nota Cobranza pagada', 'Cerrada OK', 'Cerrada con observaciones'
];

$totales_estado = [];
foreach ($estados_validos as $estado) {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM remesa WHERE estado_rms = ?");
    $stmt->execute([$estado]);
    $totales_estado[$estado] = (int)$stmt->fetchColumn();
}

// === 2. Totales financieros ===
// Total transferido
$stmt = $pdo->query("SELECT COALESCE(SUM(total_transferir_rms), 0) FROM remesa");
$total_transferido = (float)$stmt->fetchColumn();

// Total rendido (suma de todos los montos en rendicion)
$stmt = $pdo->query("
    SELECT 
        COALESCE(SUM(monto_pago_rndcn), 0) + 
        COALESCE(SUM(monto_gastos_agencia_rndcn * 1.19), 0) 
    FROM rendicion
");
$total_rendido = (float)$stmt->fetchColumn();

// Total notas cobranza (asumiendo columna `total_nota_cobranza` en remesa o tabla separada)
// Aquí asumimos que hay una tabla `notacobranza` con campo `monto_nc`
$stmt = $pdo->query("SELECT COALESCE(SUM(monto_nc), 0) FROM notacobranza");
$total_notas_cobranza = (float)$stmt->fetchColumn();

// Saldo cliente y agencia (simulado: saldo = transferido - rendido)
$saldo_cliente = max(0, $total_transferido - $total_rendido);
$saldo_agencia = max(0, $total_rendido - $total_transferido);

// === 3. Últimas 5 remesas ===
$stmt = $pdo->query("
    SELECT
        r.id_rms,
        r.fecha_rms,
        r.estado_rms,
        c.nombre_clt AS cliente_nombre,
        r.total_transferir_rms
    FROM remesa r
    LEFT JOIN clientes c ON r.cliente_rms = c.id_clt
    ORDER BY r.id_rms DESC
    LIMIT 5
");
$ultimas_remesas = $stmt->fetchAll();

// === 4. Obtener lista de clientes para búsqueda inteligente ===
$stmt = $pdo->query("SELECT id_clt, nombre_clt FROM clientes ORDER BY nombre_clt");
$clientes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard - SIGA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <link rel="stylesheet" href="/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            border-left: 4px solid #3498db;
        }
        .stat-value {
            font-size: 1.4rem;
            font-weight: bold;
            color: #2c3e50;
            margin: 0.3rem 0;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #7f8c8d;
        }
        .search-box {
            margin: 1.5rem 0;
            position: relative;
        }
        .search-box input {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .search-results {
            position: absolute;
            z-index: 1000;
            background: white;
            border: 1px solid #ccc;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            display: none;
        }
        .search-results div {
            padding: 0.6rem;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        .search-results div:hover {
            background: #f1f1f1;
        }
        .chart-container {
            height: 200px;
            margin: 1.5rem 0;
        }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="container">
    <h2 style="font-weight: bold; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem;">
        <i class="fas fa-tachometer-alt"></i> Dashboard
    </h2>

    <!-- === FICHAS POR ESTADO === -->
    <h3 style="margin-bottom: 0.8rem; font-weight: bold;">Estado de Solicitudes</h3>
    <div class="stats-grid">
        <?php foreach ($estados_validos as $estado): ?>
        <div class="stat-card">
            <div class="stat-label"><?= htmlspecialchars($estado) ?></div>
            <div class="stat-value"><?= $totales_estado[$estado] ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- === TOTALES FINANCIEROS === -->
    <h3 style="margin-bottom: 0.8rem; font-weight: bold; margin-top: 2rem;">Totales Financieros</h3>
    <div class="stats-grid">
        <div class="stat-card" style="border-left-color: #27ae60;">
            <div class="stat-label">Total Transferido</div>
            <div class="stat-value">$<?= number_format($total_transferido, 0, ',', '.') ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #e67e22;">
            <div class="stat-label">Total Rendido</div>
            <div class="stat-value">$<?= number_format($total_rendido, 0, ',', '.') ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #9b59b6;">
            <div class="stat-label">Notas Cobranza</div>
            <div class="stat-value">$<?= number_format($total_notas_cobranza, 0, ',', '.') ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #2980b9;">
            <div class="stat-label">Saldo Cliente</div>
            <div class="stat-value">$<?= number_format($saldo_cliente, 0, ',', '.') ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #e74c3c;">
            <div class="stat-label">Saldo Agencia</div>
            <div class="stat-value">$<?= number_format($saldo_agencia, 0, ',', '.') ?></div>
        </div>
    </div>

    <!-- === GRÁFICO DE BARRAS === -->
    <h3 style="margin-bottom: 0.8rem; font-weight: bold; margin-top: 2rem;">Distribución por Estado</h3>
    <div class="chart-container">
        <canvas id="estadoChart"></canvas>
    </div>

    <!-- === BÚSQUEDA INTELIGENTE === -->
    <h3 style="margin-bottom: 0.8rem; font-weight: bold; margin-top: 2rem;">Búsqueda Rápida</h3>
    <div class="search-box">
        <input type="text" id="busqueda-inteligente" placeholder="Buscar cliente...">
        <div id="resultados-busqueda" class="search-results"></div>
    </div>

    <!-- === ÚLTIMAS 5 REMESAS === -->
    <h3 style="margin-bottom: 0.8rem; font-weight: bold; margin-top: 1.5rem;">Últimas Solicitudes</h3>
    <div class="card">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Estado</th>
                        <th>Transferido</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ultimas_remesas)): ?>
                        <tr><td colspan="6" style="text-align: center;">No hay remesas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($ultimas_remesas as $r): ?>
                        <tr>
                            <td><?= $r['id_rms'] ?></td>
                            <td><?= htmlspecialchars($r['fecha_rms']) ?></td>
                            <td><?= htmlspecialchars($r['cliente_nombre'] ?? '–') ?></td>
                            <td><?= htmlspecialchars($r['estado_rms']) ?></td>
                            <td><?= number_format($r['total_transferir_rms'], 0, ',', '.') ?></td>
                            <td>
                                <a href="/pages/remesa_view.php?edit=<?= $r['id_rms'] ?>" class="btn-primary" title="Editar">
                                    <i class="fas fa-edit"></i>
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

<script>
// === BÚSQUEDA INTELIGENTE ===
const clientes = <?= json_encode($clientes) ?>;
document.getElementById('busqueda-inteligente').addEventListener('input', function() {
    const term = this.value.trim().toLowerCase();
    const div = document.getElementById('resultados-busqueda');
    div.innerHTML = '';
    if (term.length < 2) {
        div.style.display = 'none';
        return;
    }
    const filtrados = clientes.filter(c => c.nombre_clt.toLowerCase().includes(term));
    if (filtrados.length > 0) {
        filtrados.forEach(c => {
            const el = document.createElement('div');
            el.textContent = c.nombre_clt;
            el.onclick = () => {
                window.location.href = `/pages/remesa_view.php?cliente=${c.id_clt}`;
                div.style.display = 'none';
            };
            div.appendChild(el);
        });
        div.style.display = 'block';
    } else {
        div.style.display = 'none';
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

// === GRÁFICO DE BARRAS ===
const ctx = document.getElementById('estadoChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_values($estados_validos)) ?>,
        datasets: [{
            label: 'Cantidad de Remesas',
            data: <?= json_encode(array_values($totales_estado)) ?>,
            backgroundColor: [
                '#3498db', '#2ecc71', '#e74c3c', '#f39c12',
                '#9b59b6', '#1abc9c', '#d35400', '#2c3e50'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { precision: 0 }
            }
        }
    }
});
</script>
</body>
</html>