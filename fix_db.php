<?php
require 'db.php';
try {
    // Intentar agregar la columna
    $pdo->exec("ALTER TABLE orders ADD COLUMN image_ref_asset_id INT NULL AFTER custom_json");
    echo "Columna 'image_ref_asset_id' agregada con éxito.\n";
    
    // También verificar si la tabla assets existe, por si acaso
    $pdo->exec("CREATE TABLE IF NOT EXISTS assets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(50) NOT NULL,
        path_original TEXT NOT NULL,
        path_medium TEXT NULL,
        path_thumb TEXT NULL,
        mime VARCHAR(100) NULL,
        size_bytes BIGINT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Tabla 'assets' verificada/creada.\n";

} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "La columna ya existía.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
