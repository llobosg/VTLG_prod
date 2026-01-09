<?php
require_once '../config.php';
$pdo = getDBConnection();

if (!isset($_FILES['archivo']) || empty($_POST['id_rms'])) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos.']);
    exit;
}

$id_rms = (int)$_POST['id_rms'];
$archivo = $_FILES['archivo'];

$tipos_validos = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
if (!in_array($archivo['type'], $tipos_validos)) {
    echo json_encode(['success' => false, 'message' => 'Tipo no permitido. Usa PDF, JPG o PNG.']);
    exit;
}

if ($archivo['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'Máx 5MB.']);
    exit;
}

$carpeta = __DIR__ . '/../uploads';
if (!is_dir($carpeta)) mkdir($carpeta, 0755, true);

// Nombre único
$ext = pathinfo($archivo['name'], PATHINFO_EXTENSION);
$nombre = 'remesa_' . $id_rms . '_' . time() . '.' . $ext;
$ruta = $carpeta . '/' . $nombre;

if (move_uploaded_file($archivo['tmp_name'], $ruta)) {
    $pdo->prepare("INSERT INTO adjuntos (id_rms, nombre_adj, fecha_adj) VALUES (?, ?, NOW())")
        ->execute([$id_rms, $nombre]);
    echo json_encode(['success' => true, 'message' => 'Archivo subido.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al guardar el archivo.']);
}
?>