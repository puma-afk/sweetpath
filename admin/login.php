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
  <title>ESENCIA · Login de Admin</title>
  <link rel="stylesheet" href="../public/css/app.css">
  <style>
    body {
      background-color: var(--fondo);
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      margin: 0;
      padding: 20px;
    }
    .card-admin {
      background: #fff;
      border: 1px solid var(--linea);
      border-top: 3px solid var(--negro); /* Borde fino */
      border-radius: 20px;
      padding: 40px;
      width: 100%;
      max-width: 420px;
      box-shadow: 0 15px 45px var(--negro-suave);
      position: relative;
      overflow: hidden;
    }
    /* Eliminar franja gruesa */
    .card-admin::before {
      display: none;
    }
    .login-logo {
      width: 120px;
      display: block;
      margin: 0 auto 30px;
    }
    h2 {
      margin-top: 0;
      font-size: 1.5rem;
      color: var(--primario);
      text-align: center;
    }
    .input-group {
      margin-bottom: 20px;
    }
    input {
      width: 100%;
      padding: 14px;
      border-radius: 12px;
      border: 1px solid var(--linea);
      background: var(--tarjeta);
      transition: all 0.2s;
    }
    input:focus {
      outline: none;
      border-color: var(--primario);
      box-shadow: 0 0 0 4px var(--suave);
    }
    button {
      width: 100%;
      padding: 14px;
      background: var(--primario);
      color: white;
      border: none;
      border-radius: 12px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.2s;
      margin-top: 10px;
    }
    button:hover {
      background: #003d2c;
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(0, 79, 57, 0.2);
    }
    .err {
      background: #fff3f3;
      color: #d63031;
      border: 1px solid #fab1a0;
      padding: 12px;
      border-radius: 10px;
      margin-bottom: 20px;
      font-size: 14px;
    }
    .info {
      background: #e7f3ff;
      color: #0984e3;
      border: 1px solid #74b9ff;
      padding: 12px;
      border-radius: 10px;
      margin-bottom: 20px;
      font-size: 14px;
    }
    .footer-admin {
      text-align: center;
      margin-top: 25px;
      font-size: 13px;
      color: var(--atenuado);
    }
  </style>
</head>
<body>
  <div class="card-admin">
    <img src="../public/img/logo.png" alt="ESENCIA" class="login-logo">
    <h2>Panel de Control</h2>

    <?php if ($info): ?>
      <div class="info"><i class="fas fa-info-circle"></i> <?= h($info) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="err"><i class="fas fa-exclamation-circle"></i> <?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <div class="input-group">
        <input
          name="username"
          placeholder="Usuario"
          autocomplete="username"
          required
        >
      </div>
      <div class="input-group">
        <input
          name="password"
          type="password"
          placeholder="Contraseña"
          autocomplete="current-password"
          required
        >
      </div>
      <button type="submit">Iniciar Sesión</button>
    </form>
    
    <div class="footer-admin">
      &copy; <?= date('Y') ?> Esencia Repostería · Admin
    </div>
  </div>
</body>
</html>