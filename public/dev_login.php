<?php
// TEMPORAL - Solo para pruebas locales. ELIMINAR EN PRODUCCION.
session_set_cookie_params(86400 * 30);
session_start();

require '../db.php';

$email = $_GET['email'] ?? '';
if (!$email) { die("Falta ?email=..."); }

$stmt = $pdo->prepare("SELECT id, nombre, avatar FROM clientes WHERE email = ?");
$stmt->execute([$email]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    // Create test user
    $pdo->prepare("INSERT INTO clientes (email, nombre, google_id, avatar) VALUES (?, 'Test User', 'test123', '')")
        ->execute([$email]);
    $cliente = ['id' => $pdo->lastInsertId(), 'nombre' => 'Test User', 'avatar' => ''];
}

$_SESSION['user_id'] = $cliente['id'];
$_SESSION['user_name'] = $cliente['nombre'];
$_SESSION['user_email'] = $email;
$_SESSION['user_avatar'] = $cliente['avatar'] ?? '';

echo "Sesión iniciada como: " . htmlspecialchars($email) . " (ID: {$cliente['id']})<br>";
echo "<a href='/sweetpath/public/mis-pedidos.html'>Ir a Mis Pedidos</a> | ";
echo "<a href='/sweetpath/public/cart.html'>Ir al Carrito</a>";
