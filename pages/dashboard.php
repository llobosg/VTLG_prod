<?php
require_once '../session_check.php';
require_once '../config.php';

$rol = $_SESSION['rol'] ?? 'usuario';
$usuario_nombre = $_SESSION['user'] ?? 'Usuario';

// Función para cargar datos del dashboard (solo si es necesario)
function cargarDatosDashboard($rol) {
    try {
        $pdo = getDBConnection();
        if (!$pdo) {
            return ['error' => 'No se pudo conectar a la base de datos.'];
        }

        $datos = [];

        // Conteo de remesas
        $stmt = $pdo->query("SELECT COUNT(*) FROM remesa");
        $datos['total_remesas'] = $stmt->fetchColumn();

        // Conteo de notas de cobranza
        $stmt = $pdo->query("SELECT COUNT(*) FROM notacobranza");
        $datos['total_notas_cobranza'] = $stmt->fetchColumn();

        // Conteo de rendiciones
        $stmt = $pdo->query("SELECT COUNT(*) FROM rendicion");
        $datos['total_rendiciones'] = $stmt->fetchColumn();

        // Solo para admin: usuarios
        if ($rol === 'admin') {
            $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios");
            $datos['total_usuarios'] = $stmt->fetchColumn();
        }

        return $datos;
    } catch (Exception $e) {
        error_log("Error al cargar datos del dashboard: " . $e->getMessage());
        return ['error' => 'Error al cargar los datos.'];
    }
}

// Solo cargar datos si es una petición web (no en CLI)
$dashboard_data = [];
if (php_sapi_name() !== 'cli') {
    $dashboard_data = cargarDatosDashboard($rol);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard - SIGA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <link rel="stylesheet" href="/styles.css">
    <style>
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border-left: 4px solid #3498db;
        }
        .stat-card.admin { border-left-color: #e74c3c; }
        .stat-card i {
            font-size: 2rem;
            color: #3498db;
            margin-bottom: 1rem;
        }
        .stat-card.admin i { color: #e74c3c; }
        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: #2c3e50;
            margin: 0.5rem 0;
        }
        .error { color: #e74c3c; font-weight: bold; }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="container">
    <h2 style="margin-bottom: 1.5rem;">
        <i class="fas fa-tachometer-alt"></i> Dashboard
        <span style="font-size: 0.9rem; color: #7f8c8d; margin-left: 1rem;">
            Bienvenido, <strong><?= htmlspecialchars($usuario_nombre) ?></strong> (Rol: <?= htmlspecialchars($rol) ?>)
        </span>
    </h2>

    <?php if (!empty($dashboard_data['error'])): ?>
        <div class="error">
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($dashboard_data['error']) ?>
        </div>
    <?php else: ?>
        <div class="card-grid">
            <div class="stat-card">
                <i class="fas fa-ship"></i>
                <div>Remesas</div>
                <div class="stat-value"><?= $dashboard_data['total_remesas'] ?? 0 ?></div>
            </div>

            <div class="stat-card">
                <i class="fas fa-file-invoice-dollar"></i>
                <div>Notas de Cobranza</div>
                <div class="stat-value"><?= $dashboard_data['total_notas_cobranza'] ?? 0 ?></div>
            </div>

            <div class="stat-card">
                <i class="fas fa-file-invoice"></i>
                <div>Rendiciones</div>
                <div class="stat-value"><?= $dashboard_data['total_rendiciones'] ?? 0 ?></div>
            </div>

            <?php if ($rol === 'admin'): ?>
            <div class="stat-card admin">
                <i class="fas fa-users"></i>
                <div>Usuarios</div>
                <div class="stat-value"><?= $dashboard_data['total_usuarios'] ?? 0 ?></div>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Enlaces rápidos según rol -->
    <div style="margin-top: 2.5rem;">
        <h3><i class="fas fa-link"></i> Accesos Rápidos</h3>
        <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1rem;">
            <?php if (in_array($rol, ['admin', 'comercial', 'pricing'])): ?>
                <a href="/pages/remesa_lista.php" class="btn-primary" style="text-decoration: none; padding: 0.6rem 1rem;">
                    <i class="fas fa-ship"></i> Gestión de Remesas
                </a>
            <?php endif; ?>
            
            <?php if (in_array($rol, ['admin', 'comercial'])): ?>
                <a href="/pages/notacobranza_lista.php" class="btn-primary" style="text-decoration: none; padding: 0.6rem 1rem;">
                    <i class="fas fa-file-invoice-dollar"></i> Notas de Cobranza
                </a>
            <?php endif; ?>
            
            <?php if (in_array($rol, ['admin', 'comercial'])): ?>
                <a href="/pages/rendicion_lista.php" class="btn-primary" style="text-decoration: none; padding: 0.6rem 1rem;">
                    <i class="fas fa-file-invoice"></i> Rendiciones de Gasto
                </a>
            <?php endif; ?>
            
            <?php if ($rol === 'admin'): ?>
                <a href="/pages/usuarios_view.php" class="btn-primary" style="text-decoration: none; padding: 0.6rem 1rem;">
                    <i class="fas fa-users"></i> Usuarios
                </a>
                <a href="/pages/personas_view.php" class="btn-primary" style="text-decoration: none; padding: 0.6rem 1rem;">
                    <i class="fas fa-id-card"></i> Personal
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>