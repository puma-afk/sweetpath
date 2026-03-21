<?php
require __DIR__ . '/_auth.php';
require_admin();
require __DIR__ . '/_csrf.php';
csrf_verify_or_die();
require __DIR__ . '/../db.php';


function back($msg){
  header("Location: /admin/orders.php?msg=" . rawurlencode($msg));
  exit;
}

$payment_id = (int)($_POST['payment_id'] ?? 0);
if ($payment_id <= 0) back("payment_id inválido");

$row = $pdo->prepare("SELECT id, verified FROM payments WHERE id=? LIMIT 1");
$row->execute([$payment_id]);
$p = $row->fetch();

if (!$p) back("Pago no encontrado");
if ((int)$p['verified'] === 1) back("Ya estaba verificado");

$u = $pdo->prepare("UPDATE payments SET verified=1 WHERE id=?");
$u->execute([$payment_id]);

back("Pago verificado ✅ (la dueña decide cuándo pasar a producción)");