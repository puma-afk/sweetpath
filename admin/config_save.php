<?php
require __DIR__ . '/_auth.php';
require_admin();
require __DIR__ . '/_csrf.php';
csrf_verify_or_die();
require __DIR__ . '/../db.php';

function back_ok($msg){
  header("Location: /sweetpath/admin/config.php?msg=" . rawurlencode($msg));
  exit;
}
function back_err($msg){
  header("Location: /sweetpath/admin/config.php?err=" . rawurlencode($msg));
  exit;
}
function clean_whatsapp($n): string {
  $n = preg_replace('/[^0-9]/', '', (string)$n);
  return $n;
}

$action = $_POST['action'] ?? '';

if ($action === 'save_main') {
  $whatsapp = clean_whatsapp($_POST['whatsapp_number'] ?? '');
  $start = $_POST['business_hours_start'] ?? '';
  $end = $_POST['business_hours_end'] ?? '';
  $timezone = trim($_POST['timezone'] ?? 'America/La_Paz');

  $manual_pause = isset($_POST['manual_pause']) ? 1 : 0;
  $pause_msg = trim($_POST['manual_pause_message'] ?? '');
  $pause_until = trim($_POST['manual_pause_until'] ?? '');

  // datetime-local comes as YYYY-MM-DDTHH:MM
  $pause_until_db = null;
  if ($pause_until !== '') {
    $pause_until_db = str_replace('T', ' ', $pause_until) . ':00';
  }

  if ($whatsapp === '' || strlen($whatsapp) < 8) back_err("WhatsApp inválido");
  if ($start === '' || $end === '') back_err("Horario inválido");

  $st = $pdo->prepare("UPDATE config
    SET whatsapp_number=?,
        business_hours_start=?,
        business_hours_end=?,
        timezone=?,
        manual_pause=?,
        manual_pause_message=?,
        manual_pause_until=?,
        updated_at=NOW()
    WHERE id=1
  ");
  $st->execute([$whatsapp, $start.":00", $end.":00", $timezone, $manual_pause, ($pause_msg===''?null:$pause_msg), $pause_until_db]);

  back_ok("Configuración guardada ✅");
}

if ($action === 'upload_qr') {
  $f = $_FILES['qr_image'] ?? null;
  if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) back_err("Error subiendo QR");

  $mime = mime_content_type($f['tmp_name']);
  $allowed = ['image/jpeg','image/png','image/webp'];
  if (!in_array($mime, $allowed, true)) back_err("Formato no permitido (JPG/PNG/WebP)");

  $uploadDirFs = __DIR__ . '/../storage/qr';
  $uploadDirWeb = '/sweetpath/storage/qr';
  if (!is_dir($uploadDirFs)) mkdir($uploadDirFs, 0755, true);

  $ext = ($mime === 'image/png') ? 'png' : (($mime === 'image/webp') ? 'webp' : 'jpg');
  $filename = 'qr_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

  $destFs = $uploadDirFs . '/' . $filename;
  $destWeb = $uploadDirWeb . '/' . $filename;

  if (!move_uploaded_file($f['tmp_name'], $destFs)) back_err("No se pudo guardar el archivo");

  // Insert asset
  $a = $pdo->prepare("INSERT INTO assets (type, path_original, path_medium, path_thumb, mime, size_bytes)
                      VALUES ('QR_IMAGE', ?, NULL, NULL, ?, ?)");
  $a->execute([$destWeb, $mime, (int)$f['size']]);
  $assetId = (int)$pdo->lastInsertId();

  // Update config
  $u = $pdo->prepare("UPDATE config SET qr_asset_id=?, updated_at=NOW() WHERE id=1");
  $u->execute([$assetId]);

  back_ok("QR actualizado ✅ (asset_id=$assetId)");
}

back_err("Acción inválida");