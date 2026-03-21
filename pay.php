<?php
require __DIR__ . '/db.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bs(?int $cents): string {
  if ($cents === null) return '-';
  return number_format($cents/100, 2, '.', '');
}

$code = trim($_GET['code'] ?? '');
if ($code === '') { http_response_code(400); exit("Falta ?code="); }

$stmt = $pdo->prepare("
  SELECT o.*, c.qr_asset_id
  FROM orders o
  LEFT JOIN config c ON c.id=1
  WHERE o.order_code = ?
  LIMIT 1
");
$stmt->execute([$code]);
$o = $stmt->fetch();

if (!$o) { http_response_code(404); exit("Pedido no encontrado."); }

$status = strtoupper($o['status'] ?? '');
$total = $o['total_final_cents'] !== null ? (int)$o['total_final_cents'] : null;

// Reglas de pago: MIXED paga 100%, CUSTOM/PACK adelanto del 30%
$min_adv = null;
if ($total !== null) {
  if (in_array(strtoupper($o['type']), ['CUSTOM', 'PACK'])) {
    $min_adv = (int)floor($total * 0.3);
  } else {
    // EXPRESS or MIXED (carrito) -> Pago Completo
    $min_adv = $total;
  }
}

// Load QR asset (if configured)
$qrUrl = null;
if (!empty($o['qr_asset_id'])) {
  $qr = $pdo->prepare("SELECT path_original FROM assets WHERE id=? LIMIT 1");
  $qr->execute([(int)$o['qr_asset_id']]);
  $qrRow = $qr->fetch();
  if ($qrRow && !empty($qrRow['path_original'])) {
    $qrUrl = $qrRow['path_original']; // should be a web path like /storage/uploads/qr.webp
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Pagar <?= h($code) ?></title>
  <style>
    body{font-family:system-ui,Arial;margin:16px;background:#fffaca;color:#151613}
    .card{background:#fff;border:1px solid #ddd;border-radius:14px;padding:14px;max-width:560px}
    .muted{color:#666}
    .warn{background:#fff3cd;border:1px solid #ffeeba;padding:10px;border-radius:10px}
    input{padding:10px;border-radius:10px;border:1px solid #ccc;width:100%}
    button{padding:12px 14px;border-radius:10px;border:1px solid #004f39;background:#004f39;color:#fffaca;cursor:pointer;width:100%}
    img{max-width:100%;border-radius:12px;border:1px solid #ddd}
    .row{display:flex;gap:10px;flex-wrap:wrap}
    .half{flex:1;min-width:180px}
  </style>
</head>
<body>

<div class="card">
  <h2>💳 Pago de adelanto</h2>
  <p class="muted">Pedido: <b><?= h($o['order_code']) ?></b> (<?= h($o['type']) ?>)</p>

  <?php if ($status !== 'APROBADO_PARA_PAGO'): ?>
    <div class="warn">
      <b>Aún no está habilitado para pago.</b><br>
      Estado actual: <b><?= h($status) ?></b><br>
      Te confirmaremos por WhatsApp cuando esté aprobado ✅
    </div>
  <?php else: ?>
    <p>Total a Pagar: <b>Bs <?= h(bs($total)) ?></b></p>
    <?php if (in_array(strtoupper($o['type']), ['CUSTOM', 'PACK'])): ?>
      <p style="color:var(--danger);font-weight:bold;">Adelanto sugerido (Mínimo 30%): Bs <?= h(bs($min_adv)) ?></p>
      <p class="muted" style="font-size:14px">El 70% restante se paga al contra-entrega (efectivo o transferencia al retirar).</p>
    <?php else: ?>
      <p style="color:var(--danger);font-weight:bold;">Total (Pago Completo 100%): Bs <?= h(bs($min_adv)) ?></p>
      <p class="muted" style="font-size:14px">Por favor cancela el monto total para confirmar tu pedido en vitrina o packs.</p>
    <?php endif; ?>
    <p class="muted">Tip: en el concepto del pago escribe tu código <b><?= h($o['order_code']) ?></b>.</p>

    <hr>

    <h3>1) Escanea el QR</h3>
    <?php if ($qrUrl): ?>
      <img src="<?= h($qrUrl) ?>" alt="QR de pago">
    <?php else: ?>
      <div class="warn">
        No hay QR configurado todavía. (Admin debe subir QR y asignarlo en config).
      </div>
    <?php endif; ?>

    <hr>

    <h3>2) Sube tu comprobante</h3>

    <form action="/api/payment_submit.php" method="post" enctype="multipart/form-data">
      <input type="hidden" name="order_code" value="<?= h($o['order_code']) ?>">

      <div class="row">
        <div class="half">
          <label class="muted">Monto pagado (Bs)</label>
          <input name="amount_bs" type="number" step="0.01" min="0" required placeholder="Ej: 100.00">
        </div>
        <div class="half">
          <label class="muted">Comprobante (imagen)</label>
          <input name="proof" type="file" accept="image/*" required>
        </div>
      </div>

      <p class="muted" style="margin-top:10px">
        Tu comprobante será revisado antes de confirmar el pago ✅
      </p>

      <button type="submit">Subir comprobante</button>
    </form>
  <?php endif; ?>
</div>

</body>
</html>