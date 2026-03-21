<?php
require __DIR__ . '/_auth.php';
require_admin();
require __DIR__ . '/_csrf.php';
csrf_verify_or_die();
require __DIR__ . '/../db.php';


function back($msg){
  header("Location: /admin/orders.php?msg=" . rawurlencode($msg));
  exit;
}

function bs_to_cents($bs): int {
  $s = trim((string)$bs);
  $s = str_replace(',', '.', $s);
  $s = preg_replace('/[^0-9.]/', '', $s);
  if ($s === '' || $s === '.') return 0;
  $parts = explode('.', $s, 2);
  $whole = (int)($parts[0] ?: 0);
  $frac = $parts[1] ?? '0';
  $frac = substr(str_pad($frac, 2, '0'), 0, 2);
  return ($whole * 100) + (int)$frac;
}

$order_id = (int)($_POST['order_id'] ?? 0);
$amount_bs = $_POST['amount_bs'] ?? '';

if ($order_id <= 0) back("order_id inválido");
$amount_cents = bs_to_cents($amount_bs);
if ($amount_cents <= 0) back("Monto inválido");

$o = $pdo->prepare("SELECT id, status FROM orders WHERE id=? LIMIT 1");
$o->execute([$order_id]);
$order = $o->fetch();
if (!$order) back("Pedido no encontrado");

// Solo se puede registrar pago en efectivo en estados activos/pendientes
$payableStatuses = ['CREATED', 'SOLICITADO', 'APROBADO_PARA_PAGO', 'EN_PRODUCCION', 'LISTO'];
$curStatus = strtoupper($order['status'] ?? '');
if (!in_array($curStatus, $payableStatuses, true)) {
  back("No se puede registrar pago: el pedido está en estado '{$curStatus}'.");
}

$ins = $pdo->prepare("INSERT INTO payments (order_id, method, amount_cents, proof_asset_id, verified)
                      VALUES (?, 'CASH', ?, NULL, 1)");
$ins->execute([$order_id, $amount_cents]);

back("Pago en efectivo registrado ✅");