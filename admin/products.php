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
  <title>ESENCIA · Panel de control</title>
  <style>
    :root {
        --primary: #004f39;
        --bg: #fffaca;
        --text: #151613;
        --accent: #ffd32a;
        --card-bg: #ffffff;
        --success: #10b981;
        --danger: #ef4444;
    }

    html, body {
        overflow-x: hidden;
        width: 100%;
        max-width: 100vw;
    }
    body {
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        margin: 0;
        background: var(--bg);
        color: var(--text);
        line-height: 1.5;
    }
    .admin-page-content {
        padding: 16px;
        max-width: 1200px;
        margin: 0 auto;
        padding-bottom: 40px;
    }

    h2 {
        color: var(--primary);
        font-weight: 800;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    /* === COMPACT TOOLBAR === */
    .admin-toolbar {
        display: flex; justify-content: space-between; align-items: center;
        gap: 12px; margin-bottom: 20px; flex-wrap: wrap;
    }
    .toolbar-left { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; flex-wrap: wrap; }
    .toolbar-right { display: flex; gap: 8px; flex-wrap: wrap; }
    .search-inline { display: flex; gap: 6px; flex: 1; min-width: 0; max-width: 340px; }
    .search-input {
        padding: 9px 14px; border-radius: 10px; border: 1px solid rgba(0,0,0,0.12);
        background: #fff; font-size: 14px; flex: 1; min-width: 0;
    }
    .toolbar-btn {
        padding: 9px 14px; border-radius: 10px; border: 1px solid rgba(0,0,0,0.12);
        background: #fff; font-size: 13px; font-weight: 700; cursor: pointer;
        display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;
        transition: background 0.15s; color: #151613; text-decoration: none; position: relative;
    }
    .toolbar-btn:hover { background: #f1f5f9; }
    .toolbar-btn.primary { background: #004f39; color: #fffaca; border-color: #004f39; }
    .toolbar-btn.primary:hover { background: #003d2b; }
    .filter-dot {
        position: absolute; top: -3px; right: -3px;
        width: 8px; height: 8px; background: #ef4444; border-radius: 50%;
        border: 2px solid #fffaca;
    }
    /* === COLLAPSIBLE FILTERS === */
    .adv-filters {
        max-height: 0; overflow: hidden;
        transition: max-height 0.3s ease, padding 0.3s ease;
        background: rgba(255,255,255,0.5); border-radius: 14px;
        backdrop-filter: blur(5px);
        border: 1px solid transparent;
        margin-bottom: 0;
    }
    .adv-filters.open {
        max-height: 200px;
        padding: 14px 16px;
        border-color: rgba(0,0,0,0.07);
        margin-bottom: 20px;
    }
    .adv-filters-inner {
        display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;
    }
    .adv-filters-group {
        display: flex; flex-direction: column; gap: 4px; min-width: 140px; flex: 1;
    }
    .adv-filters-group label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; }
    .adv-filters-group select {
        padding: 9px 12px; border-radius: 10px; border: 1px solid rgba(0,0,0,0.1);
        background: #fff; font-size: 14px;
    }
    .adv-filters-actions { display: flex; gap: 8px; align-items: flex-end; flex-wrap: wrap; }
    
    @media (max-width: 640px) {
        .admin-toolbar { flex-direction: column; align-items: stretch; }
        .toolbar-left, .toolbar-right { flex-direction: column; align-items: stretch; }
        .toolbar-right { display: grid; grid-template-columns: 1fr 1fr; }
        .toolbar-right a { grid-column: 1 / -1; display: flex; }
        .toolbar-right a .toolbar-btn { width: 100%; justify-content: center; }
        .search-inline { max-width: 100%; }
        .adv-filters-group { min-width: 100%; }
    }

    /* --- BOTONES --- */
    button, .btn {
        padding: 12px 20px;
        border-radius: 12px;
        border: none;
        cursor: pointer;
        font-weight: 700;
        font-size: 14px;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
    }

    button.primary { background: var(--primary); color: #fffaca; box-shadow: 0 4px 12px rgba(0, 79, 57, 0.2); }
    button.primary:hover { background: #003d2b; transform: translateY(-2px); }

    button.secondary, .btn-secondary { background: #fff; color: var(--text); border: 1px solid rgba(0,0,0,0.1); }
    button.secondary:hover { background: #f9fafb; border-color: rgba(0,0,0,0.2); }

    /* --- PRODUCT CARDS --- */
    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }

    .card {
        background: var(--card-bg);
        border-radius: 24px;
        padding: 24px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
        border: 1px solid rgba(0, 0, 0, 0.03);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        display: flex;
        flex-direction: column;
    }
    
    .card:hover { transform: translateY(-4px); box-shadow: 0 15px 40px rgba(0, 0, 0, 0.06); }

    .img-container {
        width: 100%;
        height: 180px;
        border-radius: 18px;
        overflow: hidden;
        margin-bottom: 15px;
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        position: relative;
    }

    .img-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .status-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-active { background: #dcfce7; color: #166534; }
    .status-inactive { background: #fee2e2; color: #991b1b; }

    .prod-name { font-size: 1.25rem; margin: 0; font-weight: 800; color: var(--primary); }
    .prod-desc { font-size: 0.875rem; color: #64748b; margin: 8px 0 15px 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

    .prod-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: auto;
        padding-top: 15px;
        border-top: 1px solid #f1f5f9;
    }

    .price { font-size: 1.125rem; font-weight: 800; color: var(--text); }
    .pill {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 800;
        background: #f1f5f9;
        color: #475569;
    }

    .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 20px; }

    @media (max-width: 640px) {
        .bar { grid-template-columns: 1fr; }
        .products-grid { grid-template-columns: 1fr; }
    }
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
<body>

<?php require __DIR__ . '/_navbar.php'; ?>

<div class="admin-page-content">

<?php if ($msg): ?>
  <div style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 16px; margin-bottom: 20px; font-weight: 600; border: 1px solid #bbf7d0;">
    <i class="fas fa-check-circle"></i> <?= h($msg) ?>
  </div>
<?php endif; ?>
<?php if ($err): ?>
  <div style="background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 16px; margin-bottom: 20px; font-weight: 600; border: 1px solid #fecaca;">
    <i class="fas fa-exclamation-circle"></i> <?= h($err) ?>
  </div>
<?php endif; ?>

<!-- TOOLBAR COMPACTO -->
<div class="admin-toolbar">
  <div class="toolbar-left">
    <h2 style="margin:0; font-size:1.2rem; color:#004f39; font-weight:800;"><i class="fas fa-boxes"></i> Catálogo</h2>
    <form method="get" class="search-inline">
      <input type="text" name="q" value="<?= h($q) ?>" placeholder="Buscar producto…" class="search-input">
      <?php if ($type): ?><input type="hidden" name="type" value="<?= h($type) ?>"> <?php endif; ?>
      <?php if ($active !== ''): ?><input type="hidden" name="active" value="<?= h($active) ?>"> <?php endif; ?>
      <button type="submit" class="toolbar-btn primary"><i class="fas fa-search"></i></button>
    </form>
  </div>
  <div class="toolbar-right">
    <button class="toolbar-btn" id="toggleFilters" onclick="document.getElementById('advFilters').classList.toggle('open')">
      <i class="fas fa-sliders-h"></i> Filtros
      <?php if ($type || $active !== ''): ?><span class="filter-dot"></span><?php endif; ?>
    </button>
    <a href="/admin/product_form.php" style="text-decoration:none;">
      <button class="toolbar-btn primary"><i class="fas fa-plus"></i> Nuevo Producto</button>
    </a>
  </div>
</div>

<!-- FILTROS AVANZADOS COLAPSABLES -->
<div class="adv-filters" id="advFilters">
  <form class="adv-filters-inner" method="get">
    <?php if ($q): ?><input type="hidden" name="q" value="<?= h($q) ?>"><?php endif; ?>
    <div class="adv-filters-group">
      <label>Tipo</label>
      <select name="type">
        <option value="">Todos los tipos</option>
        <option value="EXPRESS" <?= $type==='EXPRESS'?'selected':'' ?>>EXPRESS</option>
        <option value="CUSTOM" <?= $type==='CUSTOM'?'selected':'' ?>>CUSTOM</option>
        <option value="PACK" <?= $type==='PACK'?'selected':'' ?>>PACK</option>
      </select>
    </div>
    <div class="adv-filters-group">
      <label>Estado</label>
      <select name="active">
        <option value="">Todos</option>
        <option value="1" <?= $active==='1'?'selected':'' ?>>Activos</option>
        <option value="0" <?= $active==='0'?'selected':'' ?>>Inactivos</option>
      </select>
    </div>
    <div class="adv-filters-actions">
      <button class="toolbar-btn primary" type="submit"><i class="fas fa-filter"></i> Aplicar</button>
      <a href="/admin/products.php"><button type="button" class="toolbar-btn"><i class="fas fa-undo"></i> Limpiar</button></a>
    </div>
  </form>
</div>

<div class="products-grid">
<?php foreach ($rows as $p): ?>
  <div class="card">
    <div class="img-container">
      <?php if (!empty($p['img'])): ?>
        <img src="<?= h($p['img']) ?>" alt="<?= h($p['name']) ?>">
      <?php else: ?>
        <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#cbd5e1;">
          <i class="fas fa-image fa-3x"></i>
        </div>
      <?php endif; ?>
      <span class="status-badge <?= (int)$p['is_active']===1 ? 'status-active' : 'status-inactive' ?>">
        <?= (int)$p['is_active']===1 ? 'Activo' : 'Inactivo' ?>
      </span>
    </div>
    
    <h3 class="prod-name"><?= h($p['name']) ?></h3>
    <p class="prod-desc"><?= h($p['description'] ?? 'Sin descripción') ?></p>
    
    <div class="prod-meta">
      <div class="price"><?= $p['price_cents']===null ? 'Consultar' : ('Bs '.number_format(((int)$p['price_cents'])/100,2,'.','')) ?></div>
      <span class="pill"><i class="fas fa-tag" style="margin-right:4px; font-size:8px;"></i> <?= h($p['type']) ?></span>
    </div>

    <div class="actions" style="grid-template-columns: 1fr 1fr 1fr;">
      <a href="/admin/product_form.php?id=<?= (int)$p['id'] ?>" class="toolbar-btn" style="justify-content:center;">
        <i class="fas fa-edit"></i> Editar
      </a>

      <form method="post" action="/admin/product_save.php" style="display:contents">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="toggle_active">
        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <button type="submit" class="toolbar-btn" style="justify-content:center; <?= (int)$p['is_active']===1 ? 'color:#ef4444;' : 'color:#10b981;' ?>">
          <i class="fas <?= (int)$p['is_active']===1 ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
          <?= (int)$p['is_active']===1 ? 'Ocultar' : 'Activar' ?>
        </button>
      </form>

      <form method="post" action="/admin/product_save.php" style="display:contents" onsubmit="return confirm('¿Seguro que deseas eliminar este producto permanentemente (soft-delete)?');">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <button type="submit" class="toolbar-btn" style="justify-content:center; color:#ef4444; border-color:#fecaca; background:#fff5f5;">
          <i class="fas fa-trash"></i>
        </button>
      </form>
    </div>
  </div>
<?php endforeach; ?>
</div>

<?php if (count($rows)===0): ?>
  <div style="text-align:center; padding:80px; color:#64748b; background:white; border-radius:24px; border:1px solid rgba(0,0,0,0.03);">
    <i class="fas fa-search" style="font-size:3rem; opacity:0.1; display:block; margin-bottom:20px;"></i>
    No se encontraron productos con esos filtros.
  </div>
<?php endif; ?>

</div>
</body>
</html>