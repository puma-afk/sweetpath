<?php
require __DIR__ . '/_auth.php';
require_admin();
require __DIR__ . '/_csrf.php';
csrf_verify_or_die();
require __DIR__ . '/../db.php';


function back(string $msg): void {
  header("Location: /sweetpath/admin/orders.php?msg=" . rawurlencode($msg));
  exit;
}

function bs_to_cents($bs): int {
  // Accepts "123.45" and converts to 12345
  $s = trim((string)$bs);
  if ($s === '') return 0;
  // normalize comma to dot
  $s = str_replace(',', '.', $s);
  // keep only digits and dot
  $s = preg_replace('/[^0-9.]/', '', $s);
  if ($s === '' || $s === '.') return 0;

  // Avoid float issues by splitting:
  $parts = explode('.', $s, 2);
  $whole = (int)($parts[0] ?: 0);
  $frac = $parts[1] ?? '0';
  $frac = substr(str_pad($frac, 2, '0'), 0, 2);
  return ($whole * 100) + (int)$frac;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) back("ID inválido");

$action = $_POST['action'] ?? '';
$status = strtoupper(trim($_POST['status'] ?? ''));

if ($action === 'approve_with_quote') {
  $total_bs = $_POST['total_bs'] ?? '';
  $total_cents = bs_to_cents($total_bs);

  if ($total_cents <= 0) back("Total inválido. Debe ser mayor a 0.");

  // Set total and approve
  $stmt = $pdo->prepare("UPDATE orders
                         SET total_final_cents = ?,
                             status = 'APROBADO_PARA_PAGO',
                             updated_at = NOW()
                         WHERE id = ?");
  $stmt->execute([$total_cents, $id]);

  back("Aprobado. Total Bs " . number_format($total_cents/100, 2, '.', '') . " (habilitado para pago)");
}

// Normal status update (fallback)
$allowed = [
  'SOLICITADO',
  'APROBADO_PARA_PAGO',
  'RECHAZADO',
  'EN_PRODUCCION',
  'LISTO',
  'ENTREGADO',
  'CANCELADO',
  'VENCIDO',
  'CREATED'
];

if (!in_array($status, $allowed, true)) back("Estado no permitido");

$stmt = $pdo->prepare("UPDATE orders SET status=?, updated_at=NOW() WHERE id=?");
$stmt->execute([$status, $id]);

back("Pedido actualizado a $status");