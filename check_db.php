<?php
require 'db.php';
header('Content-Type: text/plain');
try {
    $stmt = $pdo->query("DESCRIBE orders");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columnas en 'orders':\n";
    foreach ($columns as $col) {
        echo "- $col\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
