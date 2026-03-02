<?php
require __DIR__ . '/db.php';

$stmt = $pdo->query("SELECT id, name, type, availability, price_cents, stock_internal FROM products ORDER BY id DESC");
$rows = $stmt->fetchAll();

header('Content-Type: application/json; charset=utf-8');
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);