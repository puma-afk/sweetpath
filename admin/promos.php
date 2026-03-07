<?php
require __DIR__ . '/_auth.php';
require_admin();
require __DIR__ . '/_csrf.php';
require __DIR__ . '/../db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$active = trim($_GET['active'] ?? '');
$where = [];
$params = [];

if ($active === '1' || $active === '0') {
  $where[] = "p.is_active = ?";
  $params[] = (int)$active;
}

$sql = "SELECT p.*, a.path_original AS img
        FROM promos p
        JOIN assets a ON a.id = p.asset_id";

if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY p.is_active DESC, p.priority ASC, p.created_at DESC LIMIT 200";

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
  <title>ESENCIA · Promos</title>
  <style>
    body{font-family:system-ui,Arial,sans-serif;margin:16px;background:#fffaca;color:#151613}
    .bar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px}
    .card{background:#fff;border:1px solid #ddd;border-radius:12px;padding:12px;margin:10px 0}
    input,select{padding:10px;border-radius:10px;border:1px solid #ccc}
    button{padding:10px 16px;border-radius:12px;border:1px solid #ccc;background:#fff;cursor:pointer; font-weight:600; transition: 0.2s; color: #151613;}
    button:hover{filter: brightness(0.92); transform: translateY(-1px);}
    button.primary{background:#004f39;color:#fffaca;border-color:#004f39; box-shadow: 0 4px 10px rgba(0,79,57,0.2);}
    .ok{background:#e9ffe8;border:1px solid #b6ffb3;padding:10px;border-radius:10px;margin:10px 0}
    .err{background:#ffe8e8;border:1px solid #ffb3b3;padding:10px;border-radius:10px;margin:10px 0}
    .img{width:140px;height:60px;object-fit:cover;border-radius:12px;border:1px solid #ddd;background:#fff}
    small{color:#666}
    a{color:#111}
    table{width:100%;border-collapse:collapse}
    td,th{padding:10px;border-bottom:1px solid #eee;text-align:left;vertical-align:top}
  </style>
</head>
<body>

<?php require __DIR__ . '/_navbar.php'; ?>

<?php if ($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

<form class="bar" method="get">
  <select name="active">
    <option value="">Estado (todas)</option>
    <option value="1" <?= $active==='1'?'selected':'' ?>>Activas</option>
    <option value="0" <?= $active==='0'?'selected':'' ?>>Inactivas</option>
  </select>
  <button class="primary" type="submit">Filtrar</button>
  <a href="/sweetpath/admin/promos.php"><button type="button">Limpiar</button></a>
</form>

<div class="card">
  <table>
    <thead>
      <tr>
        <th>Banner</th>
        <th>Promo</th>
        <th>Fechas</th>
        <th>Prioridad</th>
        <th>Activa</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $p): ?>
      <tr>
        <td><img class="img" src="<?= h($p['img']) ?>" alt=""></td>
        <td>
          <b><?= h($p['title']) ?></b><br>
          <small>id: <?= (int)$p['id'] ?> | asset: <?= (int)$p['asset_id'] ?></small>
        </td>
        <td>
          <small>
            Inicio: <?= h($p['start_at'] ?? '-') ?><br>
            Fin: <?= h($p['end_at'] ?? '-') ?>
          </small>
        </td>
        <td><?= (int)$p['priority'] ?></td>
        <td><?= (int)$p['is_active']===1 ? '✅' : '❌' ?></td>
        <td class="bar">
          <a href="/sweetpath/admin/promo_form.php?id=<?= (int)$p['id'] ?>"><button type="button">Editar</button></a>

          <form method="post" action="/sweetpath/admin/promo_save.php" style="display:inline">
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
    <p>No hay promos aún.</p>
  <?php endif; ?>
</div>

</body>
</html>