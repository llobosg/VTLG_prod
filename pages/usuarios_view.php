<?php
require_once '../session_check.php';
require_once '../config.php';

$rol = $_SESSION['rol'] ?? '';
if ($rol !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso denegado: solo administradores.');
}

// Cargar datos solo en contexto web (no durante build)
$usuarios = [];
if (php_sapi_name() !== 'cli') {
    try {
        $pdo = getDBConnection();
        if ($pdo) {
            // Verificar si existe columna 'activo', si no, agregarla
            $check = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'activo'");
            if (!$check->fetch()) {
                $pdo->exec("ALTER TABLE usuarios ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER rol_usr");
            }

            $stmt = $pdo->query("
                SELECT 
                    id_usr AS id_usuario,
                    nombre_usr AS nombre_usuario,
                    nombre_usr AS usuario,
                    rol_usr AS rol,
                    activo
                FROM usuarios 
                ORDER BY nombre_usr
            ");
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Error al cargar usuarios: " . $e->getMessage());
        $usuarios = [];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Gestión de Usuarios - SIGA</title>
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
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="container">
    <div class="card-header">
        <h2 style="font-weight: bold;">
            <i class="fas fa-users"></i> Gestión de Usuarios
        </h2>
        <button class="btn-primary" onclick="abrirSubmodalUsuario()">
            <i class="fas fa-plus"></i> Nuevo Usuario
        </button>
    </div>

    <?php if (!empty($usuarios)): ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Activo</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?= (int)$u['id_usuario'] ?></td>
                    <td><?= htmlspecialchars($u['nombre_usuario'] ?? '') ?></td>
                    <td><?= htmlspecialchars($u['usuario'] ?? '') ?></td>
                    <td><?= htmlspecialchars($u['rol'] ?? '') ?></td>
                    <td><?= $u['activo'] ? 'Sí' : 'No' ?></td>
                    <td>
                        <a href="#" class="btn-edit" title="Editar" onclick="editarUsuario(<?= $u['id_usuario'] ?>)">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="#" class="btn-delete" title="Eliminar" onclick="eliminarUsuario(<?= $u['id_usuario'] ?>)">
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
            <i class="fas fa-users" style="font-size: 3rem; color: #bdc3c7; margin-bottom: 1rem;"></i>
            <p>No hay usuarios registrados.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Submodal Usuario -->
<div id="submodal-usuario" class="submodal" style="display: none;">
    <div class="submodal-content" style="max-width: 500px; padding: 1.8rem; position: relative;">
        <span class="submodal-close" onclick="cerrarSubmodalUsuario()" style="position: absolute; top: 1.2rem; right: 1.2rem;">×</span>
        <h3 style="margin: 0 0 1.4rem 0; font-size: 1.3rem;" id="submodal-titulo-usuario">
            <i class="fas fa-user-plus"></i> Nuevo Usuario
        </h3>

        <form id="form-usuario">
            <input type="hidden" id="id_usuario">

            <div class="form-grid">
                <div>
                    <label for="nombre_usuario">Usuario:</label>
                    <input type="text" id="nombre_usuario" required>
                </div>
                <div>
                    <label for="rol">Rol:</label>
                    <select id="rol" required>
                        <option value="admin">Administrador</option>
                        <option value="comercial">Comercial</option>
                        <option value="pricing">Pricing</option>
                        <option value="usuario">Usuario</option>
                    </select>
                </div>
                <div>
                    <label for="password">Contraseña:</label>
                    <input type="password" id="password" required>
                </div>
                <div>
                    <label for="activo">Activo:</label>
                    <select id="activo" required>
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>
                </div>
            </div>

            <div style="text-align: right; display: flex; gap: 0.8rem; justify-content: flex-end;">
                <button type="button" class="btn-primary" onclick="guardarUsuario()" style="padding: 0.55rem 1.4rem;">
                    <i class="fas fa-save"></i> Guardar Usuario
                </button>
                <button type="button" class="btn-secondary" onclick="cerrarSubmodalUsuario()" style="padding: 0.55rem 1.4rem;">
                    Cerrar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirSubmodalUsuario() {
    document.getElementById('submodal-titulo-usuario').innerHTML = '<i class="fas fa-user-plus"></i> Nuevo Usuario';
    document.getElementById('id_usuario').value = '';
    document.getElementById('nombre_usuario').value = '';
    document.getElementById('rol').value = 'usuario';
    document.getElementById('password').value = '';
    document.getElementById('activo').value = '1';
    document.getElementById('submodal-usuario').style.display = 'flex';
}

function cerrarSubmodalUsuario() {
    document.getElementById('submodal-usuario').style.display = 'none';
}

function guardarUsuario() {
    const formData = new FormData();
    const id_usuario = document.getElementById('id_usuario').value;
    
    formData.append('action', id_usuario ? 'actualizar_usuario' : 'crear_usuario');
    formData.append('nombre_usuario', document.getElementById('nombre_usuario').value.trim());
    formData.append('rol', document.getElementById('rol').value);
    formData.append('activo', document.getElementById('activo').value);
    
    if (!id_usuario || document.getElementById('password').value) {
        formData.append('password', document.getElementById('password').value);
    }
    
    if (id_usuario) {
        formData.append('id_usuario', id_usuario);
    }

    fetch('/pages/usuarios_logic.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✅ Usuario guardado.');
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

function editarUsuario(id) {
    fetch(`/pages/usuarios_logic.php?action=obtener_usuario&id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (!data || !data.id_usuario) {
                alert('❌ No se pudo cargar el usuario.');
                return;
            }
            document.getElementById('submodal-titulo-usuario').innerHTML = '<i class="fas fa-user-edit"></i> Editar Usuario';
            document.getElementById('id_usuario').value = data.id_usuario;
            document.getElementById('nombre_usuario').value = data.nombre_usuario;
            document.getElementById('rol').value = data.rol;
            document.getElementById('activo').value = data.activo;
            document.getElementById('password').value = ''; // No se muestra la contraseña
            document.getElementById('submodal-usuario').style.display = 'flex';
        })
        .catch(err => {
            console.error('Error al cargar usuario:', err);
            alert('❌ Error al cargar el usuario.');
        });
}

function eliminarUsuario(id) {
    if (confirm('¿Eliminar este usuario? Esta acción no se puede deshacer.')) {
        const formData = new FormData();
        formData.append('action', 'eliminar_usuario');
        formData.append('id_usuario', id);

        fetch('/pages/usuarios_logic.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Usuario eliminado.');
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

// Detectar sesión inválida desde el frontend
window.addEventListener('load', function() {
    // Si hay un mensaje de "Sesión inválida" en el cuerpo, redirigir
    if (document.body.innerText.includes('Sesión inválida') || 
        document.body.innerHTML.includes('faltan user_id')) {
        localStorage.clear();
        sessionStorage.clear();
        alert('Sesión expirada. Por favor, inicie sesión nuevamente.');
        window.location.href = '/login.php';
    }
    
    // Timeout de seguridad (opcional)
    setTimeout(() => {
        if (document.readyState === 'complete' && 
            !document.querySelector('.container, .card, table')) {
            console.warn('Página sin contenido. Recargando...');
            window.location.reload();
        }
    }, 10000);
});
</script>
</body>
</html>