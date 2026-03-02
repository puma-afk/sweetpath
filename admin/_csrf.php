<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function csrf_token(): string {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

function csrf_input(): string {
  $t = csrf_token();
  return '<input type="hidden" name="csrf_token" value="'.htmlspecialchars($t, ENT_QUOTES, 'UTF-8').'">';
}

function csrf_verify_or_die(): void {
  $sent = $_POST['csrf_token'] ?? '';
  $sess = $_SESSION['csrf_token'] ?? '';
  if (!$sent || !$sess || !hash_equals($sess, $sent)) {
    http_response_code(403);
    exit("CSRF inválido");
  }
}