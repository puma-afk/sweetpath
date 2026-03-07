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

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bs($c){ return number_format(((int)$c)/100, 2, '.', ''); }
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
    a:hover{text-decoration: underline;}
  </style>
</head>
<?php require __DIR__ . '/_navbar.php'; ?>

<h2>💰 ESENCIA — Pagos</h2>

<?php foreach($rows as $r): ?>
  <div class="card">
    <b><?= h($r['order_code']) ?></b>
    <span class="pill"><?= h($r['method']) ?></span>
    <span class="pill"><?= $r['verified'] ? 'VERIFICADO' : 'PENDIENTE' ?></span>
    <span class="pill"><?= h(strtoupper($r['status'])) ?></span>
    <div style="margin-top:6px">
      Monto: <b>Bs <?= h(bs($r['amount_cents'])) ?></b> |
      Total pedido: <b>Bs <?= $r['total_final_cents'] ? h(bs($r['total_final_cents'])) : '-' ?></b>
    </div>

    <?php if (!empty($r['proof_url'])): ?>
      <div style="margin-top:8px">
        <a href="/sweetpath/admin/ver_comprobante.php?payment_id=<?= h($r['payment_id']) ?>" target="_blank">📎 Ver comprobante</a>
      </div>
    <?php endif; ?>

    <?php if (!$r['verified']): ?>
      <form method="post" action="/sweetpath/admin/payment_verify.php" style="margin-top:10px">
         <?= csrf_input() ?>
        <input type="hidden" name="payment_id" value="<?= h($r['payment_id']) ?>">
        <button type="submit">✅ Verificar pago</button>
      </form>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

</body>
</html>