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
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $id>0 ? 'Editar' : 'Nueva' ?> promo</title>
  <style>
    body{font-family:system-ui,Arial;margin:16px;background:#fafafa}
    .card{background:#fff;border:1px solid #ddd;border-radius:12px;padding:12px;max-width:720px}
    input,textarea{width:100%;padding:12px;border-radius:10px;border:1px solid #ccc;margin:8px 0}
    button{padding:10px 12px;border-radius:10px;border:1px solid #ccc;background:#fff;cursor:pointer}
    button.primary{background:#111;color:#fff;border-color:#111}
    .row{display:flex;gap:12px;flex-wrap:wrap}
    .col{flex:1;min-width:220px}
    .img{width:100%;max-width:520px;height:180px;object-fit:cover;border-radius:12px;border:1px solid #ddd;background:#fff}
    .ok{background:#e9ffe8;border:1px solid #b6ffb3;padding:10px;border-radius:10px;margin:10px 0}
    .err{background:#ffe8e8;border:1px solid #ffb3b3;padding:10px;border-radius:10px;margin:10px 0}
    a{color:#111}
    small{color:#666}
  </style>
</head>
<body>
  <div class="card">
    <h2><?= $id>0 ? '✏️ Editar promo' : '➕ Nueva promo' ?></h2>
    <p><a href="/sweetpath/admin/promos.php">← Volver</a></p>

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

      <button class="primary" type="submit"><?= $id>0 ? 'Guardar cambios' : 'Crear promo' ?></button>
    </form>
  </div>
</body>
</html>