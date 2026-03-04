<?php
/**
 * admin/ver_comprobante.php
 * Sirve imágenes de comprobantes de pago SOLO a admins autenticados.
 * Uso: /sweetpath/admin/ver_comprobante.php?payment_id=123
 */
require __DIR__ . '/_auth.php';
require_admin();
require __DIR__ . '/../db.php';

$payment_id = (int)($_GET['payment_id'] ?? 0);
if ($payment_id <= 0) {
    http_response_code(400);
    exit('ID de pago inválido.');
}

// Obtener la ruta del archivo desde la DB (nunca confiar en input del usuario para la ruta)
$stmt = $pdo->prepare("
    SELECT a.path_original, a.mime
    FROM payments p
    JOIN assets a ON a.id = p.proof_asset_id
    WHERE p.id = ?
    LIMIT 1
");
$stmt->execute([$payment_id]);
$row = $stmt->fetch();

if (!$row || empty($row['path_original'])) {
    http_response_code(404);
    exit('Comprobante no encontrado.');
}

// Convertir web path a filesystem path
// path_original es algo como: /sweetpath/storage/uploads/proof_SP-xxx_abc.jpg
$webPath = $row['path_original'];
// Construir ruta real en el sistema de archivos
$fsPath = realpath(__DIR__ . '/../' . ltrim(str_replace('/sweetpath/', '/', $webPath), '/'));

if (!$fsPath || !file_exists($fsPath)) {
    http_response_code(404);
    exit('Archivo no encontrado en el servidor.');
}

// Verificar que el archivo esté dentro de storage/uploads/ (prevenir path traversal)
$allowedDir = realpath(__DIR__ . '/../storage/uploads');
if (!$allowedDir || strpos($fsPath, $allowedDir) !== 0) {
    http_response_code(403);
    exit('Acceso denegado.');
}

// Servir el archivo
$mime = $row['mime'] ?: 'image/jpeg';
$allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mime, $allowedMimes, true)) {
    http_response_code(403);
    exit('Tipo de archivo no permitido.');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fsPath));
header('Cache-Control: private, max-age=3600');
// Forzar descarga o visualización inline (inline para ver en pantalla)
header('Content-Disposition: inline; filename="' . basename($fsPath) . '"');
readfile($fsPath);
exit;
