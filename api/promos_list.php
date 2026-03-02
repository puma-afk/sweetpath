<?php
require __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $code, array $payload): void {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;
}

// Usar timezone de config (si existe)
$cfg = $pdo->query("SELECT timezone FROM config WHERE id=1")->fetch();
$tz = $cfg && !empty($cfg['timezone']) ? (string)$cfg['timezone'] : 'America/La_Paz';

$now = new DateTime('now', new DateTimeZone($tz));
$nowStr = $now->format('Y-m-d H:i:s');

$sql = "
SELECT
  p.id,
  p.title,
  p.start_at,
  p.end_at,
  p.priority,
  a.path_original AS image_url
FROM promos p
JOIN assets a ON a.id = p.asset_id
WHERE
  p.is_active = 1
  AND (p.start_at IS NULL OR p.start_at <= ?)
  AND (p.end_at IS NULL OR p.end_at >= ?)
ORDER BY p.priority ASC, p.created_at DESC
LIMIT 20
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$nowStr, $nowStr]);
$rows = $stmt->fetchAll();

respond(200, [
  "ok" => true,
  "timezone" => $tz,
  "now" => $nowStr,
  "count" => count($rows),
  "promos" => $rows
]);