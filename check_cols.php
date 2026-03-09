<?php
require 'db.php';
$stmt = $pdo->query('SHOW COLUMNS FROM orders');
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($cols as $c) echo $c['Field']."\n";
