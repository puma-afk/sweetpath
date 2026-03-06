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
} else {
  // Por defecto, ocultar los completados y rechazados
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
  <style>
    body{font-family:system-ui,Arial;margin:16px;background:#f0efe4;color:#151613}
    .bar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;align-items:center}
    .card{background:#fff;border:1px solid #ddd;border-radius:18px;padding:20px;margin:15px 0;box-shadow: 0 4px 12px rgba(0,0,0,0.05); position:relative; overflow:hidden;}
    .card.status-solicitado, .card.status-created { border-left: 8px solid #ffd32a; background: #fffcf0; }
    .card.status-produccion { border-left: 8px solid #3498db; background: #f0f7ff; }
    .card.status-listo { border-left: 8px solid #2ecc71; background: #f2fff6; }
    .card.status-entregado { border-left: 8px solid #aaa; color: #777; }
    
    .row{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
    .pill{display:inline-block;padding:5px 12px;border-radius:999px;background:#eee;font-size:12px; font-weight: 600;}
    button{padding:10px 16px;border-radius:12px;border:1px solid #ccc;background:#fff;cursor:pointer; font-weight:500; transition: 0.2s;}
    button:hover{filter: brightness(0.95);}
    button.primary{background:#004f39;color:#fffaca;border-color:#004f39}
    button.danger{background:#b00020;color:#fff;border-color:#b00020}
    input,select{padding:10px 14px;border-radius:12px;border:1px solid #ccc}
    .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:15px; border-top: 1px solid #eee; padding-top:15px;}
    small{color:#666}
    .note{background:#f8f9fa;border:1px solid #eee;padding:12px;border-radius:14px;margin:12px 0}
    .moneyline{margin-top:6px;line-height:1.4}
    .muted{color:#666}
    .client-name{font-size:1.3rem; margin:0; color:#004f39;}
    .ref-thumbnail{width:80px; height:80px; border-radius:12px; object-fit:cover; border:2px solid #fff; box-shadow:0 2px 8px rgba(0,0,0,0.1); cursor:pointer;}
    .wa-btn { background: #25D366; color:#fff; border:none; padding: 8px 12px; border-radius:10px; font-size:13px; font-weight:bold; display:inline-flex; align-items:center; gap:5px; text-decoration:none; }
    details summary { cursor:pointer; color:#004f39; font-weight:bold; margin-top:10px; font-size:14px; outline:none; }
  </style>
</head>
<body>

<h2>🏠 ESENCIA · Panel de control</h2>

<div class="bar" style="justify-content:space-between">
  <div class="muted">
    Sesión: <b><?= h($_SESSION['admin_user']['username'] ?? 'admin') ?></b>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="/sweetpath/admin/payments.php"><button type="button">Ver pagos</button></a>
    <a href="/sweetpath/admin/estadisticas.php"><button type="button">📊 Estadísticas</button></a>
    <a href="/sweetpath/admin/products.php"><button type="button">🧁 Productos</button></a>
    <a href="/sweetpath/admin/promos.php"><button type="button">📣 Promos</button></a>
    <a href="/sweetpath/admin/change_password.php"><button type="button">Cambiar contraseña</button></a>
    <a href="/sweetpath/admin/config.php"><button type="button">⚙️ Config</button></a>
    <a href="/sweetpath/admin/logout.php"><button type="button" class="danger">Cerrar sesión</button></a>
  </div>
</div>

<?php if ($msg !== ''): ?>
  <div class="note"><?= h($msg) ?></div>
<?php endif; ?>

<form class="bar" method="get">
  <input type="text" name="q" value="<?= h($q) ?>" placeholder="Buscar cliente, tel o código..." style="min-width:260px">
  
  <select name="type">
    <option value="">Todos los tipos</option>
    <option value="EXPRESS" <?= $type==='EXPRESS'?'selected':''; ?>>EXPRESS</option>
    <option value="CUSTOM" <?= $type==='CUSTOM'?'selected':''; ?>>CUSTOM</option>
    <option value="PACK" <?= $type==='PACK'?'selected':''; ?>>PACK</option>
  </select>

  <select name="status">
    <option value="">Pendientes / En Curso</option>
    <option value="ALL" <?= $status==='ALL'?'selected':''; ?>>Historial Completo (Todos)</option>
    <option value="SOLICITADO" <?= $status==='SOLICITADO'?'selected':''; ?>>SOLICITADO</option>
    <option value="APROBADO_PARA_PAGO" <?= $status==='APROBADO_PARA_PAGO'?'selected':''; ?>>APROBADO_PARA_PAGO</option>
    <option value="EN_PRODUCCION" <?= $status==='EN_PRODUCCION'?'selected':''; ?>>EN_PRODUCCION</option>
    <option value="LISTO" <?= $status==='LISTO'?'selected':''; ?>>LISTO</option>
    <option value="ENTREGADO" <?= $status==='ENTREGADO'?'selected':''; ?>>ENTREGADO</option>
    <option value="RECHAZADO" <?= $status==='RECHAZADO'?'selected':''; ?>>RECHAZADO</option>
    <option value="CREATED" <?= $status==='CREATED'?'selected':''; ?>>CREATED</option>
  </select>

  <button class="primary" type="submit">Filtrar</button>
  <a href="/sweetpath/admin/orders.php"><button type="button">Limpiar</button></a>
</form>

<?php foreach ($orders as $o): ?>
  <?php
    $total = $o['total_final_cents'] !== null ? (int)$o['total_final_cents'] : null;
    $paidV = (int)$o['paid_verified_cents'];
    $paidP = (int)$o['paid_pending_cents'];
    $remaining = ($total !== null) ? max($total - $paidV, 0) : null;
    $st = strtoupper((string)$o['status']);
    $canPay = ($st === 'APROBADO_PARA_PAGO');
    
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
  <div class="card <?= $cardClass ?>">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:15px;">
      <div style="flex:1">
        <div class="row">
          <small class="muted">#<?= h($o['order_code']) ?></small>
          <div class="pill"><?= h($o['type']) ?></div>
          <div class="pill" style="background:var(--primary); color:#fff;"><?= h($o['status']) ?></div>
        </div>
        <h3 class="client-name"><?= h($o['customer_name'] ?: 'Cliente sin nombre') ?></h3>
        <div style="margin-top:4px">
          <a href="<?= $whatsappLink ?>" target="_blank" class="wa-btn">
            <span>📲</span> <?= h($o['customer_phone'] ?: '-') ?>
          </a>
        </div>
      </div>
      <?php if ($o['ref_image_path']): ?>
        <a href="<?= h($o['ref_image_path']) ?>" target="_blank">
          <img src="<?= h($o['ref_image_path']) ?>" class="ref-thumbnail" title="Click para ver imagen de referencia">
        </a>
      <?php endif; ?>
    </div>

    <div style="margin-top:12px; display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:10px;">
      <div>
        <small class="muted">Fecha de recojo:</small><br>
        <b>📅 <?= h($o['pickup_date'] ?: '-') ?></b> | <b>🕒 <?= h($o['pickup_time'] ?: '-') ?></b>
      </div>
      <div>
        <small class="muted">Monto total:</small><br>
        <b>Bs <?= h(bs($total)) ?></b> 
        <?php if ($remaining > 0): ?>
          <span style="color:#b00020">(Resta: Bs <?= h(bs($remaining)) ?>)</span>
        <?php else: ?>
          <span style="color:#28a745">(Pagado)</span>
        <?php endif; ?>
      </div>
    </div>

    <?php 
    $cjson = json_decode($o['custom_json'] ?? '{}', true) ?: [];
    if (!empty($cjson)): 
    ?>
    <details>
      <summary>🔍 Ver más detalles del pedido</summary>
      <div class="note" style="font-size:14px">
        
        <?php 
        $items = $orderItemsMap[$o['id']] ?? [];
        if (!empty($items)):
        ?>
          <div style="margin-bottom:10px; border-bottom:1px solid #eee; padding-bottom:10px;">
            <b>🛒 Productos en el carrito:</b>
            <ul style="margin:5px 0 0 0px; padding-left:15px;">
              <?php foreach ($items as $item): ?>
                <li>
                  <?= h($item['quantity']) ?> x <b><?= h($item['product_name']) ?></b> 
                  <?php if ($item['unit_price_cents']): ?>
                    (Bs <?= h(number_format($item['unit_price_cents']/100, 2)) ?> c/u)
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <ul style="margin:0; padding-left:15px;">
          <?php foreach ($cjson as $k => $v): ?>
            <?php if (is_array($v) || $v === '') continue; ?>
            <?php 
              $label = $translations[$k] ?? ucfirst($k); 
              $val = $valTranslations[strtoupper((string)$v)] ?? $v;
            ?>
            <li style="margin-bottom:5px"><b><?= h($label) ?>:</b> <?= nl2br(h($val)) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </details>
    <?php endif; ?>

    <div class="actions">
      <?php if ($st === 'CREATED' || $st === 'SOLICITADO'): ?>
        <form method="post" action="/sweetpath/admin/order_update.php" style="display:inline">
          <?= csrf_input() ?>
          <input type="hidden" name="id" value="<?= h($o['id']) ?>">
          <input type="hidden" name="action" value="approve_with_quote">
          <input type="number" name="total_bs" step="0.01" min="0" placeholder="Cotizar Bs" style="width:110px" required>
          <button class="primary" type="submit">⏳ Aprobar y Cotizar</button>
        </form>
        <form method="post" action="/sweetpath/admin/order_update.php" style="display:inline">
          <?= csrf_input() ?>
          <input type="hidden" name="id" value="<?= h($o['id']) ?>">
          <input type="hidden" name="status" value="RECHAZADO">
          <button class="danger" type="submit">❌ Rechazar</button>
        </form>
      <?php elseif ($st === 'APROBADO_PARA_PAGO'): ?>
        <form method="post" action="/sweetpath/admin/order_update.php" style="display:inline">
          <?= csrf_input() ?>
          <input type="hidden" name="id" value="<?= h($o['id']) ?>">
          <input type="hidden" name="status" value="EN_PRODUCCION">
          <button class="primary" type="submit" style="background:#3498db; border-color:#3498db">🏭 Pasar a Producción</button>
        </form>
        <?php if ($remaining !== null && $remaining > 0): ?>
        <form method="post" action="/sweetpath/admin/cash_payment.php" style="display:inline">
          <?= csrf_input() ?>
          <input type="hidden" name="order_id" value="<?= h($o['id']) ?>">
          <input type="number" name="amount_bs" step="0.01" min="0" placeholder="Bs Efectivo" style="width:110px" required>
          <button type="submit">💵 Registrar Pago</button>
        </form>
        <?php endif; ?>
      <?php elseif ($st === 'EN_PRODUCCION'): ?>
        <form method="post" action="/sweetpath/admin/order_update.php" style="display:inline">
          <?= csrf_input() ?>
          <input type="hidden" name="id" value="<?= h($o['id']) ?>">
          <input type="hidden" name="status" value="LISTO">
          <button class="primary" type="submit" style="background:#2ecc71; border-color:#2ecc71">📌 Marcar LISTO</button>
        </form>
      <?php elseif ($st === 'LISTO'): ?>
        <form method="post" action="/sweetpath/admin/order_update.php" style="display:inline">
          <?= csrf_input() ?>
          <input type="hidden" name="id" value="<?= h($o['id']) ?>">
          <input type="hidden" name="status" value="ENTREGADO">
          <button class="primary" type="submit" style="background:#28a745; border-color:#28a745">✅ Entregar Pedido</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>

<?php if (count($orders) === 0): ?>
  <p style="text-align:center; padding:40px; color:#666;">No se encontraron pedidos con esos filtros.</p>
<?php endif; ?>

</body>
</html>