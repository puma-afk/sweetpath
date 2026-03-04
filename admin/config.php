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
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>ESENCIA · Configuración</title>
  <style>
    body{font-family:system-ui,Arial;margin:16px;background:#fffaca;color:#151613}
    .card{background:#fff;border:1px solid #ddd;border-radius:14px;padding:14px;max-width:720px}
    input,select,textarea{width:100%;padding:12px;border-radius:10px;border:1px solid #ccc;margin:8px 0}
    button{padding:12px 14px;border-radius:10px;border:1px solid #004f39;background:#004f39;color:#fffaca;cursor:pointer}
    .row{display:flex;gap:12px;flex-wrap:wrap}
    .col{flex:1;min-width:220px}
    .ok{background:#e9ffe8;border:1px solid #b6ffb3;padding:10px;border-radius:10px;margin:10px 0}
    .err{background:#ffe8e8;border:1px solid #ffb3b3;padding:10px;border-radius:10px;margin:10px 0}
    a{color:#111}
    small{color:#666}
  </style>
</head>
<body>

<div class="card">
  <h2>⚙️ ESENCIA — Configuración</h2>
  <p>
    <a href="/sweetpath/admin/orders.php">← Volver a Pedidos</a>
    &nbsp;|&nbsp;
    <a href="/sweetpath/admin/logout.php">Cerrar sesión</a>
  </p>

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

    <button type="submit">Guardar configuración</button>
  </form>

  <hr>

  <h3>💳 QR (imagen fija)</h3>
  <p><small>Sube el QR general. El sistema lo usará para todos los pagos aprobados.</small></p>

  <form method="post" action="/sweetpath/admin/config_save.php" enctype="multipart/form-data">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="upload_qr">

    <input type="file" name="qr_image" accept="image/*" required>
    <button type="submit">Subir / Reemplazar QR</button>
  </form>

  <p><small>QR actual (asset_id): <b><?= h($c['qr_asset_id'] ?? '-') ?></b></small></p>
</div>

</body>
</html>