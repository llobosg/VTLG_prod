<?php
require_once __DIR__ . '/../session_check.php';
require_once __DIR__ . '/../config.php';

$pdo = getDBConnection();

/*
=====================================================
CARGA DE REMESAS CON TOTALES DE RENDICIÓN
=====================================================
*/
try {
    $stmt = $pdo->prepare("
        SELECT
            r.id_rms,
            r.despacho_rms,
            r.ref_clte_rms,
            r.total_transferir_rms,

            c.nombre_clt AS cliente_nombre,
            m.mercancia_mrcc AS mercancia_nombre,

            /* Total gastos cliente */
            (
                SELECT IFNULL(SUM(rd.monto_pago_rndcn),0)
                FROM rendicion rd
                WHERE rd.id_rms = r.id_rms
                  AND rd.concepto_rndcn IS NOT NULL
            ) AS total_cliente,

            /* Total gastos agencia (NETO, sin IVA) */
            (
                SELECT IFNULL(SUM(rd.monto_gastos_agencia_rndcn),0)
                FROM rendicion rd
                WHERE rd.id_rms = r.id_rms
                  AND rd.concepto_agencia_rndcn IS NOT NULL
            ) AS total_agencia

        FROM remesa r
        LEFT JOIN clientes c ON c.id_clt = r.cliente_rms
        LEFT JOIN mercancias m ON m.id_mrcc = r.mercancia_rms
        ORDER BY r.id_rms DESC
    ");
    $stmt->execute();
    $remesas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('Error rendicion_listas.php: ' . $e->getMessage());
    $remesas = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Rendición de Gastos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.2rem;">
        <h2 style="font-weight:bold; display:flex; align-items:center; gap:0.5rem;">
            <i class="fas fa-receipt"></i> Rendición de Gastos
        </h2>
        <a href="/pages/rendicion_view.php" class="btn-primary">
            <i class="fas fa-plus"></i> Agregar Rendición
        </a>
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
</div>

<!-- SUBMODAL RENDICIÓN -->
<div id="submodal-rendicion" class="submodal">
    <div class="submodal-content" style="max-width: 650px; padding: 1.8rem; position: relative;">
        <span class="submodal-close" onclick="cerrarSubmodalRendicion()" style="position: absolute; top: 1.2rem; right: 1.2rem;">×</span>
        <h3 style="margin: 0 0 1.4rem 0; font-size: 1.3rem;"><i class="fas fa-receipt"></i> 
            <span id="submodal-titulo">Agregar Concepto</span>
        </h3>

        <input type="hidden" id="id_rndcn_edicion">
        <input type="hidden" id="id_rms_rendicion">
        <input type="hidden" id="id_clt_rms_hidden">

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

<!-- Modal confirmación eliminar (solo UI, sin formulario) -->
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
let id_rms_actual = null;

// === BÚSQUEDA INTELIGENTE ===
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
                    cargarFichaRemesa(r.id_rms);
                    div.style.display = 'none';
                    this.value = '';
                };
                div.appendChild(d);
            });
            div.style.display = 'block';
        }
    } catch (e) {
        console.error('Error en búsqueda:', e);
        alert('Error en la búsqueda.');
    }
});

// === CARGAR FICHA DE REMESA ===
function cargarFichaRemesa(id_rms) {
    fetch(`/api/get_remesa.php?id=${id_rms}`)
        .then(r => r.json())
        .then(data => {
            if (!data) return;

            const setField = (id, value) => {
                const el = document.getElementById(id);
                if (el) el.innerText = value;
            };

            setField('id_clt_ficha', data.id_clt_rms || '–');
            setField('cliente_ficha', data.cliente_nombre || '–');
            setField('contacto_ficha', data.contacto_rms || '');
            setField('fecha_ficha', data.fecha_rms || '');
            setField('mes_ficha', data.mes_rms || '');
            setField('despacho_ficha', data.despacho_rms || '');
            setField('ref_clte_ficha', data.ref_clte_rms || '');
            setField('mercancia_ficha', data.mercancia_nombre || '–');
            setField('estado_ficha', data.estado_rms || '');

            const hiddenIdClt = document.getElementById('id_clt_rms_hidden');
            if (hiddenIdClt) hiddenIdClt.value = data.id_clt_rms || '';

            // === TOTALES PRINCIPALES ===
            const total_tesoreria = parseFloat(data.total_tesoreria2_rms) || 0;
            const gastos_operacionales = parseFloat(data.total_gastos_operacionales2_rms) || 0;
            const total_transferir = parseFloat(data.total_transferir_rms) || 0;

            setField('total_tesoreria_ficha', total_tesoreria.toLocaleString('es-CL'));
            setField('gastos_operacionales_ficha', gastos_operacionales.toLocaleString('es-CL'));
            setField('total_transferir_ficha', total_transferir.toLocaleString('es-CL'));

            // === GASTOS OPERACIONALES DETALLADOS ===
            const subtotal_gastos = parseFloat(data.subtotal_gastos_operacionales_rms) || 0;
            const iva = parseFloat(data.iva_rms) || 0;
            const total_gastos_operacionales2 = parseFloat(data.total_gastos_operacionales2_rms) || 0;

            setField('subtotal_gastos_ficha', subtotal_gastos.toLocaleString('es-CL'));
            setField('iva_ficha', iva.toLocaleString('es-CL'));
            setField('total_gastos_operacionales2_ficha', total_gastos_operacionales2.toLocaleString('es-CL'));

            // === CÁLCULO DE SALDOS ===
            const a_favor = total_transferir > total_tesoreria ? 'agencia' : 'cliente';
            const saldo = Math.abs(total_transferir - total_tesoreria);

            setField('afavor_ficha', a_favor);
            setField('saldo_ficha', saldo.toLocaleString('es-CL'));

            // Mostrar ficha si existe
            const ficha = document.getElementById('ficha-remesa');
            if (ficha) {
                ficha.style.display = 'block';
            }

            // Mostrar botón PDF si existe
            const btnPdf = document.getElementById('btn-pdf-rendicion');
            if (btnPdf) {
                btnPdf.style.display = 'inline-flex';
            }

            id_rms_actual = id_rms;
            cargarRendiciones(id_rms);

        })
        .catch(err => {
            console.error('Error al cargar ficha:', err);
            alert('Error al cargar la ficha de remesa.');
        });
}

// === CARGAR RENDICIONES (CON CÁLCULO DE TOTALES) ===
function cargarRendiciones(id_rms) {
    fetch(`/api/get_rendiciones.php?id=${id_rms}`)
        .then(r => r.json())
        .then(rendiciones => {
            const tbody = document.getElementById('lista-rendiciones');
            if (rendiciones.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Sin conceptos registrados.</td></tr>';
                actualizarTotales(0, 0);
                return;
            }

            let totalCliente = 0;
            let totalAgencia = 0;

            tbody.innerHTML = rendiciones.map(r => {
                const tipo = r.concepto_rndcn ? 'Cliente' : 'Agencia';
                const concepto = r.concepto_rndcn || r.concepto_agencia_rndcn || '–';
                const monto = r.concepto_rndcn ? parseFloat(r.monto_pago_rndcn) : parseFloat(r.monto_gastos_agencia_rndcn);
                const montoNum = isNaN(monto) ? 0 : monto;

                if (r.concepto_rndcn) totalCliente += montoNum;
                else totalAgencia += montoNum;

                return `
                    <tr>
                        <td>${tipo}</td>
                        <td>${concepto}</td>
                        <td>${r.nro_documento_rndcn || '-'}</td>
                        <td>${r.fecha_pago_rndcn || '-'}</td>
                        <td>${montoNum.toLocaleString('es-CL')}</td>
                        <td>
                            <a href="#" class="btn-edit" title="Editar" onclick="editarRendicion(${r.id_rndcn})">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="#" class="btn-delete" title="Eliminar" onclick="confirmarEliminar(${r.id_rndcn})">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                        </td>
                    </tr>
                `;
            }).join('');

            actualizarTotales(totalCliente, totalAgencia);
        })
        .catch(err => {
            console.error('Error al cargar rendiciones:', err);
            alert('Error al cargar las rendiciones.');
        });
}

// === ACTUALIZAR TOTALES (CON FICHA) ===
function actualizarTotales(totalCliente, totalAgencia) {
    const contenedor = document.getElementById('contenedor-totales');
    if (!contenedor) return;

    const netoAgencia = totalAgencia;
    const ivaAgencia = netoAgencia * 0.19;
    const totalGastosAgencia = netoAgencia + ivaAgencia;
    
    // Obtener total_transferir desde la ficha
    const totalTransferirText = document.getElementById('total_transferir_ficha')?.innerText || '0';
    const totalTransferir = parseFloat(totalTransferirText.replace(/\./g, '').replace(',', '.')) || 0;
    
    const totalRendicion = totalCliente + totalGastosAgencia;
    const saldo = totalTransferir - totalRendicion;
    
    let aFavor = 'OK';
    if (saldo > 0) aFavor = 'cliente';
    else if (saldo < 0) aFavor = 'agencia';

    // ✅ ACTUALIZAR FICHA DE REMESA (SIN DECIMALES)
    const setFicha = (id, valor) => {
        const el = document.getElementById(id);
        if (el) el.innerText = valor.toLocaleString('es-CL', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    };

    setFicha('total_gastos_ctaclte_ficha', totalCliente);
    setFicha('total_gastos_agencia_ficha', totalGastosAgencia);
    setFicha('total_rendicion_ficha', totalRendicion);
    setFicha('saldo_ficha', Math.abs(saldo));
    setFicha('afavor_ficha', aFavor !== 'OK' ? aFavor : 'equilibrado');

    // ✅ ACTUALIZAR SECCIÓN DE TOTALES (tabla inferior)
    contenedor.innerHTML = `
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-weight: bold; font-size: 0.95rem;">
            <div style="display: flex; justify-content: space-between;">
                <span>TOTAL CLIENTE:</span>
                <span style="color: #2c3e50;">${totalCliente.toLocaleString('es-CL')}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span>NETO AGENCIA:</span>
                <span style="color: #2c3e50;">${netoAgencia.toLocaleString('es-CL')}</span>
            </div>
            <div></div>
            <div style="display: flex; justify-content: space-between;">
                <span>IVA 19%:</span>
                <span style="color: #2c3e50;">${ivaAgencia.toLocaleString('es-CL')}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span>SALDO:</span>
                <span style="color: ${saldo > 0 ? '#27ae60' : (saldo < 0 ? '#e74c3c' : '#3498db')};">
                    ${Math.abs(saldo).toLocaleString('es-CL')}
                    ${aFavor !== 'OK' ? `(${aFavor})` : ''}
                </span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span>TOTAL GASTOS AGENCIA:</span>
                <span style="color: #2c3e50;">${totalGastosAgencia.toLocaleString('es-CL')}</span>
            </div>
        </div>
    `;
}

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

// === ABRIR SUBMODAL ===
function abrirSubmodalRendicion() {
    if (!id_rms_actual) {
        alert('Seleccione una remesa primero.');
        return;
    }
    document.getElementById('id_rms_rendicion').value = id_rms_actual;
    document.getElementById('id_rndcn_edicion').value = '';
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

// === GUARDAR RENDICIÓN ===
function guardarRendicion() {
    const formData = new FormData();
    const id_rndcn = document.getElementById('id_rndcn_edicion').value;
    const id_rms = document.getElementById('id_rms_rendicion').value;
    const grupo = document.querySelector('input[name="grupo"]:checked').value;

    formData.append('id_rms', id_rms);
    if (id_rndcn) formData.append('id_rndcn', id_rndcn);

    if (grupo === 'cliente') {
        const concepto = document.getElementById('concepto_rndcn').value;
        if (!concepto) {
            alert('Seleccione un concepto de cliente.');
            return;
        }
        formData.append('concepto_rndcn', concepto);
        formData.append('nro_documento_rndcn', document.getElementById('nro_documento_rndcn').value || '');
        formData.append('fecha_pago_rndcn', document.getElementById('fecha_pago_rndcn').value || '');
        formData.append('monto_pago_rndcn', document.getElementById('monto_pago_rndcn').value || 0);
    } else {
        const concepto = document.getElementById('concepto_agencia_rndcn').value;
        if (!concepto) {
            alert('Seleccione un concepto de agencia.');
            return;
        }
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
            alert('✅ Concepto guardado correctamente.');
            if (!id_rndcn) {
                // Si es nuevo, limpiar y mantener abierto
                limpiarFormRendicion();
            } else {
                // Si es edición, cerrar
                cerrarSubmodalRendicion();
            }
            cargarRendiciones(id_rms_actual);
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error de conexión.');
    });
}

// === EDITAR RENDICIÓN ===
function editarRendicion(id_rndcn) {
    fetch(`/api/get_rendicion.php?id=${id_rndcn}`)
        .then(r => r.json())
        .then(data => {
            if (!data) return;

            document.getElementById('id_rndcn_edicion').value = data.id_rndcn;
            document.getElementById('id_rms_rendicion').value = data.id_rms;
            document.getElementById('btn-texto').innerText = 'Actualizar Concepto';

            if (data.concepto_rndcn) {
                // Concepto de cliente
                document.querySelector('input[value="cliente"]').checked = true;
                document.getElementById('grupo-cliente').style.display = 'block';
                document.getElementById('grupo-agencia').style.display = 'none';
                document.getElementById('submodal-titulo').innerText = 'Editar Concepto (Cliente)';

                document.getElementById('concepto_rndcn').value = data.concepto_rndcn;
                document.getElementById('nro_documento_rndcn').value = data.nro_documento_rndcn || '';
                document.getElementById('fecha_pago_rndcn').value = data.fecha_pago_rndcn || '';
                document.getElementById('monto_pago_rndcn').value = data.monto_pago_rndcn || '';
            } else {
                // Concepto de agencia
                document.querySelector('input[value="agencia"]').checked = true;
                document.getElementById('grupo-cliente').style.display = 'none';
                document.getElementById('grupo-agencia').style.display = 'block';
                document.getElementById('submodal-titulo').innerText = 'Editar Concepto (Agencia)';

                document.getElementById('concepto_agencia_rndcn').value = data.concepto_agencia_rndcn || '';
                document.getElementById('nro_documento_rndcn_agencia').value = data.nro_documento_rndcn || '';
                document.getElementById('fecha_pago_rndcn_agencia').value = data.fecha_pago_rndcn || '';
                document.getElementById('monto_gastos_agencia_rndcn').value = data.monto_gastos_agencia_rndcn || '';
            }

            document.getElementById('submodal-rendicion').style.display = 'flex';
        })
        .catch(err => {
            console.error('Error al cargar concepto:', err);
            alert('Error al cargar el concepto para edición.');
        });
}

let id_rndcn_a_eliminar = null;

function confirmarEliminar(id) {
    id_rndcn_a_eliminar = id;
    document.getElementById('modal-confirm').style.display = 'flex';
}

function confirmarEliminarAction() {
    if (!id_rndcn_a_eliminar) return;
    
    fetch('/pages/rendicion_logic.php?delete=' + id_rndcn_a_eliminar)
        .then(res => res.json())
        .then(data => {
            cerrarModal();
            if (data.success) {
                mostrarNotificacion('✅ ' + data.message, 'success');
                cargarRendiciones(id_rms_actual);
            } else {
                mostrarNotificacion('❌ ' + data.message, 'error');
            }
        })
        .catch(err => {
            cerrarModal();
            console.error('Error:', err);
            mostrarNotificacion('❌ Error de conexión.', 'error');
        });
}

function cerrarModal() {
    document.getElementById('modal-confirm').style.display = 'none';
}

// Cerrar resultados al hacer clic fuera
document.addEventListener('click', function(e) {
    const resultados = document.getElementById('resultados-busqueda');
    const input = document.getElementById('busqueda-inteligente');
    if (!resultados.contains(e.target) && e.target !== input) {
        resultados.style.display = 'none';
    }
});

// === NOTIFICACIONES TOAST ===
function mostrarNotificacion(mensaje, tipo = 'success') {
    // Crear contenedor si no existe
    let contenedor = document.getElementById('notificaciones-container');
    if (!contenedor) {
        contenedor = document.createElement('div');
        contenedor.id = 'notificaciones-container';
        contenedor.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        `;
        document.body.appendChild(contenedor);
    }

    // Crear notificación
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

    // Agregar animaciones
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

    // Eliminar después de la animación
    setTimeout(() => {
        if (notif.parentNode) {
            notif.parentNode.removeChild(notif);
        }
    }, 3000);
}

// === BOTÓN PDF RENDICIÓN ===
function generarPDFRendicion() {
    if (!id_rms_actual) {
        alert('Seleccione una remesa primero.');
        return;
    }
    window.open(`/pages/generar_pdf_rendicion.php?id=${id_rms_actual}`, '_blank');
}

// Al cargar la página, si hay ?seleccionar=ID, cargar esa remesa
window.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const idSeleccionar = urlParams.get('seleccionar');
    if (idSeleccionar) {
        cargarFichaRemesa(idSeleccionar);
    }
});
</script>
</body>
</html>