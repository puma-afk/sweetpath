<?php
require __DIR__ . '/_auth.php';
require_admin();
require __DIR__ . '/_csrf.php';
csrf_verify_or_die();
require __DIR__ . '/../db.php';

function back($ok, $msg, $to='/sweetpath/admin/products.php'){
  $k = $ok ? 'msg' : 'err';
  header("Location: {$to}?{$k}=" . rawurlencode($msg));
  exit;
}
function clean_int_nullable($v): ?int {
  if ($v === null) return null;
  $s = trim((string)$v);
  if ($s === '') return null;
  return (int)$s;
}
function bs_to_cents_nullable($v): ?int {
  $s = trim((string)$v);
  if ($s === '') return null;
  $f = (float)str_replace(',', '.', $s);
  return (int)round($f * 100);
}

$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);

if ($action === 'toggle_active') {
  if ($id <= 0) back(false, "ID inválido");
  $pdo->prepare("UPDATE products SET is_active = IF(is_active=1,0,1), updated_at=NOW() WHERE id=?")->execute([$id]);
  back(true, "Estado actualizado ✅");
}

if (!in_array($action, ['create','update'], true)) {
  back(false, "Acción inválida");
}

$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$type = strtoupper(trim($_POST['type'] ?? 'EXPRESS'));
$availability = strtoupper(trim($_POST['availability'] ?? 'AVAILABLE'));

$price_cents = bs_to_cents_nullable($_POST['price_bs'] ?? '');
$stock_internal = clean_int_nullable($_POST['stock_internal'] ?? '');
$max_per_order = (int)($_POST['max_per_order'] ?? 10);
$min_lead_hours = (int)($_POST['min_lead_hours'] ?? 0);

if ($name === '') back(false, "Falta nombre");
if (!in_array($type, ['EXPRESS','CUSTOM','PACK'], true)) back(false, "Tipo inválido");
if (!in_array($availability, ['AVAILABLE','LOW','OUT'], true)) back(false, "Disponibilidad inválida");
if ($max_per_order < 1) back(false, "max_per_order inválido");
if ($min_lead_hours < 0) back(false, "min_lead_hours inválido");

// Upload image (optional)
$image_asset_id = null;
$f = $_FILES['image'] ?? null;
if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
  $mime = mime_content_type($f['tmp_name']);
  $allowed = ['image/jpeg','image/png','image/webp'];
  if (!in_array($mime, $allowed, true)) back(false, "Imagen no permitida (JPG/PNG/WebP)");

  $uploadDirFs = __DIR__ . '/../storage/uploads';
  $uploadDirWeb = '/sweetpath/storage/uploads';
  if (!is_dir($uploadDirFs)) mkdir($uploadDirFs, 0777, true);

  $ext = ($mime === 'image/png') ? 'png' : (($mime === 'image/webp') ? 'webp' : 'jpg');
  $filename = 'prod_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
  $destFs = $uploadDirFs . '/' . $filename;
  $destWeb = $uploadDirWeb . '/' . $filename;

  if (!move_uploaded_file($f['tmp_name'], $destFs)) back(false, "No se pudo guardar imagen");

  $a = $pdo->prepare("INSERT INTO assets (type, path_original, path_medium, path_thumb, mime, size_bytes)
                      VALUES ('PRODUCT_IMAGE', ?, NULL, NULL, ?, ?)");
  $a->execute([$destWeb, $mime, (int)$f['size']]);
  $image_asset_id = (int)$pdo->lastInsertId();
}

if ($action === 'create') {
  $st = $pdo->prepare("INSERT INTO products
    (name, description, type, price_cents, availability, stock_internal, max_per_order, min_lead_hours, image_asset_id, is_active, created_at, updated_at)
    VALUES (?, NULLIF(?,''), ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
  ");
  $st->execute([
    $name, $description, $type, $price_cents, $availability, $stock_internal, $max_per_order, $min_lead_hours, $image_asset_id
  ]);
  back(true, "Producto creado ✅");
}

if ($action === 'update') {
  if ($id <= 0) back(false, "ID inválido");

  // if no new image, keep existing
  if ($image_asset_id === null) {
    $st = $pdo->prepare("UPDATE products SET
      name=?,
      description=NULLIF(?, ''),
      type=?,
      price_cents=?,
      availability=?,
      stock_internal=?,
      max_per_order=?,
      min_lead_hours=?,
      updated_at=NOW()
      WHERE id=?
    ");
    $st->execute([
      $name, $description, $type, $price_cents, $availability, $stock_internal, $max_per_order, $min_lead_hours, $id
    ]);
  } else {
    $st = $pdo->prepare("UPDATE products SET
      name=?,
      description=NULLIF(?, ''),
      type=?,
      price_cents=?,
      availability=?,
      stock_internal=?,
      max_per_order=?,
      min_lead_hours=?,
      image_asset_id=?,
      updated_at=NOW()
      WHERE id=?
    ");
    $st->execute([
      $name, $description, $type, $price_cents, $availability, $stock_internal, $max_per_order, $min_lead_hours, $image_asset_id, $id
    ]);
  }

  back(true, "Producto actualizado ✅");
}