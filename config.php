<?php
// config.php - Conexión a la base de datos en Railway (texto plano en desarrollo)

function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        $host = 'nozomi.proxy.rlwy.net';
        $port = 11739;
        $dbname = 'railway';
        $user = 'root';
        $pass = 'hwUzzIcqljzFCeTIFDVcarQWaDFrMGUn';

        try {
            $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log("Error de conexión a DB: " . $e->getMessage());
            die("Error al conectar con la base de datos.");
        }
    }

    return $pdo;
}
// === MANEJADOR DE SESIONES EN BASE DE DATOS ===
class DBSessionHandler {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function open($savePath, $sessionName) {
        return true;
    }
    
    public function close() {
        return true;
    }
    
    public function read($id) {
        $stmt = $this->pdo->prepare("SELECT data FROM sessions WHERE id = ? AND timestamp > ?");
        $stmt->execute([$id, time() - 86400]); // Sesión válida por 24h
        return $stmt->fetchColumn() ?: '';
    }
    
    public function write($id, $data) {
        $stmt = $this->pdo->prepare("REPLACE INTO sessions (id, data, timestamp) VALUES (?, ?, ?)");
        return $stmt->execute([$id, $data, time()]);
    }
    
    public function destroy($id) {
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function gc($maxlifetime) {
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE timestamp < ?");
        $stmt->execute([time() - $maxlifetime]);
        return $stmt->rowCount();
    }
}
?>