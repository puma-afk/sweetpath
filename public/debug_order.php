<?php
ob_start();
require '../db.php';
require '../lib/store_status.php';
ob_end_clean();

echo "<pre>DB and store_status loaded OK\n";
$st = store_status($pdo);
echo "Store open: " . ($st['is_open'] ? 'YES' : 'NO') . "\n";
if (!$st['is_open']) echo "Reason: " . $st['reason'] . " - " . $st['message'] . "\n";

// Check config table
$cfg = $pdo->query("SELECT * FROM config WHERE id=1")->fetch(PDO::FETCH_ASSOC);
echo "\nConfig row:\n";
print_r($cfg);

// Check products table columns
$cols = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);
echo "\nProducts columns: " . implode(', ', $cols) . "\n";

// Check payments table columns
$pcols = $pdo->query("SHOW COLUMNS FROM payments")->fetchAll(PDO::FETCH_COLUMN);
echo "\nPayments columns: " . implode(', ', $pcols) . "\n";

// Check orders table columns
$ocols = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
echo "\nOrders columns: " . implode(', ', $ocols) . "\n";

// Check clientes table
$ccols = $pdo->query("SHOW COLUMNS FROM clientes")->fetchAll(PDO::FETCH_COLUMN);
echo "\nClientes columns: " . implode(', ', $ccols) . "\n";

// Sample an active express product
$p = $pdo->query("SELECT id, name, type, price_cents, availability, stock_internal, max_per_order, min_lead_hours FROM products WHERE type='EXPRESS' AND is_active=1 LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
echo "\nExpress products:\n";
print_r($p);

echo "</pre>";
