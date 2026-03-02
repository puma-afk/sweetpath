<?php

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);

require __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) session_start();


$error = '';
$info = '';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function get_ip(): string {
  // En localhost esto será 127.0.0.1
  return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

const MAX_ATTEMPTS = 5;
const LOCK_MINUTES = 10;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  $ip = get_ip();

  if ($username === '' || $password === '') {
    $error = "Completa usuario y contraseña.";
  } else {
    // 1) Revisar bloqueo
    $st = $pdo->prepare("SELECT attempts, locked_until
                         FROM login_attempts
                         WHERE ip=? AND username=? LIMIT 1");
    $st->execute([$ip, $username]);
    $attemptRow = $st->fetch();

    if ($attemptRow && !empty($attemptRow['locked_until'])) {
      $lockedUntil = strtotime($attemptRow['locked_until']);
      if ($lockedUntil !== false && time() < $lockedUntil) {
        $minsLeft = (int)ceil(($lockedUntil - time()) / 60);
        $error = "Demasiados intentos. Intenta de nuevo en {$minsLeft} minuto(s).";
      }
    }

    if ($error === '') {
      // 2) Buscar usuario admin
      $stmt = $pdo->prepare("SELECT id, username, password_hash, is_active
                             FROM admin_users
                             WHERE username = ?
                             LIMIT 1");
      $stmt->execute([$username]);
      $u = $stmt->fetch();

      $ok = false;
      if ($u && (int)$u['is_active'] === 1) {
        $ok = password_verify($password, $u['password_hash']);
      }

      if ($ok) {
        // Reset intentos
        $pdo->prepare("DELETE FROM login_attempts WHERE ip=? AND username=?")->execute([$ip, $username]);

        session_regenerate_id(true);
        $_SESSION['admin_user'] = [
          'id' => (int)$u['id'],
          'username' => (string)$u['username']
        ];
        $_SESSION['admin_last_activity'] = time();

        header("Location: /sweetpath/admin/orders.php");
        exit;
      } else {
        // 3) Incrementar intentos + lock si excede
        if (!$attemptRow) {
          $pdo->prepare("INSERT INTO login_attempts (ip, username, attempts, locked_until)
                         VALUES (?, ?, 1, NULL)")
              ->execute([$ip, $username]);
          $error = "Usuario o contraseña incorrectos. (Intento 1/" . MAX_ATTEMPTS . ")";
        } else {
          $attempts = (int)$attemptRow['attempts'] + 1;

          if ($attempts >= MAX_ATTEMPTS) {
            $lockUntil = (new DateTime())->modify("+" . LOCK_MINUTES . " minutes")->format("Y-m-d H:i:s");
            $pdo->prepare("UPDATE login_attempts SET attempts=?, locked_until=? WHERE ip=? AND username=?")
                ->execute([$attempts, $lockUntil, $ip, $username]);
            $error = "Demasiados intentos. Bloqueado por " . LOCK_MINUTES . " minutos.";
          } else {
            $pdo->prepare("UPDATE login_attempts SET attempts=?, locked_until=NULL WHERE ip=? AND username=?")
                ->execute([$attempts, $ip, $username]);
            $error = "Usuario o contraseña incorrectos. (Intento {$attempts}/" . MAX_ATTEMPTS . ")";
          }
        }
      }
    }
  }
}

// Mensajes por query
$e = $_GET['e'] ?? '';
if ($e === 'timeout') $info = "Sesión cerrada por inactividad. Vuelve a ingresar.";
if ($e === 'notlogged') $info = "Debes iniciar sesión para entrar al admin.";
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Login</title>
  <style>
    body{font-family:system-ui,Arial;background:#fafafa;margin:0;padding:24px}
    .card{max-width:380px;margin:0 auto;background:#fff;border:1px solid #ddd;border-radius:14px;padding:16px}
    input{width:100%;padding:12px;border-radius:10px;border:1px solid #ccc;margin:8px 0}
    button{width:100%;padding:12px;border-radius:10px;border:1px solid #111;background:#111;color:#fff;cursor:pointer}
    .err{background:#ffe8e8;border:1px solid #ffb3b3;padding:10px;border-radius:10px;margin:10px 0}
    .info{background:#e7f3ff;border:1px solid #b3d7ff;padding:10px;border-radius:10px;margin:10px 0}
    .muted{color:#666;font-size:13px}
  </style>
</head>
<body>
  <div class="card">
    <h2>🔒 Admin</h2>
    <p class="muted">solo personal autorizado.</p>

    <?php if ($info): ?>
      <div class="info"><?= h($info) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="err"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
  <input
    name="username"
    placeholder="Usuario"
    autocomplete="username"
    required
  >
  <input
    name="password"
    type="password"
    placeholder="Contraseña"
    autocomplete="new-password"
    required
  >
  <button type="submit">Entrar</button>
</form>
  </div>
</body>
</html>