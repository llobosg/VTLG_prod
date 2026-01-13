<?php
require_once '../session_check.php';
require_once '../config.php';

$pdo = getDBConnection();

// Obtener todas las cabeceras de nota cobranza
$stmt = $pdo->prepare("
    SELECT 
        nc.id_cabecera,
        nc.fecha_nc,
        nc.nro_nc,
        nc.concepto_nc,
        nc.total_neto_nc,
        nc.total_iva_nc,
        nc.total_monto_nc,
        nc.afavor_nc,
        nc.saldo_nc,
        r.id_rms,
        r.cliente_rms,
        r.despacho_rms,
        r.ref_clte_rms,
        r.total_transferir_rms,
        c.nombre_clt AS cliente_nombre
    FROM notacobranza nc
    LEFT JOIN remesa r ON nc.id_rms_nc = r.id_rms
    LEFT JOIN clientes c ON r.cliente_rms = c.id_clt
    ORDER BY nc.fecha_nc DESC
");
$stmt->execute();
$notas = $stmt->fetchAll();
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
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem;">
        <h2 style="font-weight: bold; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-file-invoice-dollar"></i> Nota de Cobranza
        </h2>
        <a href="/pages/notacobranza_view.php" class="btn-primary">
            <i class="fas fa-plus"></i> Agregar Nota Cobranza
        </a>
    </div>

    <div class="card">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Despacho</th>
                        <th>Ref.Clte.</th>
                        <th>Nro. NC</th>
                        <th>Monto Neto</th>
                        <th>Monto Iva</th>
                        <th>Monto</th>
                        <th>Transferido</th>
                        <th>Saldo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($notas)): ?>
                    <tr>
                        <td colspan="11" style="text-align: center;">No hay notas de cobranza registradas.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($notas as $n): ?>
                    <tr>
                        <td><?= htmlspecialchars($n['fecha_nc']) ?></td>
                        <td><?= htmlspecialchars($n['cliente_nombre'] ?? '–') ?></td>
                        <td><?= htmlspecialchars($n['despacho_rms'] ?? '–') ?></td>
                        <td><?= htmlspecialchars($n['ref_clte_rms'] ?? '–') ?></td>
                        <td><?= htmlspecialchars($n['nro_nc'] ?? '–') ?></td>
                        <td><?= number_format($n['total_neto_nc'], 0, ',', '.') ?></td>
                        <td><?= number_format($n['total_iva_nc'], 0, ',', '.') ?></td>
                        <td><?= number_format($n['total_monto_nc'], 0, ',', '.') ?></td>
                        <td><?= number_format($n['total_transferir_rms'], 0, ',', '.') ?></td>
                        <td style="color: <?= $n['saldo_nc'] > 0 ? '#2980b9' : ($n['saldo_nc'] < 0 ? '#e74c3c' : '#3498db') ?>;">
                            <?= number_format(abs($n['saldo_nc']), 0, ',', '.') ?>
                            <?= $n['afavor_nc'] !== 'OK' ? ' (' . $n['afavor_nc'] . ')' : '' ?>
                        </td>
                        <td style="white-space: nowrap;">
                            <a href="/pages/notacobranza_view.php?id=<?= $n['id_cabecera'] ?>" class="btn-primary" title="Editar" style="padding: 0.4rem 0.6rem; margin-right: 0.3rem;">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="/pages/generar_pdf_notacobranza.php?id=<?= $n['id_cabecera'] ?>" target="_blank" class="btn-comment" title="PDF" style="padding: 0.4rem 0.6rem; margin-right: 0.3rem;">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                            <a href="#" class="btn-delete" title="Eliminar" onclick="confirmarEliminarNota(<?= $nota['id_cabecera'] ?>)">
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
</div>
<!-- Modal eliminar cabecera -->
<div id="modal-eliminar-cabecera" class="modal" style="display: none;">
    <div class="modal-content" style="text-align: center; padding: 2rem;">
        <span class="close" onclick="cerrarModalEliminar()" style="float: right; font-size: 1.5rem; cursor: pointer;">&times;</span>
        <h3 style="color: #e74c3c; margin: 0 0 1rem 0;"><i class="fas fa-exclamation-triangle"></i> ¿Eliminar Nota de Cobranza?</h3>
        <p>Esta acción eliminará la nota completa y todos sus conceptos. ¿Estás seguro?</p>
        <div style="margin-top: 1.5rem;">
            <button type="button" class="btn-delete" onclick="eliminarCabeceraConfirmada()" style="padding: 0.5rem 1.2rem; margin-right: 0.5rem;">
                <i class="fas fa-trash-alt"></i> Eliminar
            </button>
            <button type="button" class="btn-secondary" onclick="cerrarModalEliminar()" style="padding: 0.5rem 1.2rem;">
                Cancelar
            </button>
        </div>
    </div>
</div>

<script>
let id_cabecera_a_eliminar = null;

function confirmarEliminarCabecera(id) {
    id_cabecera_a_eliminar = id;
    document.getElementById('modal-eliminar-cabecera').style.display = 'flex';
}

function cerrarModalEliminar() {
    document.getElementById('modal-eliminar-cabecera').style.display = 'none';
}

function eliminarCabeceraConfirmada() {
    if (!id_cabecera_a_eliminar) return;
    
    fetch('/pages/notacobranza_logic.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=eliminar_cabecera&id_cabecera=' + id_cabecera_a_eliminar
    })
    .then(response => {
        if (response.ok) {
            // Redirigir a la lista tras eliminar
            window.location.href = '/pages/notacobranza_lista.php';
        } else {
            alert('Error al eliminar la nota.');
            cerrarModalEliminar();
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error de conexión.');
        cerrarModalEliminar();
    });
}

// Cerrar al hacer clic fuera
window.onclick = function(event) {
    const modal = document.getElementById('modal-eliminar-cabecera');
    if (event.target === modal) {
        cerrarModalEliminar();
    }
}

<script>
function confirmarEliminarNota(id) {
    if (confirm('¿Eliminar esta nota de cobranza y todos sus ítems?')) {
        const formData = new FormData();
        formData.append('action', 'eliminar_nota_cobranza');
        formData.append('id_cabecera', id);

        fetch('/pages/notacobranza_logic.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('❌ ' + data.message);
            }
        })
        .catch(err => {
            alert('❌ Error de conexión.');
        });
    }
}
</script>
</script>
</script>
</body>
</html>