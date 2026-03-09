<?php
require __DIR__ . '/db.php';
$errors = [];
$ok = [];

$patches = [
    "ALTER TABLE config ADD COLUMN qr_account_info TEXT NULL",
    "ALTER TABLE config ADD COLUMN qr_image_path VARCHAR(500) NULL",
    "ALTER TABLE config ADD COLUMN whatsapp_number VARCHAR(30) NULL",
    "ALTER TABLE config ADD COLUMN manual_pause TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE config ADD COLUMN manual_pause_until DATETIME NULL",
    "ALTER TABLE config ADD COLUMN manual_pause_message VARCHAR(255) NULL",
    "ALTER TABLE config ADD COLUMN business_hours_start VARCHAR(10) NULL DEFAULT '08:00:00'",
    "ALTER TABLE config ADD COLUMN business_hours_end VARCHAR(10) NULL DEFAULT '20:00:00'",
    "ALTER TABLE config ADD COLUMN timezone VARCHAR(50) NULL DEFAULT 'America/La_Paz'",
    "ALTER TABLE payments ADD COLUMN proof_asset_id INT NULL",
    "ALTER TABLE payments ADD COLUMN verified TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE payments ADD COLUMN method VARCHAR(30) NULL DEFAULT 'QR'",
    "ALTER TABLE payments ADD COLUMN reference_id VARCHAR(100) NULL",
    "ALTER TABLE orders ADD COLUMN cliente_id INT NULL",
    "ALTER TABLE orders ADD COLUMN total_final_cents INT NULL",
];

foreach ($patches as $sql) {
    try {
        $pdo->exec($sql);
        $ok[] = $sql;
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $ok[] = "(already exists) $sql";
        } else {
            $errors[] = $e->getMessage() . " | SQL: $sql";
        }
    }
}

// Make sure config row 1 exists
$pdo->exec("INSERT IGNORE INTO config (id) VALUES (1)");

echo "<pre>--- OK ---\n";
foreach ($ok as $s) echo "✓ $s\n";
echo "\n--- ERRORS ---\n";
foreach ($errors as $s) echo "✗ $s\n";
echo "</pre>";
echo "<br><a href='/sweetpath/public/dev_login.php?email=test@test.com'>Ir a Test Login</a>";
