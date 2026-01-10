<?php
require_once __DIR__ . '/../session_check.php';
require_once __DIR__ . '/../config.php';

$rol = $_SESSION['rol'] ?? 'usuario';
$remesas = [];
$remesa_seleccionada = null;
$conceptos_cliente = [];
$conceptos_agencia = [];

// === Modo 1: Listado general (sin parámetro) ===
if (!isset($_GET['seleccionar'])) {
    // Cargar solo en contexto web
    if (php_sapi_name() !== 'cli') {
        try {
            $pdo = getDBConnection();
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
                HAVING COUNT(rend.id_rendicion) > 0
                ORDER BY r.fecha_rms DESC
            ");
            $stmt->execute();
            $remesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('Error en rendicion_view (listado): ' . $e->getMessage());
            $remesas = [];
        }
    }
}
// === Modo 2: Edición de rendición (con ?seleccionar=ID) ===
else {
    $id_rms = (int)($_GET['seleccionar'] ?? 0);
    if ($id_rms > 0 && php_sapi_name() !== 'cli') {
        try {
            $pdo = getDBConnection();
            // Cargar remesa
            $stmt = $pdo->prepare("
                SELECT 
                    r.*,
                    c.nombre_clt AS cliente_nombre,
                    c.id_clt AS id_clt_rms,
                    m.mercancia_mrcc AS mercancia_nombre
                FROM remesa r
                LEFT JOIN clientes c ON r.cliente_rms = c.id_clt
                LEFT JOIN mercancias m ON r.mercancia_rms = m.id_mrcc
                WHERE r.id_rms = ?
            ");
            $stmt->execute([$id_rms]);
            $remesa_seleccionada = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($remesa_seleccionada) {
                // Cargar conceptos cliente
                $stmt = $pdo->prepare("SELECT * FROM rendicion WHERE id_rms = ? AND tipo_concepto = 'cliente' ORDER BY id_rendicion");
                $stmt->execute([$id_rms]);
                $conceptos_cliente = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Cargar conceptos agencia
                $stmt = $pdo->prepare("SELECT * FROM rendicion WHERE id_rms = ? AND tipo_concepto = 'agencia' ORDER BY id_rendicion");
                $stmt->execute([$id_rms]);
                $conceptos_agencia = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $e) {
            error_log('Error en rendicion_view (edición): ' . $e->getMessage());
            $remesa_seleccionada = null;
        }
    }
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
    <?php if (!isset($_GET['seleccionar'])): ?>
        <!-- === MODO LISTADO === -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.2rem;">
            <h2 style="font-weight:bold; display:flex; align-items:center; gap:0.5rem;">
                <i class="fas fa-receipt"></i> Rendición de Gastos
            </h2>
            <!-- Botón eliminado porque no aplica en listado -->
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

    <?php else: ?>
        <!-- === MODO EDICIÓN === -->
        <?php if (!$remesa_seleccionada): ?>
            <div class="card" style="text-align: center; padding: 2rem;">
                <p>❌ No se encontró la remesa seleccionada.</p>
                <a href="/pages/rendicion_view.php" class="btn-secondary" style="margin-top: 1rem;">Volver a Lista</a>
            </div>
        <?php else: ?>
            <!-- Ficha de Remesa -->
            <div id="ficha-remesa" class="card" style="margin-bottom: 1.5rem;">
                <div style="display: grid; grid-template-columns: repeat(8, 1fr); gap: 0.6rem; font-size: 0.9rem; align-items: center;">
                    <div><strong>CLIENTE:</strong></div>
                    <div class="valor-ficha" id="cliente_ficha" style="grid-column: span 3;"><?= htmlspecialchars($remesa_seleccionada['cliente_nombre'] ?? '') ?></div>
                    <div><strong>Rut:</strong></div>
                    <div class="valor-ficha"><?= htmlspecialchars($remesa_seleccionada['rut_clt'] ?? '') ?></div>
                    <div><strong>FECHA:</strong></div>
                    <div class="valor-ficha"><?= htmlspecialchars($remesa_seleccionada['fecha_rms'] ?? '') ?></div>

                    <div><strong>DESPACHO:</strong></div>
                    <div class="valor-ficha"><?= htmlspecialchars($remesa_seleccionada['despacho_rms'] ?? '') ?></div>
                    <div><strong>REF.CLTE.:</strong></div>
                    <div class="valor-ficha"><?= htmlspecialchars($remesa_seleccionada['ref_clte_rms'] ?? '') ?></div>
                    <div><strong>MERCANCÍA:</strong></div>
                    <div class="valor-ficha" style="grid-column: span 2;"><?= htmlspecialchars($remesa_seleccionada['mercancia_nombre'] ?? '') ?></div>

                    <div><strong>TOTAL TRANSFERIDO:</strong></div>
                    <div class="valor-ficha"><?= number_format($remesa_seleccionada['total_transferir_rms'] ?? 0, 0, ',', '.') ?></div>
                    <div></div>
                    <div></div>
                    <div></div>
                    <div></div>
                    <div></div>
                    <div></div>
                </div>
            </div>

            <!-- Botones de acción -->
            <div style="display: flex; justify-content: space-between; margin-bottom: 1.5rem; align-items: center;">
                <h3 style="font-weight: bold; margin: 0;">
                    <i class="fas fa-list"></i> Conceptos Registrados
                </h3>
                <button class="btn-primary" onclick="abrirSubmodalRendicion()">
                    <i class="fas fa-plus"></i> Agregar Concepto
                </button>
            </div>

            <!-- Tabla de Conceptos -->
            <div class="card">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Concepto</th>
                                <th>Nro. Doc</th>
                                <th>Fecha</th>
                                <th>Monto</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="lista-rendiciones">
                            <?php if (empty($conceptos_cliente) && empty($conceptos_agencia)): ?>
                                <tr><td colspan="6" style="text-align: center;">Sin conceptos registrados.</td></tr>
                            <?php else: ?>
                                <?php foreach ($conceptos_cliente as $c): ?>
                                <tr>
                                    <td>Cliente</td>
                                    <td><?= htmlspecialchars($c['concepto_rndcn'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($c['nro_documento_rndcn'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($c['fecha_pago_rndcn'] ?? '') ?></td>
                                    <td><?= number_format($c['monto_pago_rndcn'] ?? 0, 0, ',', '.') ?></td>
                                    <td>
                                        <a href="#" class="btn-edit" title="Editar" onclick="editarRendicion(<?= $c['id_rendicion'] ?>, 'cliente')">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="#" class="btn-delete" title="Eliminar" onclick="confirmarEliminar(<?= $c['id_rendicion'] ?>)">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php foreach ($conceptos_agencia as $c): ?>
                                <tr>
                                    <td>Agencia</td>
                                    <td><?= htmlspecialchars($c['concepto_agencia_rndcn'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($c['nro_documento_rndcn'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($c['fecha_pago_rndcn'] ?? '') ?></td>
                                    <td><?= number_format(($c['monto_gastos_agencia_rndcn'] ?? 0) * 1.19, 0, ',', '.') ?></td>
                                    <td>
                                        <a href="#" class="btn-edit" title="Editar" onclick="editarRendicion(<?= $c['id_rendicion'] ?>, 'agencia')">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="#" class="btn-delete" title="Eliminar" onclick="confirmarEliminar(<?= $c['id_rendicion'] ?>)">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- TOTALES (simple) -->
                <div id="contenedor-totales" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee;">
                    <?php
                    $totalCliente = array_sum(array_column($conceptos_cliente, 'monto_pago_rndcn'));
                    $totalAgencia = array_sum(array_column($conceptos_agencia, 'monto_gastos_agencia_rndcn'));
                    $ivaAgencia = $totalAgencia * 0.19;
                    $totalGastosAgencia = $totalAgencia + $ivaAgencia;
                    $totalRendicion = $totalCliente + $totalGastosAgencia;
                    $saldo = (float)($remesa_seleccionada['total_transferir_rms'] ?? 0) - $totalRendicion;
                    $aFavor = $saldo > 0 ? 'cliente' : ($saldo < 0 ? 'agencia' : 'OK');
                    ?>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-weight: bold; font-size: 0.95rem;">
                        <div style="display: flex; justify-content: space-between;">
                            <span>TOTAL CLIENTE:</span>
                            <span style="color: #2c3e50;"><?= number_format($totalCliente, 0, ',', '.') ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>NETO AGENCIA:</span>
                            <span style="color: #2c3e50;"><?= number_format($totalAgencia, 0, ',', '.') ?></span>
                        </div>
                        <div></div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>IVA 19%:</span>
                            <span style="color: #2c3e50;"><?= number_format($ivaAgencia, 0, ',', '.') ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>SALDO:</span>
                            <span style="color: <?= $saldo > 0 ? '#27ae60' : ($saldo < 0 ? '#e74c3c' : '#3498db') ?>;">
                                <?= number_format(abs($saldo), 0, ',', '.') ?>
                                <?= $aFavor !== 'OK' ? "({$aFavor})" : '' ?>
                            </span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>TOTAL GASTOS AGENCIA:</span>
                            <span style="color: #2c3e50;"><?= number_format($totalGastosAgencia, 0, ',', '.') ?></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- SUBMODAL RENDICIÓN -->
<div id="submodal-rendicion" class="submodal">
    <div class="submodal-content" style="max-width: 650px; padding: 1.8rem; position: relative;">
        <span class="submodal-close" onclick="cerrarSubmodalRendicion()" style="position: absolute; top: 1.2rem; right: 1.2rem;">×</span>
        <h3 style="margin: 0 0 1.4rem 0; font-size: 1.3rem;"><i class="fas fa-receipt"></i> 
            <span id="submodal-titulo">Agregar Concepto</span>
        </h3>

        <input type="hidden" id="id_rendicion_edicion">
        <input type="hidden" id="id_rms_rendicion" value="<?= isset($_GET['seleccionar']) ? (int)$_GET['seleccionar'] : '0' ?>">

        <!-- Selector de grupo -->
        <div style="margin-bottom: 1.4rem; display: flex; gap: 1.5rem; border-bottom: 1px solid #eee; padding-bottom: 0.8rem;">
            <label style="display: flex; align-items: center; gap: 0.4rem;">
                <input type="radio" name="grupo" value="cliente" checked> Gastos Cuenta Cliente
            </label>
            <label style="display: flex; align-items: center; gap: 0.4rem;">
                <input type="radio" name="grupo" value="agencia"> Agenciamento Aduanero
            </label>
        </div>

        <!-- Grupo Cliente -->
        <div id="grupo-cliente">
            <div style="display: grid; grid-template-columns: 120px 1fr; gap: 0.8rem; margin-bottom: 0.8rem;">
                <label>Concepto:</label>
                <input type="text" 
                    id="concepto_rndcn" 
                    list="conceptos-cliente" 
                    style="height: 2.0rem; text-transform: uppercase;">
                <datalist id="conceptos-cliente">
                    <option value="GASTOS AGA">
                    <option value="HONORARIOS">
                    <option value="TRANSM. EDI">
                    <option value="GASTO LOCAL">
                    <option value="FLETE LOCAL">
                    <option value="GATE IN">
                    <option value="GASTOS OPERATIVOS">
                    <option value="PÓLIZA CONTENEDOR">
                    <option value="SEGURO CARGA">
                    <option value="GICONA">
                    <option value="OTROS">
                </datalist>
            </div>
            <div style="display: grid; grid-template-columns: 120px 1fr; gap: 0.8rem; margin-bottom: 0.8rem;">
                <label>Nro. Documento:</label>
                <input type="text" id="nro_documento_rndcn" style="height: 2.0rem;">
            </div>
            <div style="display: grid; grid-template-columns: 120px 1fr; gap: 0.8rem; margin-bottom: 0.8rem;">
                <label>Fecha Pago:</label>
                <input type="date" id="fecha_pago_rndcn" style="height: 2.0rem;">
            </div>
            <div style="display: grid; grid-template-columns: 120px 1fr; gap: 0.8rem;">
                <label>Monto:</label>
                <input type="number" step="0.01" id="monto_pago_rndcn" style="height: 2.0rem;">
            </div>
        </div>

        <!-- Grupo Agencia -->
        <div id="grupo-agencia" style="display: none;">
            <div style="display: grid; grid-template-columns: 140px 1fr; gap: 0.8rem; margin-bottom: 0.8rem;">
                <label>Concepto Agencia:</label>
                <input type="text" 
                    id="concepto_agencia_rndcn" 
                    list="conceptos-agencia" 
                    style="height: 2.0rem; text-transform: uppercase;">
                <datalist id="conceptos-agencia">
                    <option value="HONORARIOS">
                    <option value="GASTOS DESPACHO">
                    <option value="GASTOS OPERATIVOS">
                    <option value="FLETE LOCAL">
                </datalist>
            </div>
            <div style="display: grid; grid-template-columns: 140px 1fr; gap: 0.8rem; margin-bottom: 0.8rem;">
                <label>Nro. Documento:</label>
                <input type="text" id="nro_documento_rndcn_agencia" style="height: 2.0rem;">
            </div>
            <div style="display: grid; grid-template-columns: 140px 1fr; gap: 0.8rem; margin-bottom: 0.8rem;">
                <label>Fecha Pago:</label>
                <input type="date" id="fecha_pago_rndcn_agencia" style="height: 2.0rem;">
            </div>
            <div style="display: grid; grid-template-columns: 140px 1fr; gap: 0.8rem;">
                <label>Monto Agencia:</label>
                <input type="number" step="0.01" id="monto_gastos_agencia_rndcn" style="height: 2.0rem;">
            </div>
        </div>

        <!-- Botones -->
        <div style="text-align: right; margin-top: 1.6rem; display: flex; gap: 0.8rem; justify-content: flex-end;">
            <button type="button" class="btn-primary" onclick="guardarRendicion()" style="padding: 0.55rem 1.4rem;">
                <i class="fas fa-save"></i> <span id="btn-texto">Guardar Concepto</span>
            </button>
            <button type="button" class="btn-secondary" onclick="cerrarSubmodalRendicion()" style="padding: 0.55rem 1.4rem;">
                Cerrar
            </button>
        </div>
    </div>
</div>

<!-- Modal confirmación eliminar -->
<div id="modal-confirm" class="modal">
    <div class="modal-content">
        <span class="close" onclick="cerrarModal()">&times;</span>
        <h3 style="color: #ff9900; margin-bottom: 1rem;"><i class="fas fa-exclamation-triangle"></i> Confirmar Eliminación</h3>
        <p>¿Estás seguro de eliminar este concepto de rendición?</p>
        <div style="margin-top: 1.2rem; display: flex; gap: 1rem; justify-content: center;">
            <button type="button" class="btn-delete" onclick="confirmarEliminarAction()">Eliminar</button>
            <button type="button" class="btn-secondary" onclick="cerrarModal()">Cancelar</button>
        </div>
    </div>
</div>

<script>
let id_rms_actual = <?= isset($_GET['seleccionar']) ? (int)$_GET['seleccionar'] : 'null' ?>;
let id_rendicion_a_eliminar = null;

// === SUBMODAL: alternar grupos ===
document.querySelectorAll('input[name="grupo"]').forEach(radio => {
    radio.addEventListener('change', function() {
        if (this.value === 'cliente') {
            document.getElementById('grupo-cliente').style.display = 'block';
            document.getElementById('grupo-agencia').style.display = 'none';
            document.getElementById('submodal-titulo').innerText = 'Agregar Concepto (Cliente)';
        } else {
            document.getElementById('grupo-cliente').style.display = 'none';
            document.getElementById('grupo-agencia').style.display = 'block';
            document.getElementById('submodal-titulo').innerText = 'Agregar Concepto (Agencia)';
        }
        limpiarFormRendicion();
    });
});

function abrirSubmodalRendicion() {
    if (!id_rms_actual) {
        alert('Error: ID de remesa no disponible.');
        return;
    }
    document.getElementById('id_rendicion_edicion').value = '';
    document.getElementById('btn-texto').innerText = 'Guardar Concepto';
    document.querySelector('input[value="cliente"]').checked = true;
    document.getElementById('grupo-cliente').style.display = 'block';
    document.getElementById('grupo-agencia').style.display = 'none';
    document.getElementById('submodal-titulo').innerText = 'Agregar Concepto (Cliente)';
    limpiarFormRendicion();
    document.getElementById('submodal-rendicion').style.display = 'flex';
}

function limpiarFormRendicion() {
    document.getElementById('concepto_rndcn').value = '';
    document.getElementById('nro_documento_rndcn').value = '';
    document.getElementById('fecha_pago_rndcn').value = '';
    document.getElementById('monto_pago_rndcn').value = '';

    document.getElementById('concepto_agencia_rndcn').value = '';
    document.getElementById('nro_documento_rndcn_agencia').value = '';
    document.getElementById('fecha_pago_rndcn_agencia').value = '';
    document.getElementById('monto_gastos_agencia_rndcn').value = '';
}

function cerrarSubmodalRendicion() {
    document.getElementById('submodal-rendicion').style.display = 'none';
}

function guardarRendicion() {
    const formData = new FormData();
    const id_rendicion = document.getElementById('id_rendicion_edicion').value;
    const id_rms = document.getElementById('id_rms_rendicion').value;
    const grupo = document.querySelector('input[name="grupo"]:checked').value;

    formData.append('action', id_rendicion ? 'actualizar_rendicion' : 'crear_rendicion');
    formData.append('id_rms', id_rms);
    if (id_rendicion) formData.append('id_rendicion', id_rendicion);

    if (grupo === 'cliente') {
        const concepto = document.getElementById('concepto_rndcn').value.trim();
        if (!concepto) {
            alert('Ingrese un concepto de cliente.');
            return;
        }
        formData.append('tipo_concepto', 'cliente');
        formData.append('concepto_rndcn', concepto);
        formData.append('nro_documento_rndcn', document.getElementById('nro_documento_rndcn').value || '');
        formData.append('fecha_pago_rndcn', document.getElementById('fecha_pago_rndcn').value || '');
        formData.append('monto_pago_rndcn', document.getElementById('monto_pago_rndcn').value || 0);
    } else {
        const concepto = document.getElementById('concepto_agencia_rndcn').value.trim();
        if (!concepto) {
            alert('Ingrese un concepto de agencia.');
            return;
        }
        formData.append('tipo_concepto', 'agencia');
        formData.append('concepto_agencia_rndcn', concepto);
        formData.append('nro_documento_rndcn', document.getElementById('nro_documento_rndcn_agencia').value || '');
        formData.append('fecha_pago_rndcn', document.getElementById('fecha_pago_rndcn_agencia').value || '');
        formData.append('monto_gastos_agencia_rndcn', document.getElementById('monto_gastos_agencia_rndcn').value || 0);
    }

    fetch('/pages/rendicion_logic.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('✅ Concepto guardado.');
            cerrarSubmodalRendicion();
            window.location.reload();
        } else {
            alert('❌ ' + (data.message || 'Error al guardar.'));
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('❌ Error de conexión.');
    });
}

function editarRendicion(id, tipo) {
    // En una versión futura, cargar datos vía API.
    alert('Edición no implementada aún. Recargando para ver cambios.');
}

function confirmarEliminar(id) {
    id_rendicion_a_eliminar = id;
    document.getElementById('modal-confirm').style.display = 'flex';
}

function confirmarEliminarAction() {
    if (!id_rendicion_a_eliminar) return;
    
    const formData = new FormData();
    formData.append('action', 'eliminar_rendicion');
    formData.append('id_rendicion', id_rendicion_a_eliminar);

    fetch('/pages/rendicion_logic.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        cerrarModal();
        if (data.success) {
            alert('✅ Concepto eliminado.');
            window.location.reload();
        } else {
            alert('❌ ' + (data.message || 'Error al eliminar.'));
        }
    })
    .catch(err => {
        cerrarModal();
        console.error('Error:', err);
        alert('❌ Error de conexión.');
    });
}

function cerrarModal() {
    document.getElementById('modal-confirm').style.display = 'none';
}
</script>
</body>
</html>