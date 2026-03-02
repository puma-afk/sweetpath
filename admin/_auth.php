<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
  header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}

/**
 * Config
 */
const ADMIN_SESSION_TIMEOUT_SECONDS = 30 * 60; // 30 minutos

function require_admin(): void {
  // Si no hay sesión de admin -> login
  if (empty($_SESSION['admin_user'])) {
    header("Location: /sweetpath/admin/login.php?e=notlogged");
    exit;
  }

  // Timeout por inactividad
  $now = time();
  $last = $_SESSION['admin_last_activity'] ?? $now;

  if (($now - $last) > ADMIN_SESSION_TIMEOUT_SECONDS) {
    // Expira sesión
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
      $params = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"], $params["secure"], $params["httponly"]
      );
    }
    session_destroy();
    header("Location: /sweetpath/admin/login.php?e=timeout");
    exit;
  }

  // Actualiza actividad
  $_SESSION['admin_last_activity'] = $now;
}