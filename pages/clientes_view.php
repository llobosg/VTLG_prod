<?php
require_once '../session_check.php';
require_once '../config.php';

$rol = $_SESSION['rol'] ?? '';
if ($rol !== 'admin' && $rol !== 'comercial') {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso denegado.');
}

// Cargar datos solo en contexto web (no durante build)
$clientes = [];
if (php_sapi_name() !== 'cli') {
    try {
        $pdo = getDBConnection();
        if ($pdo) {
            $stmt = $pdo->query("SELECT * FROM clientes ORDER BY nombre_clt");
            $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Error al cargar clientes: " . $e->getMessage());
        $clientes = [];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Gestión de Clientes - SIGA</title>
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
        .form-grid input, .form-grid select {
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
        .table-container th:nth-child(2),
        .table-container td:nth-child(2) {
            min-width: 200px;
        }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="container">
    <div class="card-header">
        <h2 style="font-weight: bold;">
            <i class="fas fa-user-tie"></i> Gestión de Clientes
        </h2>
        <button class="btn-primary" onclick="abrirSubmodalCliente()">
            <i class="fas fa-plus"></i> Nuevo Cliente
        </button>
    </div>

    <?php if (!empty($clientes)): ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>RUT</th>
                    <th>Ciudad</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientes as $c): ?>
                <tr>
                    <td><?= (int)$c['id_clt'] ?></td>
                    <td><?= htmlspecialchars($c['nombre_clt'] ?? '') ?></td>
                    <td><?= htmlspecialchars($c['rut_clt'] ?? '') ?></td>
                    <td><?= htmlspecialchars($c['ciudad_clt'] ?? '') ?></td>
                    <td>
                        <a href="#" class="btn-edit" title="Editar" onclick="editarCliente(<?= $c['id_clt'] ?>)">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="#" class="btn-delete" title="Eliminar" onclick="eliminarCliente(<?= $c['id_clt'] ?>)">
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
            <i class="fas fa-user-tie" style="font-size: 3rem; color: #bdc3c7; margin-bottom: 1rem;"></i>
            <p>No hay clientes registrados.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Submodal Cliente -->
<div id="submodal-cliente" class="submodal" style="display: none;">
    <div class="submodal-content" style="max-width: 600px; padding: 1.8rem; position: relative;">
        <span class="submodal-close" onclick="cerrarSubmodalCliente()" style="position: absolute; top: 1.2rem; right: 1.2rem;">×</span>
        <h3 style="margin: 0 0 1.4rem 0; font-size: 1.3rem;" id="submodal-titulo-cliente">
            <i class="fas fa-user-plus"></i> Nuevo Cliente
        </h3>

        <form id="form-cliente">
            <input type="hidden" id="id_clt">

            <div class="form-grid">
                <div>
                    <label for="nombre_clt">Nombre del Cliente:</label>
                    <input type="text" id="nombre_clt" required>
                </div>
                <div>
                    <label for="rut_clt">RUT:</label>
                    <input type="text" id="rut_clt" placeholder="12.345.678-9" required>
                </div>
                <div>
                    <label for="direccion_clt">Dirección:</label>
                    <input type="text" id="direccion_clt">
                </div>
                <div>
                    <label for="ciudad_clt">Ciudad:</label>
                    <input type="text" id="ciudad_clt">
                </div>
                <div>
                    <label for="contacto_clt">Contacto:</label>
                    <input type="text" id="contacto_clt">
                </div>
                <div>
                    <label for="email_clt">Email:</label>
                    <input type="email" id="email_clt">
                </div>
            </div>

            <div style="text-align: right; display: flex; gap: 0.8rem; justify-content: flex-end;">
                <button type="button" class="btn-primary" onclick="guardarCliente()" style="padding: 0.55rem 1.4rem;">
                    <i class="fas fa-save"></i> Guardar Cliente
                </button>
                <button type="button" class="btn-secondary" onclick="cerrarSubmodalCliente()" style="padding: 0.55rem 1.4rem;">
                    Cerrar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirSubmodalCliente() {
    document.getElementById('submodal-titulo-cliente').innerHTML = '<i class="fas fa-user-plus"></i> Nuevo Cliente';
    document.getElementById('id_clt').value = '';
    document.getElementById('nombre_clt').value = '';
    document.getElementById('rut_clt').value = '';
    document.getElementById('direccion_clt').value = '';
    document.getElementById('ciudad_clt').value = '';
    document.getElementById('contacto_clt').value = '';
    document.getElementById('email_clt').value = '';
    document.getElementById('submodal-cliente').style.display = 'flex';
}

function cerrarSubmodalCliente() {
    document.getElementById('submodal-cliente').style.display = 'none';
}

function guardarCliente() {
    const formData = new FormData();
    const id_clt = document.getElementById('id_clt').value;

    formData.append('action', id_clt ? 'actualizar_cliente' : 'crear_cliente');
    formData.append('nombre_clt', document.getElementById('nombre_clt').value);
    formData.append('rut_clt', document.getElementById('rut_clt').value);
    formData.append('direccion_clt', document.getElementById('direccion_clt').value);
    formData.append('ciudad_clt', document.getElementById('ciudad_clt').value);
    formData.append('contacto_clt', document.getElementById('contacto_clt').value);
    formData.append('email_clt', document.getElementById('email_clt').value);

    if (id_clt) {
        formData.append('id_clt', id_clt);
    }

    fetch('/pages/clientes_logic.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✅ Cliente guardado.');
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

function editarCliente(id) {
    alert('Edición no implementada en esta versión (reimplementar con API si es necesario).');
}

function eliminarCliente(id) {
    if (confirm('¿Eliminar este cliente?')) {
        const formData = new FormData();
        formData.append('action', 'eliminar_cliente');
        formData.append('id_clt', id);

        fetch('/pages/clientes_logic.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Cliente eliminado.');
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