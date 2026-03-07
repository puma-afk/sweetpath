<?php
require_once '../db.php'; // Usa tu conexión $pdo
header('Content-Type: application/json');

// Buscamos el QR configurado en la tabla config y su ruta en assets
$stmt = $pdo->query("
    SELECT a.path_original 
    FROM config c 
    JOIN assets a ON c.qr_asset_id = a.id 
    WHERE c.id = 1
");
$res = $stmt->fetch();
echo json_encode(['qr_url' => $res['path_original'] ?? '']);