<?php
// Asegurar sesión activa
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user'])) {
    header('Location: /login.php');
    exit;
}

$nombre_usuario = $_SESSION['user'] ?? 'Usuario';
$rol_usuario = $_SESSION['rol'] ?? 'usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
    <div class="navbar">
        <div class="logo">
            <span style="font-weight: bold; font-size: 1.3rem; color: white;">SIGA_LG</span>
        </div>
        <div class="nav-links">
            <span class="welcome-text">Hola, <strong><?= htmlspecialchars($nombre_usuario) ?></strong></span>

            <!-- Menú horizontal por rol -->
            <nav class="main-nav">
                <a href="/pages/dashboard.php"><i class="fas fa-chart-line"></i> dashboard</a>
                <a href="/pages/remesa_view.php"><i class="fas fa-money-bill-alt"></i> Solicitudes de Remesa</a>
                <a href="/pages/rendicion_listas.php"><i class="fas fa-receipt"></i> Rendición de Gastos</a>
                <li><a href="/pages/notacobranza_lista.php"><i class="fas fa-file-invoice-dollar"></i> Nota de Cobranza</a></li>
                <!-- Menú desplegable de Mantenedores (solo admin) -->
                <?php if ($rol_usuario === 'admin'): ?>
                    <li class="dropdown">
                        <a href="#" class="dropbtn">
                            <i class="fas fa-cogs"></i> Mantenedores <i class="fas fa-caret-down"></i>
                        </a>
                        <div class="dropdown-content">
                            <a href="/pages/clientes_view.php"><i class="fas fa-user-friends"></i> Clientes</a>
                            <a href="/pages/mercancias_view.php"><i class="fas fa-boxes"></i> Mercancías</a>
                            <a href="/pages/transporte_view.php"><i class="fas fa-truck"></i> Transporte</a>
                            <a href="/pages/usuarios_view.php"><i class="fas fa-users"></i> Usuarios</a>
                        </div>
                    </li>
                <?php endif; ?>
                <a href="/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Salir</a>
            </nav>
        </div>
    </div>

    <style>
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #2c3e50;
            color: white;
            padding: 0.6rem 1.8rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .welcome-text {
            font-size: 0.95rem;
            color: #ecf0f1;
        }
        .main-nav {
            display: flex;
            gap: 1.1rem;
            align-items: center;
        }
        .main-nav a {
            color: #ecf0f1;
            text-decoration: none;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.6rem;
            border-radius: 4px;
            transition: background 0.2s;
        }
        .main-nav a:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        .logout-link {
            color: #ff6b6b !important;
        }
        .logout-link:hover {
            background: rgba(255, 107, 107, 0.15) !important;
        }
        @media (max-width: 768px) {
            .navbar {
                flex-wrap: wrap;
                gap: 0.8rem;
                padding: 1rem;
            }
            .nav-links {
                width: 100%;
                justify-content: space-between;
            }
            .main-nav {
                flex-wrap: wrap;
                justify-content: center;
                gap: 0.6rem;
            }
        }

        * Estilos para menú desplegable */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #fff;
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            z-index: 1000;
            border-radius: 4px;
        }

        .dropdown-content a {
            color: #333;
            padding: 10px 16px;
            text-decoration: none;
            display: block;
            font-size: 0.95rem;
        }

        .dropdown-content a:hover {
            background-color: #f1f1f1;
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        .dropdown:hover .dropbtn {
            background-color: #f0f0f0;
        }

        /* Asegurar que los íconos se alineen */
        .main-nav ul li a i {
            margin-right: 6px;
        }
    </style>
</body>
</html>