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
    body{font-family:system-ui,Arial,sans-serif;margin:16px;background:#fffaca;color:#151613}
    .card{background:#fff;border:1px solid #ddd;border-radius:18px;padding:25px;max-width:800px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);}
    input,select,textarea{width:100%;padding:12px;border-radius:12px;border:1px solid #ccc;margin:8px 0; font-family: inherit;}
    button{padding:12px 20px;border-radius:12px;border:1px solid #ccc;background:#fff;cursor:pointer; font-weight:600; transition: 0.2s; color: #151613;}
    button:hover{filter: brightness(0.92); transform: translateY(-1px);}
    button[type="submit"]{background:#004f39;color:#fffaca;border-color:#004f39; box-shadow: 0 4px 10px rgba(0,79,57,0.2);}
    h2, h3{color:#004f39; font-family: 'Playfair Display', serif;}
    .ok{background:#e9ffe8;border:1px solid #b6ffb3;padding:12px;border-radius:12px;margin:10px 0}
    .err{background:#ffe8e8;border:1px solid #ffb3b3;padding:12px;border-radius:12px;margin:10px 0}
    a{color:#004f39; font-weight: 600;}
  </style>
</head>
<?php require __DIR__ . '/_navbar.php'; ?>

<div class="card" style="margin: 0 auto;">
  <h2>⚙️ ESENCIA — Configuración</h2>

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