<?php
require_once '../session_check.php';
require_once '../config.php';

$rol = $_SESSION['rol'] ?? 'usuario';
if ($rol !== 'admin' && $rol !== 'comercial') {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso denegado.');
}

$pdo = getDBConnection();
$remesa_seleccionada = null;
$nc_existe = false;
$nc_data = null;
$conceptos_cliente = [];
$conceptos_agencia = [];

if (isset($_GET['seleccionar'])) {
    $id_rms = (int)$_GET['seleccionar'];
    if ($id_rms > 0) {
        try {
            // Cargar remesa
            $stmt = $pdo->prepare("
                SELECT
                    r.*,
                    c.nombre_clt AS cliente_nombre,
                    c.rut_clt,
                    c.contacto_clt,
                    m.mercancia_mrcc AS mercancia_nombre
                FROM remesa r
                LEFT JOIN clientes c ON r.cliente_rms = c.id_clt
                LEFT JOIN mercancias m ON r.mercancia_rms = m.id_mrcc
                WHERE r.id_rms = ?
            ");
            $stmt->execute([$id_rms]);
            $remesa_seleccionada = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($remesa_seleccionada) {
                // Cargar NC si existe
                $stmt = $pdo->prepare("SELECT * FROM notacobranza WHERE id_rms_nc = ?");
                $stmt->execute([$id_rms]);
                $nc_data = $stmt->fetch(PDO::FETCH_ASSOC);
                $nc_existe = (bool)$nc_data;

                // Cargar conceptos de rendición
                $stmt = $pdo->prepare("SELECT * FROM rendicion WHERE id_rms = ? AND concepto_rndcn IS NOT NULL");
                $stmt->execute([$id_rms]);
                $conceptos_cliente = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $stmt = $pdo->prepare("SELECT * FROM rendicion WHERE id_rms = ? AND concepto_agencia_rndcn IS NOT NULL");
                $stmt->execute([$id_rms]);
                $conceptos_agencia = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $e) {
            error_log("Error en notacobranza_view: " . $e->getMessage());
        }
    }
}
// Cálculos
$totalCliente = array_sum(array_column($conceptos_cliente, 'monto_pago_rndcn'));
$totalAgencia = array_sum(array_column($conceptos_agencia, 'monto_gastos_agencia_rndcn'));
$totalGastosAgencia = $totalAgencia * 1.19;
$totalRendicion = $totalCliente + $totalGastosAgencia;
$totalRemesa = (float)($remesa_seleccionada['total_transferir_rms'] ?? 0);
$notaCobranza = (float)($nc_data['total_monto_nc'] ?? 0);
$saldo = ($totalRemesa - $totalRendicion) + $notaCobranza;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Nota de Cobranza</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="container">
    <!-- Título + botón cerrar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem;">
        <h2 style="font-weight: bold; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-file-invoice"></i> Nota de Cobranza
        </h2>
        <a href="/pages/notacobranza_listas.php" class="btn-secondary" style="padding: 0.3rem 0.7rem; font-size: 1.2rem; text-decoration: none;">&times;</a>
    </div>

    <?php if (!$remesa_seleccionada): ?>
        <div class="card" style="text-align: center; padding: 2rem;">
            <p>❌ No se encontró la remesa.</p>
            <a href="/pages/notacobranza_listas.php" class="btn-secondary">Volver a Lista</a>
        </div>
    <?php else: ?>
        <!-- Ficha de Nota de Cobranza -->
        <div id="ficha-remesa" class="card" style="margin-bottom: 1.5rem;">
            <div style="display: grid; grid-template-columns: repeat(8, 1fr); gap: 0.6rem; font-size: 0.9rem; align-items: center;">
                <!-- Fila 1 -->
                <div><strong>CLIENTE:</strong></div>
                <div class="valor-ficha" style="grid-column: span 3;"><?= htmlspecialchars($remesa_seleccionada['cliente_nombre'] ?? '') ?></div>
                <div><strong>CONTACTO:</strong></div>
                <div class="valor-ficha"><?= htmlspecialchars($remesa_seleccionada['contacto_clt'] ?? '') ?></div>
                <div><strong>FECHA:</strong></div>
                <div class="valor-ficha"><?= htmlspecialchars($remesa_seleccionada['fecha_rms'] ?? '') ?></div>

                <!-- Fila 2 -->
                <div><strong>REF.CLTE.:</strong></div>
                <div class="valor-ficha" style="grid-column: span 3;"><?= htmlspecialchars($remesa_seleccionada['ref_clte_rms'] ?? '') ?></div>
                <div><strong>DESPACHO:</strong></div>
                <div class="valor-ficha"><?= htmlspecialchars($remesa_seleccionada['despacho_rms'] ?? '') ?></div>
                <div><strong>MES:</strong></div>
                <div class="valor-ficha"><?= htmlspecialchars($remesa_seleccionada['mes_rms'] ?? '') ?></div>

                <!-- Fila 3 -->
                <div><strong>ESTADO:</strong></div>
                <div class="valor-ficha" style="grid-column: span 5;"><?= htmlspecialchars($remesa_seleccionada['estado_rms'] ?? '') ?></div>
                <div><strong>TOTAL REMESA:</strong></div>
                <div class="valor-ficha"><?= number_format($totalRemesa, 0, ',', '.') ?></div>

                <!-- Fila 4 -->
                <div><strong>CONCEPTO:</strong></div>
                <div class="valor-ficha" style="grid-column: span 5;">
                    <?php if ($nc_existe): ?>
                        <?= htmlspecialchars($nc_data['concepto_nc'] ?? '') ?>
                    <?php else: ?>
                        <input type="text" id="concepto_nc" style="width: 100%; height: 2.0rem; padding: 0.3rem;" placeholder="Concepto de la nota de cobranza">
                    <?php endif; ?>
                </div>
                <div><strong>TOTAL RENDICIÓN:</strong></div>
                <div class="valor-ficha"><?= number_format($totalRendicion, 0, ',', '.') ?></div>

                <!-- Fila 5 -->
                <div><strong>NRO.NC:</strong></div>
                <div class="valor-ficha">
                    <?php if ($nc_existe): ?>
                        <?= htmlspecialchars($nc_data['nro_nc'] ?? '') ?>
                    <?php else: ?>
                        <input type="text" id="nro_nc" style="width: 100%; height: 2.0rem; padding: 0.3rem;">
                    <?php endif; ?>
                </div>
                <div><strong>FECHA VCTO.:</strong></div>
                <div class="valor-ficha">
                    <?php if ($nc_existe): ?>
                        <?= htmlspecialchars($nc_data['fecha_vence_nc'] ?? '') ?>
                    <?php else: ?>
                        <input type="date" id="fecha_vence_nc" style="width: 100%; height: 2.0rem; padding: 0.3rem;">
                    <?php endif; ?>
                </div>
                <div></div>
                <div></div>
                <div><strong>NOTA COBRANZA:</strong></div>
                <div class="valor-ficha"><?= number_format($notaCobranza, 0, ',', '.') ?></div>

                <!-- Fila 6 -->
                <div><strong>A FAVOR DE:</strong></div>
                <div class="valor-ficha">
                    <?php if ($nc_existe): ?>
                        <?= htmlspecialchars($nc_data['afavor_nc'] ?? '') ?>
                    <?php else: ?>
                        <select id="afavor_nc" style="width: 100%; height: 2.0rem; padding: 0.3rem;">
                            <option value="cliente">Cliente</option>
                            <option value="agencia">Agencia</option>
                        </select>
                    <?php endif; ?>
                </div>
                <div><strong>SALDO:</strong></div>
                <div class="valor-ficha" style="color: <?= $saldo > 0 ? '#27ae60' : '#e74c3c' ?>;">
                    <?= number_format(abs($saldo), 0, ',', '.') ?>
                    <?= $saldo > 0 ? ' (cliente)' : ' (agencia)' ?>
                </div>
                <div></div>
                <div></div>
                <div></div>
                <div></div>

                <!-- Fila 7: Botón solo si no existe NC -->
                <div style="grid-column: span 8; display: flex; justify-content: flex-end; margin-top: 0.5rem;">
                    <?php if (!$nc_existe): ?>
                        <button class="btn-primary" onclick="guardarNotaCobranza()" style="padding: 0.4rem 0.8rem;">
                            <i class="fas fa-save"></i> Grabar Nota de Cobranza
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tabla de Conceptos de Rendición (solo lectura) -->
        <div class="card">
            <h3 style="font-weight: bold; margin: 0 0 0.8rem 0;"><i class="fas fa-list"></i> Conceptos de Rendición</h3>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Concepto</th>
                            <th>Nro. Doc</th>
                            <th>Fecha</th>
                            <th>Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conceptos_cliente as $c): ?>
                        <tr>
                            <td>Cliente</td>
                            <td><?= htmlspecialchars($c['concepto_rndcn']) ?></td>
                            <td><?= htmlspecialchars($c['nro_documento_rndcn']) ?></td>
                            <td><?= htmlspecialchars($c['fecha_rndcn']) ?></td>
                            <td><?= number_format($c['monto_pago_rndcn'], 0, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php foreach ($conceptos_agencia as $c): ?>
                        <tr>
                            <td>Agencia</td>
                            <td><?= htmlspecialchars($c['concepto_agencia_rndcn']) ?></td>
                            <td><?= htmlspecialchars($c['nro_documento_rndcn']) ?></td>
                            <td><?= htmlspecialchars($c['fecha_rndcn']) ?></td>
                            <td><?= number_format($c['monto_gastos_agencia_rndcn'] * 1.19, 0, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function guardarNotaCobranza() {
    const formData = new FormData();
    formData.append('id_rms', <?= (int)($_GET['seleccionar'] ?? 0) ?>);
    formData.append('concepto_nc', document.getElementById('concepto_nc').value.trim());
    formData.append('nro_nc', document.getElementById('nro_nc').value.trim());
    formData.append('fecha_vence_nc', document.getElementById('fecha_vence_nc').value);
    formData.append('afavor_nc', document.getElementById('afavor_nc').value);
    formData.append('total_rendicion', <?= $totalRendicion ?>);
    formData.append('total_remesa', <?= $totalRemesa ?>);

    fetch('/pages/notacobranza_logic.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✅ Nota de cobranza creada.');
            location.reload();
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(err => {
        alert('❌ Error de conexión.');
    });
}
</script>
</body>
</html>