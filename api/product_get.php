<?php
require __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $code, array $payload): void {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) respond(400, ["ok"=>false, "error"=>"BAD_ID", "message"=>"Falta id válido."]);

$sql = "
SELECT
  p.id, p.name, p.description, p.type, p.price_cents, p.availability,
  p.max_per_order, p.min_lead_hours,
  a.path_original AS image_url
FROM products p
LEFT JOIN assets a ON a.id = p.image_asset_id
WHERE p.id = ? AND p.is_active = 1
LIMIT 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$p = $stmt->fetch();

if (!$p) respond(404, ["ok"=>false, "error"=>"NOT_FOUND", "message"=>"Producto no encontrado."]);

$price = $p['price_cents'] !== null ? (int)$p['price_cents'] : null;

respond(200, [
  "ok" => true,
  "product" => [
    "id" => (int)$p['id'],
    "name" => (string)$p['name'],
    "description" => $p['description'] !== null ? (string)$p['description'] : null,
    "type" => (string)$p['type'],
    "availability" => (string)$p['availability'],
    "price_cents" => $price,
    "price_bs" => $price !== null ? number_format($price/100, 2, '.', '') : null,
    "max_per_order" => (int)$p['max_per_order'],
    "min_lead_hours" => (int)$p['min_lead_hours'],
    "image_url" => $p['image_url'] ? (string)$p['image_url'] : null,
  ]
]);