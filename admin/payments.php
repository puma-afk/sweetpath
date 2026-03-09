<?php
require __DIR__ . '/_auth.php';
require_admin();
require __DIR__ . '/_csrf.php';
require __DIR__ . '/../db.php';


$rows = $pdo->query("
  SELECT p.id AS payment_id, p.order_id, p.amount_cents, p.method, p.verified, p.created_at,
         o.order_code, o.total_final_cents, o.status,
         a.path_original AS proof_url
  FROM payments p
  JOIN orders o ON o.id = p.order_id
  LEFT JOIN assets a ON a.id = p.proof_asset_id
  ORDER BY p.created_at DESC
  LIMIT 200
")->fetchAll();

$grouped = [];
foreach ($rows as $r) {
    if (!isset($grouped[$r['order_id']])) {
        $grouped[$r['order_id']] = [
            'order_code' => $r['order_code'],
            'total_final_cents' => $r['total_final_cents'],
            'status' => $r['status'],
            'payments' => []
        ];
    }
    $grouped[$r['order_id']]['payments'][] = $r;
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bs($c) { return number_format((int)$c / 100, 2, '.', ''); }

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>ESENCIA · Pagos</title>
  <style>
    body{font-family:system-ui,Arial,sans-serif;margin:16px;background:#fffaca;color:#151613}
    .card{background:#fff;border:1px solid #ddd;border-radius:18px;padding:20px;margin:15px 0;box-shadow: 0 4px 12px rgba(0,0,0,0.05);}
    .pill{display:inline-block;padding:5px 12px;border-radius:999px;background:#eee;font-size:12px; font-weight: 600;}
    button{padding:10px 16px;border-radius:12px;border:1px solid #ccc;background:#fff;cursor:pointer; font-weight:600; transition: 0.2s; color: #151613;}
    button:hover{filter: brightness(0.92); transform: translateY(-1px);}
    button.primary, button[type="submit"]{background:#004f39;color:#fffaca;border-color:#004f39; box-shadow: 0 4px 10px rgba(0,79,57,0.2);}
    a{color:#004f39; font-weight: 600; text-decoration: none;}
    a.btn{display:inline-block; padding:8px 14px; border-radius:10px; border:1px solid #ccc; font-size:13px; color:#333; transition:0.2s; background:#fffoca;}
    a.btn:hover{background:#eee; text-decoration:none;}
  </style>
</head>
<body>
<?php require __DIR__ . '/_navbar.php'; ?>

<h2>💰 ESENCIA — Pagos</h2>

<?php foreach($grouped as $order_id => $group): ?>
  <div class="card" style="margin-bottom: 25px; border-left: 6px solid var(--admin-primary)">
    <div style="border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
      <div>
        <h3 style="margin:0 0 5px 0">Pedido <?= h($group['order_code']) ?></h3>
        <span class="pill" style="font-size: 11px"><?= h(strtoupper($group['status'])) ?></span>
        <span style="margin-left: 10px; font-size: 14px">Total Pedido: <b>Bs <?= $group['total_final_cents'] ? h(bs($group['total_final_cents'])) : '-' ?></b></span>
      </div>
      <a href="/sweetpath/admin/orders.php?q=<?= h($group['order_code']) ?>" class="btn">👁️ Ver Pedido</a>
    </div>

    <div style="display: grid; gap: 15px;">
    <?php foreach($group['payments'] as $r): ?>
      <div style="background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
           <b style="font-size: 16px;">Bs <?= h(bs($r['amount_cents'])) ?></b> <small style="color: #666">vía <?= h($r['method']) ?></small><br>
           <small style="color: #888"><?= h($r['created_at']) ?></small>
           <div style="margin-top: 5px;">
             <?= $r['verified'] ? '<span class="pill" style="background:#dcfce7; color:#166534">✅ VERIFICADO</span>' : '<span class="pill" style="background:#fef3c7; color:#92400e">⏳ PENDIENTE</span>' ?>
           </div>
        </div>
        
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
          <?php if (!empty($r['proof_url'])): ?>
            <a href="/sweetpath/admin/ver_comprobante.php?payment_id=<?= h($r['payment_id']) ?>" target="_blank" class="btn">📸 Ver comprobante</a>
          <?php endif; ?>

          <?php if (!$r['verified']): ?>
            <form method="post" action="/sweetpath/admin/payment_verify.php" style="margin:0" onsubmit="return confirm('¿Confirma que el pago ingresó a su cuenta bancaria/caja?');">
               <?= csrf_input() ?>
              <input type="hidden" name="payment_id" value="<?= h($r['payment_id']) ?>">
              <button type="submit" style="padding: 8px 12px; font-size: 13px; margin:0;" class="primary">✅ Validar Pago</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>

<?php if (empty($grouped)): ?>
  <div class="card"><p style="text-align:center; color:#666;">No hay pagos registrados.</p></div>
<?php endif; ?>

</body>
</html>