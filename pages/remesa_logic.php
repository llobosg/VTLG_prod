<?php
require_once '../session_check.php';
require_once '../config.php';

// Establecer cabecera JSON desde el inicio para todas las respuestas
header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    
    // Manejar APIs GET
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // === API: obtener clientes ===
        if (isset($_GET['getClientes'])) {
            $stmt = $pdo->query("SELECT nombre_clt FROM clientes ORDER BY nombre_clt");
            echo json_encode(array_column($stmt->fetchAll(), 'nombre_clt'));
            exit;
        }
        
        // === API: obtener contacto por ID ===
        if (isset($_GET['getContactoById'])) {
            $stmt = $pdo->prepare("SELECT contacto_clt FROM clientes WHERE id_clt = ?");
            $stmt->execute([(int)$_GET['id']]);
            $contacto = $stmt->fetchColumn();
            echo json_encode(['contacto' => $contacto ?: '']);
            exit;
        }
        
        // === Cargar remesa para editar ===
        if (isset($_GET['edit'])) {
            $id = (int)$_GET['edit'];
            if ($id <= 0) {
                echo json_encode(null);
                exit;
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    r.*,
                    COALESCE(r.mercancia_nombre, m.mercancia_mrcc) AS mercancia_display
                FROM remesa r
                LEFT JOIN mercancias m ON r.mercancia_rms = m.id_mrcc
                WHERE r.id_rms = ?
            ");
            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($data) {
                // Convertir campos numéricos a float para JSON
                foreach ($data as $key => $value) {
                    if (is_numeric($value) && !in_array($key, ['id_rms', 'cliente_rms', 'mercancia_rms', 'cia_transp_rms'])) {
                        $data[$key] = (float)$value;
                    }
                }
            }
            
            echo json_encode($data ?: null);
            exit;
        }
        
        // === Eliminar remesa ===
        if (isset($_GET['delete'])) {
            $pdo->prepare("DELETE FROM remesa WHERE id_rms = ?")->execute([(int)$_GET['delete']]);
            echo json_encode(['success' => true]);
            exit;
        }
        
        // Si es GET pero no coincide con ninguna API, redirigir
        header('Location: /pages/remesa_view.php');
        exit;
    }
    
    // Manejar acciones POST
    $action = $_POST['action'] ?? '';
    
    /* ===============================
       CREAR REMESA
    =============================== */
    if ($action === 'crear_remesa') {
        $required = ['cliente_rms', 'mercancia_rms', 'despacho_rms', 'fecha_rms', 'mes_rms'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                echo json_encode(['success' => false, 'message' => 'Campo requerido: ' . $field]);
                exit;
            }
        }

        $pdo->beginTransaction();
        try {
            // Buscar ID de mercancía si existe
            $mercancia_id = null;
            if (!empty($_POST['mercancia_rms'])) {
                $stmt = $pdo->prepare("SELECT id_mrcc FROM mercancias WHERE mercancia_mrcc = ?");
                $stmt->execute([$_POST['mercancia_rms']]);
                $result = $stmt->fetch();
                if ($result) {
                    $mercancia_id = $result['id_mrcc'];
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO remesa (
                    cliente_rms,
                    mercancia_rms,
                    mercancia_nombre,
                    despacho_rms,
                    ref_clte_rms,
                    fecha_rms,
                    mes_rms,
                    contacto_rms,
                    aduana_rms,
                    motonave_rms,
                    tramite_rms,
                    cia_transp_rms,
                    estado_rms
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'creada')
            ");
            $stmt->execute([
                $_POST['cliente_rms'],
                $mercancia_id,
                $_POST['mercancia_rms'],
                $_POST['despacho_rms'],
                $_POST['ref_clte_rms'] ?? '',
                $_POST['fecha_rms'],
                $_POST['mes_rms'],
                $_POST['contacto_rms'] ?? '',
                $_POST['aduana_rms'] ?? '',
                $_POST['motonave_rms'] ?? '',
                $_POST['tramite_rms'] ?? '',
                $_POST['cia_transp_rms'] ?? ''
            ]);

            $id_rms = $pdo->lastInsertId();
            $pdo->commit();

            echo json_encode([
                'success' => true,
                'id_rms' => $id_rms,
                'message' => 'Remesa creada correctamente.'
            ]);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /* ===============================
       ACTUALIZAR REMESA
    =============================== */
    if ($action === 'actualizar_remesa') {
        if (empty($_POST['id_rms']) || !is_numeric($_POST['id_rms'])) {
            echo json_encode(['success' => false, 'message' => 'ID de remesa inválido.']);
            exit;
        }

        $required = ['cliente_rms', 'mercancia_rms', 'despacho_rms', 'fecha_rms', 'mes_rms'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                echo json_encode(['success' => false, 'message' => 'Campo requerido: ' . $field]);
                exit;
            }
        }

        $pdo->beginTransaction();
        try {
            $mercancia_id = null;
            if (!empty($_POST['mercancia_rms'])) {
                $stmt = $pdo->prepare("SELECT id_mrcc FROM mercancias WHERE mercancia_mrcc = ?");
                $stmt->execute([$_POST['mercancia_rms']]);
                $result = $stmt->fetch();
                if ($result) {
                    $mercancia_id = $result['id_mrcc'];
                }
            }

            $stmt = $pdo->prepare("
                UPDATE remesa SET
                    cliente_rms = ?,
                    mercancia_rms = ?,
                    mercancia_nombre = ?,
                    despacho_rms = ?,
                    ref_clte_rms = ?,
                    fecha_rms = ?,
                    mes_rms = ?,
                    contacto_rms = ?,
                    aduana_rms = ?,
                    motonave_rms = ?,
                    tramite_rms = ?,
                    cia_transp_rms = ?
                WHERE id_rms = ?
            ");
            $stmt->execute([
                $_POST['cliente_rms'],
                $mercancia_id,
                $_POST['mercancia_rms'],
                $_POST['despacho_rms'],
                $_POST['ref_clte_rms'] ?? '',
                $_POST['fecha_rms'],
                $_POST['mes_rms'],
                $_POST['contacto_rms'] ?? '',
                $_POST['aduana_rms'] ?? '',
                $_POST['motonave_rms'] ?? '',
                $_POST['tramite_rms'] ?? '',
                $_POST['cia_transp_rms'] ?? '',
                $_POST['id_rms']
            ]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Remesa actualizada correctamente.']);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // Acción no reconocida
    echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
    exit;

} catch (Exception $e) {
    error_log("Error en remesa_logic: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
    exit;
}
?>