<?php
session_set_cookie_params(86400 * 30);
session_start();
header('Content-Type: application/json');
require_once '../db.php'; // Tu conexión PDO

$data = json_decode(file_get_contents('php://input'), true);
$id_token = $data['token'] ?? '';

if (!$id_token) {
    echo json_encode(['success' => false, 'error' => 'Token no recibido']);
    exit;
}

// Validar con Google (sin librerías externas)
$url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $id_token;
$response = @file_get_contents($url);
$google_user = json_decode($response, true);

if (isset($google_user['error'])) {
    echo json_encode(['success' => false, 'error' => 'Token inválido']);
    exit;
}

$google_id = $google_user['sub'];
$email = $google_user['email'];
$nombre = $google_user['name'];
$avatar = $google_user['picture'];

try {
    // Buscar si ya existe el cliente
    $stmt = $pdo->prepare("SELECT id, telefono FROM clientes WHERE google_id = ? OR email = ?");
    $stmt->execute([$google_id, $email]);
    $cliente = $stmt->fetch();

    if (!$cliente) {
        // Registrar cliente nuevo
        $stmt = $pdo->prepare("INSERT INTO clientes (google_id, nombre, email, avatar) VALUES (?, ?, ?, ?)");
        $stmt->execute([$google_id, $nombre, $email, $avatar]);
        $cliente_id = $pdo->lastInsertId();
    } else {
        $cliente_id = $cliente['id'];
        // Actualizar datos por si cambiaron en Google
        $stmt = $pdo->prepare("UPDATE clientes SET google_id = ?, avatar = ? WHERE id = ?");
        $stmt->execute([$google_id, $avatar, $cliente_id]);
    }

    // Guardar sesión del CLIENTE (diferente a admin_users)
    $_SESSION['user_id'] = $cliente_id;
    $_SESSION['user_name'] = $nombre;
    $_SESSION['user_email'] = $email;

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error en la base de datos']);
}