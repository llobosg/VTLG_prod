<?php
require_once '../session_check.php';
require_once '../config.php';

$rol = $_SESSION['rol'] ?? '';
if ($rol !== 'admin' && $rol !== 'comercial') {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso denegado.');
}

$notas = [];

if (php_sapi_name() !== 'cli') {
    try {
        $pdo = getDBConnection();
        if ($pdo) {
            $stmt = $pdo->prepare("
                SELECT
                    nc.id_cabecera,
                    nc.nro_nc,
                    nc.concepto_nc,
                    nc.total_monto_nc,
                    nc.fecha_vence_nc,
                    r.estado_rms,
                    c.nombre_clt AS cliente_nombre
                FROM notacobranza nc
                LEFT JOIN remesa r ON nc.id_rms_nc = r.id_rms
                LEFT JOIN clientes c ON r.cliente_rms = c.id_clt
                ORDER BY nc.id_cabecera DESC
            ");
            $stmt->execute();
            $notas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Error al cargar lista de notas de cobranza: " . $e->getMessage());
        $notas = [];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Notas de Cobranza</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem;">
        <h2 style="font-weight: bold; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-file-invoice-dollar"></i> Notas de Cobranza
        </h2>
        <!-- ✅ Botón restaurado -->
        <a href="/pages/notacobranza_view.php" class="btn-primary" style="padding: 0.4rem 0.8rem; display: flex; align-items: center; gap: 0.4rem;">
            <i class="fas fa-plus"></i> Nueva Nota
        </a>
    </div>

    <div class="card">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nro.NC</th>
                        <th>Cliente</th>
                        <th>Concepto</th>
                        <th>Total</th>
                        <th>Fecha Vcto.</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($notas)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">No hay notas de cobranza registradas.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($notas as $n): ?>
                    <tr>
                        <td><?= htmlspecialchars($n['nro_nc'] ?? '') ?></td>
                        <td><?= htmlspecialchars($n['cliente_nombre'] ?? '') ?></td>
                        <td><?= htmlspecialchars($n['concepto_nc'] ?? '') ?></td>
                        <td><?= number_format($n['total_monto_nc'] ?? 0, 0, ',', '.') ?></td>
                        <td><?= htmlspecialchars($n['fecha_vence_nc'] ?? '') ?></td>
                        <td><?= htmlspecialchars($n['estado_rms'] ?? '–') ?></td>
                        <td>
                            <a href="/pages/notacobranza_view.php?id=<?= (int)$n['id_cabecera'] ?>" class="btn-primary" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="#" class="btn-delete" title="Eliminar" onclick="confirmarEliminarNota(<?= (int)$n['id_cabecera'] ?>)">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                            <a href="/pages/generar_pdf_notacobranza.php?id=<?= (int)$n['id_cabecera'] ?>" target="_blank" class="btn-comment" title="PDF">
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
            console.error('Error:', err);
            alert('❌ Error de conexión.');
        });
    }
}
</script>
</body>
</html>