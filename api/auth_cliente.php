<?php
// api/auth_cliente.php
session_start();
require __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

function json_body() {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function respond($code, $payload) {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

$data = json_body();

$provider = $data['provider'] ?? ''; // 'google' or 'whatsapp'
$email = trim($data['email'] ?? '');
$google_id = trim($data['google_id'] ?? '');
$phone = trim($data['phone'] ?? '');
$name = trim($data['name'] ?? '');

if (!$provider) {
  respond(400, ["ok" => false, "error" => "MISSING_PROVIDER"]);
}

try {
  // LÓGICA DE UPSERT: Prevención de duplicados
  // Buscamos si ya existe el usuario por email O por teléfono
  $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? AND email != '') OR (phone = ? AND phone != '') LIMIT 1");
  $stmt->execute([$email, $phone]);
  $existing_user = $stmt->fetch();

  if ($existing_user) {
    // Si existe, hacemos FUSIÓN (UPDATE) de los datos que falten
    $user_id = $existing_user['id'];
    
    $new_email = $existing_user['email'] ? $existing_user['email'] : $email;
    $new_google_id = $existing_user['google_id'] ? $existing_user['google_id'] : $google_id;
    $new_phone = $existing_user['phone'] ? $existing_user['phone'] : $phone;
    $new_name = $existing_user['name'] ? $existing_user['name'] : $name;

    $upd = $pdo->prepare("UPDATE users SET email = ?, google_id = ?, phone = ?, name = ? WHERE id = ?");
    $upd->execute([$new_email, $new_google_id, $new_phone, $new_name, $user_id]);
    
  } else {
    // Si no existe, creamos la cuenta nueva (INSERT)
    $ins = $pdo->prepare("INSERT INTO users (email, google_id, phone, name) VALUES (?, ?, ?, ?)");
    $ins->execute([$email, $google_id, $phone, $name]);
    $user_id = $pdo->lastInsertId();
  }

  // Establecemos la sesión de forma segura
  $_SESSION['user_id'] = $user_id;

  respond(200, [
    "ok" => true,
    "message" => "Login exitoso",
    "user_id" => $user_id
  ]);

} catch (PDOException $e) {
  respond(500, ["ok" => false, "error" => "DB_ERROR", "message" => "Error al autenticar: " . $e->getMessage()]);
}
