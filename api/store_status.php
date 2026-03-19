<?php
require __DIR__ . '/../db.php';
require __DIR__ . '/../lib/store_status.php';

header('Content-Type: application/json; charset=utf-8');

$status = store_status($pdo);
echo json_encode($status, JSON_UNESCAPED_UNICODE);
