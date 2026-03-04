<?php
require __DIR__ . '/_auth.php';
require_admin();
require __DIR__ . '/_csrf.php';
require __DIR__ . '/../db.php';

$type = strtoupper(trim($_GET['type'] ?? ''));
$status = strtoupper(trim($_GET['status'] ?? ''));

$where = [];
$params = [];

if (in_array($type, ['EXPRESS','CUSTOM','PACK'], true)) {
  $where[] = "o.type = ?";
  $params[] = $type;
}
if ($status !== '') {
  $where[] = "UPPER(o.status) = ?";
  $params[] = $status;
}

$sql = "
SELECT
  o.id, o.order_code, o.type, o.channel, o.status, o.custom_json,
  o.customer_name, o.customer_phone,
  o.pickup_date, o.pickup_time,
  o.total_final_cents,
  o.created_at,

  COALESCE(SUM(CASE WHEN p.verified=1 THEN p.amount_cents ELSE 0 END),0) AS paid_verified_cents,
  COALESCE(SUM(CASE WHEN p.verified=0 THEN p.amount_cents ELSE 0 END),0) AS paid_pending_cents

FROM orders o
LEFT JOIN payments p ON p.order_id = o.id
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
    body{font-family:system-ui,Arial;margin:16px;background:#fffaca;color:#151613}
    .bar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;align-items:center}
    .card{background:#fff;border:1px solid #ddd;border-radius:12px;padding:12px;margin:10px 0}
    .row{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
    .pill{display:inline-block;padding:4px 8px;border-radius:999px;background:#eee;font-size:12px}
    button{padding:10px 12px;border-radius:10px;border:1px solid #ccc;background:#fff;cursor:pointer}
    button.primary{background:#004f39;color:#fffaca;border-color:#004f39}
    button.danger{background:#b00020;color:#fff;border-color:#b00020}
    input,select{padding:10px;border-radius:10px;border:1px solid #ccc}
    .actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
    small{color:#666}
    .note{background:#e7f3ff;border:1px solid #b3d7ff;padding:10px;border-radius:10px;margin:10px 0}
    .moneyline{margin-top:6px;line-height:1.4}
    .muted{color:#666}
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
  <select name="type">
    <option value="">Todos los tipos</option>
    <option value="EXPRESS" <?= $type==='EXPRESS'?'selected':''; ?>>EXPRESS</option>
    <option value="CUSTOM" <?= $type==='CUSTOM'?'selected':''; ?>>CUSTOM</option>
    <option value="PACK" <?= $type==='PACK'?'selected':''; ?>>PACK</option>
  </select>

  <select name="status">
    <option value="">Todos los estados</option>
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
    $canPay = (strtoupper((string)$o['status']) === 'APROBADO_PARA_PAGO');
  ?>
  <div class="card">
    <div class="row">
      <div><b><?= h($o['order_code']) ?></b></div>
      <div class="pill"><?= h($o['type']) ?></div>
      <div class="pill"><?= h($o['status']) ?></div>
      <div class="pill"><?= h($o['channel']) ?></div>
      <?php if ($canPay): ?>
        <div class="pill">Pago habilitado ✅</div>
      <?php endif; ?>
    </div>

    <div style="margin-top:6px">
      <small>
        <?= h($o['created_at']) ?> |
        Cliente: <?= h($o['customer_name'] ?: '-') ?> |
        WhatsApp: <?= h($o['customer_phone'] ?: '-') ?>
      </small>
    </div>

    <div style="margin-top:6px">
      Recojo: <b><?= h($o['pickup_date'] ?: '-') ?></b>
      <?= $o['pickup_time'] ? ('<b>'.h($o['pickup_time']).'</b>') : '' ?>
    </div>

      Total final: <b>Bs <?= h(bs($total)) ?></b><br>
      Pagado (verificado): <b>Bs <?= h(bs($paidV)) ?></b>
      <?php if ($paidP > 0): ?>
        <span class="pill">Pendiente: Bs <?= h(bs($paidP)) ?></span>
      <?php endif; ?>
      <br>
      Falta: <b>Bs <?= $remaining !== null ? h(bs($remaining)) : '-' ?></b>
    </div>

    <?php 
    $cjson = json_decode($o['custom_json'] ?? '{}', true) ?: [];
    if (!empty($cjson)): 
    ?>
    <div class="note" style="margin-top:10px; font-size:13px">
      <b>Detalles / Mensaje:</b>
      <ul style="margin:5px 0 0 -15px;">
        <?php foreach ($cjson as $k => $v): ?>
          <?php if (is_array($v) || $v === '') continue; ?>
          <li><b><?= h(ucfirst($k)) ?>:</b> <?= nl2br(h($v)) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <div style="margin-top:10px" class="muted">
      Link de pago:
      <a href="/sweetpath/pay.php?code=<?= h($o['order_code']) ?>" target="_blank">Abrir pay.php</a>
    </div>

    <div class="actions">
      <!-- Aprobar + Cotizar -->
      <form method="post" action="/sweetpath/admin/order_update.php" style="display:inline">
        <?= csrf_input() ?>
        <input type="hidden" name="id" value="<?= h($o['id']) ?>">
        <input type="hidden" name="action" value="approve_with_quote">
        <input
          type="number"
          name="total_bs"
          step="0.01"
          min="0"
          placeholder="Total Bs"
          style="width:110px"
          required
        >
        <button class="primary" type="submit">✅ Aprobar + Cotizar</button>
      </form>

      <!-- Registrar CASH -->
      <form method="post" action="/sweetpath/admin/cash_payment.php" style="display:inline">
        <?= csrf_input() ?>
        <input type="hidden" name="order_id" value="<?= h($o['id']) ?>">
        <input type="number" name="amount_bs" step="0.01" min="0" placeholder="Efectivo Bs" style="width:120px" required>
        <button type="submit">💵 Registrar CASH</button>
      </form>

      <!-- Rechazar -->
      <form method="post" action="/sweetpath/admin/order_update.php" style="display:inline">
        <?= csrf_input() ?>
        <input type="hidden" name="id" value="<?= h($o['id']) ?>">
        <input type="hidden" name="status" value="RECHAZADO">
        <button class="danger" type="submit">❌ Rechazar</button>
      </form>

      <!-- Marcar LISTO -->
      <form method="post" action="/sweetpath/admin/order_update.php" style="display:inline">
        <?= csrf_input() ?>
        <input type="hidden" name="id" value="<?= h($o['id']) ?>">
        <input type="hidden" name="status" value="LISTO">
        <button type="submit">📌 Marcar LISTO</button>
      </form>

      <!-- Marcar EN_PRODUCCION -->
      <form method="post" action="/sweetpath/admin/order_update.php" style="display:inline">
        <?= csrf_input() ?>
        <input type="hidden" name="id" value="<?= h($o['id']) ?>">
        <input type="hidden" name="status" value="EN_PRODUCCION">
        <button type="submit">🏭 EN_PRODUCCION</button>
      </form>

      <!-- Marcar ENTREGADO -->
      <form method="post" action="/sweetpath/admin/order_update.php" style="display:inline">
        <?= csrf_input() ?>
        <input type="hidden" name="id" value="<?= h($o['id']) ?>">
        <input type="hidden" name="status" value="ENTREGADO">
        <button type="submit">✅ ENTREGADO</button>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<?php if (count($orders) === 0): ?>
  <p>No hay pedidos con esos filtros.</p>
<?php endif; ?>

</body>
</html>