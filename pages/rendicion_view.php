<?php
require_once '../session_check.php';
require_once '../config.php';

$rol = $_SESSION['rol'] ?? 'usuario';
if ($rol !== 'admin' && $rol !== 'comercial') {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso denegado.');
}

// Evitar advertencias en producción
if (php_sapi_name() !== 'cli') {
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
}

// === Modo edición: cargar remesa y conceptos ===
$remesa_seleccionada = null;
$conceptos_cliente = [];
$conceptos_agencia = [];

if (isset($_GET['seleccionar'])) {
    $id_rms = (int)$_GET['seleccionar'];
    if ($id_rms > 0 && php_sapi_name() !== 'cli') {
        try {
            $pdo = getDBConnection();
            // Cargar remesa
            $stmt = $pdo->prepare("
                SELECT 
                    r.*,
                    c.nombre_clt AS cliente_nombre,
                    c.rut_clt,
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
                // Conceptos cliente
                $stmt = $pdo->prepare("SELECT * FROM rendicion WHERE id_rms = ? AND concepto_rndcn IS NOT NULL ORDER BY id_rndcn");
                $stmt->execute([$id_rms]);
                $conceptos_cliente = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Conceptos agencia
                $stmt = $pdo->prepare("SELECT * FROM rendicion WHERE id_rms = ? AND concepto_agencia_rndcn IS NOT NULL ORDER BY id_rndcn");
                $stmt->execute([$id_rms]);
                $conceptos_agencia = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $e) {
            error_log("Error en rendicion_view (edición): " . $e->getMessage());
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
    <style>
        .valor-ficha { color: #2c3e50; }
        #resultados-busqueda {
            position: absolute;
            z-index: 1000;
            background: white;
            border: 1px solid #ccc;
            max-height: 200px;
            overflow-y: auto;
            width: calc(100% - 2rem);
            margin-top: 0.2rem;
        }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="container">
    <?php if (!isset($_GET['seleccionar'])): ?>
        <!-- === MODO SELECCIÓN DE REMESA === -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem;">
            <h2 style="font-weight: bold; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-receipt"></i> Rendición de Gastos
            </h2>
            <a href="/pages/rendicion_listas.php" class="btn-secondary" style="padding: 0.4rem 0.8rem;">
                <i class="fas fa-arrow-left"></i> Volver a Lista
            </a>
        </div>

        <div class="card" style="margin-bottom: 1.5rem;">
            <h3 style="margin: 0 0 1rem 0; font-weight: bold;">Seleccionar Remesa para Rendir</h3>
            <div style="display: flex; gap: 0.8rem; margin-bottom: 1rem;">
                <input type="text" 
                    id="busqueda-inteligente" 
                    placeholder="Buscar por cliente, ref. clte, mercancía..." 
                    style="flex: 1; padding: 0.6rem; border: 1px solid #ccc; border-radius: 4px;">
                <button class="btn-primary" onclick="buscarRemesa()">Buscar</button>
            </div>
            <div id="resultados-busqueda" style="display: none;"></div>
        </div>

        <!-- Ficha y tabla (iniciales ocultos) -->
        <div id="ficha-remesa" class="card" style="display: none; margin-bottom: 1.5rem;"></div>
        <div id="tabla-conceptos-wrapper" style="display: none;"></div>
        <button id="btn-pdf-rendicion" class="btn-secondary" onclick="generarPDFRendicion()" style="display: none; margin-top: 1rem;">
            <i class="fas fa-file-pdf"></i> Generar PDF Rendición
        </button>

    <?php else: ?>
        <!-- === MODO EDICIÓN === -->
        <?php if (!$remesa_seleccionada): ?>
            <div class="card" style="text-align: center; padding: 2rem;">
                <p>❌ No se encontró la remesa seleccionada.</p>
                <a href="/pages/rendicion_view.php" class="btn-secondary" style="margin-top: 1rem;">Seleccionar otra remesa</a>
            </div>
        <?php else: ?>
            <!-- === TÍTULO + CERRAR === -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem;">
                <h2 style="font-weight: bold; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-receipt"></i> Rendición de Gastos
                </h2>
                <a href="/pages/rendicion_listas.php" class="btn-secondary" style="padding: 0.3rem 0.7rem; font-size: 1.2rem; text-decoration: none;">
                    &times;
                </a>
            </div>
            <!-- Ficha de Remesa -->
            <div id="ficha-remesa" class="card" style="margin-bottom: 1.5rem;">
                <div style="display: grid; grid-template-columns: repeat(8, 1fr); gap: 0.6rem; font-size: 0.9rem; align-items: center;">
                    <!-- Fila1 -->
                    <div><strong>CLIENTE:</strong></div>
                    <div class="valor-ficha" id="cliente_ficha" style="grid-column: span 3;"><?= htmlspecialchars($remesa_seleccionada['cliente_nombre'] ?? '') ?></div>
                    <div><strong>Rut:</strong></div>
                    <div class="valor-ficha"><?= htmlspecialchars($remesa_seleccionada['rut_clt'] ?? '') ?></div>
                    <div><strong>FECHA:</strong></div>
                    <div class="valor-ficha"><?= htmlspecialchars($remesa_seleccionada['fecha_rms'] ?? '') ?></div>
                    <!-- Fila2 -->
                    <div><strong>REF.CLTE.:</strong></div>
                    <div class="valor-ficha" style="grid-column: span 3;"><?= htmlspecialchars($remesa_seleccionada['ref_clte_rms'] ?? '') ?></div>
                    <div><strong>DESPACHO:</strong></div>
                    <div class="valor-ficha"><?= htmlspecialchars($remesa_seleccionada['despacho_rms'] ?? '') ?></div>
                    <div><strong>TOTAL REMESA:</strong></div>
                    <div class="valor-ficha"><?= number_format($remesa_seleccionada['total_transferir_rms'] ?? 0, 0, ',', '.') ?></div>
                    <!-- Fila2 -->
                    <div><strong>MERCANCÍA:</strong></div>
                    <div class="valor-ficha" style="grid-column: span 2;"><?= htmlspecialchars($remesa_seleccionada['mercancia_nombre'] ?? '') ?></div>

                    
                </div>
            </div>

            <!-- Botones -->
            <div style="display: flex; justify-content: space-between; margin-bottom: 1.5rem; align-items: center;">
                <h3 style="font-weight: bold; margin: 0;"><i class="fas fa-list"></i> Conceptos Registrados</h3>
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
                                    <td><?= htmlspecialchars($c['fecha_rndcn'] ?? '') ?></td>
                                    <td><?= number_format($c['monto_pago_rndcn'] ?? 0, 0, ',', '.') ?></td>
                                    <td>
                                        <a href="#" class="btn-edit" title="Editar" onclick="editarRendicion(<?= (int)($c['id_rndcn'] ?? 0) ?>, 'cliente')">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="#" class="btn-delete" title="Eliminar" onclick="confirmarEliminar(<?= (int)($c['id_rndcn'] ?? 0) ?>)">
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
                                    <td><?= htmlspecialchars($c['fecha_rndcn'] ?? '') ?></td>
                                    <td><?= number_format(($c['monto_gastos_agencia_rndcn'] ?? 0) * 1.19, 0, ',', '.') ?></td>
                                    <td>
                                        <a href="#" class="btn-edit" title="Editar" onclick="editarRendicion(<?= (int)($c['id_rndcn'] ?? 0) ?>, 'agencia')">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="#" class="btn-delete" title="Eliminar" onclick="confirmarEliminar(<?= (int)($c['id_rndcn'] ?? 0) ?>)">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- TOTALES -->
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

                    <div style="display: grid; grid-template-columns: repeat(8, 1fr); gap: 0.6rem; font-size: 0.9rem; align-items: center; font-weight: bold;">
                        <!-- Fila 1 -->
                        <div></div>
                        <div><label style="margin: 0;">Total Clte.:</label></div>
                        <div style="color: #2c3e50;"><?= number_format($totalCliente, 0, ',', '.') ?></div>
                        <div></div>
                        <div></div>
                        <div><label style="margin: 0;">Neto:</label></div>
                        <div style="color: #2c3e50;"><?= number_format($totalAgencia, 0, ',', '.') ?></div>
                        <div></div>

                        <!-- Fila 2 -->
                        <div></div>
                        <div></div>
                        <div></div>
                        <div></div>
                        <div></div>
                        <div><label style="margin: 0;">Iva:</label></div>
                        <div style="color: #2c3e50;"><?= number_format($ivaAgencia, 0, ',', '.') ?></div>
                        <div></div>

                        <!-- Fila 3 -->
                        <div></div>
                        <div><label style="margin: 0;">Total Liquidación:</label></div>
                        <div style="color: #2c3e50;"><?= number_format($totalRendicion, 0, ',', '.') ?></div>
                        <div></div>
                        <div></div>
                        <div><label style="margin: 0;">Total Agencia:</label></div>
                        <div style="color: #2c3e50;"><?= number_format($totalGastosAgencia, 0, ',', '.') ?></div>
                        <div></div>
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
                <input type="text" id="concepto_rndcn" list="conceptos-cliente" style="height: 2.0rem; text-transform: uppercase;">
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
                <input type="text" id="concepto_agencia_rndcn" list="conceptos-agencia" style="height: 2.0rem; text-transform: uppercase;">
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

// === BÚSQUEDA INTELIGENTE (modo selección) ===
function buscarRemesa() {
    const term = document.getElementById('busqueda-inteligente').value.trim();
    if (!term) return;
    fetch(`/api/buscar_remesas.php?term=${encodeURIComponent(term)}`)
        .then(res => res.json())
        .then(data => {
            const div = document.getElementById('resultados-busqueda');
            div.innerHTML = '';
            if (data.length > 0) {
                data.forEach(r => {
                    const d = document.createElement('div');
                    d.style.padding = '0.8rem';
                    d.style.cursor = 'pointer';
                    d.style.borderBottom = '1px solid #eee';
                    d.innerHTML = `<strong>${r.cliente_nombre || 'ID: ' + r.cliente_rms}</strong><br>
                                  <small>
                                    Mercancía: ${r.mercancia_nombre || '–'} | 
                                    Ref.Clte: ${r.ref_clte_rms || '–'} | 
                                    Fecha: ${r.fecha_rms}
                                  </small>`;
                    d.onclick = () => {
                        cargarFichaRemesa(r.id_rms);
                        div.style.display = 'none';
                    };
                    div.appendChild(d);
                });
                div.style.display = 'block';
            }
        })
        .catch(err => {
            console.error('Error en búsqueda:', err);
        });
}

// === CARGAR FICHA DESDE API ===
function cargarFichaRemesa(id_rms) {
    fetch(`/api/get_remesa.php?id=${id_rms}`)
        .then(res => res.json())
        .then(data => {
            if (!data) return;

            // Crear ficha dinámica
            const ficha = `
                <div style="display: grid; grid-template-columns: repeat(8, 1fr); gap: 0.6rem; font-size: 0.9rem; align-items: center;">
                    <div><strong>CLIENTE:</strong></div>
                    <div class="valor-ficha" id="cliente_ficha" style="grid-column: span 3;">${data.cliente_nombre || '–'}</div>
                    <div><strong>Rut:</strong></div>
                    <div class="valor-ficha">${data.rut_clt || '–'}</div>
                    <div><strong>FECHA:</strong></div>
                    <div class="valor-ficha">${data.fecha_rms || '–'}</div>

                    <div><strong>DESPACHO:</strong></div>
                    <div class="valor-ficha">${data.despacho_rms || '–'}</div>
                    <div><strong>REF.CLTE.:</strong></div>
                    <div class="valor-ficha">${data.ref_clte_rms || '–'}</div>
                    <div><strong>MERCANCÍA:</strong></div>
                    <div class="valor-ficha" style="grid-column: span 2;">${data.mercancia_nombre || '–'}</div>

                    <div><strong>TOTAL TRANSFERIDO:</strong></div>
                    <div class="valor-ficha">${new Intl.NumberFormat('es-CL').format(data.total_transferir_rms || 0)}</div>
                </div>
            `;
            document.getElementById('ficha-remesa').innerHTML = ficha;
            document.getElementById('ficha-remesa').style.display = 'block';

            // Mostrar botones y tabla vacía
            const tabla = `
                <div style="display: flex; justify-content: space-between; margin-bottom: 1.5rem; align-items: center;">
                    <h3 style="font-weight: bold; margin: 0;"><i class="fas fa-list"></i> Conceptos Registrados</h3>
                    <button class="btn-primary" onclick="abrirSubmodalRendicion()">
                        <i class="fas fa-plus"></i> Agregar Concepto
                    </button>
                </div>
                <div class="card">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Tipo</th><th>Concepto</th><th>Nro. Doc</th><th>Fecha</th><th>Monto</th><th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="lista-rendiciones">
                                <tr><td colspan="6" style="text-align: center;">Sin conceptos registrados.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="contenedor-totales" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee;"></div>
                </div>
            `;
            document.getElementById('tabla-conceptos-wrapper').innerHTML = tabla;
            document.getElementById('tabla-conceptos-wrapper').style.display = 'block';
            document.getElementById('btn-pdf-rendicion').style.display = 'inline-block';

            id_rms_actual = id_rms;
            document.getElementById('id_rms_rendicion').value = id_rms;

            // Cargar conceptos reales (si existen)
            cargarRendiciones(id_rms);
        });
}

// === CARGAR RENDICIONES DESDE API ===
function cargarRendiciones(id_rms) {
    fetch(`/api/get_rendiciones.php?id=${id_rms}`)
        .then(res => res.json())
        .then(rendiciones => {
            const tbody = document.getElementById('lista-rendiciones');
            if (!tbody) return;
            if (rendiciones.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Sin conceptos registrados.</td></tr>';
                return;
            }

            tbody.innerHTML = rendiciones.map(r => {
                const tipo = r.concepto_rndcn ? 'Cliente' : 'Agencia';
                const concepto = r.concepto_rndcn || r.concepto_agencia_rndcn || '–';
                const monto = r.concepto_rndcn ? parseFloat(r.monto_pago_rndcn) : parseFloat(r.monto_gastos_agencia_rndcn) * 1.19;
                return `
                    <tr>
                        <td>${tipo}</td>
                        <td>${concepto}</td>
                        <td>${r.nro_documento_rndcn || '-'}</td>
                        <td>${r.fecha_rndcn || '-'}</td>
                        <td>${new Intl.NumberFormat('es-CL').format(monto)}</td>
                        <td>
                            <a href="#" class="btn-edit" title="Editar" onclick="editarRendicion(${r.id_rndcn}, '${tipo === 'Cliente' ? 'cliente' : 'agencia'}')">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="#" class="btn-delete" title="Eliminar" onclick="confirmarEliminar(${r.id_rndcn})">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                        </td>
                    </tr>
                `;
            }).join('');

            // Actualizar totales
            actualizarTotales(rendiciones);
        });
}

// === ACTUALIZAR TOTALES ===
function actualizarTotales(rendiciones) {
    const contenedor = document.getElementById('contenedor-totales');
    if (!contenedor) return;

    let totalCliente = 0;
    let totalAgencia = 0;
    rendiciones.forEach(r => {
        if (r.concepto_rndcn) {
            totalCliente += parseFloat(r.monto_pago_rndcn) || 0;
        } else {
            totalAgencia += parseFloat(r.monto_gastos_agencia_rndcn) || 0;
        }
    });

    const netoAgencia = totalAgencia;
    const ivaAgencia = netoAgencia * 0.19;
    const totalGastosAgencia = netoAgencia + ivaAgencia;
    const totalRendicion = totalCliente + totalGastosAgencia;

    // ✅ Extraer total_transferir desde el HTML (regex corregida)
    let totalTransferir = 0;
    try {
        const fichaHTML = document.querySelector('#cliente_ficha').closest('.card').innerHTML;
        const match = fichaHTML.match(/TOTAL TRANSFERIDO.+?(\d{1,3}(?:\.\d{3})*)/);
        if (match && match[1]) {
            totalTransferir = parseFloat(match[1].replace(/\./g, ''));
        }
    } catch (e) {
        console.warn('No se pudo extraer total_transferir:', e);
    }

    const saldo = totalTransferir - totalRendicion;
    const aFavor = saldo > 0 ? 'cliente' : (saldo < 0 ? 'agencia' : 'OK');

    // Formatear números
    const format = (num) => new Intl.NumberFormat('es-CL').format(Math.round(num));

    contenedor.innerHTML = `
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-weight: bold; font-size: 0.95rem;">
            <div style="display: flex; justify-content: space-between;">
                <span>TOTAL CLIENTE:</span>
                <span style="color: #2c3e50;">${format(totalCliente)}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span>NETO AGENCIA:</span>
                <span style="color: #2c3e50;">${format(netoAgencia)}</span>
            </div>
            <div></div>
            <div style="display: flex; justify-content: space-between;">
                <span>IVA 19%:</span>
                <span style="color: #2c3e50;">${format(ivaAgencia)}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span>SALDO:</span>
                <span style="color: ${saldo > 0 ? '#27ae60' : (saldo < 0 ? '#e74c3c' : '#3498db')};">
                    ${format(Math.abs(saldo))}
                    ${aFavor !== 'OK' ? `(${aFavor})` : ''}
                </span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span>TOTAL GASTOS AGENCIA:</span>
                <span style="color: #2c3e50;">${format(totalGastosAgencia)}</span>
            </div>
        </div>
    `;
}

// === SUBMODAL: funciones generales ===
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
        alert('Seleccione una remesa primero.');
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

// === EDITAR: cargar desde API ===
function editarRendicion(id, tipo) {
    fetch(`/pages/rendicion_logic.php?action=obtener&id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (!data || data.success === false) {
                alert('❌ No se pudo cargar el concepto.');
                return;
            }

            document.getElementById('id_rendicion_edicion').value = data.id_rndcn;
            document.getElementById('id_rms_rendicion').value = data.id_rms;

            if (data.concepto_rndcn) {
                document.querySelector('input[value="cliente"]').checked = true;
                document.getElementById('grupo-cliente').style.display = 'block';
                document.getElementById('grupo-agencia').style.display = 'none';
                document.getElementById('submodal-titulo').innerText = 'Editar Concepto (Cliente)';
                document.getElementById('btn-texto').innerText = 'Actualizar Cliente';

                document.getElementById('concepto_rndcn').value = data.concepto_rndcn || '';
                document.getElementById('nro_documento_rndcn').value = data.nro_documento_rndcn || '';
                document.getElementById('fecha_pago_rndcn').value = data.fecha_rndcn || '';
                document.getElementById('monto_pago_rndcn').value = data.monto_pago_rndcn || '';
            } else {
                document.querySelector('input[value="agencia"]').checked = true;
                document.getElementById('grupo-cliente').style.display = 'none';
                document.getElementById('grupo-agencia').style.display = 'block';
                document.getElementById('submodal-titulo').innerText = 'Editar Concepto (Agencia)';
                document.getElementById('btn-texto').innerText = 'Actualizar Agencia';

                document.getElementById('concepto_agencia_rndcn').value = data.concepto_agencia_rndcn || '';
                document.getElementById('nro_documento_rndcn_agencia').value = data.nro_documento_rndcn || '';
                document.getElementById('fecha_pago_rndcn_agencia').value = data.fecha_rndcn || '';
                document.getElementById('monto_gastos_agencia_rndcn').value = data.monto_gastos_agencia_rndcn || '';
            }

            document.getElementById('submodal-rendicion').style.display = 'flex';
        })
        .catch(err => {
            console.error('Error al cargar concepto:', err);
            alert('❌ Error al cargar el concepto para edición.');
        });
}

// === GUARDAR ===
function guardarRendicion() {
    const formData = new FormData();
    const id_rendicion = document.getElementById('id_rendicion_edicion').value;
    const id_rms = document.getElementById('id_rms_rendicion').value;
    const grupo = document.querySelector('input[name="grupo"]:checked').value;

    formData.append('id_rms', id_rms);
    if (id_rendicion) {
        formData.append('action', 'actualizar_rendicion');
        formData.append('id_rndcn', id_rendicion);
    } else {
        formData.append('action', 'crear_rendicion');
    }

    if (grupo === 'cliente') {
        const concepto = document.getElementById('concepto_rndcn').value.trim();
        if (!concepto) {
            alert('Ingrese un concepto de cliente.');
            return;
        }
        formData.append('tipo_concepto', 'cliente');
        formData.append('concepto_rendicion', concepto);
        formData.append('nro_documento_rndcn', document.getElementById('nro_documento_rndcn').value || '');
        formData.append('fecha_rndcn', document.getElementById('fecha_pago_rndcn').value || '');
        formData.append('monto_rendicion', document.getElementById('monto_pago_rndcn').value || 0);
    } else {
        const concepto = document.getElementById('concepto_agencia_rndcn').value.trim();
        if (!concepto) {
            alert('Ingrese un concepto de agencia.');
            return;
        }
        formData.append('tipo_concepto', 'agencia');
        formData.append('concepto_rendicion', concepto);
        formData.append('nro_documento_rndcn', document.getElementById('nro_documento_rndcn_agencia').value || '');
        formData.append('fecha_rndcn', document.getElementById('fecha_pago_rndcn_agencia').value || '');
        formData.append('monto_rendicion', document.getElementById('monto_gastos_agencia_rndcn').value || 0);
    }

    fetch('/pages/rendicion_logic.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('✅ Concepto guardado correctamente.');
                if (!id_rendicion) {
                    limpiarFormRendicion();
                }
                // Recargar tabla
                if (id_rms_actual) {
                    cargarRendiciones(id_rms_actual);
                }
            } else {
                alert('❌ ' + (data.message || 'Error al guardar.'));
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('❌ Error de conexión.');
        });
}

// === ELIMINAR ===
function confirmarEliminar(id) {
    id_rendicion_a_eliminar = id;
    document.getElementById('modal-confirm').style.display = 'flex';
}

function confirmarEliminarAction() {
    if (!id_rendicion_a_eliminar) return;
    const formData = new FormData();
    formData.append('action', 'eliminar_rendicion');
    formData.append('id_rndcn', id_rendicion_a_eliminar);

    fetch('/pages/rendicion_logic.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            cerrarModal();
            if (data.success) {
                alert('✅ Concepto eliminado.');
                if (id_rms_actual) {
                    cargarRendiciones(id_rms_actual);
                }
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

// Cerrar resultados de búsqueda
document.addEventListener('click', function(e) {
    const resultados = document.getElementById('resultados-busqueda');
    const input = document.getElementById('busqueda-inteligente');
    if (resultados && input && !resultados.contains(e.target) && e.target !== input) {
        resultados.style.display = 'none';
    }
});

// PDF
function generarPDFRendicion() {
    if (!id_rms_actual) {
        alert('Seleccione una remesa primero.');
        return;
    }
    window.open(`/pages/generar_pdf_rendicion.php?id=${id_rms_actual}`, '_blank');
}

// === Activar búsqueda al escribir (manteniendo tu función existente) ===
document.getElementById('busqueda-inteligente').addEventListener('input', function() {
    const term = this.value.trim();
    if (term.length >= 2) {
        buscarRemesa();
    } else {
        document.getElementById('resultados-busqueda').style.display = 'none';
    }
});

// === NOTIFICACIÓN ESTILO CRM_ELOG ===
function mostrarNotificacion(mensaje, tipo = 'success') {
    let contenedor = document.getElementById('notificaciones-container');
    if (!contenedor) {
        contenedor = document.createElement('div');
        contenedor.id = 'notificaciones-container';
        contenedor.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        `;
        document.body.appendChild(contenedor);
    }

    const notif = document.createElement('div');
    notif.innerText = mensaje;
    notif.style.cssText = `
        padding: 12px 20px;
        border-radius: 6px;
        color: white;
        font-weight: bold;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideIn 0.3s ease, fadeOut 0.5s ease 2.5s forwards;
        ${tipo === 'success' ? 'background: #27ae60;' : 'background: #e74c3c;'}
    `;

    // Añadir estilos de animación si no existen
    if (!document.getElementById('notificaciones-styles')) {
        const style = document.createElement('style');
        style.id = 'notificaciones-styles';
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes fadeOut {
                from { opacity: 1; }
                to { opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    }

    contenedor.appendChild(notif);
    setTimeout(() => {
        if (notif.parentNode) notif.parentNode.removeChild(notif);
    }, 3000);
}
</script>
</body>
</html>