<?php
require_once '../session_check.php';
require_once '../config.php';

$rol = $_SESSION['rol'] ?? '';
if ($rol !== 'admin' && $rol !== 'comercial' && $rol !== 'pricing') {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso denegado.');
}

// Parámetros
$id_rms = $_GET['id'] ?? null;
$remesa_data = null;
$cliente_data = null;
$transporte_data = null;
$mercancias = [];
$costos = [];

// Cargar datos solo en contexto web (no durante build)
if (php_sapi_name() !== 'cli') {
    try {
        $pdo = getDBConnection();
        if ($pdo) {
            // Cargar cliente para lista desplegable
            $stmt = $pdo->query("SELECT id_clt, nombre_clt FROM clientes ORDER BY nombre_clt");
            $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Cargar remesa si existe ID
            if ($id_rms) {
                $stmt = $pdo->prepare("
                    SELECT 
                        r.*,
                        c.nombre_clt AS cliente_nombre,
                        c.rut_clt,
                        c.direccion_clt,
                        c.ciudad_clt,
                        t.tipo_transporte,
                        t.patente_transporte,
                        t.chofer_transporte
                    FROM remesa r
                    LEFT JOIN clientes c ON r.id_clt_rms = c.id_clt
                    LEFT JOIN transporte t ON r.id_transporte_rms = t.id_transporte
                    WHERE r.id_rms = ?
                ");
                $stmt->execute([$id_rms]);
                $remesa_data = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($remesa_data) {
                    // Cargar mercancías
                    $stmt = $pdo->prepare("SELECT * FROM mercancias WHERE id_rms = ? ORDER BY id_merc");
                    $stmt->execute([$id_rms]);
                    $mercancias = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Cargar costos
                    $stmt = $pdo->prepare("SELECT * FROM costos_rms WHERE id_rms = ? ORDER BY id_costo");
                    $stmt->execute([$id_rms]);
                    $costos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } else {
                // Nueva remesa: solo clientes
                $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {
        error_log("Error al cargar remesa: " . $e->getMessage());
        $clientes = [];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= $id_rms ? 'Editar Remesa' : 'Nueva Remesa' ?> - SIGA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <link rel="stylesheet" href="/styles.css">
    <style>
        .valor-ficha { color: #2c3e50; }
        .subseccion {
            background: #f8f9fa;
            padding: 0.6rem;
            margin: 1rem 0 0.6rem 0;
            border-left: 3px solid #3498db;
            font-weight: bold;
        }
        .grid-form {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.8rem;
            margin-bottom: 1.2rem;
            align-items: center;
        }
        .grid-form label {
            font-weight: normal;
            text-align: right;
        }
        .grid-form input, .grid-form select {
            width: 100%;
            height: 2.3rem;
            padding: 0.3rem;
        }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem;">
        <h2 style="font-weight: bold;">
            <i class="fas fa-ship"></i> <?= $id_rms ? 'Editar Remesa' : 'Nueva Remesa' ?>
        </h2>
        <a href="/pages/remesa_lista.php" class="btn-secondary" style="text-decoration: none; padding: 0.4rem 0.8rem;">
            <i class="fas fa-arrow-left"></i> Volver a Lista
        </a>
    </div>

    <form id="form-remesa">
        <input type="hidden" name="id_rms" value="<?= (int)($id_rms ?? 0) ?>">

        <!-- Datos del Cliente -->
        <div class="subseccion">Datos del Cliente</div>
        <div class="grid-form">
            <label>Cliente:</label>
            <select name="cliente_rms" id="cliente_rms" style="grid-column: span 3;">
                <option value="">-- Seleccione --</option>
                <?php if (!empty($clientes)): ?>
                    <?php foreach ($clientes as $clt): ?>
                        <option value="<?= $clt['id_clt'] ?>" <?= ($remesa_data && $remesa_data['id_clt_rms'] == $clt['id_clt']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($clt['nombre_clt']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>

            <label>Contacto:</label>
            <input type="text" name="contacto_rms" value="<?= htmlspecialchars($remesa_data['contacto_rms'] ?? '') ?>" style="grid-column: span 3;">

            <label>Rut:</label>
            <div class="valor-ficha" id="rut_ficha"><?= htmlspecialchars($remesa_data['rut_clt'] ?? '') ?></div>
            <label>Dirección:</label>
            <div class="valor-ficha" id="direccion_ficha"><?= htmlspecialchars($remesa_data['direccion_clt'] ?? '') ?></div>

            <label>Ciudad:</label>
            <div class="valor-ficha" id="ciudad_ficha"><?= htmlspecialchars($remesa_data['ciudad_clt'] ?? '') ?></div>
            <label>Ref. Cliente:</label>
            <input type="text" name="ref_clte_rms" value="<?= htmlspecialchars($remesa_data['ref_clte_rms'] ?? '') ?>" style="grid-column: span 2;">
        </div>

        <!-- Datos Operativos -->
        <div class="subseccion">Datos Operativos</div>
        <div class="grid-form">
            <label>Tipo:</label>
            <select name="tipo_rms" style="grid-column: span 3;">
                <option value="">-- Seleccione --</option>
                <option value="Importación" <?= ($remesa_data && $remesa_data['tipo_rms'] === 'Importación') ? 'selected' : '' ?>>Importación</option>
                <option value="Exportación" <?= ($remesa_data && $remesa_data['tipo_rms'] === 'Exportación') ? 'selected' : '' ?>>Exportación</option>
            </select>

            <label>Fecha:</label>
            <input type="date" name="fecha_rms" value="<?= htmlspecialchars($remesa_data['fecha_rms'] ?? date('Y-m-d')) ?>" style="grid-column: span 3;">
            <label>Mes:</label>
            <input type="text" name="mes_rms" value="<?= htmlspecialchars($remesa_data['mes_rms'] ?? '') ?>" style="grid-column: span 3;">
            <label>Estado:</label>
            <select name="estado_rms" style="grid-column: span 3;">
                <option value="Solicitada" <?= ($remesa_data && $remesa_data['estado_rms'] === 'Solicitada') ? 'selected' : '' ?>>Solicitada</option>
                <option value="Aprobada" <?= ($remesa_data && $remesa_data['estado_rms'] === 'Aprobada') ? 'selected' : '' ?>>Aprobada</option>
                <option value="Rechazada" <?= ($remesa_data && $remesa_data['estado_rms'] === 'Rechazada') ? 'selected' : '' ?>>Rechazada</option>
            </select>
        </div>

        <!-- Botones de acción -->
        <div style="text-align: right; margin-top: 1.5rem;">
            <button type="button" class="btn-primary" onclick="guardarRemesa()" style="padding: 0.6rem 1.2rem;">
                <i class="fas fa-save"></i> <?= $id_rms ? 'Actualizar Remesa' : 'Crear Remesa' ?>
            </button>
        </div>
    </form>

    <?php if ($id_rms && $remesa_data): ?>
        <!-- Secciones secundarias (solo en edición) -->
        <div class="subseccion" style="margin-top: 2rem;">Transporte</div>
        <div style="text-align: center; padding: 1rem;">
            <?php if (!empty($remesa_data['patente_transporte'])): ?>
                <p><strong>Patente:</strong> <?= htmlspecialchars($remesa_data['patente_transporte']) ?> | 
                   <strong>Chofer:</strong> <?= htmlspecialchars($remesa_data['chofer_transporte']) ?> |
                   <strong>Tipo:</strong> <?= htmlspecialchars($remesa_data['tipo_transporte']) ?></p>
            <?php else: ?>
                <p>Sin transporte asignado.</p>
            <?php endif; ?>
            <button class="btn-primary" onclick="abrirSubmodal('transporte')" style="margin-top: 0.5rem;">
                <i class="fas fa-truck"></i> <?= $remesa_data['id_transporte_rms'] ? 'Editar Transporte' : 'Asignar Transporte' ?>
            </button>
        </div>

        <div class="subseccion">Mercancías</div>
        <div style="text-align: center; padding: 1rem;">
            <button class="btn-primary" onclick="abrirSubmodal('mercancia')" style="margin-bottom: 1rem;">
                <i class="fas fa-box"></i> Agregar Mercancía
            </button>
            <?php if (!empty($mercancias)): ?>
                <div class="table-container">
                    <table class="data-table" style="width: 100%;">
                        <thead>
                            <tr>
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
                                <td><?= htmlspecialchars($m['nombre_merc']) ?></td>
                                <td><?= (int)($m['bultos_merc'] ?? 0) ?></td>
                                <td><?= (float)($m['peso_merc'] ?? 0) ?></td>
                                <td><?= (float)($m['volumen_merc'] ?? 0) ?></td>
                                <td>
                                    <a href="#" class="btn-edit" title="Editar" onclick="editarItem('mercancia', <?= $m['id_merc'] ?>)">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="#" class="btn-delete" title="Eliminar" onclick="eliminarItem('mercancia', <?= $m['id_merc'] ?>)">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No hay mercancías registradas.</p>
            <?php endif; ?>
        </div>

        <div class="subseccion">Costos</div>
        <div style="text-align: center; padding: 1rem;">
            <button class="btn-primary" onclick="abrirSubmodal('costo')" style="margin-bottom: 1rem;">
                <i class="fas fa-dollar-sign"></i> Agregar Costo
            </button>
            <?php if (!empty($costos)): ?>
                <div class="table-container">
                    <table class="data-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Concepto</th>
                                <th>Monto</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($costos as $c): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['concepto_costo'] ?? '') ?></td>
                                <td><?= number_format($c['monto_costo'] ?? 0, 0, ',', '.') ?></td>
                                <td>
                                    <a href="#" class="btn-edit" title="Editar" onclick="editarItem('costo', <?= $c['id_costo'] ?>)">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="#" class="btn-delete" title="Eliminar" onclick="eliminarItem('costo', <?= $c['id_costo'] ?>)">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No hay costos registrados.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Submodales (implementación mínima para este ejemplo) -->
<div id="submodal-generico" class="submodal" style="display: none;">
    <div class="submodal-content" style="max-width: 500px; padding: 1.8rem; position: relative;">
        <span class="submodal-close" onclick="cerrarSubmodal()" style="position: absolute; top: 1.2rem; right: 1.2rem;">×</span>
        <h3 id="submodal-titulo" style="margin: 0 0 1.4rem 0; font-size: 1.3rem;">Submodal</h3>
        <div id="submodal-contenido"></div>
    </div>
</div>

<script>
function guardarRemesa() {
    const formData = new FormData(document.getElementById('form-remesa'));
    formData.append('action', '<?= $id_rms ? 'actualizar' : 'crear' ?>');

    fetch('/pages/remesa_logic.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✅ Remesa guardada.');
                <?php if (!$id_rms): ?>
                window.location.href = `/pages/remesa_view.php?id=${data.id_rms}`;
                <?php else: ?>
                location.reload();
                <?php endif; ?>
            } else {
                alert('❌ ' + (data.message || 'Error al guardar.'));
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('❌ Error de conexión.');
        });
}

// Funciones genéricas (implementar según necesidad)
function abrirSubmodal(tipo) {
    alert('Submodal de ' + tipo + ' no implementado en esta versión básica.');
}
function editarItem(tipo, id) {
    alert('Editar ' + tipo + ' ID: ' + id);
}
function eliminarItem(tipo, id) {
    if (confirm('¿Eliminar este registro?')) {
        alert('Eliminar ' + tipo + ' ID: ' + id + ' (implementar en lógica)');
    }
}
function cerrarSubmodal() {
    document.getElementById('submodal-generico').style.display = 'none';
}
</script>
</body>
</html>