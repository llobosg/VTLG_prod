<?php
// config.php - Conexión con PDO (funciona en FrankenPHP)
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        if (php_sapi_name() === 'cli') {
            return null;
        }

        // Usar variables de entorno de Railway
        $host = $_ENV['DB_HOST'] ?? 'nozomi.proxy.rlwy.net';
        $port = $_ENV['DB_PORT'] ?? '11739';
        $dbname = $_ENV['DB_DATABASE'] ?? 'railway';
        $user = $_ENV['DB_USERNAME'] ?? 'root';
        $pass = $_ENV['DB_PASSWORD'] ?? 'hwUzzIcqljzFCeTIFDVcarQWaDFrMGUn';

        try {
            $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            error_log("Error de conexión PDO: " . $e->getMessage());
            die("Error al conectar con la base de datos.");
        }
    }

    return $pdo;
}
?>