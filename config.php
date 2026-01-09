<?php
// config.php - Conexión segura con MySQLi
function getDBConnection() {
    static $conn = null;
    
    if ($conn === null) {
        // No conectar en CLI (Railway build)
        if (php_sapi_name() === 'cli') {
            return null;
        }

        $host = $_ENV['DB_HOST'] ?? 'nozomi.proxy.rlwy.net';
        $port = (int)($_ENV['DB_PORT'] ?? '11739');
        $dbname = $_ENV['DB_DATABASE'] ?? 'railway';
        $user = $_ENV['DB_USERNAME'] ?? 'root';
        $pass = $_ENV['DB_PASSWORD'] ?? 'hwUzzIcqljzFCeTIFDVcarQWaDFrMGUn';

        $conn = mysqli_connect($host, $user, $pass, $dbname, $port);
        
        if (!$conn) {
            error_log("MySQLi Connect Error: " . mysqli_connect_error());
            return null; // Devuelve null, no muere
        }
        
        mysqli_set_charset($conn, 'utf8mb4');
    }

    return $conn;
}
?>