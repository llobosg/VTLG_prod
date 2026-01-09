<?php
// utils.php - Logging seguro para Railway
function log_debug($message) {
    if (is_array($message) || is_object($message)) {
        $message = json_encode($message, JSON_PRETTY_PRINT);
    }
    error_log("[DEBUG] " . $message);
    
    // Solo en local: mostrar en pantalla
    if (!getenv('RAILWAY_ENVIRONMENT')) {
        echo "<pre style='color:#d35400;'>[DEBUG] $message</pre>";
    }
}
?>