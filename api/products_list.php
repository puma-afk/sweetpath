<?php
require __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $code, array $payload): void {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;
}

function clean_type(?string $t): ?string {
  $t = strtoupper(trim((string)$t));
  return in_array($t, ['EXPRESS','CUSTOM','PACK'], true) ? $t : null;
}

$q = trim($_GET['q'] ?? '');
$type = clean_type($_GET['type'] ?? '');
$limit = (int)($_GET['limit'] ?? 60);
if ($limit < 1) $limit = 60;
if ($limit > 200) $limit = 200;

$where = ["p.is_active = 1"];
$params = [];

if ($type) {
  $where[] = "p.type = ?";
  $params[] = $type;
}
if ($q !== '') {
  $where[] = "(p.name LIKE ? OR p.description LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
}

/**
 * Orden:
 * - Si NO mandas type, ponemos EXPRESS primero, luego CUSTOM, luego PACK
 * - Disponibilidad: AVAILABLE, LOW, OUT
 * - Nombre
 */
$orderType = "
CASE p.type
  WHEN 'EXPRESS' THEN 1
  WHEN 'CUSTOM'  THEN 2
  WHEN 'PACK'    THEN 3
  ELSE 9
END
";

$orderAvail = "
CASE p.availability
  WHEN 'AVAILABLE' THEN 1
  WHEN 'LOW' THEN 2
  WHEN 'OUT' THEN 3
  ELSE 9
END
";

$sql = "
SELECT
  p.id,
  p.name,
  p.description,
  p.type,
  p.price_cents,
  p.availability,
  p.max_per_order,
  p.min_lead_hours,
  a.path_original AS image_url
FROM products p
LEFT JOIN assets a ON a.id = p.image_asset_id
WHERE " . implode(" AND ", $where) . "
ORDER BY " . ($type ? $orderAvail : "$orderType, $orderAvail") . ", p.name ASC
LIMIT $limit
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Formateo amigable para frontend (sin revelar stock_internal)
$products = array_map(function($r){
  $price = $r['price_cents'] !== null ? ((int)$r['price_cents']) : null;
  return [
    "id" => (int)$r['id'],
    "name" => (string)$r['name'],
    "description" => $r['description'] !== null ? (string)$r['description'] : null,
    "type" => (string)$r['type'],
    "price_cents" => $price,
    "price_bs" => $price !== null ? number_format($price/100, 2, '.', '') : null,
    "availability" => (string)$r['availability'],
    "max_per_order" => (int)$r['max_per_order'],
    "min_lead_hours" => (int)$r['min_lead_hours'],
    "image_url" => $r['image_url'] ? '/sweetpath/' . ltrim((string)$r['image_url'], '/') : null,
  ];
}, $rows);

respond(200, [
  "ok" => true,
  "count" => count($products),
  "filters" => [
    "type" => $type,
    "q" => $q,
    "limit" => $limit
  ],
  "products" => $products
]);