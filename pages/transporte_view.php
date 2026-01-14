<?php
require_once '../session_check.php';
require_once '../config.php';

$rol = $_SESSION['rol'] ?? '';
if ($rol !== 'admin' && $rol !== 'comercial' && $rol !== 'pricing') {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso denegado.');
}

// Cargar catálogo global de transporte
$transportes = [];
if (php_sapi_name() !== 'cli') {
    try {
        $pdo = getDBConnection();
        if ($pdo) {
            $stmt = $pdo->query("SELECT id_trnsprt, transporte_trnsprt, contacto_trnsprt, fono_trnsprt FROM transporte ORDER BY transporte_trnsprt");
            $transportes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Error al cargar catálogo de transporte: " . $e->getMessage());
        $transportes = [];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Catálogo de Transporte - SIGA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <link rel="stylesheet" href="/styles.css">
    <style>
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.2rem;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .form-grid.full-width {
            grid-template-columns: 1fr;
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
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="container">
    <div class="card-header">
        <h2 style="font-weight: bold;">
            <i class="fas fa-truck"></i> Catálogo de Transporte
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
                    <th>Empresa</th>
                    <th>Contacto</th>
                    <th>Teléfono</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transportes as $t): ?>
                <tr>
                    <td><?= (int)$t['id_trnsprt'] ?></td>
                    <td><?= htmlspecialchars($t['transporte_trnsprt'] ?? '') ?></td>
                    <td><?= htmlspecialchars($t['contacto_trnsprt'] ?? '') ?></td>
                    <td><?= htmlspecialchars($t['fono_trnsprt'] ?? '') ?></td>
                    <td>
                        <a href="#" class="btn-edit" title="Editar" onclick="editarTransporte(<?= $t['id_trnsprt'] ?>)">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="#" class="btn-delete" title="Eliminar" onclick="eliminarTransporte(<?= $t['id_trnsprt'] ?>)">
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
            <p>No hay registros de transporte en el catálogo.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Submodal Transporte -->
<div id="submodal-transporte" class="submodal" style="display: none;">
    <div class="submodal-content" style="max-width: 600px; padding: 1.8rem; position: relative;">
        <span class="submodal-close" onclick="cerrarSubmodalTransporte()" style="position: absolute; top: 1.2rem; right: 1.2rem;"></span>
        <h3 style="margin: 0 0 1.4rem 0; font-size: 1.3rem;" id="submodal-titulo-transporte">
            <i class="fas fa-truck"></i> Nuevo Transporte
        </h3>

        <form id="form-transporte">
            <input type="hidden" id="id_trnsprt">

            <div class="form-grid">
                <div>
                    <label for="transporte_trnsprt">Empresa de Transporte:</label>
                    <input type="text" id="transporte_trnsprt" required>
                </div>
                <div>
                    <label for="contacto_trnsprt">Contacto:</label>
                    <input type="text" id="contacto_trnsprt">
                </div>
                <div>
                    <label for="fono_trnsprt">Teléfono:</label>
                    <input type="text" id="fono_trnsprt">
                </div>
                <div>
                    <label for="email_trnsprt">Email:</label>
                    <input type="email" id="email_trnsprt">
                </div>
            </div>

            <div class="form-grid full-width">
                <div>
                    <label for="direccion_trnsprt">Dirección:</label>
                    <input type="text" id="direccion_trnsprt">
                </div>
            </div>

            <div style="text-align: right; display: flex; gap: 0.8rem; justify-content: flex-end;">
                <button type="button" class="btn-primary" onclick="guardarTransporte()" style="padding: 0.55rem 1.4rem;">
                    <i class="fas fa-save"></i> Guardar
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
    document.getElementById('id_trnsprt').value = '';
    document.getElementById('transporte_trnsprt').value = '';
    document.getElementById('contacto_trnsprt').value = '';
    document.getElementById('fono_trnsprt').value = '';
    document.getElementById('email_trnsprt').value = '';
    document.getElementById('direccion_trnsprt').value = '';
    document.getElementById('submodal-transporte').style.display = 'flex';
}

function cerrarSubmodalTransporte() {
    document.getElementById('submodal-transporte').style.display = 'none';
}

function guardarTransporte() {
    const formData = new FormData();
    const id_trnsprt = document.getElementById('id_trnsprt').value;

    formData.append('action', id_trnsprt ? 'actualizar_transporte_catalogo' : 'crear_transporte_catalogo');
    formData.append('transporte_trnsprt', document.getElementById('transporte_trnsprt').value.trim());
    formData.append('contacto_trnsprt', document.getElementById('contacto_trnsprt').value.trim() || '');
    formData.append('fono_trnsprt', document.getElementById('fono_trnsprt').value.trim() || '');
    formData.append('email_trnsprt', document.getElementById('email_trnsprt').value.trim() || '');
    formData.append('direccion_trnsprt', document.getElementById('direccion_trnsprt').value.trim() || '');

    if (id_trnsprt) {
        formData.append('id_trnsprt', id_trnsprt);
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
    fetch(`/pages/transporte_logic.php?action=obtener_transporte_catalogo&id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (!data || !data.id_trnsprt) {
                alert('❌ No se pudo cargar el transporte.');
                return;
            }
            document.getElementById('submodal-titulo-transporte').innerHTML = '<i class="fas fa-truck"></i> Editar Transporte';
            document.getElementById('id_trnsprt').value = data.id_trnsprt;
            document.getElementById('transporte_trnsprt').value = data.transporte_trnsprt || '';
            document.getElementById('contacto_trnsprt').value = data.contacto_trnsprt || '';
            document.getElementById('fono_trnsprt').value = data.fono_trnsprt || '';
            document.getElementById('email_trnsprt').value = data.email_trnsprt || '';
            document.getElementById('direccion_trnsprt').value = data.direccion_trnsprt || '';
            document.getElementById('submodal-transporte').style.display = 'flex';
        })
        .catch(err => {
            console.error('Error al cargar transporte:', err);
            alert('❌ Error al cargar el transporte.');
        });
}

function eliminarTransporte(id) {
    if (confirm('¿Eliminar este transporte del catálogo?')) {
        const formData = new FormData();
        formData.append('action', 'eliminar_transporte_catalogo');
        formData.append('id_trnsprt', id);

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