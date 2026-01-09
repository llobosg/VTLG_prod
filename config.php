<?php
// config.php - Conexión PDO compatible con FrankenPHP
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        if (php_sapi_name() === 'cli') {
            return null;
        }

        $host = $_ENV['DB_HOST'] ?? 'nozomi.proxy.rlwy.net';
        $port = $_ENV['DB_PORT'] ?? '11739';
        $dbname = $_ENV['DB_DATABASE'] ?? 'railway';
        $user = $_ENV['DB_USERNAME'] ?? 'root';
        $pass = $_ENV['DB_PASSWORD'] ?? 'hwUzzIcqljzFCeTIFDVcarQWaDFrMGUn';

        // ✅ DSN correcto: incluye charset=utf8mb4 en la URL
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // ✅ No usar emulación de prepares
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log("Error de conexión PDO: " . $e->getMessage());
            // Mostrar mensaje genérico al usuario
            die("Error al conectar con la base de datos.");
        }
    }

    return $pdo;
}
?>