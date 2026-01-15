<?php
require_once '../session_check.php';
require_once '../config.php';

// Definir $action desde POST o GET
$action = $_POST['action'] ?? $_GET['action'] ?? '';

$pdo = getDBConnection();

// Configurar cabecera JSON para respuestas
if (!empty($action) || isset($_GET['getClientes']) || isset($_GET['getContactoById']) || isset($_GET['edit'])) {
    header('Content-Type: application/json');
}

// === API: obtener contacto por ID cliente ===
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
    $stmt = $pdo->prepare("SELECT * FROM remesa WHERE id_rms = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($data) {
        // Convertir campos numéricos a float para JSON
        foreach ($data as $key => $value) {
            if (is_numeric($value) && $key !== 'cliente_rms' && $key !== 'id_rms') {
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
    header("Location: /pages/remesa_view.php");
    exit;
}

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

    try {
        $pdo->beginTransaction();

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

        // Insertar remesa
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
            $mercancia_id, // Puede ser NULL
            $_POST['mercancia_rms'], // Nombre como texto
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
        error_log("Error al crear remesa: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al crear la remesa.']);
        exit;
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

    try {
        $pdo->beginTransaction();

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
            $mercancia_id, // Puede ser NULL
            $_POST['mercancia_rms'], // Nombre como texto
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
        error_log("Error al actualizar remesa: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al actualizar la remesa.']);
        exit;
    }
}

// Redirección por defecto
header("Location: /pages/remesa_view.php");
exit;
?>