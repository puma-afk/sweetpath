<?php
require __DIR__ . '/db.php';
try {
    $pdo->exec("ALTER TABLE payments ADD COLUMN proof_asset_id INT NULL");
    echo "Columna proof_asset_id agregada.\n";
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE payments ADD COLUMN verified TINYINT(1) NOT NULL DEFAULT 0");
    echo "Columna verified agregada.\n";
} catch (Exception $e) {}
echo "Hecho.";
