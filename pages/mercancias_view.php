<?php
require_once '../session_check.php';
require_once '../config.php';

$rol = $_SESSION['rol'] ?? '';
if ($rol !== 'admin' && $rol !== 'comercial' && $rol !== 'pricing') {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso denegado.');
}

// Cargar catálogo global de mercancías
$mercancias = [];
if (php_sapi_name() !== 'cli') {
    try {
        $pdo = getDBConnection();
        if ($pdo) {
            $stmt = $pdo->query("SELECT id_mrcc, mercancia_mrcc FROM mercancias ORDER BY mercancia_mrcc");
            $mercancias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Error al cargar catálogo de mercancías: " . $e->getMessage());
        $mercancias = [];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Catálogo de Mercancías - SIGA</title>
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
            grid-template-columns: 1fr;
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
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="container">
    <div class="card-header">
        <h2 style="font-weight: bold;">
            <i class="fas fa-box"></i> Catálogo de Mercancías
        </h2>
        <button class="btn-primary" onclick="abrirSubmodalMercancia()">
            <i class="fas fa-plus"></i> Nueva Mercancía
        </button>
    </div>

    <?php if (!empty($mercancias)): ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre de Mercancía</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mercancias as $m): ?>
                <tr>
                    <td><?= (int)$m['id_mrcc'] ?></td>
                    <td><?= htmlspecialchars($m['mercancia_mrcc'] ?? '') ?></td>
                    <td>
                        <a href="#" class="btn-edit" title="Editar" onclick="editarMercancia(<?= $m['id_mrcc'] ?>)">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="#" class="btn-delete" title="Eliminar" onclick="eliminarMercancia(<?= $m['id_mrcc'] ?>)">
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
            <p>No hay mercancías registradas en el catálogo.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Submodal Mercancía -->
<div id="submodal-mercancia" class="submodal" style="display: none;">
    <div class="submodal-content" style="max-width: 500px; padding: 1.8rem; position: relative;">
        <span class="submodal-close" onclick="cerrarSubmodalMercancia()" style="position: absolute; top: 1.2rem; right: 1.2rem;">">×</span>
        <h3 style="margin: 0 0 1.4rem 0; font-size: 1.3rem;" id="submodal-titulo-mercancia">
            <i class="fas fa-box"></i> Nueva Mercancía
        </h3>

        <form id="form-mercancia">
            <input type="hidden" id="id_mrcc">

            <div class="form-grid">
                <div>
                    <label for="mercancia_mrcc">Nombre de la Mercancía:</label>
                    <input type="text" id="mercancia_mrcc" required>
                </div>
            </div>

            <div style="text-align: right; display: flex; gap: 0.8rem; justify-content: flex-end;">
                <button type="button" class="btn-primary" onclick="guardarMercancia()" style="padding: 0.55rem 1.4rem;">
                    <i class="fas fa-save"></i> Guardar
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
    document.getElementById('id_mrcc').value = '';
    document.getElementById('mercancia_mrcc').value = '';
    document.getElementById('submodal-mercancia').style.display = 'flex';
}

function cerrarSubmodalMercancia() {
    document.getElementById('submodal-mercancia').style.display = 'none';
}

function guardarMercancia() {
    const formData = new FormData();
    const id_mrcc = document.getElementById('id_mrcc').value;

    formData.append('action', id_mrcc ? 'actualizar_mercancia_catalogo' : 'crear_mercancia_catalogo');
    formData.append('mercancia_mrcc', document.getElementById('mercancia_mrcc').value.trim());

    if (id_mrcc) {
        formData.append('id_mrcc', id_mrcc);
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
    fetch(`/pages/mercancias_logic.php?action=obtener_mercancia_catalogo&id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (!data || !data.id_mrcc) {
                alert('❌ No se pudo cargar la mercancía.');
                return;
            }
            document.getElementById('submodal-titulo-mercancia').innerHTML = '<i class="fas fa-box"></i> Editar Mercancía';
            document.getElementById('id_mrcc').value = data.id_mrcc;
            document.getElementById('mercancia_mrcc').value = data.mercancia_mrcc;
            document.getElementById('submodal-mercancia').style.display = 'flex';
        })
        .catch(err => {
            console.error('Error al cargar mercancía:', err);
            alert('❌ Error al cargar la mercancía.');
        });
}

function eliminarMercancia(id) {
    if (confirm('¿Eliminar esta mercancía del catálogo?')) {
        const formData = new FormData();
        formData.append('action', 'eliminar_mercancia_catalogo');
        formData.append('id_mrcc', id);

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