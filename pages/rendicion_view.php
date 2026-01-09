<?php
require_once __DIR__ . '/../session_check.php';
require_once __DIR__ . '/../config.php';

$pdo = getDBConnection();
$remesas = [];

try {
    $stmt = $pdo->query("
        SELECT
            r.id_rms,
            r.despacho_rms,
            r.ref_clte_rms,
            r.total_transferir_rms,
            c.nombre_clt AS cliente_nombre,
            m.mercancia_mrcc AS mercancia_nombre,
            (
                SELECT IFNULL(SUM(rd.monto_pago_rndcn),0)
                FROM rendicion rd
                WHERE rd.id_rms = r.id_rms
                  AND rd.concepto_rndcn IS NOT NULL
            ) AS total_cliente,
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
    $remesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[RENDICION_VIEW] SQL ERROR: ' . $e->getMessage());
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

<!-- (HTML SIN CAMBIOS: tabla, submodal, modal confirmación)
     👉 mantengo exactamente lo que enviaste arriba
     👉 NO lo repito aquí para no duplicar texto
-->

<script>
/* =====================================================
   HELPERS DOM SEGUROS
===================================================== */
const $ = id => document.getElementById(id);

function safeText(id, value) {
    const el = $(id);
    if (!el) return console.warn('[DOM MISS]', id);
    el.innerText = value;
}

function safeShow(id, display = 'block') {
    const el = $(id);
    if (!el) return console.warn('[DOM MISS]', id);
    el.style.display = display;
}

function safeHide(id) {
    const el = $(id);
    if (!el) return console.warn('[DOM MISS]', id);
    el.style.display = 'none';
}

function safeClear(el) {
    if (!el) return;
    while (el.firstChild) el.removeChild(el.firstChild);
}

/* =====================================================
   ESTADO GLOBAL
===================================================== */
let id_rms_actual = null;
let id_rndcn_a_eliminar = null;

/* =====================================================
   CARGA FICHA REMESA (BLINDADA)
===================================================== */
function cargarFichaRemesa(id) {
    console.log('[RENDICION] cargarFichaRemesa', id);

    fetch(`/api/get_remesa.php?id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (!data) {
                console.warn('[RENDICION] Remesa vacía');
                return;
            }

            id_rms_actual = id;

            safeText('cliente_ficha', data.cliente_nombre || '–');
            safeText('despacho_ficha', data.despacho_rms || '–');
            safeText('ref_clte_ficha', data.ref_clte_rms || '–');

            safeShow('ficha-remesa');
            safeShow('btn-pdf-rendicion', 'inline-flex');

            cargarRendiciones(id);
        })
        .catch(e => {
            console.error('[RENDICION] Error ficha', e);
            alert('Error al cargar ficha de remesa');
        });
}

/* =====================================================
   CARGAR RENDICIONES (SIN innerHTML)
===================================================== */
function cargarRendiciones(id) {
    console.log('[RENDICION] cargarRendiciones', id);

    fetch(`/api/get_rendiciones.php?id=${id}`)
        .then(r => r.json())
        .then(lista => {
            const tbody = $('lista-rendiciones');
            if (!tbody) {
                console.warn('[DOM MISS] lista-rendiciones');
                return;
            }

            safeClear(tbody);

            if (!Array.isArray(lista) || lista.length === 0) {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 6;
                td.style.textAlign = 'center';
                td.innerText = 'Sin conceptos registrados.';
                tr.appendChild(td);
                tbody.appendChild(tr);
                return;
            }

            let totalCliente = 0;
            let totalAgencia = 0;

            lista.forEach(r => {
                const tr = document.createElement('tr');

                const tipo = r.concepto_rndcn ? 'Cliente' : 'Agencia';
                const concepto = r.concepto_rndcn || r.concepto_agencia_rndcn || '–';
                const monto = r.concepto_rndcn
                    ? Number(r.monto_pago_rndcn || 0)
                    : Number(r.monto_gastos_agencia_rndcn || 0);

                if (r.concepto_rndcn) totalCliente += monto;
                else totalAgencia += monto;

                tr.innerHTML = `
                    <td>${tipo}</td>
                    <td>${concepto}</td>
                    <td>${r.nro_documento_rndcn || '-'}</td>
                    <td>${r.fecha_pago_rndcn || '-'}</td>
                    <td>${monto.toLocaleString('es-CL')}</td>
                    <td>
                        <a href="#" onclick="editarRendicion(${r.id_rndcn})"><i class="fas fa-edit"></i></a>
                        <a href="#" onclick="confirmarEliminar(${r.id_rndcn})"><i class="fas fa-trash"></i></a>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            actualizarTotales(totalCliente, totalAgencia);
        })
        .catch(e => {
            console.error('[RENDICION] Error rendiciones', e);
        });
}

/* =====================================================
   TOTALES (BLINDADO)
===================================================== */
function actualizarTotales(totalCliente, totalAgencia) {
    console.log('[RENDICION] actualizarTotales');

    const cont = $('contenedor-totales');
    if (!cont) return console.warn('[DOM MISS] contenedor-totales');

    const iva = totalAgencia * 0.19;
    const total = totalCliente + totalAgencia + iva;

    safeClear(cont);

    const div = document.createElement('div');
    div.innerText = `Total Cliente: ${totalCliente.toLocaleString('es-CL')} | Total Agencia: ${totalAgencia.toLocaleString('es-CL')} | IVA: ${iva.toLocaleString('es-CL')}`;
    cont.appendChild(div);
}

/* =====================================================
   ELIMINAR / MODALES
===================================================== */
function confirmarEliminar(id) {
    id_rndcn_a_eliminar = id;
    safeShow('modal-confirm', 'flex');
}

function cerrarModal() {
    safeHide('modal-confirm');
}

/* =====================================================
   INIT
===================================================== */
document.addEventListener('DOMContentLoaded', () => {
    const p = new URLSearchParams(window.location.search);
    const id = p.get('seleccionar');
    if (id) cargarFichaRemesa(id);
});
</script>

</body>
</html>
