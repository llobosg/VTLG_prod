<?php
require_once '../session_check.php';
require_once '../config.php';

$rol = $_SESSION['rol'] ?? '';
if ($rol !== 'admin' && $rol !== 'comercial' && $rol !== 'pricing') {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso denegado.');
}

// Parámetro: id de remesa
$id_rms = $_GET['id_rms'] ?? null;
$mercancias = [];
$remesa_resumen = ['cliente' => '–', 'ref_clte' => '–'];

// Cargar datos solo en contexto web (no durante build)
if (php_sapi_name() !== 'cli' && $id_rms) {
    try {
        $pdo = getDBConnection();
        if ($pdo) {
            // Cargar resumen de remesa
            $stmt = $pdo->prepare("
                SELECT r.ref_clte_rms, c.nombre_clt 
                FROM remesa r
                LEFT JOIN clientes c ON r.id_clt_rms = c.id_clt
                WHERE r.id_rms = ?
            ");
            $stmt->execute([$id_rms]);
            $remesa_resumen = $stmt->fetch(PDO::FETCH_ASSOC) ?: $remesa_resumen;

            // Cargar mercancías
            $stmt = $pdo->prepare("SELECT * FROM mercancias WHERE id_rms = ? ORDER BY id_merc");
            $stmt->execute([$id_rms]);
            $mercancias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Error al cargar mercancías: " . $e->getMessage());
        $mercancias = [];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Mercancías - SIGA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <link rel="stylesheet" href="/styles.css">
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .form-grid label {
            font-weight: bold;
            margin-bottom: 0.3rem;
            display: block;
        }
        .form-grid input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.2rem;
        }
        .info-remesa {
            background: #f8f9fa;
            padding: 0.6rem 1rem;
            border-radius: 6px;
            margin-bottom: 1.2rem;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="container">
    <div class="card-header">
        <div>
            <h2 style="font-weight: bold; margin: 0;">
                <i class="fas fa-box"></i> Gestión de Mercancías
            </h2>
            <?php if ($id_rms): ?>
            <div class="info-remesa">
                <strong>Remesa ID:</strong> <?= (int)$id_rms ?> | 
                <strong>Cliente:</strong> <?= htmlspecialchars($remesa_resumen['nombre_clt'] ?? '–') ?> | 
                <strong>Ref. Clte:</strong> <?= htmlspecialchars($remesa_resumen['ref_clte_rms'] ?? '–') ?>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($id_rms): ?>
        <button class="btn-primary" onclick="abrirSubmodalMercancia()">
            <i class="fas fa-plus"></i> Agregar Mercancía
        </button>
        <?php endif; ?>
    </div>

    <?php if ($id_rms): ?>
        <?php if (!empty($mercancias)): ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Bultos</th>
                        <th>Peso (kg)</th>
                        <th>Volumen (m³)</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mercancias as $m): ?>
                    <tr>
                        <td><?= (int)$m['id_merc'] ?></td>
                        <td><?= htmlspecialchars($m['nombre_merc'] ?? '') ?></td>
                        <td><?= (int)($m['bultos_merc'] ?? 0) ?></td>
                        <td><?= (float)($m['peso_merc'] ?? 0) ?></td>
                        <td><?= (float)($m['volumen_merc'] ?? 0) ?></td>
                        <td>
                            <a href="#" class="btn-edit" title="Editar" onclick="editarMercancia(<?= $m['id_merc'] ?>)">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="#" class="btn-delete" title="Eliminar" onclick="eliminarMercancia(<?= $m['id_merc'] ?>)">
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
                <i class="fas fa-box" style="font-size: 3rem; color: #bdc3c7; margin-bottom: 1rem;"></i>
                <p>No hay mercancías registradas para esta remesa.</p>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="card" style="text-align: center; padding: 2rem;">
            <p>❌ No se especificó una remesa. Vuelva desde la lista de remesas.</p>
            <a href="/pages/remesa_lista.php" class="btn-secondary" style="margin-top: 1rem;">Ir a Remesas</a>
        </div>
    <?php endif; ?>
</div>

<!-- Submodal Mercancía -->
<div id="submodal-mercancia" class="submodal" style="display: none;">
    <div class="submodal-content" style="max-width: 550px; padding: 1.8rem; position: relative;">
        <span class="submodal-close" onclick="cerrarSubmodalMercancia()" style="position: absolute; top: 1.2rem; right: 1.2rem;">">×</span>
        <h3 style="margin: 0 0 1.4rem 0; font-size: 1.3rem;" id="submodal-titulo-mercancia">
            <i class="fas fa-box"></i> Nueva Mercancía
        </h3>

        <form id="form-mercancia">
            <input type="hidden" id="id_rms" value="<?= (int)($id_rms ?? 0) ?>">
            <input type="hidden" id="id_merc">

            <div class="form-grid">
                <div>
                    <label for="nombre_merc">Nombre:</label>
                    <input type="text" id="nombre_merc" required>
                </div>
                <div>
                    <label for="bultos_merc">Bultos:</label>
                    <input type="number" id="bultos_merc" min="1" value="1" required>
                </div>
                <div>
                    <label for="peso_merc">Peso (kg):</label>
                    <input type="number" id="peso_merc" step="0.01" min="0" value="0" required>
                </div>
                <div>
                    <label for="volumen_merc">Volumen (m³):</label>
                    <input type="number" id="volumen_merc" step="0.01" min="0" value="0" required>
                </div>
            </div>

            <div style="text-align: right; display: flex; gap: 0.8rem; justify-content: flex-end;">
                <button type="button" class="btn-primary" onclick="guardarMercancia()" style="padding: 0.55rem 1.4rem;">
                    <i class="fas fa-save"></i> Guardar Mercancía
                </button>
                <button type="button" class="btn-secondary" onclick="cerrarSubmodalMercancia()" style="padding: 0.55rem 1.4rem;">
                    Cerrar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirSubmodalMercancia() {
    document.getElementById('submodal-titulo-mercancia').innerHTML = '<i class="fas fa-box"></i> Nueva Mercancía';
    document.getElementById('id_merc').value = '';
    document.getElementById('nombre_merc').value = '';
    document.getElementById('bultos_merc').value = '1';
    document.getElementById('peso_merc').value = '0';
    document.getElementById('volumen_merc').value = '0';
    document.getElementById('submodal-mercancia').style.display = 'flex';
}

function cerrarSubmodalMercancia() {
    document.getElementById('submodal-mercancia').style.display = 'none';
}

function guardarMercancia() {
    const formData = new FormData();
    const id_merc = document.getElementById('id_merc').value;

    formData.append('action', id_merc ? 'actualizar_mercancia' : 'crear_mercancia');
    formData.append('id_rms', document.getElementById('id_rms').value);
    formData.append('nombre_merc', document.getElementById('nombre_merc').value);
    formData.append('bultos_merc', document.getElementById('bultos_merc').value);
    formData.append('peso_merc', document.getElementById('peso_merc').value);
    formData.append('volumen_merc', document.getElementById('volumen_merc').value);

    if (id_merc) {
        formData.append('id_merc', id_merc);
    }

    fetch('/pages/mercancias_logic.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✅ Mercancía guardada.');
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

function editarMercancia(id) {
    alert('Edición no implementada en esta versión (reimplementar con API si es necesario).');
}

function eliminarMercancia(id) {
    if (confirm('¿Eliminar esta mercancía?')) {
        const formData = new FormData();
        formData.append('action', 'eliminar_mercancia');
        formData.append('id_merc', id);

        fetch('/pages/mercancias_logic.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Mercancía eliminada.');
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