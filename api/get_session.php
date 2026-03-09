<?php
session_set_cookie_params(86400 * 30);
session_start();
header('Content-Type: application/json');

// Devolvemos los datos que guardamos al entrar con Google
echo json_encode([
    'logged' => isset($_SESSION['user_id']),
    'id'     => $_SESSION['user_id'] ?? null,
    'name'   => $_SESSION['user_name'] ?? '',
    'email'  => $_SESSION['user_email'] ?? '',
    'avatar' => $_SESSION['user_avatar'] ?? './img/logo_white.png'
]);