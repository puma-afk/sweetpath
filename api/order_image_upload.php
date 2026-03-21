<?php
/**
 * api/order_image_upload.php
 * Permite al cliente subir UNA imagen de referencia para su pedido CUSTOM/PACK.
 * Se llama DESPUÉS de crear el pedido con orders_request.php.
 *
 * POST multipart/form-data:
 *   order_code  string   código del pedido (ej: SP-20260304-1234)
 *   ref_image   file     imagen de referencia (JPG/PNG/WebP, max ~5MB)
 */
require __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $code, array $data): void {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

$order_code = trim($_POST['order_code'] ?? '');
if ($order_code === '') {
  respond(400, ['ok' => false, 'error' => 'Falta order_code.']);
}

// Verificar que el pedido exista, sea CUSTOM/PACK y esté en estado SOLICITADO
$stmt = $pdo->prepare("SELECT id, type, status FROM orders WHERE order_code=? LIMIT 1");
$stmt->execute([$order_code]);
$order = $stmt->fetch();

if (!$order) {
  respond(404, ['ok' => false, 'error' => 'Pedido no encontrado.']);
}
if (!in_array(strtoupper($order['type']), ['CUSTOM', 'PACK'], true)) {
  respond(400, ['ok' => false, 'error' => 'Solo pedidos CUSTOM o PACK pueden tener imagen de referencia.']);
}

// Verificar que no tenga ya una imagen de referencia
$existingImg = $pdo->prepare("
  SELECT a.id FROM assets a
  WHERE a.type = 'REF_IMAGE'
    AND a.id IN (SELECT image_ref_asset_id FROM orders WHERE id = ?)
  LIMIT 1
");
// Usamos una columna image_ref_asset_id en orders (ver SQL en guía)
$checkRef = $pdo->prepare("SELECT image_ref_asset_id FROM orders WHERE id=? LIMIT 1");
$checkRef->execute([(int)$order['id']]);
$refRow = $checkRef->fetch();
if ($refRow && !empty($refRow['image_ref_asset_id'])) {
  respond(409, ['ok' => false, 'error' => 'Ya existe una imagen de referencia para este pedido.']);
}

// Validar archivo
$f = $_FILES['ref_image'] ?? null;
if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
  respond(400, ['ok' => false, 'error' => 'No se recibió la imagen.']);
}

// Tamaño máximo 5MB
if ($f['size'] > 5 * 1024 * 1024) {
  respond(400, ['ok' => false, 'error' => 'La imagen no puede superar 5MB.']);
}

$mime = mime_content_type($f['tmp_name']);
$allowed = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mime, $allowed, true)) {
  respond(400, ['ok' => false, 'error' => 'Solo se aceptan imágenes JPG, PNG o WebP.']);
}

// Guardar en storage/uploads/ (protegido, solo admin puede verlo)
$uploadDirFs  = __DIR__ . '/../storage/uploads';
$uploadDirWeb = '/storage/uploads';
if (!is_dir($uploadDirFs)) mkdir($uploadDirFs, 0755, true);

$ext      = ($mime === 'image/png') ? 'png' : (($mime === 'image/webp') ? 'webp' : 'jpg');
$filename = 'ref_' . $order_code . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destFs   = $uploadDirFs . '/' . $filename;
$destWeb  = $uploadDirWeb . '/' . $filename;

if (!move_uploaded_file($f['tmp_name'], $destFs)) {
  respond(500, ['ok' => false, 'error' => 'No se pudo guardar la imagen.']);
}

try {
  $pdo->beginTransaction();

  // Registrar en assets
  $a = $pdo->prepare("INSERT INTO assets (type, path_original, path_medium, path_thumb, mime, size_bytes)
                      VALUES ('REF_IMAGE', ?, NULL, NULL, ?, ?)");
  $a->execute([$destWeb, $mime, (int)$f['size']]);
  $asset_id = (int)$pdo->lastInsertId();

  // Asociar al pedido en la columna image_ref_asset_id
  $u = $pdo->prepare("UPDATE orders SET image_ref_asset_id=? WHERE id=?");
  $u->execute([$asset_id, (int)$order['id']]);

  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  error_log('[SweetPath] order_image_upload error: ' . $e->getMessage());
  respond(500, ['ok' => false, 'error' => 'Error guardando imagen. Intenta de nuevo.']);
}

respond(200, [
  'ok'       => true,
  'message'  => 'Imagen de referencia subida correctamente.',
  'asset_id' => $asset_id
]);
