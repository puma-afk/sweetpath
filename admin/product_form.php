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
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
  <title><?= $id>0 ? 'Editar' : 'Nuevo' ?> producto</title>
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
  <link rel="manifest" href="./manifest.json">
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('./sw.js').then(r => console.log('Admin SW registered')).catch(e => console.log('Admin SW fail', e));
      });
    }
  </script>
</head>
<?php require __DIR__ . '/_navbar.php'; ?>

<div class="admin-page-content">
  <div class="card" style="margin: 0 auto;">
    <h2 style="margin-top:0;"><i class="fas fa-edit"></i> <?= $id>0 ? 'Editar producto' : 'Nuevo producto' ?></h2>
    <p style="margin-bottom:20px;"><a href="/admin/products.php"><i class="fas fa-arrow-left"></i> Volver a Productos</a></p>

    <?php if ($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

    <?php if ($p && !empty($p['img'])): ?>
      <img class="img" src="<?= h($p['img']) ?>" alt="">
    <?php endif; ?>

    <form method="post" action="/admin/product_save.php" enctype="multipart/form-data">
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
            <option value="EXPRESS" <?= $t==='EXPRESS'?'selected':'' ?>>RÁPIDO (EXPRESS)</option>
            <option value="CUSTOM" <?= $t==='CUSTOM'?'selected':'' ?>>PERSONALIZADO (CUSTOM)</option>
            <option value="PACK" <?= $t==='PACK'?'selected':'' ?>>PACK</option>
          </select>
        </div>

        <div class="col">
          <label><small>Disponibilidad (lo que verá el cliente)</small></label>
          <?php $av = $p['availability'] ?? 'AVAILABLE'; ?>
          <select name="availability" required>
            <option value="AVAILABLE" <?= $av==='AVAILABLE'?'selected':'' ?>>DISPONIBLE</option>
            <option value="LOW" <?= $av==='LOW'?'selected':'' ?>>POCO STOCK</option>
            <option value="OUT" <?= $av==='OUT'?'selected':'' ?>>AGOTADO</option>
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

      <div style="background: rgba(0,79,57,0.05); padding: 15px; border-radius: 16px; margin-bottom: 20px; border: 1px solid rgba(0,79,57,0.1);">
        <label><small>Imágenes de Galería (opcional, subir varias)</small></label>
        <?php if (!empty($p['gallery'])): ?>
          <div style="display: flex; gap: 8px; margin: 10px 0; overflow-x: auto; padding-bottom: 5px;">
            <?php
              $galleryIds = json_decode($p['gallery'], true) ?: [];
              if ($galleryIds) {
                $placeholders = implode(',', array_fill(0, count($galleryIds), '?'));
                $stmtG = $pdo->prepare("SELECT path_original FROM assets WHERE id IN ($placeholders)");
                $stmtG->execute($galleryIds);
                foreach($stmtG->fetchAll() as $gImg) {
                  echo '<img src="'.h($gImg['path_original']).'" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px; border: 1px solid rgba(0,0,0,0.1);">';
                }
              }
            ?>
          </div>
        <?php endif; ?>
        <input type="file" name="gallery[]" multiple accept="image/*">
        <p style="font-size: 11px; opacity: 0.6; margin: 5px 0 0 0;">(Si vuelves a subir archivos, se reemplaza la galería anterior)</p>
      </div>

      <label><small>Imagen de Portada (opcional: si subes, reemplaza la principal)</small></label>
      <input type="file" name="image" accept="image/*">

      <button class="primary" style="margin-top:10px;" type="submit"><i class="fas fa-save"></i> <?= $id>0 ? 'Guardar cambios' : 'Crear producto' ?></button>
    </form>
  </div>
</div>
</body>
</html>