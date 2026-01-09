<?php
require_once '../session_check.php';
require_once '../config.php';

$rol = $_SESSION['rol'] ?? '';
if ($rol !== 'admin' && $rol !== 'comercial' && $rol !== 'pricing') {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso denegado.');
}

// Cargar datos solo en contexto web (no durante build)
$transportes = [];
$vehiculos = [];
$choferes = [];

if (php_sapi_name() !== 'cli') {
    try {
        $pdo = getDBConnection();
        if ($pdo) {
            // Cargar lista de transportes
            $stmt = $pdo->query("
                SELECT 
                    t.*,
                    v.patente_veh AS vehiculo_patente,
                    p.nombre_per AS chofer_nombre
                FROM transporte t
                LEFT JOIN vehiculos v ON t.id_vehiculo_transporte = v.id_veh
                LEFT JOIN personal p ON t.id_chofer_transporte = p.id_per
                ORDER BY t.id_transporte DESC
            ");
            $transportes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Cargar listas para desplegables
            $stmt = $pdo->query("SELECT id_veh, patente_veh, tipo_veh FROM vehiculos ORDER BY patente_veh");
            $vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->query("SELECT id_per, nombre_per, rut_per FROM personal WHERE tipo_personal = 'Chofer' ORDER BY nombre_per");
            $choferes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Error al cargar transporte: " . $e->getMessage());
        $transportes = [];
        $vehiculos = [];
        $choferes = [];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Gestión de Transporte - SIGA</title>
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
        .form-grid select, .form-grid input {
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
        .table-container th:nth-child(4),
        .table-container td:nth-child(4) {
            min-width: 180px;
        }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="container">
    <div class="card-header">
        <h2 style="font-weight: bold;">
            <i class="fas fa-truck"></i> Gestión de Transporte
        </h2>
        <button class="btn-primary" onclick="abrirSubmodalTransporte()">
            <i class="fas fa-plus"></i> Nuevo Transporte
        </button>
    </div>

    <?php if (!empty($transportes)): ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Vehículo</th>
                    <th>Chofer</th>
                    <th>Tipo Transporte</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transportes as $t): ?>
                <tr>
                    <td><?= (int)$t['id_transporte'] ?></td>
                    <td><?= htmlspecialchars($t['vehiculo_patente'] ?? '–') ?></td>
                    <td><?= htmlspecialchars($t['chofer_nombre'] ?? '–') ?></td>
                    <td><?= htmlspecialchars($t['tipo_transporte'] ?? '–') ?></td>
                    <td>
                        <a href="#" class="btn-edit" title="Editar" onclick="editarTransporte(<?= $t['id_transporte'] ?>)">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="#" class="btn-delete" title="Eliminar" onclick="eliminarTransporte(<?= $t['id_transporte'] ?>)">
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
            <i class="fas fa-truck" style="font-size: 3rem; color: #bdc3c7; margin-bottom: 1rem;"></i>
            <p>No hay registros de transporte.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Submodal Transporte -->
<div id="submodal-transporte" class="submodal" style="display: none;">
    <div class="submodal-content" style="max-width: 550px; padding: 1.8rem; position: relative;">
        <span class="submodal-close" onclick="cerrarSubmodalTransporte()" style="position: absolute; top: 1.2rem; right: 1.2rem;">×</span>
        <h3 style="margin: 0 0 1.4rem 0; font-size: 1.3rem;" id="submodal-titulo-transporte">
            <i class="fas fa-truck"></i> Nuevo Transporte
        </h3>

        <form id="form-transporte">
            <input type="hidden" id="id_transporte">

            <div class="form-grid">
                <div>
                    <label for="id_vehiculo_transporte">Vehículo:</label>
                    <select id="id_vehiculo_transporte" required>
                        <option value="">-- Seleccione --</option>
                        <?php foreach ($vehiculos as $v): ?>
                            <option value="<?= (int)$v['id_veh'] ?>">
                                <?= htmlspecialchars($v['patente_veh'] . ' (' . $v['tipo_veh'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="id_chofer_transporte">Chofer:</label>
                    <select id="id_chofer_transporte" required>
                        <option value="">-- Seleccione --</option>
                        <?php foreach ($choferes as $p): ?>
                            <option value="<?= (int)$p['id_per'] ?>">
                                <?= htmlspecialchars($p['nombre_per'] . ' - ' . $p['rut_per']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="tipo_transporte">Tipo de Transporte:</label>
                    <select id="tipo_transporte" required>
                        <option value="Terrestre">Terrestre</option>
                        <option value="Marítimo">Marítimo</option>
                        <option value="Aéreo">Aéreo</option>
                    </select>
                </div>
            </div>

            <div style="text-align: right; display: flex; gap: 0.8rem; justify-content: flex-end;">
                <button type="button" class="btn-primary" onclick="guardarTransporte()" style="padding: 0.55rem 1.4rem;">
                    <i class="fas fa-save"></i> Guardar Transporte
                </button>
                <button type="button" class="btn-secondary" onclick="cerrarSubmodalTransporte()" style="padding: 0.55rem 1.4rem;">
                    Cerrar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirSubmodalTransporte() {
    document.getElementById('submodal-titulo-transporte').innerHTML = '<i class="fas fa-truck"></i> Nuevo Transporte';
    document.getElementById('id_transporte').value = '';
    document.getElementById('id_vehiculo_transporte').value = '';
    document.getElementById('id_chofer_transporte').value = '';
    document.getElementById('tipo_transporte').value = 'Terrestre';
    document.getElementById('submodal-transporte').style.display = 'flex';
}

function cerrarSubmodalTransporte() {
    document.getElementById('submodal-transporte').style.display = 'none';
}

function guardarTransporte() {
    const formData = new FormData();
    const id_transporte = document.getElementById('id_transporte').value;

    formData.append('action', id_transporte ? 'actualizar_transporte' : 'crear_transporte');
    formData.append('id_vehiculo_transporte', document.getElementById('id_vehiculo_transporte').value);
    formData.append('id_chofer_transporte', document.getElementById('id_chofer_transporte').value);
    formData.append('tipo_transporte', document.getElementById('tipo_transporte').value);

    if (id_transporte) {
        formData.append('id_transporte', id_transporte);
    }

    fetch('/pages/transporte_logic.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✅ Transporte guardado.');
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

function editarTransporte(id) {
    alert('Edición no implementada en esta versión (reimplementar con API si es necesario).');
}

function eliminarTransporte(id) {
    if (confirm('¿Eliminar este registro de transporte?')) {
        const formData = new FormData();
        formData.append('action', 'eliminar_transporte');
        formData.append('id_transporte', id);

        fetch('/pages/transporte_logic.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Transporte eliminado.');
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