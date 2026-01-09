<?php
require_once '../session_check.php';
require_once '../config.php';

if (php_sapi_name() === 'cli') {
    http_response_code(403);
    exit('Acceso denegado.');
}

header('Content-Type: application/json');

$pdo = getDBConnection();
$action = $_POST['action'] ?? '';

try {

    /* ===============================
       CREAR RENDICIÓN
       =============================== */
    if ($action === 'crear_rendicion') {

        $required = ['id_rms', 'tipo_concepto', 'concepto_rendicion', 'monto_rendicion'];
        foreach ($required as $field) {
            if (!isset($_POST[$field]) || $_POST[$field] === '') {
                echo json_encode([
                    'success' => false,
                    'message' => 'Campo requerido: ' . $field
                ]);
                exit;
            }
        }

        $id_rms   = (int)$_POST['id_rms'];
        $tipo     = $_POST['tipo_concepto'];
        $concepto = trim($_POST['concepto_rendicion']);
        $monto    = (float)$_POST['monto_rendicion'];

        if ($tipo === 'cliente') {

            $stmt = $pdo->prepare("
                INSERT INTO rendicion (
                    id_rms,
                    concepto_rndcn,
                    monto_pago_rndcn,
                    fecha_rndcn
                ) VALUES (?, ?, ?, CURDATE())
            ");

            $stmt->execute([
                $id_rms,
                $concepto,
                $monto
            ]);

        } elseif ($tipo === 'agencia') {

            $stmt = $pdo->prepare("
                INSERT INTO rendicion (
                    id_rms,
                    concepto_agencia_rndcn,
                    monto_gastos_agencia_rndcn,
                    fecha_rndcn
                ) VALUES (?, ?, ?, CURDATE())
            ");

            $stmt->execute([
                $id_rms,
                $concepto,
                $monto
            ]);

        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Tipo de concepto inválido.'
            ]);
            exit;
        }

        echo json_encode(['success' => true]);
        exit;
    }

    /* ===============================
       ELIMINAR RENDICIÓN
       =============================== */
    if ($action === 'eliminar_rendicion') {

        if (empty($_POST['id_rndcn']) || !is_numeric($_POST['id_rndcn'])) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de rendición inválido.'
            ]);
            exit;
        }

        $stmt = $pdo->prepare("
            DELETE FROM rendicion
            WHERE id_rndcn = ?
        ");
        $stmt->execute([(int)$_POST['id_rndcn']]);

        echo json_encode(['success' => true]);
        exit;
    }

    /* ===============================
       ACCIÓN NO VÁLIDA
       =============================== */
    echo json_encode([
        'success' => false,
        'message' => 'Acción no válida.'
    ]);

} catch (Throwable $e) {
    error_log("Error en rendicion_logic.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor.'
    ]);
}
