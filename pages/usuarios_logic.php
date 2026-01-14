<?php
require_once '../session_check.php';
require_once '../config.php';

if (php_sapi_name() === 'cli') {
    http_response_code(403);
    exit('Acceso denegado.');
}

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    
    // Leer acción desde POST o GET
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    /* ===============================
       CREAR USUARIO
    =============================== */
    if ($action === 'crear_usuario') {
        $required = ['nombre_usuario', 'password', 'rol'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                echo json_encode(['success' => false, 'message' => 'Campo requerido: ' . $field]);
                exit;
            }
        }

        // Verificar si ya existe un usuario con ese nombre
        $stmt = $pdo->prepare("SELECT id_usr FROM usuarios WHERE nombre_usr = ?");
        $stmt->execute([$_POST['nombre_usuario']]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Ya existe un usuario con ese nombre.']);
            exit;
        }

        $hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (nombre_usr, rol_usr, password_usr, activo)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['nombre_usuario'],
            $_POST['rol'],
            $hashed,
            $_POST['activo'] ?? '1'
        ]);
        echo json_encode(['success' => true, 'message' => 'Usuario creado correctamente.']);
        exit;
    }

    /* ===============================
       OBTENER USUARIO (para edición)
    =============================== */
    if ($action === 'obtener_usuario') {
        if (empty($_GET['id']) || !is_numeric($_GET['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT 
                id_usr AS id_usuario,
                nombre_usr AS nombre_usuario,
                rol_usr AS rol,
                activo
            FROM usuarios 
            WHERE id_usr = ?
        ");
        $stmt->execute([(int)$_GET['id']]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {
            echo json_encode($usuario);
        } else {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado.']);
        }
        exit;
    }

    /* ===============================
       ACTUALIZAR USUARIO
    =============================== */
    if ($action === 'actualizar_usuario') {
        if (empty($_POST['id_usuario']) || !is_numeric($_POST['id_usuario'])) {
            echo json_encode(['success' => false, 'message' => 'ID de usuario requerido.']);
            exit;
        }

        $id_usuario = (int)$_POST['id_usuario'];

        // Campos que se pueden actualizar
        $updates = [];
        $params = [];

        if (!empty($_POST['nombre_usuario'])) {
            // Verificar unicidad del nombre
            $stmt = $pdo->prepare("SELECT id_usr FROM usuarios WHERE nombre_usr = ? AND id_usr != ?");
            $stmt->execute([$_POST['nombre_usuario'], $id_usuario]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Ya existe otro usuario con ese nombre.']);
                exit;
            }
            $updates[] = "nombre_usr = ?";
            $params[] = $_POST['nombre_usuario'];
        }

        if (isset($_POST['rol'])) {
            $updates[] = "rol_usr = ?";
            $params[] = $_POST['rol'];
        }

        if (isset($_POST['activo'])) {
            $updates[] = "activo = ?";
            $params[] = $_POST['activo'];
        }

        // Actualizar contraseña si se proporciona
        if (!empty($_POST['password'])) {
            $updates[] = "password_usr = ?";
            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        if (empty($updates)) {
            echo json_encode(['success' => false, 'message' => 'No hay datos para actualizar.']);
            exit;
        }

        $params[] = $id_usuario;
        $sql = "UPDATE usuarios SET " . implode(', ', $updates) . " WHERE id_usr = ?";
        $pdo->prepare($sql)->execute($params);

        echo json_encode(['success' => true, 'message' => 'Usuario actualizado correctamente.']);
        exit;
    }

    /* ===============================
       ELIMINAR USUARIO
    =============================== */
    if ($action === 'eliminar_usuario') {
        if (empty($_POST['id_usuario']) || !is_numeric($_POST['id_usuario'])) {
            echo json_encode(['success' => false, 'message' => 'ID de usuario requerido.']);
            exit;
        }

        // No permitir eliminar al usuario actual
        if ((int)$_POST['id_usuario'] === ($_SESSION['user_id'] ?? 0)) {
            echo json_encode(['success' => false, 'message' => 'No puedes eliminarte a ti mismo.']);
            exit;
        }

        $pdo->prepare("DELETE FROM usuarios WHERE id_usr = ?")->execute([(int)$_POST['id_usuario']]);
        echo json_encode(['success' => true, 'message' => 'Usuario eliminado correctamente.']);
        exit;
    }

    /* ===============================
       ACCIÓN NO VÁLIDA
    =============================== */
    echo json_encode(['success' => false, 'message' => 'Acción no válida.']);

} catch (Exception $e) {
    error_log("Error en usuarios_logic: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}
?>