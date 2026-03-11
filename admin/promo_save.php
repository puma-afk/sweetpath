<?php
require __DIR__ . '/_auth.php';
require_admin();
require __DIR__ . '/_csrf.php';
csrf_verify_or_die();
require __DIR__ . '/../db.php';

function back($ok, $msg){
  $k = $ok ? 'msg' : 'err';
  header("Location: /sweetpath/admin/promos.php?{$k}=" . rawurlencode($msg));
  exit;
}

function upload_banner(PDO $pdo, array $f): int {
  if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    throw new RuntimeException("Error subiendo banner");
  }

  $mime = mime_content_type($f['tmp_name']);
  $allowed = ['image/jpeg','image/png','image/webp'];
  if (!in_array($mime, $allowed, true)) {
    throw new RuntimeException("Formato no permitido (JPG/PNG/WebP)");
  }

  $uploadDirFs = __DIR__ . '/../storage/uploads';
  $uploadDirWeb = '/sweetpath/storage/uploads';
  if (!is_dir($uploadDirFs)) mkdir($uploadDirFs, 0777, true);

  $ext = ($mime === 'image/png') ? 'png' : (($mime === 'image/webp') ? 'webp' : 'jpg');
  $filename = 'promo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

  $destFs = $uploadDirFs . '/' . $filename;
  $destWeb = $uploadDirWeb . '/' . $filename;

  if (!move_uploaded_file($f['tmp_name'], $destFs)) {
    throw new RuntimeException("No se pudo guardar banner");
  }

  $st = $pdo->prepare("INSERT INTO assets (type, path_original, path_medium, path_thumb, mime, size_bytes)
                       VALUES ('PROMO_BANNER', ?, NULL, NULL, ?, ?)");
  $st->execute([$destWeb, $mime, (int)$f['size']]);

  return (int)$pdo->lastInsertId();
}

function dt_local_to_db(?string $v): ?string {
  $v = trim((string)$v);
  if ($v === '') return null;
  // YYYY-MM-DDTHH:MM -> YYYY-MM-DD HH:MM:00
  return str_replace('T',' ',$v) . ':00';
}

$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);

if ($action === 'toggle_active') {
  if ($id <= 0) back(false, "ID inválido");
  $pdo->prepare("UPDATE promos SET is_active = IF(is_active=1,0,1) WHERE id=?")->execute([$id]);
  back(true, "Estado actualizado ✅");
}

if ($action === 'delete') {
  if ($id <= 0) back(false, "ID inválido");
  $pdo->prepare("DELETE FROM promos WHERE id=?")->execute([$id]);
  back(true, "Promo eliminada ✅");
}

if (!in_array($action, ['create','update'], true)) back(false, "Acción inválida");

$title = trim($_POST['title'] ?? '');
$start_at = dt_local_to_db($_POST['start_at'] ?? '');
$end_at = dt_local_to_db($_POST['end_at'] ?? '');
$priority = (int)($_POST['priority'] ?? 100);

if ($title === '') back(false, "Falta título");
if ($priority < 1) back(false, "Prioridad inválida");

// banner upload (required on create, optional on update)
$bannerFile = $_FILES['banner'] ?? null;
$asset_id = null;

try {
  if ($action === 'create') {
    if (!$bannerFile) back(false, "Falta banner");
    $asset_id = upload_banner($pdo, $bannerFile);

    $st = $pdo->prepare("INSERT INTO promos (title, asset_id, start_at, end_at, is_active, priority, created_at)
                         VALUES (?, ?, ?, ?, 1, ?, NOW())");
    $st->execute([$title, $asset_id, $start_at, $end_at, $priority]);

    back(true, "Promo creada ✅");
  }

  // update
  if ($id <= 0) back(false, "ID inválido");

  if ($bannerFile && ($bannerFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $asset_id = upload_banner($pdo, $bannerFile);
    $st = $pdo->prepare("UPDATE promos SET title=?, asset_id=?, start_at=?, end_at=?, priority=? WHERE id=?");
    $st->execute([$title, $asset_id, $start_at, $end_at, $priority, $id]);
  } else {
    $st = $pdo->prepare("UPDATE promos SET title=?, start_at=?, end_at=?, priority=? WHERE id=?");
    $st->execute([$title, $start_at, $end_at, $priority, $id]);
  }

  back(true, "Promo actualizada ✅");
} catch (Throwable $e) {
  back(false, $e->getMessage());
}