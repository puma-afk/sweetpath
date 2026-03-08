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
  <style>
    body{font-family:system-ui,Arial,sans-serif;margin:16px;background:#fffaca;color:#151613}
    .bar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;align-items:center}
    .card{background:#fff;border:1px solid #ddd;border-radius:18px;padding:20px;margin:15px 0;box-shadow: 0 4px 12px rgba(0,0,0,0.05); position:relative; overflow:hidden;}
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
</head>
<body>

<?php require __DIR__ . '/_navbar.php'; ?>

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
  <div class="card <?= $cardClass ?>">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:15px;">
      <div style="flex:1">
        <div class="row">
          <div class="pill" style="background:#efeffa; color:#555; border:1px solid #ddd;">#<?= h($o['order_code']) ?></div>
          <div class="pill" style="background:#eee; color:#333; border:1px solid #ccc;"><?= h($o['type']) ?></div>
          <div class="pill" style="background:#004f39; color:#fffaca; border:1px solid #004f39;"><?= h($o['status']) ?></div>
          
          <?php if ($total !== null): ?>
            <?php if ($remaining <= 0): ?>
              <div class="pill" style="background:#dcfce7; color:#166534; border:1px solid #166534">🟢 TOTAL PAGADO</div>
            <?php elseif ($is_min_paid): ?>
              <div class="pill" style="background:#d1fae5; color:#065f46; border:1px solid #065f46">🟡 RESERVA CUBIERTA (<?= h(round($paidV/$total*100)) ?>%)</div>
            <?php else: ?>
              <div class="pill" style="background:#ffe8e8; color:#b00020; border:1px solid #b00020">🔴 REQUIERE <?= ($required_ratio*100) ?>% (Bs <?= h(bs($required_cents)) ?>)</div>
            <?php endif; ?>
          <?php endif; ?>
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

    <?php 
      $unverifiedWithProof = array_filter($orderPaymentsMap[$o['id']] ?? [], function($p) {
        return !$p['verified'] && !empty($p['proof_path']);
      }); 
    ?>
    <?php if (!empty($unverifiedWithProof)): ?>
      <div style="margin-top:10px; background:#fffcf0; border:1px solid #ffd32a; padding:12px; border-radius:12px;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <span style="font-size:13px; font-weight:bold; color:#92400e;">⚠️ Verificar abono en cuenta:</span>
          <div style="display:flex; gap:8px;">
            <?php foreach ($unverifiedWithProof as $p): ?>
              <div style="display:flex; align-items:center; gap:5px; background:#fff; padding:5px 10px; border-radius:10px; border:1px solid #ffd32a;">
                <b style="font-size:12px">Bs <?= h(bs($p['amount_cents'])) ?></b>
                <a href="/sweetpath/admin/ver_comprobante.php?payment_id=<?= h($p['id']) ?>" target="_blank" class="btn" style="background:#ffd32a; color:#151613; font-size:11px; padding:4px 8px; text-decoration:none; border-radius:6px; font-weight:bold;">📸 Ver</a>
                <form method="post" action="/sweetpath/admin/payment_verify.php" style="display:inline">
                  <?= csrf_input() ?>
                  <input type="hidden" name="payment_id" value="<?= h($p['id']) ?>">
                  <button type="submit" style="background:#28a745; color:#fff; font-size:11px; padding:4px 8px; border:none; border-radius:6px; cursor:pointer; font-weight:bold;">✅ Validar</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <small style="display:block; margin-top:5px; color:#92400e; font-size:11px;">* Asegúrese de que el monto coincida con su extracto bancario antes de validar.</small>
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
            <?php if ($calcTotalCents > 0): ?>
            <div style="margin-top:8px; text-align:right; font-size:14px; padding-right:10px;">
              <b>Subtotal Carrito:</b> Bs <?= h(bs($calcTotalCents)) ?>
            </div>
            <?php endif; ?>
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

        <?php 
        $payments = $orderPaymentsMap[$o['id']] ?? [];
        if (!empty($payments)):
        ?>
          <div style="margin-top:15px; border-top:1px solid #eee; padding-top:10px;">
            <b>💰 Historial de Pagos y Comprobantes:</b>
            <div style="margin-top:5px; display: flex; flex-direction: column; gap: 8px;">
              <?php foreach ($payments as $p): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; background:#fff; border:1px solid #eee; padding:10px; border-radius:10px;">
                  <div>
                    <span class="pill" style="background:<?= $p['verified'] ? '#dcfce7' : '#fef3c7' ?>; color:<?= $p['verified'] ? '#166534' : '#92400e' ?>; font-size:11px; padding:3px 8px;">
                      <?= $p['verified'] ? '✅ VERIFICADO' : '⏳ POR VERIFICAR' ?>
                    </span>
                    <b style="margin-left:8px; font-size:14px;">Bs <?= h(bs($p['amount_cents'])) ?></b>
                    <small class="muted" style="margin-left:5px;"> via <?= h($p['method']) ?></small>
                    <div style="font-size:10px; color:#999; margin-top:2px;">Ref: <?= h($p['reference_id'] ?? 'S/R') ?> | <?= h($p['created_at']) ?></div>
                  </div>
                  <div style="display:flex; gap:5px;">
                    <?php if ($p['proof_path']): ?>
                      <a href="/sweetpath/admin/ver_comprobante.php?payment_id=<?= h($p['id']) ?>" target="_blank" class="btn" style="background:#eee; color:#004f39; font-size:11px; padding:6px 10px; text-decoration:none; border-radius:8px; font-weight:bold; border:1px solid #ddd;">📸 Ver Comprobante</a>
                    <?php endif; ?>
                    
                    <?php if (!$p['verified']): ?>
                      <form method="post" action="/sweetpath/admin/payment_verify.php" style="display:inline" onsubmit="return confirm('¿Confirma que este monto ya ingresó a su cuenta bancaria/caja?');">
                        <?= csrf_input() ?>
                        <input type="hidden" name="payment_id" value="<?= h($p['id']) ?>">
                        <button type="submit" style="background:#28a745; color:#fff; font-size:11px; padding:6px 12px; border:none; border-radius:8px; cursor:pointer; font-weight:bold;">✅ Validar Pago</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </details>
    <?php endif; ?>

    <div class="actions">
      <?php if ($st === 'CREATED' || $st === 'SOLICITADO'): ?>
        <form method="post" action="/sweetpath/admin/order_update.php" style="display:inline">
          <?= csrf_input() ?>
          <input type="hidden" name="id" value="<?= h($o['id']) ?>">
          <input type="hidden" name="action" value="approve_with_quote">
          
          <?php if ($o['type'] === 'EXPRESS' && $calcTotalCents > 0): ?>
            <input type="hidden" name="total_bs" value="<?= h($calcTotalCents/100) ?>">
            <button class="primary" type="submit" style="background:#2ecc71; border-color:#2ecc71">✅ Aprobar Pedido (Bs <?= h(bs($calcTotalCents)) ?>)</button>
            <small style="margin-left:5px">O corregir total:</small>
            <input type="number" name="total_bs_override" step="0.01" min="0" placeholder="Nuevo Bs" style="width:90px" oninput="this.form.total_bs.value=this.value">
          <?php else: ?>
            <input type="number" name="total_bs" step="0.01" min="0" placeholder="Cotizar Bs" style="width:110px" required>
            <button class="primary" type="submit">⏳ Aprobar y Cotizar</button>
          <?php endif; ?>
        </form>
        <form method="post" action="/sweetpath/admin/order_update.php" style="display:inline">
          <?= csrf_input() ?>
          <input type="hidden" name="id" value="<?= h($o['id']) ?>">
          <input type="hidden" name="status" value="RECHAZADO">
          <button class="danger" type="submit">❌ Rechazar</button>
        </form>
      <?php elseif ($st === 'APROBADO_PARA_PAGO'): ?>
        <?php if ($is_min_paid): ?>
          <form method="post" action="/sweetpath/admin/order_update.php" style="display:inline">
            <?= csrf_input() ?>
            <input type="hidden" name="id" value="<?= h($o['id']) ?>">
            <input type="hidden" name="status" value="EN_PRODUCCION">
            <button class="primary" type="submit" style="background:#3498db; border-color:#3498db">🏭 PASAR A PRODUCCIÓN</button>
          </form>
        <?php else: ?>
          <div style="display:inline-block; padding:10px 15px; background:#fff5f5; border-radius:12px; border:1px solid #feb2b2; color:#c53030; font-size:13px; font-weight:bold;">
            🔒 Bloqueado: Falta el <?= ($required_ratio*100) ?>% de anticipo verificado (Bs <?= h(bs($required_cents)) ?>)
          </div>
        <?php endif; ?>
        <?php if ($remaining !== null && $remaining > 0): ?>
          <form method="post" action="/sweetpath/admin/cash_payment.php" style="display:inline">
            <?= csrf_input() ?>
            <input type="hidden" name="order_id" value="<?= h($o['id']) ?>">
            <input type="number" name="amount_bs" step="0.01" min="0" placeholder="Registrar Bs Efectivo" style="width:140px; padding:8px; border-radius:8px; border:1px solid #ccc;" required>
            <button type="submit" style="padding:8px 12px; border-radius:8px; background:#fff; border:1px solid #ddd; font-weight:bold;">💵 Cobrar</button>
          </form>
        <?php endif; ?>
      <?php elseif ($st === 'EN_PRODUCCION' || $st === 'LISTO'): ?>
        <?php if ($st === 'EN_PRODUCCION'): ?>
          <form method="post" action="/sweetpath/admin/order_update.php" style="display:inline">
            <?= csrf_input() ?>
            <input type="hidden" name="id" value="<?= h($o['id']) ?>">
            <input type="hidden" name="status" value="LISTO">
            <button class="primary" type="submit" style="background:#2ecc71; border-color:#2ecc71">📌 Marcar LISTO</button>
          </form>
        <?php else: ?>
          <form method="post" action="/sweetpath/admin/order_update.php" style="display:inline">
            <?= csrf_input() ?>
            <input type="hidden" name="id" value="<?= h($o['id']) ?>">
            <input type="hidden" name="status" value="ENTREGADO">
            <button class="primary" type="submit" style="background:#28a745; border-color:#28a745">✅ Entregar Pedido</button>
          </form>
        <?php endif; ?>

        <?php if ($remaining !== null && $remaining > 0): ?>
          <form method="post" action="/sweetpath/admin/cash_payment.php" style="display:inline; margin-left:10px;">
            <?= csrf_input() ?>
            <input type="hidden" name="order_id" value="<?= h($o['id']) ?>">
            <input type="number" name="amount_bs" step="0.01" min="0" placeholder="Cobrar Bs" style="width:90px; padding:8px; border-radius:8px; border:1px solid #ccc;" required>
            <button type="submit" style="padding:8px 12px; border-radius:8px; background:#fff; border:1px solid #ddd; font-weight:bold;">💵 Cobrar</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>

<?php if (count($orders) === 0): ?>
  <p style="text-align:center; padding:40px; color:#666;">No se encontraron pedidos con esos filtros.</p>
<?php endif; ?>

</body>
</html>