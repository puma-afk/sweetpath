<?php
// Session must be configured before any output or require
session_set_cookie_params(86400 * 30);
session_start();

ob_start(); // Buffer all output to prevent accidental corruption of JSON
require __DIR__ . '/../db.php';
require __DIR__ . '/../lib/store_status.php';

$cliente_id = $_SESSION['user_id'] ?? null; // Captura el ID de la sesión de Google

ob_end_clean(); // Discard any warnings from requires so JSON stays clean
header('Content-Type: application/json; charset=utf-8');

function json_body(): array {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function respond(int $code, array $payload): void {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;
}

function now_la_paz(): DateTime {
  return new DateTime('now', new DateTimeZone('America/La_Paz'));
}

function format_bs(?int $cents): string {
  if ($cents === null) return "-";
  $bs = $cents / 100.0;
  return number_format($bs, 2, '.', '');
}

function wa_link(string $phone, string $text): string {
  $phone = preg_replace('/\D+/', '', $phone);
  return "https://wa.me/{$phone}?text=" . rawurlencode($text);
}

// --------------- MAIN ---------------
$data = json_body();

$type = strtoupper(trim($data['type'] ?? ''));
if ($type !== 'EXPRESS' && $type !== 'MIXED') {
  respond(400, ["ok"=>false, "error"=>"BAD_TYPE", "message"=>"Este endpoint solo crea pedidos EXPRESS o Mixtos."]);
}

/**
 * ✅ Bloqueo por horario/pausa SOLO para EXPRESS / MIXTOS rápidos
 */
$st = store_status($pdo);
if (!$st['is_open']) {
  respond(403, [
    "ok" => false,
    "error" => $st['reason'] ?: "CLOSED",
    "message" => $st['message'] ?: "No estamos aceptando pedidos en este momento."
  ]);
}

$items = $data['items'] ?? [];
if (!is_array($items) || count($items) === 0) {
  respond(400, ["ok"=>false, "error"=>"EMPTY_CART", "message"=>"Carrito vacío."]);
}

$customer_name  = trim((string)($data['customer_name'] ?? ''));
$customer_phone = trim((string)($data['customer_phone'] ?? ''));
$pickup_date    = trim((string)($data['pickup_date'] ?? ''));
$pickup_time    = trim((string)($data['pickup_time'] ?? ''));
$customer_note  = trim((string)($data['customer_note'] ?? ''));
$payment_method = strtoupper(trim((string)($data['payment_method'] ?? 'QR')));

if ($customer_phone === '') {
  respond(400, ["ok"=>false, "error"=>"MISSING_PHONE", "message"=>"Falta customer_phone (WhatsApp)."]);
}

// Build validated items list & fetch product data
$productIds = [];
$qtyMap = [];
foreach ($items as $it) {
  $pid = (int)($it['product_id'] ?? 0);
  $qty = (int)($it['qty'] ?? 0);
  if ($pid <= 0 || $qty <= 0) continue;
  $productIds[] = $pid;
  $qtyMap[$pid] = ($qtyMap[$pid] ?? 0) + $qty;
}

$productIds = array_values(array_unique($productIds));
if (count($productIds) === 0) {
  respond(400, ["ok"=>false, "error"=>"INVALID_ITEMS", "message"=>"Items inválidos."]);
}

$in = implode(',', array_fill(0, count($productIds), '?'));
$stmt = $pdo->prepare("SELECT id, name, type, availability, stock_internal, max_per_order, price_cents, min_lead_hours
                       FROM products
                       WHERE id IN ($in) AND is_active=1");
$stmt->execute($productIds);
$products = $stmt->fetchAll();

if (count($products) !== count($productIds)) {
  respond(400, ["ok"=>false, "error"=>"PRODUCT_NOT_FOUND", "message"=>"Uno o más productos no existen o están inactivos."]);
}

// Validate product rules
$lines = [];
$total_est_cents = 0;
$maxLeadHoursNeeded = 0;

foreach ($products as $p) {
  if ($p['type'] !== 'EXPRESS' && $p['type'] !== 'PACK') {
    respond(400, ["ok"=>false, "error"=>"NOT_EXPRESS_NOR_PACK", "message"=>"Producto {$p['id']} no se puede agregar al carrito rápido (es {$p['type']})."]);
  }
  if ($p['availability'] === 'OUT') {
    respond(400, ["ok"=>false, "error"=>"OUT_OF_STOCK", "message"=>"El producto '{$p['name']}' está agotado."]);
  }

  $pid = (int)$p['id'];
  $qty = (int)($qtyMap[$pid] ?? 0);

  $maxPer = (int)$p['max_per_order'];
  if ($qty > $maxPer) {
    respond(400, ["ok"=>false, "error"=>"LIMIT_PER_ORDER", "message"=>"Cantidad no disponible para '{$p['name']}'. (Límite por pedido)"]);
  }

  if ($p['type'] === 'EXPRESS') {
      if ($p['stock_internal'] !== null) {
        $stock = (int)$p['stock_internal'];
        if ($qty > $stock) {
          respond(400, ["ok"=>false, "error"=>"INSUFFICIENT_STOCK", "message"=>"Cantidad no disponible para '{$p['name']}'. Reduce la cantidad y confirmamos por WhatsApp."]);
        }
      }
  }

  // Find maximum lead time in hours needed for the order
  if ($p['min_lead_hours'] !== null) {
      $maxLeadHoursNeeded = max($maxLeadHoursNeeded, (int)$p['min_lead_hours']);
  }

  $unit = $p['price_cents'] !== null ? (int)$p['price_cents'] : null;
  if ($unit !== null) $total_est_cents += ($unit * $qty);

  $lines[] = [
    "id" => $pid,
    "name" => $p['name'],
    "qty" => $qty,
    "unit_price_cents" => $unit,
    "is_express_with_stock" => ($p['type'] === 'EXPRESS' && $p['stock_internal'] !== null)
  ];
}

// Validate Lead Time based on Max Lead Time found in Cart
if ($maxLeadHoursNeeded > 0) {
    // We expect pickup_time in format like "Mañana (10:00 - 13:00)"
    // It's a bit tricky to parse exact time. Let's assume start of range or midday.
    $time_str = "12:00"; 
    if (strpos($pickup_time, "10:00") !== false) $time_str = "10:00";
    if (strpos($pickup_time, "14:00") !== false) $time_str = "14:00";

    $dt = DateTime::createFromFormat('Y-m-d H:i', "{$pickup_date} {$time_str}", new DateTimeZone('America/La_Paz'));
    if (!$dt) {
        respond(400, ["ok"=>false, "error"=>"BAD_DATE", "message"=>"Fecha/hora inválida."]);
    }

    $now = now_la_paz();
    $diffHours = (int)floor(($dt->getTimestamp() - $now->getTimestamp()) / 3600);

    if ($diffHours < $maxLeadHoursNeeded) {
        respond(400, ["ok"=>false, "error"=>"LEAD_TIME", "message"=>"Por los productos en tu carrito, necesitamos mínimo {$maxLeadHoursNeeded}h de anticipación para prepararlos."]);
    }
}

// Define if we should auto-approve this order
$is_all_express = ($type === 'EXPRESS');
foreach ($products as $p) {
  if ($p['type'] !== 'EXPRESS' || $p['price_cents'] === null) {
      $is_all_express = false;
  }
}

// Create order_code
$now = now_la_paz();
$order_code = "SP-" . $now->format('Ymd') . "-" . random_int(1000, 9999);

$db_type = $is_all_express ? 'EXPRESS' : 'MIXED';
$db_status = $is_all_express ? 'APROBADO_PARA_PAGO' : 'CREATED';
$db_total = $is_all_express ? $total_est_cents : null;

// Save order + items in transaction
try {
  $pdo->beginTransaction();

  $customJson = [];
  if ($customer_note !== '') $customJson['note'] = $customer_note;
  if ($payment_method !== '') $customJson['payment_method'] = $payment_method;
  $jsonStr = empty($customJson) ? null : json_encode($customJson, JSON_UNESCAPED_UNICODE);

  $o = $pdo->prepare("INSERT INTO orders (order_code, type, channel, status, customer_name, customer_phone, pickup_date, pickup_time, custom_json, cliente_id, total_final_cents)
                    VALUES (?, ?, 'WEB', ?, ?, ?, NULLIF(?,''), NULLIF(?,''), ?, ?, ?)");
  $o->execute([$order_code, $db_type, $db_status, $customer_name ?: null, $customer_phone, $pickup_date, $pickup_time, $jsonStr, $cliente_id, $db_total]);
  $order_id = (int)$pdo->lastInsertId();

  $itStmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price_cents)
                           VALUES (?, ?, ?, ?)");
  $stockStmt = $pdo->prepare("UPDATE products SET stock_internal = stock_internal - ? WHERE id = ? AND stock_internal IS NOT NULL");
  
  foreach ($lines as $ln) {
    $itStmt->execute([$order_id, $ln['id'], $ln['qty'], $ln['unit_price_cents']]);
    if ($ln['is_express_with_stock']) {
      $stockStmt->execute([$ln['qty'], $ln['id']]);
    }
  }

  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  respond(500, ["ok"=>false, "error"=>"DB_ERROR", "message"=>"Error guardando pedido."]);
}

// Build WhatsApp message
$cfg = $pdo->query("SELECT whatsapp_number FROM config WHERE id=1")->fetch();
$bizPhone = $cfg ? (string)$cfg['whatsapp_number'] : '';

$msg = "Hola! Quiero confirmar un pedido ({$db_type})\n";
$msg .= "Pedido: {$order_code}\n";
if ($pickup_date !== '') $msg .= "Recojo: {$pickup_date}" . ($pickup_time ? " ({$pickup_time})" : "") . "\n";
if ($customer_note !== '') $msg .= "Nota: {$customer_note}\n";
$msg .= "Método Pago: " . ($payment_method === 'TIENDA' ? 'En Tienda (Reserva 4h)' : 'QR') . "\n";
$msg .= "Items:\n";
foreach ($lines as $ln) {
  $msg .= "- {$ln['qty']} x {$ln['name']}";
  if ($ln['unit_price_cents'] !== null) $msg .= " (Bs " . format_bs($ln['unit_price_cents']) . ")";
  $msg .= "\n";
}
if ($total_est_cents > 0) {
  $msg .= "Total: Bs " . format_bs($total_est_cents) . "\n";
}
if (!$is_all_express) {
    $msg .= "\nPor favor confirmar cotización y disponibilidad antes del pago. Gracias!";
} else {
    $msg .= "\nPedido generado vía web, procediendo al pago.";
}

$wa = $bizPhone ? wa_link($bizPhone, $msg) : null;

// QR logic if auto_approved
$qr_account = null;
$qr_image = null;
if ($is_all_express && $payment_method !== 'TIENDA') {
    $cqr = $pdo->query("SELECT qr_account_info, qr_image_path FROM config WHERE id=1")->fetch();
    if ($cqr && $cqr['qr_image_path']) {
        $qr_account = $cqr['qr_account_info'];
        $qr_image = $cqr['qr_image_path'];
    }
}

respond(201, [
  "ok" => true,
  "order_id" => $order_id,
  "order_code" => $order_code,
  "whatsapp_link" => $wa,
  "total_estimated_cents" => $total_est_cents,
  "status" => $db_status,
  "qr_account" => $qr_account,
  "qr_image" => $qr_image,
  "note" => $is_all_express ? "Pedido auto-aprobado." : "No se muestra QR hasta confirmación."
]);