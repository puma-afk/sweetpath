<?php
require __DIR__ . '/_auth.php';
require_admin();
require __DIR__ . '/_csrf.php';
require __DIR__ . '/../db.php';

$type = strtoupper(trim($_GET['type'] ?? ''));
$status = strtoupper(trim($_GET['status'] ?? ''));
$q = trim($_GET['q'] ?? '');

$where = [];
$params = [];

if ($q !== '') {
  $where[] = "(o.customer_name LIKE ? OR o.customer_phone LIKE ? OR o.order_code LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
}

if (in_array($type, ['EXPRESS','CUSTOM','PACK'], true)) {
  $where[] = "o.type = ?";
  $params[] = $type;
}
if ($status === 'ALL') {
  // Sin filtro adicional de estado
} elseif ($status !== '') {
  $where[] = "UPPER(o.status) = ?";
  $params[] = $status;
} elseif ($q === '') {
  // Por defecto, si no hay búsqueda, ocultar los completados y rechazados
  $where[] = "UPPER(o.status) NOT IN ('ENTREGADO', 'RECHAZADO')";
}

$sql = "
SELECT
  o.id, o.order_code, o.type, o.channel, o.status, o.custom_json,
  o.customer_name, o.customer_phone,
  o.pickup_date, o.pickup_time,
  o.total_final_cents,
  o.created_at,
  a.path_original AS ref_image_path,

  COALESCE(SUM(CASE WHEN p.verified=1 THEN p.amount_cents ELSE 0 END),0) AS paid_verified_cents,
  COALESCE(SUM(CASE WHEN p.verified=0 THEN p.amount_cents ELSE 0 END),0) AS paid_pending_cents

FROM orders o
LEFT JOIN payments p ON p.order_id = o.id
LEFT JOIN assets a ON o.image_ref_asset_id = a.id
";

if ($where) $sql .= " WHERE " . implode(" AND ", $where);

$sql .= "
GROUP BY o.id
ORDER BY o.created_at DESC
LIMIT 200
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// --- NUEVA LÓGICA: Cargar items de los pedidos en una sola consulta ---
$orderItemsMap = [];
if (count($orders) > 0) {
    $ids = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmtItems = $pdo->prepare("
        SELECT oi.*, p.name AS product_name 
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id IN ($placeholders)
    ");
    $stmtItems->execute($ids);
    $allOrderItems = $stmtItems->fetchAll();
    
    foreach ($allOrderItems as $item) {
        $orderItemsMap[$item['order_id']][] = $item;
    }

    // --- NUEVA LÓGICA: Cargar pagos y comprobantes asociados ---
    $orderPaymentsMap = [];
    $stmtPayments = $pdo->prepare("
        SELECT p.*, a.path_original AS proof_path
        FROM payments p
        LEFT JOIN assets a ON p.proof_asset_id = a.id
        WHERE p.order_id IN ($placeholders)
        ORDER BY p.created_at DESC
    ");
    $stmtPayments->execute($ids);
    $allPayments = $stmtPayments->fetchAll();
    foreach ($allPayments as $pay) {
        $orderPaymentsMap[$pay['order_id']][] = $pay;
    }
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function bs($cents): string {
  if ($cents === null) return '-';
  $c = (int)$cents;
  return number_format($c/100, 2, '.', '');
}

$msg = trim($_GET['msg'] ?? '');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>ESENCIA · Panel de control</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
        --primary: #004f39;
        --bg: #fffaca;
        --text: #151613;
        --accent: #ffd32a;
        --success: #10b981;
        --info: #3b82f6;
        --card-bg: #ffffff;
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
        max-width: 1040px;
        margin: 0 auto;
    }
    /* --- FILTROS --- */
    .bar {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
        background: rgba(255, 255, 255, 0.4);
        padding: 15px;
        border-radius: 15px;
        backdrop-filter: blur(5px);
        margin-bottom: 20px;
        border: 1px solid rgba(0, 0, 0, 0.05);
        align-items: flex-end;
    }

    .bar input, .bar select {
        padding: 10px 14px;
        border-radius: 10px;
        border: 1px solid rgba(0,0,0,0.1);
        background: #fff;
        font-size: 14px;
        width: 100%;
        box-sizing: border-box;
    }

    @media (max-width: 640px) {
        .bar { 
            grid-template-columns: 1fr; /* Stack on mobile */
            gap: 12px;
            padding: 12px;
        }
        .bar > *:last-child {
            margin-top: 5px;
        }
    }
    .card.status-solicitado, .card.status-created { border-left: 8px solid #ffd32a; background: #fffcf0; }
    .card.status-produccion { border-left: 8px solid #3498db; background: #f0f7ff; }
    .card.status-listo { border-left: 8px solid #2ecc71; background: #f2fff6; }
    .card.status-entregado { border-left: 8px solid #aaa; color: #777; }
    
    .row{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
    .pill{display:inline-block;padding:5px 12px;border-radius:999px;background:#eee;font-size:12px; font-weight: 600;}
    button{padding:10px 16px;border-radius:12px;border:1px solid #ccc;background:#fff;cursor:pointer; font-weight:600; transition: 0.2s; color: #151613;}
    button:hover{filter: brightness(0.92); transform: translateY(-1px);}
    button.primary{background:#004f39;color:#fffaca;border-color:#004f39; box-shadow: 0 4px 10px rgba(0,79,57,0.2);}
    button.danger{background:#b00020;color:#fff;border-color:#b00020}
    input,select{padding:10px 14px;border-radius:12px;border:1px solid #ccc}
    .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:15px; border-top: 1px solid #eee; padding-top:15px;}
    small{color:#666}
    .note{background:#f8f9fa;border:1px solid #eee;padding:12px;border-radius:14px;margin:12px 0}
    .moneyline{margin-top:6px;line-height:1.4}
    .muted{color:#666}
    .client-name{font-size:1.4rem; margin:0; color:#004f39; font-weight: 800;}
    .ref-thumbnail{width:80px; height:80px; border-radius:12px; object-fit:cover; border:2px solid #fff; box-shadow:0 2px 8px rgba(0,0,0,0.1); cursor:pointer;}
    .wa-btn { background: #25D366; color:#fff; border:none; padding: 8px 12px; border-radius:10px; font-size:13px; font-weight:bold; display:inline-flex; align-items:center; gap:5px; text-decoration:none; }
    details summary { cursor:pointer; color:#004f39; font-weight:bold; margin-top:10px; font-size:14px; outline:none; }
    .card { background: var(--card-bg); border-radius: 20px; padding: 22px; margin-bottom: 18px; box-shadow: 0 4px 16px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.05); }
    .status-line { height: 0; }
    /* === COMPACT TOOLBAR === */
    .admin-toolbar {
        display: flex; justify-content: space-between; align-items: center;
        gap: 12px; margin-bottom: 10px; flex-wrap: wrap;
    }
    .toolbar-left { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; flex-wrap: wrap; }
    .toolbar-right { display: flex; gap: 8px; }
    .search-inline { display: flex; gap: 6px; flex: 1; min-width: 0; max-width: 340px; }
    .search-input {
        padding: 9px 14px; border-radius: 10px; border: 1px solid rgba(0,0,0,0.12);
        background: #fff; font-size: 14px; flex: 1; min-width: 0;
    }
    .toolbar-btn {
        padding: 9px 14px; border-radius: 10px; border: 1px solid rgba(0,0,0,0.12);
        background: #fff; font-size: 13px; font-weight: 700; cursor: pointer;
        display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;
        transition: background 0.15s; color: #151613; position: relative;
    }
    .toolbar-btn:hover { background: #f1f5f9; }
    .toolbar-btn.primary { background: #004f39; color: #fffaca; border-color: #004f39; }
    .toolbar-btn.primary:hover { background: #003d2b; }
    .toolbar-btn.success { background: #10b981; color: #fff; border-color: #10b981; }
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
        margin-bottom: 14px;
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
        .toolbar-left { flex-direction: column; align-items: stretch; }
        .search-inline { max-width: 100%; }
        .adv-filters-group { min-width: 100%; }
        .actions button, .actions .wa-btn { width: 100%; justify-content: center; }
        .client-name { font-size: 1.1rem; }
    }

    /* MODAL STYLES */
    .modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.6); backdrop-filter: blur(5px);
        display: flex; align-items: center; justify-content: center;
        opacity: 0; visibility: hidden; transition: all 0.3s; z-index: 3000;
        padding: 20px;
    }
    .modal-overlay.active { opacity: 1; visibility: visible; }
    .modal-box {
        background: #fff; border-radius: 24px; width: 100%; max-width: 500px;
        max-height: 85vh; display: flex; flex-direction: column;
        box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        transform: translateY(20px); transition: transform 0.3s;
    }
    .modal-overlay.active .modal-box { transform: translateY(0); }
    .modal-header { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
    .modal-header h3 { margin: 0; font-size: 1.25rem; }
    .modal-close { background: none; border: none; font-size: 24px; color: #94a3b8; cursor: pointer; padding: 0; display:flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; transition:0.2s; }
    .modal-close:hover { background: #f1f5f9; color: #0f172a; }
    .modal-body { padding: 24px; overflow-y: auto; }
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

<?php if ($msg !== ''): ?>
  <div style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 16px; margin-bottom: 20px; font-weight: 600; border: 1px solid #bbf7d0;">
    <i class="fas fa-check-circle"></i> <?= h($msg) ?>
  </div>
<?php endif; ?>

<!-- TOOLBAR COMPACTO -->
<div class="admin-toolbar">
  <div class="toolbar-left">
    <h2 style="margin:0; font-size:1.2rem; color:#004f39; font-weight:800;"><i class="fas fa-box"></i> Pedidos</h2>
    <form method="get" class="search-inline">
      <input type="text" name="q" value="<?= h($q) ?>" placeholder="Buscar nombre, tel, código…" class="search-input" id="inlineSearchQ">
      <?php if ($type): ?><input type="hidden" name="type" value="<?= h($type) ?>"> <?php endif; ?>
      <?php if ($status): ?><input type="hidden" name="status" value="<?= h($status) ?>"> <?php endif; ?>
      <button type="submit" class="toolbar-btn primary"><i class="fas fa-search"></i></button>
    </form>
  </div>
  <div class="toolbar-right">
    <button class="toolbar-btn" id="toggleFilters" onclick="document.getElementById('advFilters').classList.toggle('open')">
      <i class="fas fa-sliders-h"></i> Filtros
      <?php if ($type || ($status && $status !== '')): ?><span class="filter-dot"></span><?php endif; ?>
    </button>
  </div>
</div>

<!-- FILTROS AVANZADOS COLAPSABLES -->
<div class="adv-filters" id="advFilters">
  <form class="adv-filters-inner" method="get">
    <?php if ($q): ?><input type="hidden" name="q" value="<?= h($q) ?>"><?php endif; ?>
    <div class="adv-filters-group">
      <label>Tipo</label>
      <select name="type">
        <option value="">Todos</option>
        <option value="EXPRESS" <?= $type==='EXPRESS'?'selected':''; ?>>EXPRESS</option>
        <option value="CUSTOM" <?= $type==='CUSTOM'?'selected':''; ?>>CUSTOM</option>
        <option value="PACK" <?= $type==='PACK'?'selected':''; ?>>PACK</option>
      </select>
    </div>
    <div class="adv-filters-group">
      <label>Estado</label>
      <select name="status">
        <option value="">Pendientes / En Curso</option>
        <option value="ALL" <?= $status==='ALL'?'selected':''; ?>>Historial Completo</option>
        <option value="SOLICITADO" <?= $status==='SOLICITADO'?'selected':''; ?>>SOLICITADO</option>
        <option value="APROBADO_PARA_PAGO" <?= $status==='APROBADO_PARA_PAGO'?'selected':''; ?>>APROBADO_PARA_PAGO</option>
        <option value="EN_PRODUCCION" <?= $status==='EN_PRODUCCION'?'selected':''; ?>>EN PRODUCCIÓN</option>
        <option value="LISTO" <?= $status==='LISTO'?'selected':''; ?>>LISTO</option>
        <option value="ENTREGADO" <?= $status==='ENTREGADO'?'selected':''; ?>>ENTREGADO</option>
        <option value="RECHAZADO" <?= $status==='RECHAZADO'?'selected':''; ?>>RECHAZADO</option>
      </select>
    </div>
    <div class="adv-filters-actions">
      <button class="toolbar-btn primary" type="submit"><i class="fas fa-filter"></i> Aplicar</button>
      <a href="/admin/orders.php"><button type="button" class="toolbar-btn"><i class="fas fa-undo"></i> Limpiar</button></a>
    </div>
  </form>
</div>

<?php foreach ($orders as $o): ?>
  <?php
    $total = $o['total_final_cents'] !== null ? (int)$o['total_final_cents'] : null;
    $paidV = (int)$o['paid_verified_cents'];
    $paidP = (int)$o['paid_pending_cents'];
    $st = strtoupper((string)$o['status']);
    $canPay = ($st === 'APROBADO_PARA_PAGO');

    // Lógica de anticipo requerida
    $required_ratio = ($o['type'] === 'CUSTOM' || $o['type'] === 'PACK') ? 0.5 : 0.3;
    $required_cents = ($total !== null) ? (int)round($total * $required_ratio) : 0;
    $is_min_paid = ($total !== null && $paidV >= $required_cents);
    $remaining = ($total !== null) ? max($total - $paidV, 0) : null;
    
    // Auto-cálculo para Express
    $calcTotalCents = 0;
    $hasItems = false;
    if (!empty($orderItemsMap[$o['id']])) {
        $hasItems = true;
        foreach ($orderItemsMap[$o['id']] as $item) {
            $calcTotalCents += ($item['quantity'] * ($item['unit_price_cents'] ?? 0));
        }
    }
    
    // Si no tiene total definido pero tiene items y es Express, usamos el calculado para mostrar
    $displayTotal = ($total !== null) ? $total : ($o['type'] === 'EXPRESS' ? $calcTotalCents : null);
    $displayRemaining = ($displayTotal !== null) ? max($displayTotal - $paidV, 0) : null;
    
    $cardClass = '';
    if ($st === 'SOLICITADO' || $st === 'CREATED') $cardClass = 'status-solicitado';
    elseif ($st === 'EN_PRODUCCION') $cardClass = 'status-produccion';
    elseif ($st === 'LISTO') $cardClass = 'status-listo';
    elseif ($st === 'ENTREGADO') $cardClass = 'status-entregado';

    $translations = [
      'people' => 'Personas/Porciones',
      'flavor' => 'Sabor',
      'size' => 'Tamaño',
      'theme' => 'Temática',
      'message' => 'Mensaje/Dedicatoria',
      'qty' => 'Cantidad',
      'notes' => 'Notas adicionales',
      'payment_method' => 'Método de Pago',
      'note' => 'Nota del cliente'
    ];

    $valTranslations = [
      'TIENDA' => '📍 Recojo en Tienda',
      'QR' => '💳 Pago por QR',
      'EXPRESS' => 'Rápido (Express)',
      'CUSTOM' => 'Personalizado',
      'PACK' => 'Paquete/Pack'
    ];

    $whatsappMsg = "Hola " . ($o['customer_name'] ?: '') . "! Te escribo de ESENCIA por tu pedido " . $o['order_code'];
    $whatsappLink = "https://wa.me/" . preg_replace('/\D+/', '', $o['customer_phone']) . "?text=" . rawurlencode($whatsappMsg);
  ?>
  <div class="card">
    <div class="status-line st-<?= strtolower($o['status']) ?>"></div>
    <div class="client-header" style="display:flex; justify-content:space-between; align-items:flex-start; gap:20px;">
      <div style="flex:1">
        <div class="row" style="margin-bottom: 12px;">
          <div class="pill">#<?= h($o['order_code']) ?></div>
          <div class="pill" style="background:#fef3c7; color:#92400e;"><?= h($o['type']) ?></div>
          <div class="pill" style="background:var(--primary); color:#fffaca;"><?= h($o['status']) ?></div>
          
          <?php if ($total !== null): ?>
            <?php if ($remaining <= 0): ?>
              <div class="pill" style="background:#dcfce7; color:#166534;"><i class="fas fa-check"></i> TOTAL PAGADO</div>
            <?php elseif ($is_min_paid): ?>
              <div class="pill" style="background:#ecfdf5; color:#065f46;"><i class="fas fa-clock"></i> RESERVA CUBIERTA (<?= h(round($paidV/$total*100)) ?>%)</div>
            <?php else: ?>
              <div class="pill" style="background:#fef2f2; color:#991b1b;"><i class="fas fa-exclamation-triangle"></i> REQUIERE Bs <?= h(bs($required_cents)) ?></div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
        <h3 class="client-name"><?= h($o['customer_name'] ?: 'Cliente sin nombre') ?></h3>
        <div style="margin-top:8px">
          <a href="<?= $whatsappLink ?>" target="_blank" class="wa-btn">
            <i class="fab fa-whatsapp"></i> <?= h($o['customer_phone'] ?: '-') ?>
          </a>
        </div>
      </div>
      <?php if ($o['ref_image_path']): ?>
        <a href="<?= h($o['ref_image_path']) ?>" target="_blank">
          <img src="<?= h($o['ref_image_path']) ?>" class="ref-thumbnail" title="Click para ver imagen de referencia">
        </a>
      <?php endif; ?>
    </div>

    <?php 
      $unverifiedWithProof = array_filter($orderPaymentsMap[$o['id']] ?? [], function($p) {
        return !$p['verified'] && !empty($p['proof_path']);
      }); 
    ?>
    <?php if (!empty($unverifiedWithProof)): ?>
      <div style="margin-top:20px; background:#fffcf0; border:1px solid #fde68a; padding:15px; border-radius:18px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
          <span style="font-size:13px; font-weight:800; color:#92400e;"><i class="fas fa-search-dollar"></i> VERIFICAR ABONO:</span>
          <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <?php foreach ($unverifiedWithProof as $p): ?>
              <div style="display:flex; align-items:center; gap:8px; background:#fff; padding:6px 12px; border-radius:12px; border:1px solid #fde68a; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                <b style="font-size:14px; color:var(--text);">Bs <?= h(bs($p['amount_cents'])) ?></b>
                <a href="/admin/ver_comprobante.php?payment_id=<?= h($p['id']) ?>" target="_blank" class="secondary" style="padding:6px 10px; font-size:11px; text-decoration:none; border-radius:8px; display:inline-flex; align-items:center; gap:4px; font-weight:bold; background:#f3f4f6; border:1px solid #e5e7eb;">📸 Ver</a>
                <form method="post" action="/admin/payment_verify.php" style="display:inline">
                  <?= csrf_input() ?>
                  <input type="hidden" name="payment_id" value="<?= h($p['id']) ?>">
                  <button type="submit" class="primary" style="padding:6px 12px; font-size:11px; border-radius:8px; background:#10b981;">✅ Validar</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <small style="display:block; margin-top:8px; color:#92400e; font-size:11px; font-style:italic;">* Asegúrese de que el monto coincida con su extracto bancario antes de validar.</small>
      </div>
    <?php endif; ?>

    <div style="margin-top:12px; display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:10px;">
      <div>
        <small class="muted">Fecha de recojo:</small><br>
        <b>📅 <?= h($o['pickup_date'] ?: '-') ?></b> | <b>🕒 <?= h($o['pickup_time'] ?: '-') ?></b>
      </div>
      <div>
        <small class="muted">Monto total:</small><br>
        <b>Bs <?= h(bs($displayTotal)) ?></b> 
        <?php if ($displayRemaining > 0): ?>
          <span style="color:#b00020">(Resta: Bs <?= h(bs($displayRemaining)) ?>)</span>
        <?php elseif ($displayTotal !== null): ?>
          <span style="color:#28a745">(Pagado)</span>
        <?php endif; ?>
      </div>
    </div>

    <?php 
    $cjson = json_decode($o['custom_json'] ?? '{}', true) ?: [];
    $items = $orderItemsMap[$o['id']] ?? [];
    $payments = $orderPaymentsMap[$o['id']] ?? [];
    if (!empty($cjson) || !empty($items) || !empty($payments)): 
    ?>
    <button type="button" class="toolbar-btn" style="margin-top:12px; width:100%; justify-content:center;" onclick="openModal('modal_<?= h($o['id']) ?>')">
      <i class="fas fa-search-plus"></i> Ver Detalles Completos y Productos
    </button>
    
    <!-- MODAL PARA ESTE PEDIDO -->
    <div class="modal-overlay" id="modal_<?= h($o['id']) ?>" onclick="closeModal(event, this)">
      <div class="modal-box">
        <div class="modal-header">
          <h3><i class="fas fa-receipt"></i> Detalles del Pedido #<?= h($o['order_code']) ?></h3>
          <button type="button" class="modal-close" onclick="closeModal(event, 'modal_<?= h($o['id']) ?>')">&times;</button>
        </div>
        <div class="modal-body">
          <?php if (!empty($items)): ?>
            <div style="margin-bottom:20px; border-bottom:1px solid #f1f5f9; padding-bottom:15px;">
              <b style="color:var(--primary); font-size:15px;"><i class="fas fa-shopping-cart"></i> Productos:</b>
              <ul style="margin:10px 0 0 0; padding-left:20px; font-size:14px;">
                <?php foreach ($items as $item): ?>
                  <li style="margin-bottom:6px">
                    <b><?= h($item['quantity']) ?></b> x <?= h($item['product_name']) ?> 
                    <?php if ($item['unit_price_cents']): ?>
                      <small class="muted" style="margin-left:5px;">(Bs <?= h(number_format($item['unit_price_cents']/100, 2)) ?> c/u)</small>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <?php if (!empty($cjson)): ?>
          <div style="margin-bottom:20px;">
            <b style="color:var(--primary); font-size:15px;"><i class="fas fa-clipboard-list"></i> Personalización:</b>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:12px; margin-top:10px; font-size:14px;">
            <?php foreach ($cjson as $k => $v): ?>
              <?php if (is_array($v) || $v === '') continue; ?>
              <?php 
                $label = $translations[$k] ?? ucfirst($k); 
                $val = $valTranslations[strtoupper((string)$v)] ?? $v;
              ?>
              <div style="background:#f8fafc; padding:10px; border-radius:10px;">
                <small class="muted" style="font-size:10px; text-transform:uppercase; font-weight:800;"><?= h($label) ?></small><br>
                <span style="font-weight:600; color:#0f172a;"><?= nl2br(h($val)) ?></span>
              </div>
            <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <?php if (!empty($payments)): ?>
            <div style="margin-top:20px; border-top:2px dashed #f1f5f9; padding-top:15px;">
              <b style="color:var(--primary); font-size:15px;"><i class="fas fa-history"></i> Historial de Pagos:</b>
              <div style="margin-top:10px; display: grid; gap: 8px;">
                <?php foreach ($payments as $p): ?>
                  <div style="display:flex; justify-content:space-between; align-items:center; background:#fffaca; border:1px solid #fde68a; padding:12px; border-radius:12px;">
                    <div>
                      <span class="pill" style="font-size:10px; background:<?= $p['verified'] ? '#dcfce7' : '#fef3c7' ?>; color:<?= $p['verified'] ? '#166534' : '#92400e' ?>; border:1px solid <?= $p['verified'] ? '#bbf7d0' : '#fde047' ?>">
                        <?= $p['verified'] ? 'VERIFICADO' : 'PENDIENTE' ?>
                      </span>
                      <b style="margin-left:8px; font-size:15px;">Bs <?= h(bs($p['amount_cents'])) ?></b>
                      <small class="muted" style="margin-left:5px;">vía <?= h($p['method']) ?></small>
                      <div style="font-size:11px; color:#64748b; margin-top:4px; font-weight:600;"><i class="far fa-clock"></i> <?= h($p['created_at']) ?></div>
                    </div>
                    <?php if ($p['proof_path']): ?>
                      <a href="/admin/ver_comprobante.php?payment_id=<?= h($p['id']) ?>" target="_blank" class="toolbar-btn primary" style="font-size:11px; padding:8px 12px; border-radius:10px; box-shadow:none;"><i class="fas fa-image"></i> Ver Foto</a>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="actions">
      <?php if ($st === 'CREATED' || $st === 'SOLICITADO'): ?>
        <form method="post" action="/admin/order_update.php" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; flex:1;">
          <?= csrf_input() ?>
          <input type="hidden" name="id" value="<?= h($o['id']) ?>">
          <input type="hidden" name="action" value="approve_with_quote">
          
          <?php if ($o['type'] === 'EXPRESS' && $calcTotalCents > 0): ?>
            <input type="hidden" name="total_bs" value="<?= h($calcTotalCents/100) ?>">
            <button class="primary" type="submit" style="background:var(--success);">
              <i class="fas fa-check-double"></i> Aprobar (Bs <?= h(bs($calcTotalCents)) ?>)
            </button>
            <div style="display:flex; align-items:center; gap:8px;">
              <small class="muted" style="font-size:10px; font-weight:700;">CORREGIR TOTAL:</small>
              <input type="number" name="total_bs_override" step="0.01" min="0" placeholder="Nuevo Bs" style="width:100px; padding:10px;" oninput="this.form.total_bs.value=this.value">
            </div>
          <?php else: ?>
            <input type="number" name="total_bs" step="0.01" min="0" placeholder="Cotizar Bs" style="width:120px; padding:12px;" required>
            <button class="primary" type="submit">
              <i class="fas fa-paper-plane"></i> Aprobar y Cotizar
            </button>
          <?php endif; ?>
        </form>
        <form method="post" action="/admin/order_update.php" style="display:inline">
          <?= csrf_input() ?>
          <input type="hidden" name="id" value="<?= h($o['id']) ?>">
          <input type="hidden" name="status" value="RECHAZADO">
          <button class="danger" type="submit"><i class="fas fa-times"></i> Rechazar</button>
        </form>
      <?php elseif ($st === 'APROBADO_PARA_PAGO'): ?>
        <?php if ($is_min_paid): ?>
          <form method="post" action="/admin/order_update.php" style="display:inline">
            <?= csrf_input() ?>
            <input type="hidden" name="id" value="<?= h($o['id']) ?>">
            <input type="hidden" name="status" value="EN_PRODUCCION">
            <button class="primary" type="submit" style="background:var(--info);">
              <i class="fas fa-industry"></i> PASAR A PRODUCCIÓN
            </button>
          </form>
        <?php else: ?>
          <div style="display:inline-flex; align-items:center; gap:10px; padding:12px 20px; background:#fff1f2; border-radius:14px; border:1px solid #fecaca; color:#991b1b; font-size:13px; font-weight:800;">
            <i class="fas fa-lock"></i> Bloqueado: Falta <?= ($required_ratio*100) ?>% (Bs <?= h(bs($required_cents)) ?>)
          </div>
        <?php endif; ?>
        <?php if ($remaining !== null && $remaining > 0): ?>
          <form method="post" action="/admin/cash_payment.php" style="display:flex; align-items:center; gap:10px; flex:1;">
            <?= csrf_input() ?>
            <input type="hidden" name="order_id" value="<?= h($o['id']) ?>">
            <input type="number" name="amount_bs" step="0.01" min="0" placeholder="Bs Efectivo" style="width:120px; padding:12px;" required>
            <button type="submit" class="secondary" style="background:var(--accent); border:none; color:var(--primary); font-weight:800; padding:12px 20px;">
              <i class="fas fa-money-bill-wave"></i> Cobrar Físico
            </button>
          </form>
        <?php endif; ?>
      <?php elseif ($st === 'EN_PRODUCCION' || $st === 'LISTO'): ?>
        <?php if ($st === 'EN_PRODUCCION'): ?>
          <form method="post" action="/admin/order_update.php" style="display:inline">
            <?= csrf_input() ?>
            <input type="hidden" name="id" value="<?= h($o['id']) ?>">
            <input type="hidden" name="status" value="LISTO">
            <button class="primary" type="submit" style="background:#8b5cf6;">
              <i class="fas fa-thumbtack"></i> Marcar LISTO
            </button>
          </form>
        <?php else: ?>
          <form method="post" action="/admin/order_update.php" style="display:inline">
            <?= csrf_input() ?>
            <input type="hidden" name="id" value="<?= h($o['id']) ?>">
            <input type="hidden" name="status" value="ENTREGADO">
            <button class="primary" type="submit" style="background:var(--success);">
              <i class="fas fa-handshake"></i> Entregar Pedido
            </button>
          </form>
        <?php endif; ?>

        <?php if ($remaining !== null && $remaining > 0): ?>
          <form method="post" action="/admin/cash_payment.php" style="display:flex; align-items:center; gap:10px; margin-left:auto;">
            <?= csrf_input() ?>
            <input type="hidden" name="order_id" value="<?= h($o['id']) ?>">
            <input type="number" name="amount_bs" step="0.01" min="0" placeholder="Cobrar Bs" style="width:100px; padding:12px;" required>
            <button type="submit" class="secondary" style="background:var(--accent); border:none; color:var(--primary);">
              <i class="fas fa-coins"></i> Cobrar
            </button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>

<?php if (count($orders) === 0): ?>
  <div style="text-align:center; padding:80px; color:#64748b;">
    <i class="fas fa-box-open" style="font-size:3rem; opacity:0.3; margin-bottom:20px; display:block;"></i>
    No se encontraron pedidos con esos filtros.
  </div>
<?php endif; ?>

</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeModal(e, idOrElement) {
    if (e.target.classList.contains('modal-overlay') || typeof idOrElement === 'string') {
        const el = typeof idOrElement === 'string' ? document.getElementById(idOrElement) : idOrElement;
        el.classList.remove('active');
        document.body.style.overflow = '';
    }
}
</script>

</body>
</html>