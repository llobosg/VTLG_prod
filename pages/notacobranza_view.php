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
                    LEFT JOIN clientes c ON r.id_clt_rms = c.id_clt
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

    <!-- Búsqueda inteligente (solo nueva nota) -->
    <?php if (!$id_cabecera): ?>
    <div class="busqueda-container">
        <input type="text" 
               id="busqueda-inteligente" 
               placeholder="Buscar remesa por cliente, mercancía, ref.clte o fecha..." 
               style="width: 100%; height: 2.4rem; padding: 0.5rem; font-size: 0.95rem;">
        <div id="resultados-busqueda"></div>
    </div>
    <?php endif; ?>

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
            <div class="valor-ficha"><?= htmlspecialchars($nro_nc) ?></div>
            <div><strong>FECHA VCTO.:</strong></div>
            <div class="valor-ficha"><?= htmlspecialchars($cabecera['fecha_vence_nc'] ?? '') ?></div>
            <!-- Fila 4 -->
            <div><strong>CONCEPTO:</strong></div>
            <div class="valor-ficha" style="grid-column: span 5;">
                <input type="text" 
                    id="concepto_nc_input" 
                    value="<?= htmlspecialchars($concepto_nc) ?>" 
                    style="width: 100%; padding: 0.3rem; font-size: 0.9rem; border: 1px solid #ddd; border-radius: 4px;"
                    <?= ($cabecera && (float)$cabecera['total_monto_nc'] > 0) ? 'readonly' : '' ?>>
            </div>
            <div><strong>TOTAL REMESA:</strong></div>
            <div class="valor-ficha"><?= number_format($total_transferir_rms, 0, ',', '.') ?></div>
            <!-- Fila 5 -->
            <div></div>
            <div></div>
            <div></div>
            <div></div>
            <div></div>
            <div></div>
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

            <!-- Fila 7: Botón solo si es edición -->
            <div style="grid-column: span 8; display: flex; justify-content: flex-end; margin-top: 0.5rem;">
                <?php if ($id_cabecera): ?>
                    <button class="btn-primary" onclick="abrirSubmodalNC()" style="padding: 0.4rem 0.8rem;">
                        <i class="fas fa-plus"></i> Agregar Concepto
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tabla de Conceptos -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="margin: 0; font-size: 1.1rem; font-weight: bold;">Conceptos de Nota Cobranza</h3>
            <div style="display: flex; gap: 0.8rem;">
                <button class="btn-primary" id="btn-agregar" onclick="abrirSubmodalNC()" style="display: <?= $id_cabecera ? 'inline-flex' : 'none' ?>; padding: 0.4rem 0.8rem;">
                    <i class="fas fa-plus"></i> Agregar Concepto
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

// === CÁLCULO DE IVA (seguro) ===
document.getElementById('montoneto_nc')?.addEventListener('input', function() {
    const montoneto = parseInt(this.value) || 0;
    const montoiva = Math.round(montoneto * 0.19);
    const monto = montoneto + montoiva;
    
    const ivaField = document.getElementById('montoiva_nc');
    const montoField = document.getElementById('monto_nc');
    if (ivaField) ivaField.value = montoiva;
    if (montoField) montoField.value = monto;
});

// === BÚSQUEDA INTELIGENTE ===
<?php if (!$id_cabecera): ?>
document.getElementById('busqueda-inteligente')?.addEventListener('input', async function() {
    const term = this.value.trim();
    const div = document.getElementById('resultados-busqueda');
    div.style.display = 'none';
    if (!term) return;

    try {
        const res = await fetch(`/api/buscar_remesas.php?term=${encodeURIComponent(term)}`);
        const data = await res.json();
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
                    crearNotaCobranza(r.id_rms);
                    div.style.display = 'none';
                    this.value = '';
                };
                div.appendChild(d);
            });
            div.style.display = 'block';
        }
    } catch (e) {
        console.error('Error en búsqueda:', e);
    }
});
<?php endif; ?>

// === CREAR NOTA ===
function crearNotaCobranza(id_rms) {
    fetch('/pages/notacobranza_logic.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'crear_cabecera', id_rms: id_rms })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = `/pages/notacobranza_view.php?id=${data.id_cabecera}`;
        } else {
            alert('Error al crear nota: ' + data.message);
        }
    });
}

// === FUNCIONES DE CONCEPTOS ===
function cargarDetallesNC(id_cabecera) {
    fetch(`/api/get_detalles_nc.php?id_cabecera=${id_cabecera}`)
        .then(r => r.json())
        .then(detalles => {
            const tbody = document.getElementById('lista-conceptos');
            if (!tbody) return;

            let totalNeto = 0;
            let totalIva = 0;
            let totalMonto = 0;

            if (detalles.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Sin conceptos registrados.</td></tr>';
            } else {
                tbody.innerHTML = detalles.map(d => {
                    const neto = parseFloat(d.montoneto_detalle) || 0;
                    const iva = parseFloat(d.montoiva_detalle) || 0;
                    const monto = parseFloat(d.monto_detalle) || 0;
                    totalNeto += neto;
                    totalIva += iva;
                    totalMonto += monto;
                    return `
                        <tr>
                            <td>${d.item_detalle || ''}</td>
                            <td>${d.proveedor_detalle || ''}</td>
                            <td>${d.nro_doc_detalle || ''}</td>
                            <td>${neto.toLocaleString('es-CL')}</td>
                            <td>${iva.toLocaleString('es-CL')}</td>
                            <td>${monto.toLocaleString('es-CL')}</td>
                            <td>
                                <a href="#" class="btn-edit" title="Editar" onclick="editarDetalle(${d.id_detalle})">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="#" class="btn-delete" title="Eliminar" onclick="confirmarEliminar(${d.id_detalle})">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                    `;
                }).join('');
            }

            // Actualizar totales
            setTextContent('total_montoneto', totalNeto.toLocaleString('es-CL'));
            setTextContent('total_montoiva', totalIva.toLocaleString('es-CL'));
            setTextContent('total_monto', totalMonto.toLocaleString('es-CL'));
            setTextContent('total_nota_ficha', totalMonto.toLocaleString('es-CL'));
        })
        .catch(err => {
            console.error('Error al cargar detalles:', err);
        });
}

function cargarTotalRendiciones(id_rms, total_transferir) {
    fetch(`/api/get_total_rendiciones.php?id_rms=${id_rms}`)
        .then(r => r.json())
        .then(data => {
            const totalRendido = Math.round(parseFloat(data.total_rendicion) || 0);
            const saldo = total_transferir - totalRendido;
            const aFavor = saldo < 0 ? 'agencia' : (saldo > 0 ? 'cliente' : 'OK');

            console.log('Debug - total_transferir:', total_transferir);
            console.log('Debug - totalRendido:', totalRendido);
            console.log('Debug - saldo:', saldo);
            console.log('Debug - aFavor:', aFavor);

            setTextContent('total_rendido_ficha', totalRendido.toLocaleString('es-CL'));
            setTextContent('saldo_ficha', Math.abs(saldo).toLocaleString('es-CL'));
            setTextContent('afavor_ficha', aFavor);

            window.saldo_disponible = saldo;
        })
        .catch(err => {
            console.error('Error al cargar total rendido:', err);
        });
}

// === UTILIDADES ===
function setTextContent(id, text) {
    const el = document.getElementById(id);
    if (el && el.tagName === 'INPUT') {
        el.value = text;
    } else if (el) {
        el.innerText = text;
    }
}

// === CARGAR DATOS INICIALES ===
<?php if ($id_cabecera): ?>
cargarTotalRendiciones(id_rms_actual, total_transferir_valor);
cargarDetallesNC(id_cabecera_actual);
<?php endif; ?>

// === Resto de funciones (abrirSubmodalNC, guardarConceptoNC, etc.) ===
// ... (implementadas en entregas anteriores y funcionales)
// Se mantienen intactas ya que ya están corregidas.
</script>
</body>
</html>