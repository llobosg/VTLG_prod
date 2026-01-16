<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login - SIGA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <link rel="stylesheet" href="/styles.css">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 2.5rem;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            text-align: center;
            animation: fadeIn 0.6s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-logo {
            width: 220px;
            margin-bottom: 1rem;
            border-radius: 12px;
        }
        h2 {
            margin: 0 0 1.5rem 0;
            color: #3a4f63;
            font-size: 1.5rem;
        }
        .login-container input,
        .login-container button {
            width: 100%;
            padding: 0.9rem;
            margin: 0.6rem 0;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .login-container button {
            background: #0066cc;
            color: white;
            border: none;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }
        .login-container button:hover {
            background: #0055aa;
        }
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 0.7rem;
            border-radius: 6px;
            margin: 1rem 0;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
<div class="login-container">
    <img src="/includes/LogoLG.jpeg" alt="Logo SIGA" class="login-logo" onerror="this.style.display='none'">
    <h2><i class="fas fa-lock"></i> Acceso al Sistema</h2>
    <?php if (isset($_GET['error'])): ?>
        <div class="error">Usuario o contraseña incorrectos</div>
    <?php endif; ?>
    <form method="POST" action="/auth.php">
        <input type="text" name="usuario" placeholder="Nombre de usuario" required />
        <input type="password" name="password" placeholder="Contraseña" required />
        <button type="submit">Ingresar</button>
    </form>
</div>
</body>
</html>