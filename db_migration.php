<?php
// db_migration.php
require __DIR__ . '/db.php';

try {
    // Verificar si la columna 'gallery' ya existe
    $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'gallery'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE products ADD COLUMN gallery TEXT NULL AFTER image_asset_id");
        echo "Columna 'gallery' agregada exitosamente ✅\n";
    } else {
        echo "La columna 'gallery' ya existe ✨\n";
    }
} catch (Exception $e) {
    echo "Error en la migración: " . $e->getMessage() . " ❌\n";
}
