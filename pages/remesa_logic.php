<?php
require_once '../session_check.php';
require_once '../config.php';
$pdo = getDBConnection();

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
    header('Content-Type: application/json');
    echo json_encode($data ?: null);
    exit;
}

// === Eliminar remesa ===
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM remesa WHERE id_rms = ?")->execute([(int)$_GET['delete']]);
    header("Location: /pages/remesa_view.php");
    exit;
}

// === Guardar o actualizar ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = !empty($_POST['id_rms']) ? (int)$_POST['id_rms'] : null;

        // Validar campos obligatorios
        $required = ['tipo_rms', 'fecha_rms'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                echo json_encode(['success' => false, 'message' => 'Campo obligatorio faltante: ' . $field]);
                exit;
            }
        }

        // Preparar datos
        $data = [
            'tipo_rms' => $_POST['tipo_rms'],
            'fecha_rms' => $_POST['fecha_rms'],
            'mes_rms' => $_POST['mes_rms'] ?? '',
            'tipo_cambio_rms' => !empty($_POST['tipo_cambio_rms']) ? (float)$_POST['tipo_cambio_rms'] : 0.00,
            'cliente_rms' => !empty($_POST['cliente_rms']) ? (int)$_POST['cliente_rms'] : null,
            'id_clt_rms' => !empty($_POST['cliente_rms']) ? (int)$_POST['cliente_rms'] : null, // ✅ Mismo valor
            'contacto_rms' => $_POST['contacto_rms'] ?? '',
            'despacho_rms' => $_POST['despacho_rms'] ?? '',
            'ref_clte_rms' => $_POST['ref_clte_rms'] ?? '',
            'aduana_rms' => $_POST['aduana_rms'] ?? '',
            'cia_transp_rms' => !empty($_POST['cia_transp_rms']) ? (int)$_POST['cia_transp_rms'] : null,
            'mercancia_rms' => !empty($_POST['mercancia_rms']) ? (int)$_POST['mercancia_rms'] : null,
            'motonave_rms' => $_POST['motonave_rms'] ?? '',
            'estado_rms' => 'Confeccion',
        ];

        // Construir consulta
        $fields = array_keys($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';

        if ($id) {
            // Actualizar
            $set = implode(' = ?, ', $fields) . ' = ?';
            $sql = "UPDATE remesa SET $set WHERE id_rms = ?";
            $pdo->prepare($sql)->execute(array_merge(array_values($data), [$id]));
            echo json_encode(['success' => true, 'message' => 'Remesa actualizada correctamente.']);
        } else {
            // Insertar
            $sql = "INSERT INTO remesa (" . implode(', ', $fields) . ") VALUES ($placeholders)";
            $pdo->prepare($sql)->execute(array_values($data));
            echo json_encode(['success' => true, 'message' => 'Remesa creada correctamente.']);
        }
    } catch (Exception $e) {
        error_log("Error en remesa_logic: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error interno.']);
    }
    exit;
}

header("Location: /pages/remesa_view.php");
exit;
?>