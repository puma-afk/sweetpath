<?php
require __DIR__ . '/_auth.php';
require_admin();
require __DIR__ . '/_csrf.php';
require __DIR__ . '/../db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$stmt = $pdo->query("SELECT * FROM config WHERE id=1 LIMIT 1");
$c = $stmt->fetch();
if (!$c) { http_response_code(500); exit("Falta config id=1"); }

$msg = trim($_GET['msg'] ?? '');
$err = trim($_GET['err'] ?? '');

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
  <title>ESENCIA · Configuración</title>
  <style>
    html, body { overflow-x: hidden; width: 100%; max-width: 100vw; }
    body{font-family:'Inter',system-ui,Arial,sans-serif;margin:0;background:var(--bg,#fffaca);color:var(--text,#151613);line-height:1.5;}
    .admin-page-content { padding: 16px; max-width: 1200px; margin: 0 auto; padding-bottom: 40px; }
    .card{background:#fff;border:1px solid rgba(0,0,0,0.05);border-radius:24px;padding:30px;max-width:800px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);}
    input,select,textarea{width:100%;padding:12px 16px;border-radius:12px;border:1px solid rgba(0,0,0,0.1);margin:8px 0; font-family: inherit; font-size:14px;}
    button{padding:12px 20px;border-radius:12px;border:1px solid rgba(0,0,0,0.1);background:#fff;cursor:pointer; font-weight:700; transition: border-color 0.2s, background 0.2s; color: #151613;}
    button:hover{background:#f8fafc;}
    button.primary, button[type="submit"]{background:#004f39;color:#fffaca;border-color:#004f39; font-weight:800; display:inline-flex; align-items:center; gap:8px;}
    button.primary:hover, button[type="submit"]:hover{background:#003d2b;}
    h2, h3{color:#004f39; font-family: 'Playfair Display', serif;}
    .ok{background:#dcfce7; color:#166534; padding:15px; border-radius:16px; margin-bottom:20px; font-weight:600; border:1px solid #bbf7d0;}
    .err{background:#fee2e2; color:#991b1b; padding:15px; border-radius:16px; margin-bottom:20px; font-weight:600; border:1px solid #fecaca;}
    a{color:#004f39; font-weight: 700; text-decoration: none; display:inline-flex; align-items:center; gap:6px;}
    a:hover{text-decoration: underline;}
  </style>
</head>
<?php require __DIR__ . '/_navbar.php'; ?>

<div class="admin-page-content">
<div class="card" style="margin: 0 auto;">
  <h2 style="margin-top:0;"><i class="fas fa-cog"></i> ESENCIA — Configuración</h2>

  <?php if ($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

  <h3>📞 WhatsApp</h3>
  <form method="post" action="/sweetpath/admin/config_save.php">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="save_main">

    <label><small>Número WhatsApp (sin +, sin espacios)</small></label>
    <input name="whatsapp_number" required value="<?= h($c['whatsapp_number']) ?>" placeholder="59171234567">

    <div class="row">
      <div class="col">
        <label><small>Horario inicio</small></label>
        <input name="business_hours_start" type="time" required value="<?= h(substr($c['business_hours_start'],0,5)) ?>">
      </div>
      <div class="col">
        <label><small>Horario fin</small></label>
        <input name="business_hours_end" type="time" required value="<?= h(substr($c['business_hours_end'],0,5)) ?>">
      </div>
      <div class="col">
        <label><small>Zona horaria</small></label>
        <input name="timezone" required value="<?= h($c['timezone']) ?>" placeholder="America/La_Paz">
      </div>
    </div>

    <h3>⏸️ Pausa manual</h3>
    <label>
      <input type="checkbox" name="manual_pause" value="1" <?= ((int)$c['manual_pause']===1?'checked':'') ?>>
      Activar pausa (no aceptar pedidos)
    </label>

    <label><small>Mensaje de pausa (se muestra al cliente)</small></label>
    <textarea name="manual_pause_message" rows="2" placeholder="Hoy no atendemos. Volvemos mañana."><?= h($c['manual_pause_message'] ?? '') ?></textarea>

    <label><small>Pausa hasta (opcional)</small></label>
    <input name="manual_pause_until" type="datetime-local"
           value="<?= $c['manual_pause_until'] ? h(str_replace(' ', 'T', substr($c['manual_pause_until'],0,16))) : '' ?>">

    <button type="submit" style="margin-top:10px;"><i class="fas fa-save"></i> Guardar configuración</button>
  </form>

  <hr>

  <h3>💳 Pago por QR</h3>
  <p><small>Sube el QR de tu cuenta bancaria o billetera. El sistema lo mostrará a los clientes cuando aprueben su pedido.</small></p>

  <?php
    // Get current QR image from assets table
    $currQR = null;
    if (!empty($c['qr_asset_id'])) {
        $qa = $pdo->prepare("SELECT path_original FROM assets WHERE id=?");
        $qa->execute([$c['qr_asset_id']]);
        $currQR = $qa->fetchColumn();
    }
  ?>

  <?php if ($currQR): ?>
  <div style="margin-bottom: 15px; padding: 15px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px;">
    <p style="font-size:13px; color:#166534; margin:0 0 8px 0;"><b>✅ QR actual configurado:</b></p>
    <img src="<?= h($currQR) ?>" style="max-width:160px; border-radius:10px; border:2px solid #bbf7d0;">
  </div>
  <?php else: ?>
  <div style="margin-bottom: 15px; padding: 12px; background: #fff3e0; border: 1px solid #ffd32a; border-radius: 12px;">
    <p style="font-size:13px; color:#92400e; margin:0;">⚠️ No hay QR configurado aún. Sube tu imagen de QR para que los clientes puedan pagar.</p>
  </div>
  <?php endif; ?>

  <form method="post" action="/sweetpath/admin/config_save.php" enctype="multipart/form-data">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="upload_qr">
    <label><small>Imagen QR (JPG / PNG / WebP)</small></label>
    <input type="file" name="qr_image" accept="image/*" required>
    <button type="submit" style="margin-top:10px;"><i class="fas fa-upload"></i> Subir / Reemplazar QR</button>
  </form>

  <hr style="margin:20px 0;">

  <h3>🏦 Info de cuenta (aparece debajo del QR)</h3>
  <p><small>Escribe aquí el nombre del banco, número de cuenta, nombre del titular, etc. Se muestra junto al QR en la sección de pagos.</small></p>
  <form method="post" action="/sweetpath/admin/config_save.php">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="save_qr_info">
    <textarea name="qr_account_info" rows="4"
      placeholder="Ej:\nBanco BNB\nCuenta: 1234567890\nTitular: Esencia Repostería"><?= h($c['qr_account_info'] ?? '') ?></textarea>
    <button type="submit" style="margin-top:10px;"><i class="fas fa-save"></i> Guardar info de cuenta</button>
  </form>

</div>
</div>

</body>
</html>