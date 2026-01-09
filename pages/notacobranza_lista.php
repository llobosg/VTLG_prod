<?php
require_once '../session_check.php';
require_once '../config.php';

$rol = $_SESSION['rol'] ?? '';
if ($rol !== 'admin' && $rol !== 'comercial') {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso denegado.');
}

// Cargar datos solo en contexto web
$notas = [];
if (php_sapi_name() !== 'cli') {
    try {
        $pdo = getDBConnection();
        if ($pdo) {
            $stmt = $pdo->prepare("
                SELECT 
                    nc.*,
                    r.cliente_rms,
                    r.ref_clte_rms,
                    r.fecha_rms,
                    c.nombre_clt AS cliente_nombre
                FROM notacobranza nc
                LEFT JOIN remesa r ON nc.id_rms_nc = r.id_rms
                LEFT JOIN clientes c ON r.id_clt_rms = c.id_clt
                ORDER BY nc.fecha_nc DESC, nc.id_cabecera DESC
            ");
            $stmt->execute();
            $notas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Error al cargar lista de notas: " . $e->getMessage());
        $notas = [];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Lista de Notas de Cobranza - SIGA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-weight: bold;">
            <i class="fas fa-file-invoice-dollar"></i> Notas de Cobranza
        </h2>
        <a href="/pages/notacobranza_view.php" class="btn-primary" style="text-decoration: none;">
            <i class="fas fa-plus"></i> Nueva Nota
        </a>
    </div>

    <?php if (!empty($notas)): ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Ref. Clte</th>
                    <th>Fecha</th>
                    <th>Nro. Nota</th>
                    <th>Total Nota</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notas as $n): ?>
                <tr>
                    <td><?= (int)$n['id_cabecera'] ?></td>
                    <td><?= htmlspecialchars($n['cliente_nombre'] ?? 'ID: ' . ($n['cliente_rms'] ?? '–')) ?></td>
                    <td><?= htmlspecialchars($n['ref_clte_rms'] ?? '–') ?></td>
                    <td><?= htmlspecialchars($n['fecha_nc'] ?? '–') ?></td>
                    <td><?= htmlspecialchars($n['nro_nc'] ?? '–') ?></td>
                    <td><?= isset($n['total_monto_nc']) ? number_format($n['total_monto_nc'], 0, ',', '.') : '–' ?></td>
                    <td>
                        <a href="/pages/notacobranza_view.php?id=<?= (int)$n['id_cabecera'] ?>" class="btn-primary" title="Editar">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="/pages/generar_pdf_notacobranza.php?id=<?= (int)$n['id_cabecera'] ?>" target="_blank" class="btn-comment" title="PDF">
                            <i class="fas fa-file-pdf"></i>
                        </a>
                        <a href="#" class="btn-delete" title="Eliminar" onclick="confirmarEliminarCabecera(<?= (int)$n['id_cabecera'] ?>)">
                            <i class="fas fa-trash-alt"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="card" style="text-align: center; padding: 2rem;">
            <i class="fas fa-file-invoice-dollar" style="font-size: 3rem; color: #bdc3c7; margin-bottom: 1rem;"></i>
            <p>No hay notas de cobranza registradas.</p>
        </div>
    <?php endif; ?>
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
            window.location.reload();
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

window.onclick = function(event) {
    const modal = document.getElementById('modal-eliminar-cabecera');
    if (event.target === modal) {
        cerrarModalEliminar();
    }
}
</script>
</body>
</html>