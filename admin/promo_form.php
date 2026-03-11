<?php
require __DIR__ . '/_auth.php';
require_admin();
require __DIR__ . '/_csrf.php';
require __DIR__ . '/../db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
$p = null;

if ($id > 0) {
  $st = $pdo->prepare("SELECT p.*, a.path_original AS img
                       FROM promos p
                       JOIN assets a ON a.id = p.asset_id
                       WHERE p.id=? LIMIT 1");
  $st->execute([$id]);
  $p = $st->fetch();
  if (!$p) { http_response_code(404); exit("Promo no encontrada"); }
}

$msg = trim($_GET['msg'] ?? '');
$err = trim($_GET['err'] ?? '');

function dt_local_value(?string $dt): string {
  if (!$dt) return '';
  return str_replace(' ', 'T', substr($dt, 0, 16));
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
  <title><?= $id>0 ? 'Editar' : 'Nueva' ?> promo</title>
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
    <h2 style="margin-top:0;"><i class="fas fa-bullhorn"></i> <?= $id>0 ? 'Editar promo' : 'Nueva promo' ?></h2>
    <p style="margin-bottom:20px;"><a href="/sweetpath/admin/promos.php"><i class="fas fa-arrow-left"></i> Volver a Promos</a></p>

    <?php if ($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

    <?php if ($p): ?>
      <img class="img" src="<?= h($p['img']) ?>" alt="">
    <?php endif; ?>

    <form method="post" action="/sweetpath/admin/promo_save.php" enctype="multipart/form-data">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="<?= $id>0 ? 'update' : 'create' ?>">
      <input type="hidden" name="id" value="<?= $id ?>">

      <label><small>Título</small></label>
      <input name="title" required value="<?= h($p['title'] ?? '') ?>">

      <div class="row">
        <div class="col">
          <label><small>Inicio (opcional)</small></label>
          <input type="datetime-local" name="start_at" value="<?= h(dt_local_value($p['start_at'] ?? null)) ?>">
        </div>
        <div class="col">
          <label><small>Fin (opcional)</small></label>
          <input type="datetime-local" name="end_at" value="<?= h(dt_local_value($p['end_at'] ?? null)) ?>">
        </div>
        <div class="col">
          <label><small>Prioridad (menor = más arriba)</small></label>
          <input type="number" name="priority" min="1" step="1" required value="<?= h($p['priority'] ?? 100) ?>">
        </div>
      </div>

      <label><small>Banner (imagen) <?= $id>0 ? '— opcional si deseas reemplazar' : '' ?></small></label>
      <input type="file" name="banner" accept="image/*" <?= $id>0 ? '' : 'required' ?>>

      <button class="primary" style="margin-top:10px;" type="submit"><i class="fas fa-save"></i> <?= $id>0 ? 'Guardar cambios' : 'Crear promo' ?></button>
    </form>
  </div>
</div>
</body>
</html>