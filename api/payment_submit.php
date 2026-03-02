<?php
require __DIR__ . '/../db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bs_to_cents($bs): int {
  $s = trim((string)$bs);
  $s = str_replace(',', '.', $s);
  $s = preg_replace('/[^0-9.]/', '', $s);
  if ($s === '' || $s === '.') return 0;
  $parts = explode('.', $s, 2);
  $whole = (int)($parts[0] ?: 0);
  $frac = $parts[1] ?? '0';
  $frac = substr(str_pad($frac, 2, '0'), 0, 2);
  return ($whole * 100) + (int)$frac;
}

$order_code = trim($_POST['order_code'] ?? '');
$amount_bs = $_POST['amount_bs'] ?? '';

if ($order_code === '') { http_response_code(400); exit("Falta order_code"); }
$amount_cents = bs_to_cents($amount_bs);
if ($amount_cents <= 0) { http_response_code(400); exit("Monto inválido"); }

$proof = $_FILES['proof'] ?? null;
if (!$proof || ($proof['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
  http_response_code(400); exit("Error subiendo comprobante");
}

$stmt = $pdo->prepare("SELECT id, status, total_final_cents FROM orders WHERE order_code=? LIMIT 1");
$stmt->execute([$order_code]);
$o = $stmt->fetch();
if (!$o) { http_response_code(404); exit("Pedido no encontrado"); }

$status = strtoupper($o['status'] ?? '');
if ($status !== 'APROBADO_PARA_PAGO') {
  http_response_code(403); exit("Este pedido no está habilitado para pago");
}

$allowed = ['image/jpeg','image/png','image/webp'];
$mime = mime_content_type($proof['tmp_name']);
if (!in_array($mime, $allowed, true)) {
  http_response_code(400); exit("Tipo de archivo no permitido (solo JPG/PNG/WebP)");
}

$uploadDirFs = __DIR__ . '/../storage/uploads';
$uploadDirWeb = '/sweetpath/storage/uploads';
if (!is_dir($uploadDirFs)) {
  mkdir($uploadDirFs, 0777, true);
}

$ext = ($mime === 'image/png') ? 'png' : (($mime === 'image/webp') ? 'webp' : 'jpg');
$filename = 'proof_' . $order_code . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

$destFs = $uploadDirFs . '/' . $filename;
$destWeb = $uploadDirWeb . '/' . $filename;

if (!move_uploaded_file($proof['tmp_name'], $destFs)) {
  http_response_code(500); exit("No se pudo guardar el archivo");
}

try {
  $pdo->beginTransaction();

  // Save asset
  $a = $pdo->prepare("INSERT INTO assets (type, path_original, path_medium, path_thumb, mime, size_bytes)
                      VALUES ('PROOF_IMAGE', ?, NULL, NULL, ?, ?)");
  $a->execute([$destWeb, $mime, (int)$proof['size']]);
  $asset_id = (int)$pdo->lastInsertId();

  // Insert payment (unverified until owner checks)
  $p = $pdo->prepare("INSERT INTO payments (order_id, method, amount_cents, proof_asset_id, verified)
                      VALUES (?, 'QR', ?, ?, 0)");
  $p->execute([(int)$o['id'], $amount_cents, $asset_id]);

  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  exit("Error registrando pago: " . $e->getMessage());
}

header("Location: /sweetpath/pay_thanks.php?code=" . rawurlencode($order_code));
exit;