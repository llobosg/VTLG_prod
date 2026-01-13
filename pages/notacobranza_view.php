<?php
require_once '../session_check.php';
require_once '../config.php';

$rol = $_SESSION['rol'] ?? '';
if ($rol !== 'admin' && $rol !== 'comercial') {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso denegado.');
}

// Parámetros
$id_cabecera = $_GET['id'] ?? null;
$cabecera = null;
$remesa_data = null;

// Valores por defecto
$cliente_nombre = '';
$rut_clt = '';
$direccion_clt = '';
$ciudad_clt = '';
$despacho_rms = '';
$ref_clte_rms = '';
$mercancia_nombre = '';
$contacto_rms = '';
$fecha_rms = '';
$mes_rms = '';
$estado_rms = '';
$total_transferir_rms = 0;
$id_rms_para_rendicion = 0;
$nro_nc = '';
$concepto_nc = '';

// Cargar datos solo en contexto web (no durante build)
if (php_sapi_name() !== 'cli') {
    try {
        $pdo = getDBConnection();
        if ($pdo && $id_cabecera) {
            $stmt = $pdo->prepare("SELECT * FROM notacobranza WHERE id_cabecera = ?");
            $stmt->execute([$id_cabecera]);
            $cabecera = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($cabecera) {
                $stmt = $pdo->prepare("
                    SELECT 
                        r.*,
                        c.nombre_clt AS cliente_nombre,
                        c.rut_clt,
                        c.direccion_clt,
                        c.ciudad_clt
                    FROM remesa r
                    LEFT JOIN clientes c ON r.cliente_rms = c.id_clt
                    WHERE r.id_rms = ?
                ");
                $stmt->execute([$cabecera['id_rms_nc']]);
                $remesa_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($remesa_data) {
                    $cliente_nombre = $remesa_data['cliente_nombre'] ?? '';
                    $rut_clt = $remesa_data['rut_clt'] ?? '';
                    $direccion_clt = $remesa_data['direccion_clt'] ?? '';
                    $ciudad_clt = $remesa_data['ciudad_clt'] ?? '';
                    $despacho_rms = $remesa_data['despacho_rms'] ?? '';
                    $ref_clte_rms = $remesa_data['ref_clte_rms'] ?? '';
                    $mercancia_nombre = $remesa_data['mercancia_nombre'] ?? '';
                    $contacto_rms = $remesa_data['contacto_rms'] ?? '';
                    $fecha_rms = $remesa_data['fecha_rms'] ?? '';
                    $mes_rms = $remesa_data['mes_rms'] ?? '';
                    $estado_rms = $remesa_data['estado_rms'] ?? '';
                    $total_transferir_rms = (float)($remesa_data['total_transferir_rms'] ?? 0);
                    $id_rms_para_rendicion = (int)$remesa_data['id_rms'];
                    $nro_nc = $cabecera['nro_nc'] ?? '';
                    $concepto_nc = $cabecera['concepto_nc'] ?? '';
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error al cargar nota de cobranza: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= $id_cabecera ? 'Editar Nota de Cobranza' : 'Nueva Nota de Cobranza' ?> - SIGA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <link rel="stylesheet" href="/styles.css">
    <style>
        .valor-ficha { color: #2c3e50; }
        #resultados-busqueda {
            position: absolute; z-index: 3000; background: white; border: 1px solid #ddd;
            border-top: none; max-height: 300px; overflow-y: auto; width: 100%;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: none;
        }
        #resultados-busqueda div:hover { background: #f0f0f0; }
        .busqueda-container { position: relative; margin-bottom: 1.5rem; }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="container">
    <!-- Título con botón X -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem;">
        <h2 style="font-weight: bold; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-file-invoice-dollar"></i> Nota de Cobranza
        </h2>
        <div style="display: flex; gap: 0.8rem; align-items: center;">
            <a href="/pages/notacobranza_lista.php" style="font-size: 1.4rem; color: #777; text-decoration: none; margin-right: 10px;" title="Volver a la lista">
                &times;
            </a>
            <button class="btn-secondary" id="btn-pdf" onclick="generarPDFNotacobranza()" style="display: <?= $id_cabecera ? 'inline-block' : 'none' ?>;">
                <i class="fas fa-file-pdf"></i> PDF Nota Cobranza
            </button>
        </div>
    </div>

    <script>
    // === BÚSQUEDA INTELIGENTE (solo en modo nueva nota) ===
    document.getElementById('busqueda-inteligente').addEventListener('input', function() {
        const term = this.value.trim();
        const div = document.getElementById('resultados-busqueda');
        div.style.display = 'none';
        div.innerHTML = '';

        if (term.length < 2) return;

        // Debounce simple
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
            fetch(`/api/buscar_remesas.php?term=${encodeURIComponent(term)}`)
                .then(res => res.json())
                .then(data => {
                    if (!Array.isArray(data)) {
                        console.error('Respuesta inválida de API:', data);
                        return;
                    }
                    if (data.length === 0) return;

                    data.forEach(r => {
                        const el = document.createElement('div');
                        el.style.padding = '0.8rem';
                        el.style.cursor = 'pointer';
                        el.style.borderBottom = '1px solid #eee';
                        el.innerHTML = `<strong>${r.cliente_nombre || 'ID: ' + r.cliente_rms}</strong><br>
                                    <small>
                                        Mercancía: ${r.mercancia_nombre || '–'} | 
                                        Ref.Clte: ${r.ref_clte_rms || '–'} | 
                                        Fecha: ${r.fecha_rms}
                                    </small>`;
                        el.onclick = () => {
                            crearNotaCobranza(r.id_rms);
                            div.style.display = 'none';
                            this.value = '';
                        };
                        div.appendChild(el);
                    });
                    div.style.display = 'block';
                })
                .catch(err => {
                    console.error('Error en búsqueda:', err);
                    alert('❌ Error al buscar remesas.');
                });
        }, 300);
    });

    // Cerrar resultados al hacer clic fuera
    document.addEventListener('click', function(e) {
        const resultados = document.getElementById('resultados-busqueda');
        const input = document.getElementById('busqueda-inteligente');
        if (resultados && !resultados.contains(e.target) && e.target !== input) {
            resultados.style.display = 'none';
        }
    });
    </script>

    <!-- Ficha de Nota de Cobranza -->
    <div id="ficha-remesa" style="display: <?= $id_cabecera ? 'block' : 'none' ?>;" class="card" style="margin-bottom: 1.5rem;">
        <div style="display: grid; grid-template-columns: repeat(8, 1fr); gap: 0.6rem; font-size: 0.9rem; align-items: center;">
            <!-- Fila 1 -->
            <div><strong>CLIENTE:</strong></div>
            <div class="valor-ficha" id="cliente_ficha" style="grid-column: span 3;"><?= htmlspecialchars($cliente_nombre) ?></div>
            <div><strong>CONTACTO:</strong></div>
            <div class="valor-ficha"><?= htmlspecialchars($contacto_rms) ?></div>
            <div><strong>FECHA:</strong></div>
            <div class="valor-ficha"><?= htmlspecialchars($fecha_rms) ?></div>

            <!-- Fila 2 -->
            <div><strong>REF.CLTE.:</strong></div>
            <div class="valor-ficha" style="grid-column: span 3;"><?= htmlspecialchars($ref_clte_rms) ?></div>
            <div><strong>DESPACHO:</strong></div>
            <div class="valor-ficha"><?= htmlspecialchars($despacho_rms) ?></div>
            <div><strong>MES:</strong></div>
            <div class="valor-ficha"><?= htmlspecialchars($mes_rms) ?></div>

            <!-- Fila 3 -->
            <div><strong>ESTADO:</strong></div>
            <div class="valor-ficha"><?= htmlspecialchars($estado_rms) ?></div>
            <div></div>
            <div></div>
            <div><strong>NRO.NC:</strong></div>
            <div class="valor-ficha">
                <?php if ($id_cabecera): ?>
                    <?= htmlspecialchars($nro_nc) ?>
                <?php else: ?>
                    <input type="text" 
                        id="nro_nc_input" 
                        readonly 
                        style="width: 100%; height: 2.0rem; padding: 0.3rem; font-size: 0.9rem; 
                            border: 1px solid #ccc; border-radius: 4px; background-color: #f9f9f9;">
                <?php endif; ?>
            </div>
            <div><strong>FECHA VCTO.:</strong></div>
            <div class="valor-ficha">
                <?php if ($id_cabecera): ?>
                    <?= htmlspecialchars($cabecera['fecha_vence_nc'] ?? '') ?>
                <?php else: ?>
                    <input type="date" 
                        id="fecha_vence_nc_input" 
                        style="width: 100%; height: 2.0rem; padding: 0.3rem; font-size: 0.9rem; 
                            border: 1px solid #ccc; border-radius: 4px; background-color: white;">
                <?php endif; ?>
            </div>

            <!-- Fila 4 -->
            <div><strong>CONCEPTO:</strong></div>
            <div class="valor-ficha" style="grid-column: span 4;">
                <?php if ($id_cabecera): ?>
                    <?= htmlspecialchars($concepto_nc) ?>
                <?php else: ?>
                    <input type="text" 
                        id="concepto_nc_input" 
                        placeholder="Concepto de la nota de cobranza"
                        style="width: 100%; height: 2.0rem; padding: 0.3rem; font-size: 0.9rem; 
                            border: 1px solid #ccc; border-radius: 4px; background-color: white;">
                <?php endif; ?>
            </div>
            <div><strong>TOTAL REMESA:</strong></div>
            <div class="valor-ficha"><?= number_format($total_transferir_rms, 0, ',', '.') ?></div>

            <!-- Fila 5 -->
            <div></div>
            <div></div>
            <div></div>
            <div style="grid-column: span 3; display: flex; justify-content: flex-end;">
                <?php if (!$id_cabecera): ?>
                    <button class="btn-primary" onclick="guardarCabeceraNC()" style="padding: 0.4rem 0.8rem;">
                        <i class="fas fa-save"></i> + Guardar Concepto, Nro.NC y Fecha Vcto
                    </button>
                <?php endif; ?>
            </div>
            <div><strong>TOTAL RENDICIÓN:</strong></div>
            <div class="valor-ficha" id="total_rendido_ficha">0</div>

            <!-- Fila 6 -->
            <div><strong>A FAVOR DE:</strong></div>
            <div class="valor-ficha" id="afavor_ficha">OK</div>
            <div><strong>SALDO:</strong></div>
            <div class="valor-ficha" id="saldo_ficha">0</div>
            <div></div>
            <div></div>
            <div><strong>NOTA COBRANZA:</strong></div>
            <div class="valor-ficha" id="total_nota_ficha">0</div>
        </div>
    </div>

    <!-- Tabla de Conceptos -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="margin: 0; font-size: 1.1rem; font-weight: bold;">Conceptos de Nota Cobranza</h3>
            <div style="display: flex; gap: 0.8rem;">
                <button class="btn-primary" id="btn-agregar" onclick="abrirSubmodalNC()" style="display: <?= $id_cabecera ? 'inline-flex' : 'none' ?>; padding: 0.4rem 0.8rem;">
                    <i class="fas fa-plus"></i> Agregar ítem NC
                </button>
            </div>
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ítem</th>
                        <th>Proveedor</th>
                        <th>Nro. Docto.</th>
                        <th>Monto Neto</th>
                        <th>Monto Iva</th>
                        <th>Monto</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="lista-conceptos">
                    <tr><td colspan="7" style="text-align: center;">Seleccione una remesa para ver sus conceptos.</td></tr>
                </tbody>
            </table>
        </div>

        <div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 0.6rem; margin-top: 1rem; font-weight: bold; font-size: 0.95rem;">
            <div style="grid-column: span 3; text-align: right;">Totales:</div>
            <div id="total_montoneto" style="text-align: right;">0</div>
            <div id="total_montoiva" style="text-align: right;">0</div>
            <div id="total_monto" style="text-align: right;">0</div>
        </div>
    </div>

<!-- SUBMODAL -->
<div id="submodal-nc" class="submodal">
    <div class="submodal-content" style="max-width: 700px; padding: 1.8rem; position: relative;">
        <span class="submodal-close" onclick="cerrarSubmodalNC()" style="position: absolute; top: 1.2rem; right: 1.2rem;">×</span>
        <h3 style="margin: 0 0 1.4rem 0; font-size: 1.3rem;"><i class="fas fa-file-invoice-dollar"></i> Agregar Concepto</h3>

        <form id="form-nc">
            <input type="hidden" id="id_cabecera" value="<?= (int)($id_cabecera ?? 0) ?>">
            <input type="hidden" id="id_detalle">

            <div style="display: grid; grid-template-columns: 100px auto 20px 100px auto; gap: 0.8rem; margin-bottom: 1.2rem; align-items: center;">
                <div><label>Item:</label></div>
                <div><input type="number" id="item_nc" style="height: 2.3rem; width: 4ch; text-align: right;"></div>
                <div></div>
                <div><label>Monto Neto:</label></div>
                <div><input type="number" id="montoneto_nc" style="height: 2.3rem; width: 100%; text-align: right;"></div>

                <div><label>Proveedor:</label></div>
                <div style="grid-column: span 2;"><input type="text" id="proveedor_nc" style="height: 2.3rem; width: 100%;"></div>
                <div><label>Monto Iva:</label></div>
                <div><input type="number" id="montoiva_nc" style="height: 2.3rem; width: 100%; text-align: right;" readonly></div>

                <div><label>Nro. Dcto.:</label></div>
                <div><input type="text" id="nro_doc_nc" style="height: 2.3rem; width: 8ch;"></div>
                <div></div>
                <div><label>Monto:</label></div>
                <div><input type="number" id="monto_nc" style="height: 2.3rem; width: 100%; text-align: right;" readonly></div>
            </div>

            <div style="text-align: right; display: flex; gap: 0.8rem; justify-content: flex-end;">
                <button type="button" class="btn-primary" onclick="guardarConceptoNC()" style="padding: 0.55rem 1.4rem;">
                    <i class="fas fa-save"></i> Guardar Concepto
                </button>
                <button type="button" class="btn-secondary" onclick="cerrarSubmodalNC()" style="padding: 0.55rem 1.4rem;">
                    Cerrar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal eliminar concepto -->
<div id="modal-confirm" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close" onclick="cerrarModal()">&times;</span>
        <h3 style="color: #ff9900; margin-bottom: 1rem;"><i class="fas fa-exclamation-triangle"></i> Confirmar Eliminación</h3>
        <p>¿Estás seguro de eliminar este concepto?</p>
        <div style="margin-top: 1.2rem; display: flex; gap: 1rem; justify-content: center;">
            <button type="button" class="btn-delete" onclick="confirmarEliminarAction()">Eliminar</button>
            <button type="button" class="btn-secondary" onclick="cerrarModal()">Cancelar</button>
        </div>
    </div>
</div>

<script>
// === VARIABLES GLOBALES ===
let id_detalle_a_eliminar = null;
const id_cabecera_actual = <?= (int)($id_cabecera ?? 0) ?>;
const id_rms_actual = <?= (int)($id_rms_para_rendicion ?? 0) ?>;
const total_transferir_valor = <?= (float)$total_transferir_rms ?>;

// === CÁLCULO DE IVA ===
document.getElementById('montoneto_nc')?.addEventListener('input', function() {
    const montoneto = parseInt(this.value) || 0;
    const montoiva = Math.round(montoneto * 0.19);
    const monto = montoneto + montoiva;
    const ivaField = document.getElementById('montoiva_nc');
    const montoField = document.getElementById('monto_nc');
    if (ivaField) ivaField.value = montoiva;
    if (montoField) montoField.value = monto;
});

// === CREAR CABECERA NC (solo primera vez) ===
function guardarCabeceraNC() {
    const fechaVence = document.getElementById('fecha_vence_nc_input').value;
    const concepto = document.getElementById('concepto_nc_input').value.trim();
    
    if (!fechaVence || !concepto) {
        mostrarNotificacion('❌ Fecha Vencimiento y Concepto son obligatorios.', 'error');
        return;
    }

    // Calcular nro_nc = YYMMDD + id_rms
    const today = new Date().toISOString().slice(2, 10).replace(/-/g, ''); // YYMMDD
    const nro_nc = today + <?= (int)$id_rms_para_rendicion ?>;

    document.getElementById('nro_nc_input').value = nro_nc;

    const formData = new FormData();
    formData.append('action', 'crear_cabecera');
    formData.append('id_rms', <?= (int)$id_rms_para_rendicion ?>);
    formData.append('nro_nc', nro_nc);
    formData.append('fecha_vence_nc', fechaVence);
    formData.append('concepto_nc', concepto);

    fetch('/pages/notacobranza_logic.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            mostrarNotificacion('✅ Cabecera de nota de cobranza creada.', 'success');
            window.location.href = `/pages/notacobranza_view.php?id=${data.id_cabecera}`;
        } else {
            mostrarNotificacion('❌ ' + data.message, 'error');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        mostrarNotificacion('❌ Error de conexión.', 'error');
    });
}

function editarDetalle(id) {
    fetch(`/pages/notacobranza_logic.php?action=obtener_detalle&id=${id}`)
        .then(response => {
            if (!response.ok) throw new Error('Error en la respuesta del servidor');
            return response.json();
        })
        .then(data => {
            if (!data || !data.id_detalle) {
                alert('❌ No se pudo cargar el ítem para edición.');
                return;
            }

            // Rellenar campos del submodal
            document.getElementById('id_cabecera').value = data.id_cabecera;
            document.getElementById('id_detalle').value = data.id_detalle;
            document.getElementById('item_nc').value = data.item_detalle || '';
            document.getElementById('proveedor_nc').value = data.proveedor_detalle || '';
            document.getElementById('nro_doc_nc').value = data.nro_doc_detalle || '';

            const montoneto = parseFloat(data.montoneto_detalle) || 0;
            document.getElementById('montoneto_nc').value = montoneto;

            // Recalcular IVA y total
            const montoiva = Math.round(montoneto * 0.19);
            const monto = montoneto + montoiva;
            document.getElementById('montoiva_nc').value = montoiva;
            document.getElementById('monto_nc').value = monto;

            // Mostrar submodal
            document.getElementById('submodal-nc').style.display = 'flex';
        })
        .catch(error => {
            console.error('Error al cargar ítem para edición:', error);
            alert('❌ Error al cargar el ítem. Revisa la consola F12.');
        });
}

// === ABRIR SUBMODAL ÍTEM NC ===
function abrirSubmodalNC() {
    if (!id_cabecera_actual) {
        alert('❌ Primero debes grabar la cabecera de la nota de cobranza.');
        return;
    }
    // Limpiar y mostrar
    ['item_nc', 'proveedor_nc', 'nro_doc_nc', 'montoneto_nc', 'montoiva_nc', 'monto_nc'].forEach(id => {
        document.getElementById(id).value = '';
    });
    document.getElementById('id_cabecera').value = id_cabecera_actual;
    document.getElementById('id_detalle').value = '';
    document.getElementById('submodal-nc').style.display = 'flex';
}

// === GUARDAR ÍTEM NC (CORREGIDO) ===
function guardarConceptoNC() {
    const id_cabecera = document.getElementById('id_cabecera').value;
    const id_detalle = document.getElementById('id_detalle').value;
    const item = document.getElementById('item_nc').value.trim();
    const proveedor = document.getElementById('proveedor_nc').value.trim();
    const nro_doc = document.getElementById('nro_doc_nc').value.trim();
    const montoneto = document.getElementById('montoneto_nc').value;

    // Validación
    if (!item || !proveedor || !montoneto) {
        alert('❌ Los campos Ítem, Proveedor y Monto Neto son obligatorios.');
        return;
    }

    const formData = new FormData();
    formData.append('id_cabecera', id_cabecera);
    formData.append('item_detalle', item);
    formData.append('proveedor_detalle', proveedor);
    formData.append('nro_doc_detalle', nro_doc);
    formData.append('montoneto_detalle', montoneto);

    // Determinar acción
    if (id_detalle) {
        formData.append('action', 'actualizar_detalle');
        formData.append('id_detalle', id_detalle);
    } else {
        formData.append('action', 'crear_detalle');
    }

    fetch('/pages/notacobranza_logic.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) throw new Error('Error en la respuesta del servidor');
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            cerrarSubmodalNC();
            // Recargar la tabla de conceptos
            if (id_cabecera_actual) {
                cargarDetallesNC(id_cabecera_actual);
            }
        } else {
            alert('❌ ' + (data.message || 'Error al guardar el ítem.'));
        }
    })
    .catch(error => {
        console.error('Error al guardar ítem:', error);
        alert('❌ Error de conexión. Revisa la consola F12.');
    });
}

// === CARGAR DATOS INICIALES ===
<?php if ($id_cabecera): ?>
cargarTotalRendiciones(id_rms_actual, total_transferir_valor);
cargarDetallesNC(id_cabecera_actual);
<?php endif; ?>

// === Resto de funciones (placeholder o implementadas en lógica separada) ===
function cargarDetallesNC(id_cabecera) {
    fetch(`/api/get_detalles_nc.php?id_cabecera=${id_cabecera}`)
        .then(r => r.json())
        .then(detalles => {
            const tbody = document.getElementById('lista-conceptos');
            if (!tbody) return;
            let totalMonto = 0;
            if (detalles.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Sin conceptos registrados.</td></tr>';
            } else {
                tbody.innerHTML = detalles.map(d => {
                    const monto = parseFloat(d.monto_detalle) || 0;
                    totalMonto += monto;
                    return `
                        <tr>
                            <td>${d.item_detalle || ''}</td>
                            <td>${d.proveedor_detalle || ''}</td>
                            <td>${d.nro_doc_detalle || ''}</td>
                            <td>${parseFloat(d.montoneto_detalle).toLocaleString('es-CL')}</td>
                            <td>${parseFloat(d.montoiva_detalle).toLocaleString('es-CL')}</td>
                            <td>${monto.toLocaleString('es-CL')}</td>
                            <td>
                                <a href="#" class="btn-edit" title="Editar" onclick="editarDetalle(${d.id_detalle}); return false;">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="#" class="btn-delete" title="Eliminar" onclick="confirmarEliminar(${d.id_detalle}); return false;">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                    `;
                }).join('');
            }
            document.getElementById('total_monto').innerText = totalMonto.toLocaleString('es-CL');
            document.getElementById('total_nota_ficha').innerText = totalMonto.toLocaleString('es-CL');
        });
}

function cargarTotalRendiciones(id_rms, total_transferir) {
    fetch(`/api/get_total_rendiciones.php?id_rms=${id_rms}`)
        .then(r => r.json())
        .then(data => {
            const totalRendido = Math.round(parseFloat(data.total_rendicion) || 0);
            const saldo = total_transferir - totalRendido;
            const aFavor = saldo < 0 ? 'agencia' : (saldo > 0 ? 'cliente' : 'OK');
            document.getElementById('total_rendido_ficha').innerText = totalRendido.toLocaleString('es-CL');
            document.getElementById('saldo_ficha').innerText = Math.abs(saldo).toLocaleString('es-CL');
            document.getElementById('afavor_ficha').innerText = aFavor;
        });
}

function cerrarSubmodalNC() {
    document.getElementById('submodal-nc').style.display = 'none';
}

function confirmarEliminar(id) {
    if (confirm('¿Eliminar este ítem?')) {
        const formData = new FormData();
        formData.append('action', 'eliminar_detalle');
        formData.append('id_detalle', id);

        fetch('/pages/notacobranza_logic.php', {
            method: 'POST',
            body: formData  // ← Sin headers, FormData lo maneja
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✅ Ítem eliminado.');
                if (id_cabecera_actual) cargarDetallesNC(id_cabecera_actual);
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

function generarPDFNotacobranza() {
    if (!id_cabecera_actual) {
        mostrarNotificacion('❌ No hay nota de cobranza activa.', 'error');
        return;
    }
    window.open(`/pages/generar_pdf_notacobranza.php?id=${id_cabecera_actual}`, '_blank');
}
// === NOTIFICACIONES TOAST (estilo CRM_ELOG) ===
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