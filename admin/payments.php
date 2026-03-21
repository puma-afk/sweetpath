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
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
  <title>ESENCIA · Pagos</title>
  <style>
    html, body { overflow-x: hidden; width: 100%; max-width: 100vw; }
    .admin-page-content { padding: 16px; max-width: 1000px; margin: 0 auto; padding-bottom: 40px; }
    :root {
        --primary: #004f39;
        --bg: #fffaca;
        --text: #151613;
        --accent: #ffd32a;
        --card-bg: #ffffff;
        --success: #10b981;
        --warning: #f59e0b;
    }

    body {
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        margin: 0;
        background: var(--bg);
        color: var(--text);
        line-height: 1.5;
    }

    h2 {
        color: var(--primary);
        font-weight: 800;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .card {
        background: var(--card-bg);
        border-radius: 24px;
        padding: 24px;
        margin-bottom: 25px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
        border: 1px solid rgba(0, 0, 0, 0.03);
        position: relative;
        overflow: hidden;
    }

    .order-header {
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 15px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .order-title {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--primary);
    }

    .pill {
        display: inline-flex;
        align-items: center;
        padding: 6px 14px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: #f1f5f9;
        color: #475569;
    }

    .payment-row {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 12px;
    }

    .payment-row:last-child { margin-bottom: 0; }

    .amount {
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--text);
    }

    .method-tag {
        font-size: 12px;
        color: #64748b;
        font-weight: 600;
    }

    button, .btn {
        padding: 10px 18px;
        border-radius: 12px;
        border: none;
        cursor: pointer;
        font-weight: 700;
        font-size: 13px;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }

    button.primary {
        background: var(--primary);
        color: #fffaca;
        box-shadow: 0 4px 12px rgba(0, 79, 57, 0.15);
    }
    button.primary:hover { transform: translateY(-1px); box-shadow: 0 6px 15px rgba(0, 79, 57, 0.2); }

    .btn-view {
        background: #fff;
        color: var(--primary);
        border: 1px solid #e2e8f0;
    }
    .btn-view:hover { background: #f1f5f9; border-color: #cbd5e1; }

    @media (max-width: 640px) {
        .payment-row { flex-direction: column; align-items: stretch; text-align: center; }
        .payment-row > div { display: flex; flex-direction: column; align-items: center; }
        .payment-actions { justify-content: center; width: 100%; }
        .btn, button { width: 100%; justify-content: center; }
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

<main class="admin-page-content">

<h2><i class="fas fa-money-check-alt"></i> Gestión de Pagos</h2>

<?php foreach($grouped as $order_id => $group): ?>
  <div class="card">
    <div class="order-header">
      <div>
        <h3 class="order-title">Pedido #<?= h($group['order_code']) ?></h3>
        <div style="margin-top: 5px; display: flex; gap: 8px; align-items: center;">
            <span class="pill"><?= h($group['status']) ?></span>
            <span style="font-size: 13px; color: #64748b; font-weight: 600;">Total: <b style="color: var(--text);">Bs <?= $group['total_final_cents'] ? h(bs($group['total_final_cents'])) : '-' ?></b></span>
        </div>
      </div>
      <a href="/admin/orders.php?q=<?= h($group['order_code']) ?>" class="btn btn-view">
        <i class="fas fa-eye"></i> Ver Pedido
      </a>
    </div>

    <div>
    <?php foreach($group['payments'] as $r): ?>
      <div class="payment-row">
        <div>
           <div class="amount">Bs <?= h(bs($r['amount_cents'])) ?></div>
           <div class="method-tag"><i class="fas fa-university"></i> vía <?= h($r['method']) ?></div>
           <small style="color: #94a3b8; font-size: 11px;"><?= h($r['created_at']) ?></small>
           <div style="margin-top: 8px;">
             <?= $r['verified'] ? 
                '<span class="pill" style="background:#dcfce7; color:#166534;"><i class="fas fa-check-circle"></i> VERIFICADO</span>' : 
                '<span class="pill" style="background:#fef3c7; color:#92400e;"><i class="fas fa-clock"></i> PENDIENTE</span>' ?>
           </div>
        </div>
        
        <div class="payment-actions" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
          <?php if (!empty($r['proof_url'])): ?>
            <a href="/admin/ver_comprobante.php?payment_id=<?= h($r['payment_id']) ?>" target="_blank" class="btn btn-view">
              <i class="fas fa-file-invoice-dollar"></i> Ver comprobante
            </a>
          <?php endif; ?>

          <?php if (!$r['verified']): ?>
            <form method="post" action="/admin/payment_verify.php" style="margin:0" onsubmit="return confirm('¿Confirma que el pago ingresó a su cuenta bancaria/caja?');">
               <?= csrf_input() ?>
              <input type="hidden" name="payment_id" value="<?= h($r['payment_id']) ?>">
              <button type="submit" class="primary">
                <i class="fas fa-user-check"></i> Validar Pago
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>

<?php if (empty($grouped)): ?>
  <div style="text-align:center; padding:100px 20px; color:#64748b;">
    <i class="fas fa-hand-holding-usd" style="font-size: 4rem; opacity: 0.1; display: block; margin-bottom: 20px;"></i>
    No hay pagos registrados aún.
  </div>
<?php endif; ?>

</main>
</body>
</html>

</body>
</html>