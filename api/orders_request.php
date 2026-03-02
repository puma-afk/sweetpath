<?php
require __DIR__ . '/../db.php';
require __DIR__ . '/../lib/store_status.php';

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

function wa_link(string $phone, string $text): string {
  $phone = preg_replace('/\D+/', '', $phone);
  return "https://wa.me/{$phone}?text=" . rawurlencode($text);
}

/**
 * Create order_code like SP-20260227-1234
 */
function make_order_code(): string {
  $now = now_la_paz();
  return "SP-" . $now->format('Ymd') . "-" . random_int(1000, 9999);
}

/**
 * Simple lead time check (hours).
 * Returns [ok, message]
 */
function check_lead_time(?string $pickup_date, ?string $pickup_time, int $minLeadHours, string $tz = 'America/La_Paz'): array {
  if ($minLeadHours <= 0) return [true, ""];

  if (!$pickup_date) return [false, "Falta la fecha de recojo."];
  // time optional; default midday if not provided
  $time = $pickup_time ?: "12:00";

  $dt = DateTime::createFromFormat('Y-m-d H:i', "{$pickup_date} {$time}", new DateTimeZone($tz));
  if (!$dt) return [false, "Fecha/hora inválida."];

  $now = new DateTime('now', new DateTimeZone($tz));
  $diffSeconds = $dt->getTimestamp() - $now->getTimestamp();
  $diffHours = (int)floor($diffSeconds / 3600);

  if ($diffHours < $minLeadHours) {
    return [false, "Este tipo de pedido requiere mínimo {$minLeadHours}h de anticipación."];
  }
  return [true, ""];
}

// ---------------- MAIN ----------------
$data = json_body();

/**
 * ✅ Bloqueo por horario/pausa para CUSTOM y PACK
 */
$st = store_status($pdo);
if (!$st['is_open']) {
  respond(403, [
    "ok" => false,
    "error" => $st['reason'] ?: "CLOSED",
    "message" => $st['message'] ?: "No estamos aceptando solicitudes en este momento."
  ]);
}

/**
 * Expected payload:
 * {
 *   "type": "CUSTOM" | "PACK",
 *   "customer_name": "Joel",
 *   "customer_phone": "71234567",
 *   "pickup_date": "2026-02-28",
 *   "pickup_time": "16:00",
 *   "details": { ... }   // will be saved as JSON
 * }
 */

$type = strtoupper(trim((string)($data['type'] ?? '')));
if (!in_array($type, ['CUSTOM','PACK'], true)) {
  respond(400, ["ok"=>false, "error"=>"type debe ser CUSTOM o PACK."]);
}

$customer_name  = trim((string)($data['customer_name'] ?? ''));
$customer_phone = trim((string)($data['customer_phone'] ?? ''));
$pickup_date    = trim((string)($data['pickup_date'] ?? ''));
$pickup_time    = trim((string)($data['pickup_time'] ?? ''));
$details        = $data['details'] ?? null;

if ($customer_phone === '') {
  respond(400, ["ok"=>false, "error"=>"Falta customer_phone (WhatsApp)."]);
}
if (!is_array($details)) {
  respond(400, ["ok"=>false, "error"=>"details debe ser un objeto JSON con la información del pedido."]);
}

// Get config (whatsapp + timezone)
$cfg = $pdo->query("SELECT whatsapp_number, timezone FROM config WHERE id=1")->fetch();
$bizPhone = $cfg ? (string)$cfg['whatsapp_number'] : '';
$tz = $cfg && !empty($cfg['timezone']) ? (string)$cfg['timezone'] : 'America/La_Paz';

// Lead time policy:
// - CUSTOM: 72h
// - PACK: 24h (puedes cambiarlo)
$minLead = ($type === 'CUSTOM') ? 72 : 24;

// Validate lead time (but allow empty date if you want "to confirm date" - here we require if minLead > 0)
[$okLead, $leadMsg] = check_lead_time($pickup_date ?: null, $pickup_time ?: null, $minLead, $tz);
if (!$okLead) {
  respond(400, ["ok"=>false, "error"=>$leadMsg]);
}

$order_code = make_order_code();

try {
  $pdo->beginTransaction();

  // status starts as SOLICITADO (request). No QR, no payment.
  $stmt = $pdo->prepare("INSERT INTO orders
    (order_code, type, channel, status, customer_name, customer_phone, pickup_date, pickup_time, custom_json)
    VALUES
    (?, ?, 'WEB', 'SOLICITADO', ?, ?, NULLIF(?,''), NULLIF(?,''), ?)
  ");

  $stmt->execute([
    $order_code,
    $type,
    $customer_name ?: null,
    $customer_phone,
    $pickup_date,
    $pickup_time,
    json_encode($details, JSON_UNESCAPED_UNICODE)
  ]);

  $order_id = (int)$pdo->lastInsertId();

  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  respond(500, ["ok"=>false, "error"=>"Error guardando solicitud: " . $e->getMessage()]);
}

// Build WhatsApp message (request + confirm availability + quote)
$title = ($type === 'CUSTOM') ? "PERSONALIZADO" : "PAQUETE EVENTO";
$msg = "Hola! Quiero solicitar un pedido ({$title})\n";
$msg .= "Solicitud: {$order_code}\n";

if ($pickup_date !== '') {
  $msg .= "Fecha/Hora recojo: {$pickup_date}" . ($pickup_time ? " {$pickup_time}" : "") . "\n";
} else {
  $msg .= "Fecha/Hora recojo: (a coordinar)\n";
}

$msg .= "Datos:\n";

// Add a short summary from details (safe)
foreach ($details as $k => $v) {
  // avoid huge nested content
  if (is_array($v)) continue;
  $key = (string)$k;
  $val = (string)$v;
  if ($val === '') continue;
  $msg .= "- {$key}: {$val}\n";
}

$msg .= "\nPor favor confirmar disponibilidad y precio. (No realizaré pago hasta su aprobación)";

$wa = $bizPhone ? wa_link($bizPhone, $msg) : null;

respond(201, [
  "ok" => true,
  "order_id" => $order_id,
  "order_code" => $order_code,
  "status" => "SOLICITADO",
  "whatsapp_link" => $wa
]);