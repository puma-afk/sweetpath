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

  // Validar que el pedido esté en un estado aprobable
  $cur = $pdo->prepare("SELECT status FROM orders WHERE id=? LIMIT 1");
  $cur->execute([$id]);
  $curRow = $cur->fetch();
  if (!$curRow) back("Pedido no encontrado.");

  $curStatus = strtoupper($curRow['status'] ?? '');
  $approveableFrom = ['CREATED', 'SOLICITADO'];
  if (!in_array($curStatus, $approveableFrom, true)) {
    back("No se puede aprobar: el pedido está en estado '{$curStatus}'. Solo se puede aprobar desde CREATED o SOLICITADO.");
  }

  $stmt = $pdo->prepare("UPDATE orders
                         SET total_final_cents = ?,
                             status = 'APROBADO_PARA_PAGO',
                             updated_at = NOW()
                         WHERE id = ?");
  $stmt->execute([$total_cents, $id]);

  back("Aprobado. Total Bs " . number_format($total_cents/100, 2, '.', '') . " (habilitado para pago)");
}

// Actualización de estado con máquina de estados estricta
// Mapa de transiciones válidas: estado_actual => [estados_a_los_que_puede_ir]
$transitions = [
  'CREATED'            => ['APROBADO_PARA_PAGO', 'RECHAZADO', 'CANCELADO'],
  'SOLICITADO'         => ['APROBADO_PARA_PAGO', 'RECHAZADO', 'CANCELADO'],
  'APROBADO_PARA_PAGO' => ['EN_PRODUCCION', 'RECHAZADO', 'CANCELADO'],
  'EN_PRODUCCION'      => ['LISTO', 'CANCELADO'],
  'LISTO'              => ['ENTREGADO', 'CANCELADO'],
  'ENTREGADO'          => [], // estado final — no se puede mover
  'RECHAZADO'          => [], // estado final — no se puede mover
  'CANCELADO'          => [], // estado final — no se puede mover
  'VENCIDO'            => [], // estado final — no se puede mover
];

// Obtener estado actual
$cur = $pdo->prepare("SELECT status FROM orders WHERE id=? LIMIT 1");
$cur->execute([$id]);
$curRow = $cur->fetch();
if (!$curRow) back("Pedido no encontrado.");

$currentStatus = strtoupper($curRow['status'] ?? '');
$allowedNext = $transitions[$currentStatus] ?? [];

if (!in_array($status, $allowedNext, true)) {
  if (empty($allowedNext)) {
    back("El pedido está en estado final '{$currentStatus}' y no puede cambiar de estado.");
  }
  back("Transición no permitida: '{$currentStatus}' → '{$status}'. Opciones válidas: " . implode(', ', $allowedNext));
}

// RESTORE STOCK IF CANCELLED OR REJECTED
if (in_array($status, ['RECHAZADO', 'CANCELADO'], true) && !in_array($currentStatus, ['RECHAZADO', 'CANCELADO'], true)) {
    $stmtItems = $pdo->prepare("
        SELECT oi.product_id, oi.quantity 
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ? AND p.type = 'EXPRESS' AND p.stock_internal IS NOT NULL
    ");
    $stmtItems->execute([$id]);
    $items = $stmtItems->fetchAll();

    if ($items) {
        $restoreStmt = $pdo->prepare("UPDATE products SET stock_internal = stock_internal + ? WHERE id = ?");
        foreach ($items as $it) {
            $restoreStmt->execute([$it['quantity'], $it['product_id']]);
        }
    }
}

$stmt = $pdo->prepare("UPDATE orders SET status=?, updated_at=NOW() WHERE id=?");
$stmt->execute([$status, $id]);

back("Pedido actualizado a {$status}");