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
                       FROM products p
                       LEFT JOIN assets a ON a.id = p.image_asset_id
                       WHERE p.id=? LIMIT 1");
  $st->execute([$id]);
  $p = $st->fetch();
  if (!$p) { http_response_code(404); exit("Producto no encontrado"); }
}

function bs_to_cents(?string $bs): ?int {
  if ($bs === null || trim($bs)==='') return null;
  $v = (float)str_replace(',', '.', $bs);
  return (int)round($v * 100);
}

$msg = trim($_GET['msg'] ?? '');
$err = trim($_GET['err'] ?? '');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $id>0 ? 'Editar' : 'Nuevo' ?> producto</title>
  <style>
    body{font-family:system-ui,Arial;margin:16px;background:#fafafa}
    .card{background:#fff;border:1px solid #ddd;border-radius:12px;padding:12px;max-width:720px}
    input,select,textarea{width:100%;padding:12px;border-radius:10px;border:1px solid #ccc;margin:8px 0}
    button{padding:10px 12px;border-radius:10px;border:1px solid #ccc;background:#fff;cursor:pointer}
    button.primary{background:#111;color:#fff;border-color:#111}
    .row{display:flex;gap:12px;flex-wrap:wrap}
    .col{flex:1;min-width:220px}
    .img{width:120px;height:120px;object-fit:cover;border-radius:12px;border:1px solid #ddd;background:#fff}
    .ok{background:#e9ffe8;border:1px solid #b6ffb3;padding:10px;border-radius:10px;margin:10px 0}
    .err{background:#ffe8e8;border:1px solid #ffb3b3;padding:10px;border-radius:10px;margin:10px 0}
    a{color:#111}
    small{color:#666}
  </style>
</head>
<body>
  <div class="card">
    <h2><?= $id>0 ? '✏️ Editar producto' : '➕ Nuevo producto' ?></h2>
    <p><a href="/sweetpath/admin/products.php">← Volver</a></p>

    <?php if ($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

    <?php if ($p && !empty($p['img'])): ?>
      <img class="img" src="<?= h($p['img']) ?>" alt="">
    <?php endif; ?>

    <form method="post" action="/sweetpath/admin/product_save.php" enctype="multipart/form-data">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="<?= $id>0 ? 'update' : 'create' ?>">
      <input type="hidden" name="id" value="<?= $id ?>">

      <label><small>Nombre</small></label>
      <input name="name" required value="<?= h($p['name'] ?? '') ?>">

      <label><small>Descripción</small></label>
      <textarea name="description" rows="3"><?= h($p['description'] ?? '') ?></textarea>

      <div class="row">
        <div class="col">
          <label><small>Tipo</small></label>
          <select name="type" required>
            <?php $t = $p['type'] ?? 'EXPRESS'; ?>
            <option value="EXPRESS" <?= $t==='EXPRESS'?'selected':'' ?>>EXPRESS</option>
            <option value="CUSTOM" <?= $t==='CUSTOM'?'selected':'' ?>>CUSTOM</option>
            <option value="PACK" <?= $t==='PACK'?'selected':'' ?>>PACK</option>
          </select>
        </div>

        <div class="col">
          <label><small>Disponibilidad (lo que verá el cliente)</small></label>
          <?php $av = $p['availability'] ?? 'AVAILABLE'; ?>
          <select name="availability" required>
            <option value="AVAILABLE" <?= $av==='AVAILABLE'?'selected':'' ?>>AVAILABLE</option>
            <option value="LOW" <?= $av==='LOW'?'selected':'' ?>>LOW</option>
            <option value="OUT" <?= $av==='OUT'?'selected':'' ?>>OUT</option>
          </select>
        </div>

        <div class="col">
          <label><small>Precio (Bs) — opcional</small></label>
          <input name="price_bs" type="number" step="0.01" min="0"
                 value="<?= $p && $p['price_cents']!==null ? h(number_format(((int)$p['price_cents'])/100,2,'.','')) : '' ?>">
        </div>
      </div>

      <div class="row">
        <div class="col">
          <label><small>Stock interno (solo admin, opcional)</small></label>
          <input name="stock_internal" type="number" step="1" min="0" value="<?= h($p['stock_internal'] ?? '') ?>">
        </div>
        <div class="col">
          <label><small>Máx. por pedido</small></label>
          <input name="max_per_order" type="number" step="1" min="1" required value="<?= h($p['max_per_order'] ?? 10) ?>">
        </div>
        <div class="col">
          <label><small>Horas mín. anticipación</small></label>
          <input name="min_lead_hours" type="number" step="1" min="0" required value="<?= h($p['min_lead_hours'] ?? 0) ?>">
        </div>
      </div>

      <label><small>Imagen (opcional: si subes, reemplaza la anterior)</small></label>
      <input type="file" name="image" accept="image/*">

      <button class="primary" type="submit"><?= $id>0 ? 'Guardar cambios' : 'Crear producto' ?></button>
    </form>
  </div>
</body>
</html>