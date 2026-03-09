<?php
require __DIR__ . '/db.php';
// Show current config columns
$cols = $pdo->query("SHOW COLUMNS FROM config")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>Current config columns:\n";
foreach ($cols as $c) echo "  " . $c['Field'] . " (" . $c['Type'] . ")\n";
echo "</pre>";

// Try to add the missing QR columns
$qrCols = [
    "ALTER TABLE config ADD COLUMN qr_account_info TEXT NULL",
    "ALTER TABLE config ADD COLUMN qr_image_path VARCHAR(500) NULL",
    "ALTER TABLE config ADD COLUMN whatsapp_number VARCHAR(30) NULL",
    "ALTER TABLE config ADD COLUMN manual_pause TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE config ADD COLUMN manual_pause_until DATETIME NULL",
    "ALTER TABLE config ADD COLUMN manual_pause_message VARCHAR(255) NULL",
];
foreach ($qrCols as $sql) {
    try { $pdo->exec($sql); echo "✓ Added: $sql<br>"; }
    catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) echo "⚠ Already exists: $sql<br>";
        else echo "✗ ERROR: " . $e->getMessage() . "<br>";
    }
}

// Show result
$cols2 = $pdo->query("SHOW COLUMNS FROM config")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>AFTER - config columns:\n";
foreach ($cols2 as $c) echo "  " . $c['Field'] . " (" . $c['Type'] . ")\n";
echo "</pre>";
