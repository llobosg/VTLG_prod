<?php
    require_once '../config.php';
    $pdo = getDBConnection();

    $id = $_GET['id'] ?? null;
    if (!$id || !is_numeric($id)) {
        echo json_encode(['contacto' => '']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT contacto_clt FROM clientes WHERE id_clt = ?");
    $stmt->execute([(int)$id]);
    $contacto = $stmt->fetchColumn();

    echo json_encode(['contacto' => $contacto ?: '']);
?>