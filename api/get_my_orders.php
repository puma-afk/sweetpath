<?php
session_start();
header('Content-Type: application/json');
require_once '../db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];
$email = $_SESSION['user_email'];

// Consultamos los pedidos vinculados al ID o que coincidan con el email del cliente
$sql = "SELECT order_code, type, status, pickup_date, pickup_time, total_final_cents, created_at 
        FROM orders 
        WHERE cliente_id = ? 
        ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Formatear precios y fechas para el frontend
foreach ($orders as &$o) {
    $o['total_bs'] = $o['total_final_cents'] ? number_format($o['total_final_cents']/100, 2) : 'Por cotizar';
    $o['fecha_fmt'] = date('d/m/Y', strtotime($o['created_at']));
}

echo json_encode(['success' => true, 'orders' => $orders]);