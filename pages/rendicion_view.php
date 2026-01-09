<?php
require_once '../session_check.php';
require_once '../config.php';

$rol = $_SESSION['rol'] ?? '';
if ($rol !== 'admin' && $rol !== 'comercial') {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso denegado.');
}

/* =====================================================
   PARÁMETRO CORREGIDO (id o seleccionar)
===================================================== */
$id_rms = $_GET['id'] ?? $_GET['seleccionar'] ?? null;

$remesa_data = null;
$conceptos_cliente = [];
$conceptos_agencia = [];

if (php_sapi_name() !== 'cli' && $id_rms && is_numeric($id_rms)) {
    try {
        $pdo = getDBConnection();

        /* ===============================
           CARGAR REMESA + CLIENTE
        =============================== */
        $stmt = $pdo->prepare("
            SELECT 
                r.*,
                c.nombre_clt AS cliente_nombre,
                c.rut_clt,
                c.direccion_clt,
                c.ciudad_clt,
                m.mercancia_mrcc AS mercancia_nombre
            FROM remesa r
            LEFT JOIN clientes c ON r.cliente_rms = c.id_clt
            LEFT JOIN mercancias m ON r.mercancia_rms = m.id_mrcc
            WHERE r.id_rms = ?
            LIMIT 1
        ");
        $stmt->execute([$id_rms]);
        $remesa_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($remesa_data) {

            /* ===============================
               CONCEPTOS CLIENTE
            =============================== */
            $stmt = $pdo->prepare("
                SELECT *
                FROM rendicion
                WHERE id_rms = ?
                  AND tipo_concepto = 'cliente'
                ORDER BY item_rendicion
            ");
            $stmt->execute([$id_rms]);
            $conceptos_cliente = $stmt->fetchAll(PDO::FETCH_ASSOC);

            /* ===============================
               CONCEPTOS AGENCIA
            =============================== */
            $stmt = $pdo->prepare("
                SELECT *
                FROM rendicion
                WHERE id_rms = ?
                  AND tipo_concepto = 'agencia'
                ORDER BY item_rendicion
            ");
            $stmt->execute([$id_rms]);
            $conceptos_agencia = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (Throwable $e) {
        error_log("Error al cargar rendición: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Rendición de Gastos - SIGA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <link rel="stylesheet" href="/styles.css">
    <style>
        .valor-ficha { color: #2c3e50; }
        .seccion-titulo {
            background: #f8f9fa;
            padding: 0.6rem;
            margin: 1.2rem 0 0.8rem 0;
            border-left: 3px solid #3498db;
            font-weight: bold;
        }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="container">
    <!-- Título con botón de regreso -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem;">
        <h2 style="font-weight: bold; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-file-invoice"></i> Rendición de Gastos
        </h2>
        <a href="/pages/remesa_lista.php" class="btn-secondary" style="text-decoration: none; padding: 0.4rem 0.8rem;">
            <i class="fas fa-arrow-left"></i> Volver a Remesas
        </a>
    </div>

    <?php if (!$remesa_data): ?>
        <div class="card" style="text-align: center; padding: 2rem;">
            <p>❌ No se encontró la remesa seleccionada.</p>
        </div>
    <?php else: ?>
        <!-- Ficha de Remesa -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div style="display: grid; grid-template-columns: repeat(8, 1fr); gap: 0.6rem; font-size: 0.9rem; align-items: center;">
                <div><strong>CLIENTE:</strong></div>
                <div class="valor-ficha" style="grid-column: span 3;"><?= htmlspecialchars($remesa_data['cliente_nombre'] ?? '') ?></div>
                <div><strong>Rut:</strong></div>
                <div class="valor-ficha"><?= htmlspecialchars($remesa_data['rut_clt'] ?? '') ?></div>
                <div><strong>FECHA:</strong></div>
                <div class="valor-ficha"><?= htmlspecialchars($remesa_data['fecha_rms'] ?? '') ?></div>

                <div><strong>DESPACHO:</strong></div>
                <div class="valor-ficha"><?= htmlspecialchars($remesa_data['despacho_rms'] ?? '') ?></div>
                <div><strong>REF.CLTE.:</strong></div>
                <div class="valor-ficha"><?= htmlspecialchars($remesa_data['ref_clte_rms'] ?? '') ?></div>
                <div><strong>MERCANCÍA:</strong></div>
                <div class="valor-ficha" style="grid-column: span 3;"><?= htmlspecialchars($remesa_data['mercancia_nombre'] ?? '') ?></div>

                <div><strong>TOTAL TRANSFERIDO:</strong></div>
                <div class="valor-ficha"><?= number_format($remesa_data['total_transferir_rms'] ?? 0, 0, ',', '.') ?></div>
                <div></div>
                <div></div>
                <div></div>
                <div></div>
                <div></div>
                <div></div>
            </div>
        </div>

        <!-- Conceptos Cliente -->
        <div class="seccion-titulo">GASTOS CLIENTE</div>
        <div class="card" style="margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: flex-end; margin-bottom: 1rem;">
                <button class="btn-primary" onclick="abrirSubmodalRendicion('cliente')" style="padding: 0.4rem 0.8rem;">
                    <i class="fas fa-plus"></i> Agregar Concepto Cliente
                </button>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Ítem</th>
                            <th>Concepto</th>
                            <th>Monto</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="lista-conceptos-cliente">
                        <?php if (empty($conceptos_cliente)): ?>
                            <tr><td colspan="4" style="text-align: center;">Sin conceptos registrados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($conceptos_cliente as $c): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['item_rendicion'] ?? '') ?></td>
                                <td><?= htmlspecialchars($c['concepto_rndcn'] ?? '') ?></td>
                                <td><?= number_format($c['monto_pago_rndcn'] ?? 0, 0, ',', '.') ?></td>
                                <td>
                                    <a href="#" class="btn-edit" title="Editar" onclick="editarRendicion(<?= $c['id_rendicion'] ?>, 'cliente')">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="#" class="btn-delete" title="Eliminar" onclick="eliminarRendicion(<?= $c['id_rendicion'] ?>)">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Conceptos Agencia -->
        <div class="seccion-titulo">GASTOS AGENCIA</div>
        <div class="card">
            <div style="display: flex; justify-content: flex-end; margin-bottom: 1rem;">
                <button class="btn-primary" onclick="abrirSubmodalRendicion('agencia')" style="padding: 0.4rem 0.8rem;">
                    <i class="fas fa-plus"></i> Agregar Concepto Agencia
                </button>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Ítem</th>
                            <th>Concepto</th>
                            <th>Monto Neto</th>
                            <th>Monto IVA</th>
                            <th>Monto</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="lista-conceptos-agencia">
                        <?php if (empty($conceptos_agencia)): ?>
                            <tr><td colspan="6" style="text-align: center;">Sin conceptos registrados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($conceptos_agencia as $c): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['item_rendicion'] ?? '') ?></td>
                                <td><?= htmlspecialchars($c['concepto_agencia_rndcn'] ?? '') ?></td>
                                <td><?= number_format($c['monto_gastos_agencia_rndcn'] ?? 0, 0, ',', '.') ?></td>
                                <td><?= number_format(($c['monto_gastos_agencia_rndcn'] ?? 0) * 0.19, 0, ',', '.') ?></td>
                                <td><?= number_format(($c['monto_gastos_agencia_rndcn'] ?? 0) * 1.19, 0, ',', '.') ?></td>
                                <td>
                                    <a href="#" class="btn-edit" title="Editar" onclick="editarRendicion(<?= $c['id_rendicion'] ?>, 'agencia')">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="#" class="btn-delete" title="Eliminar" onclick="eliminarRendicion(<?= $c['id_rendicion'] ?>)">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Submodal Rendición -->
<div id="submodal-rendicion" class="submodal">
    <div class="submodal-content" style="max-width: 600px; padding: 1.8rem; position: relative;">
        <span class="submodal-close" onclick="cerrarSubmodalRendicion()" style="position: absolute; top: 1.2rem; right: 1.2rem;">×</span>
        <h3 style="margin: 0 0 1.4rem 0; font-size: 1.3rem;" id="submodal-titulo">
            <i class="fas fa-file-invoice"></i> Agregar Concepto
        </h3>

        <form id="form-rendicion">
            <input type="hidden" id="id_rms" value="<?= (int)($id_rms ?? 0) ?>">
            <input type="hidden" id="id_rendicion">
            <input type="hidden" id="tipo_concepto">

            <div style="margin-bottom: 1.2rem;">
                <label style="display: block; margin-bottom: 0.4rem;">Ítem:</label>
                <input type="number" id="item_rendicion" style="width: 6ch; height: 2.3rem; padding: 0.3rem;">
            </div>

            <div style="margin-bottom: 1.2rem;">
                <label style="display: block; margin-bottom: 0.4rem;" id="label-concepto">Concepto:</label>
                <input type="text" id="concepto_rendicion" style="width: 100%; height: 2.3rem; padding: 0.3rem;">
            </div>

            <div style="margin-bottom: 1.2rem;">
                <label style="display: block; margin-bottom: 0.4rem;" id="label-monto">Monto:</label>
                <input type="number" id="monto_rendicion" style="width: 100%; height: 2.3rem; padding: 0.3rem;">
            </div>

            <div style="text-align: right; display: flex; gap: 0.8rem; justify-content: flex-end;">
                <button type="button" class="btn-primary" onclick="guardarRendicion()" style="padding: 0.55rem 1.4rem;">
                    <i class="fas fa-save"></i> Guardar Concepto
                </button>
                <button type="button" class="btn-secondary" onclick="cerrarSubmodalRendicion()" style="padding: 0.55rem 1.4rem;">
                    Cerrar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirSubmodalRendicion(tipo) {
    document.getElementById('tipo_concepto').value = tipo;
    document.getElementById('submodal-titulo').innerText = 
        'Agregar Concepto ' + (tipo === 'cliente' ? 'Cliente' : 'Agencia');
    
    const labelConcepto = document.getElementById('label-concepto');
    const labelMonto = document.getElementById('label-monto');
    
    if (tipo === 'cliente') {
        labelConcepto.innerText = 'Concepto Cliente:';
        labelMonto.innerText = 'Monto Pago Cliente:';
    } else {
        labelConcepto.innerText = 'Concepto Agencia:';
        labelMonto.innerText = 'Monto Gasto Agencia (Neto):';
    }
    
    // Limpiar formulario
    document.getElementById('id_rendicion').value = '';
    document.getElementById('item_rendicion').value = '';
    document.getElementById('concepto_rendicion').value = '';
    document.getElementById('monto_rendicion').value = '';
    
    document.getElementById('submodal-rendicion').style.display = 'flex';
}

function cerrarSubmodalRendicion() {
    document.getElementById('submodal-rendicion').style.display = 'none';
}

function guardarRendicion() {
    const formData = new FormData();
    formData.append('action', document.getElementById('id_rendicion').value ? 'actualizar_rendicion' : 'crear_rendicion');
    formData.append('id_rms', document.getElementById('id_rms').value);
    formData.append('tipo_concepto', document.getElementById('tipo_concepto').value);
    formData.append('item_rendicion', document.getElementById('item_rendicion').value);
    formData.append('concepto_rendicion', document.getElementById('concepto_rendicion').value);
    formData.append('monto_rendicion', document.getElementById('monto_rendicion').value);
    if (document.getElementById('id_rendicion').value) {
        formData.append('id_rendicion', document.getElementById('id_rendicion').value);
    }

    fetch('/pages/rendicion_logic.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✅ Concepto guardado.');
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
    // En producción, cargar datos vía API. Aquí recargamos para simplicidad.
    alert('Edición no implementada en esta versión (reimplementar con API si es necesario).');
}

function eliminarRendicion(id) {
    if (confirm('¿Eliminar este concepto de rendición?')) {
        const formData = new FormData();
        formData.append('action', 'eliminar_rendicion');
        formData.append('id_rendicion', id);

        fetch('/pages/rendicion_logic.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Concepto eliminado.');
                    window.location.reload();
                } else {
                    alert('❌ ' + data.message);
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('❌ Error de conexión.');
            });
    }
}
</script>
</body>
</html>