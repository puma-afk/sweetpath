<?php
session_set_cookie_params(86400 * 30);
session_start();
header('Content-Type: application/json');
require_once '../db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];
$email = $_SESSION['user_email'];

// Consultamos los pedidos vinculados al ID
$sql = "SELECT id, order_code, type, status, pickup_date, pickup_time, total_final_cents, custom_json, created_at 
        FROM orders 
        WHERE cliente_id = ? 
        ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$orderIds = array_column($orders, 'id');

// Fetch payments for these orders to calculate paid amounts
$paymentsMap = [];
if (!empty($orderIds)) {
    $inPlaceholders = implode(',', array_fill(0, count($orderIds), '?'));
    $stmtPay = $pdo->prepare("SELECT order_id, amount_cents, verified FROM payments WHERE order_id IN ($inPlaceholders)");
    $stmtPay->execute($orderIds);
    $allPayments = $stmtPay->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allPayments as $p) {
        if (!isset($paymentsMap[$p['order_id']])) {
            $paymentsMap[$p['order_id']] = [
                'verified_cents' => 0,
                'pending_cents' => 0,
                'has_pending' => false
            ];
        }
        if ($p['verified']) {
            $paymentsMap[$p['order_id']]['verified_cents'] += $p['amount_cents'];
        } else {
            $paymentsMap[$p['order_id']]['pending_cents'] += $p['amount_cents'];
            $paymentsMap[$p['order_id']]['has_pending'] = true;
        }
    }
}

// Fetch QR details from config, joining assets for the actual image path
$cqr = $pdo->query("
    SELECT c.qr_account_info, a.path_original AS qr_image_path
    FROM config c
    LEFT JOIN assets a ON a.id = c.qr_asset_id
    WHERE c.id = 1
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

// Formatear precios y fechas para el frontend
foreach ($orders as &$o) {
    if ($o['total_final_cents']) {
        $o['total_bs'] = number_format($o['total_final_cents']/100, 2);
        
        $payInfo = $paymentsMap[$o['id']] ?? ['verified_cents' => 0, 'pending_cents' => 0, 'has_pending' => false];
        $o['paid_verified_bs'] = number_format($payInfo['verified_cents']/100, 2);
        $o['paid_pending_bs'] = number_format($payInfo['pending_cents']/100, 2);
        $o['has_pending_proof'] = $payInfo['has_pending'];

        $required_ratio = ($o['type'] === 'CUSTOM' || $o['type'] === 'PACK') ? 0.5 : 0.3;
        $o['min_payment_bs'] = number_format(($o['total_final_cents'] * $required_ratio) / 100, 2);
    } else {
        $o['total_bs'] = 'Por cotizar';
        $o['min_payment_bs'] = 0;
        $o['has_pending_proof'] = false;
    }
    $o['fecha_fmt'] = date('d/m/Y', strtotime($o['created_at']));

    // Extract payment_method from custom_json
    $cj = json_decode($o['custom_json'] ?? '{}', true) ?: [];
    $o['payment_method'] = strtoupper($cj['payment_method'] ?? 'QR');
    unset($o['custom_json']); // don't expose full json to frontend

    // Send QR info only if status is approved for payment and payment method is QR
    if ($o['status'] === 'APROBADO_PARA_PAGO' && $o['payment_method'] !== 'TIENDA') {
        $o['qr_info'] = is_array($cqr) ? ($cqr['qr_account_info'] ?? null) : null;
        $o['qr_image'] = is_array($cqr) ? ($cqr['qr_image_path'] ?? null) : null;
    }
}

echo json_encode(['success' => true, 'orders' => $orders]);