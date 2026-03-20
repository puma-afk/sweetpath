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
    .card { background: var(--card-bg); border-radius: 20px; padding: 22px; margin-bottom: 18px; box-shadow: 0 4px 16px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.05); }
    
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
    .adv-filters-inner { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
    .adv-filters-group { display: flex; flex-direction: column; gap: 4px; min-width: 140px; flex: 1; }
    .adv-filters-group label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; }
    .adv-filters-group select { padding: 9px 12px; border-radius: 10px; border: 1px solid rgba(0,0,0,0.1); background: #fff; font-size: 14px; }
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
    .promos-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }

    .promo-card {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 20px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.05);
        border: 1px solid rgba(0,0,0,0.05);
        display: flex;
        flex-direction: column;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .promo-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    }

    .promo-img {
        width: 100%;
        height: 140px;
        object-fit: cover;
        border-radius: 14px;
        margin-bottom: 15px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }

    .promo-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 10px;
    }

    .status-badge {
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .status-active { background: #dcfce7; color: #166534; }
    .status-inactive { background: #fee2e2; color: #991b1b; }

    .promo-title {
        font-size: 1.1rem;
        font-weight: 800;
        color: var(--primary);
        margin: 0 0 5px 0;
    }

    .promo-info {
        font-size: 13px;
        color: #64748b;
        margin-bottom: 15px;
        flex: 1;
    }

    .promo-actions {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 8px;
        border-top: 1px solid #f1f5f9;
        padding-top: 15px;
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
    <h2 style="margin:0; font-size:1.2rem; color:#004f39; font-weight:800;"><i class="fas fa-bullhorn"></i> Promos</h2>
  </div>
  <div class="toolbar-right">
    <button class="toolbar-btn" id="toggleFilters" onclick="document.getElementById('advFilters').classList.toggle('open')">
      <i class="fas fa-sliders-h"></i> Filtros
      <?php if ($active !== ''): ?><span class="filter-dot"></span><?php endif; ?>
    </button>
    <a href="/sweetpath/admin/promo_form.php" style="text-decoration:none;">
      <button class="toolbar-btn primary"><i class="fas fa-plus"></i> Nueva Promo</button>
    </a>
  </div>
</div>

<!-- FILTROS AVANZADOS COLAPSABLES -->
<div class="adv-filters" id="advFilters">
  <form class="adv-filters-inner" method="get">
    <div class="adv-filters-group">
      <label>Estado</label>
      <select name="active">
        <option value="">Todas</option>
        <option value="1" <?= $active==='1'?'selected':'' ?>>Activas</option>
        <option value="0" <?= $active==='0'?'selected':'' ?>>Inactivas</option>
      </select>
    </div>
    <div class="adv-filters-actions">
      <button class="toolbar-btn primary" type="submit"><i class="fas fa-filter"></i> Aplicar</button>
      <a href="/sweetpath/admin/promos.php"><button type="button" class="toolbar-btn"><i class="fas fa-undo"></i> Limpiar</button></a>
    </div>
  </form>
</div>

<div class="promos-grid">
  <?php foreach ($rows as $p): ?>
    <div class="promo-card">
      <img class="promo-img" src="<?= h($p['img']) ?>" alt="">
      
      <div class="promo-header">
        <h3 class="promo-title"><?= h($p['title']) ?></h3>
        <span class="status-badge <?= (int)$p['is_active']===1 ? 'status-active' : 'status-inactive' ?>">
          <?= (int)$p['is_active']===1 ? 'Activa' : 'Inactiva' ?>
        </span>
      </div>
      
      <div class="promo-info">
        <div><i class="far fa-calendar-alt"></i> <b>Inicio:</b> <?= h($p['start_at'] ?? 'Sin fecha') ?></div>
        <div><i class="far fa-calendar-check"></i> <b>Fin:</b> <?= h($p['end_at'] ?? 'Sin fecha') ?></div>
        <div style="margin-top: 8px;">
          <span style="background:#f1f5f9; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:bold; color:#475569;">
            ID: <?= (int)$p['id'] ?>
          </span>
          <span style="background:#f1f5f9; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:bold; color:#475569;">
            Prioridad: <?= (int)$p['priority'] ?>
          </span>
        </div>
      </div>

      <div class="promo-actions">
        <a href="/sweetpath/admin/promo_form.php?id=<?= (int)$p['id'] ?>" class="toolbar-btn" style="justify-content:center;">
          <i class="fas fa-edit"></i> Editar
        </a>

        <form method="post" action="/sweetpath/admin/promo_save.php" style="display:contents">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="toggle_active">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button type="submit" class="toolbar-btn" style="justify-content:center; <?= (int)$p['is_active']===1 ? 'color:#ef4444;' : 'color:#10b981;' ?>">
            <i class="fas <?= (int)$p['is_active']===1 ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
            <?= (int)$p['is_active']===1 ? 'Desactiv.' : 'Activar' ?>
          </button>
        </form>

        <form method="post" action="/sweetpath/admin/promo_save.php" style="display:contents" onsubmit="return confirm('¿Seguro que deseas eliminar esta promo permanentemente?');">
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
    <i class="fas fa-bullhorn" style="font-size:3rem; opacity:0.1; display:block; margin-bottom:20px;"></i>
    No hay promos aún.
  </div>
<?php endif; ?>

</div>
</body>
</html>