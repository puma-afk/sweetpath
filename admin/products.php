<?php
require __DIR__ . '/_auth.php';
require_admin();
require __DIR__ . '/_csrf.php';
require __DIR__ . '/../db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$q = trim($_GET['q'] ?? '');
$type = strtoupper(trim($_GET['type'] ?? ''));
$active = trim($_GET['active'] ?? '');

$where = [];
$params = [];

if ($q !== '') {
  $where[] = "(p.name LIKE ? OR p.description LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
}
if (in_array($type, ['EXPRESS','CUSTOM','PACK'], true)) {
  $where[] = "p.type = ?";
  $params[] = $type;
}
if ($active === '1' || $active === '0') {
  $where[] = "p.is_active = ?";
  $params[] = (int)$active;
}

$sql = "SELECT p.*, a.path_original AS img
        FROM products p
        LEFT JOIN assets a ON a.id = p.image_asset_id";

if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY p.updated_at DESC, p.created_at DESC LIMIT 300";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$msg = trim($_GET['msg'] ?? '');
$err = trim($_GET['err'] ?? '');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin - Productos</title>
  <style>
    body{font-family:system-ui,Arial;margin:16px;background:#fafafa}
    .bar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px}
    .card{background:#fff;border:1px solid #ddd;border-radius:12px;padding:12px;margin:10px 0}
    input,select{padding:10px;border-radius:10px;border:1px solid #ccc}
    button{padding:10px 12px;border-radius:10px;border:1px solid #ccc;background:#fff;cursor:pointer}
    button.primary{background:#111;color:#fff;border-color:#111}
    button.danger{background:#b00020;color:#fff;border-color:#b00020}
    .ok{background:#e9ffe8;border:1px solid #b6ffb3;padding:10px;border-radius:10px;margin:10px 0}
    .err{background:#ffe8e8;border:1px solid #ffb3b3;padding:10px;border-radius:10px;margin:10px 0}
    .pill{display:inline-block;padding:4px 8px;border-radius:999px;background:#eee;font-size:12px}
    .row{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
    .img{width:52px;height:52px;object-fit:cover;border-radius:10px;border:1px solid #ddd;background:#fff}
    a{color:#111}
    small{color:#666}
    table{width:100%;border-collapse:collapse}
    td,th{padding:10px;border-bottom:1px solid #eee;text-align:left}
  </style>
</head>
<body>

<div class="bar" style="justify-content:space-between">
  <div>
    <h2 style="margin:0">🧁 Admin — Productos</h2>
    <small><a href="/sweetpath/admin/orders.php">← Pedidos</a> | <a href="/sweetpath/admin/config.php">Config</a></small>
  </div>
  <div class="row">
    <a href="/sweetpath/admin/product_form.php"><button class="primary" type="button">+ Nuevo producto</button></a>
    <a href="/sweetpath/admin/logout.php"><button class="danger" type="button">Cerrar sesión</button></a>
  </div>
</div>

<?php if ($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

<form class="bar" method="get">
  <input name="q" placeholder="Buscar..." value="<?= h($q) ?>">
  <select name="type">
    <option value="">Tipo (todos)</option>
    <option value="EXPRESS" <?= $type==='EXPRESS'?'selected':'' ?>>EXPRESS</option>
    <option value="CUSTOM" <?= $type==='CUSTOM'?'selected':'' ?>>CUSTOM</option>
    <option value="PACK" <?= $type==='PACK'?'selected':'' ?>>PACK</option>
  </select>
  <select name="active">
    <option value="">Activo (todos)</option>
    <option value="1" <?= $active==='1'?'selected':'' ?>>Activos</option>
    <option value="0" <?= $active==='0'?'selected':'' ?>>Inactivos</option>
  </select>
  <button class="primary" type="submit">Filtrar</button>
  <a href="/sweetpath/admin/products.php"><button type="button">Limpiar</button></a>
</form>

<div class="card">
  <table>
    <thead>
      <tr>
        <th>Imagen</th>
        <th>Producto</th>
        <th>Tipo</th>
        <th>Precio</th>
        <th>Disponibilidad</th>
        <th>Activo</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $p): ?>
      <tr>
        <td>
          <?php if (!empty($p['img'])): ?>
            <img class="img" src="<?= h($p['img']) ?>" alt="">
          <?php else: ?>
            <div class="img"></div>
          <?php endif; ?>
        </td>
        <td>
          <b><?= h($p['name']) ?></b><br>
          <small><?= h(mb_strimwidth((string)($p['description'] ?? ''), 0, 60, '…')) ?></small>
        </td>
        <td><span class="pill"><?= h($p['type']) ?></span></td>
        <td><?= $p['price_cents']===null ? '-' : ('Bs '.number_format(((int)$p['price_cents'])/100,2,'.','')) ?></td>
        <td><?= h($p['availability']) ?></td>
        <td><?= (int)$p['is_active']===1 ? '✅' : '❌' ?></td>
        <td class="row">
          <a href="/sweetpath/admin/product_form.php?id=<?= (int)$p['id'] ?>"><button type="button">Editar</button></a>

          <form method="post" action="/sweetpath/admin/product_save.php" style="display:inline">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="toggle_active">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button type="submit"><?= (int)$p['is_active']===1 ? 'Desactivar' : 'Activar' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php if (count($rows)===0): ?>
    <p>No hay productos con esos filtros.</p>
  <?php endif; ?>
</div>

</body>
</html>