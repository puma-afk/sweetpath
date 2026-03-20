<?php
require __DIR__ . '/_auth.php';
require_admin();
require __DIR__ . '/_csrf.php';
require __DIR__ . '/../db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify_or_die();

  $current = $_POST['current_password'] ?? '';
  $new1 = $_POST['new_password'] ?? '';
  $new2 = $_POST['new_password2'] ?? '';

  if (strlen($new1) < 8) {
    $err = "La nueva contraseña debe tener al menos 8 caracteres.";
  } elseif ($new1 !== $new2) {
    $err = "La confirmación no coincide.";
  } else {
    $adminId = (int)($_SESSION['admin_user']['id'] ?? 0);

    $st = $pdo->prepare("SELECT password_hash FROM admin_users WHERE id=? LIMIT 1");
    $st->execute([$adminId]);
    $row = $st->fetch();

    if (!$row || !password_verify($current, $row['password_hash'])) {
      $err = "Contraseña actual incorrecta.";
    } else {
      $hash = password_hash($new1, PASSWORD_DEFAULT);
      $up = $pdo->prepare("UPDATE admin_users SET password_hash=? WHERE id=?");
      $up->execute([$hash, $adminId]);

      // fuerza regenerar sesión
      session_regenerate_id(true);

      $msg = "✅ Contraseña actualizada con éxito.";
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Cambiar contraseña</title>
  <style>
    body{font-family:system-ui,Arial;margin:16px;background:#fafafa}
    .card{background:#fff;border:1px solid #ddd;border-radius:14px;padding:14px;max-width:460px}
    input{width:100%;padding:12px;border-radius:10px;border:1px solid #ccc;margin:8px 0}
    button{padding:12px 14px;border-radius:10px;border:1px solid #111;background:#111;color:#fff;cursor:pointer;width:100%}
    .err{background:#ffe8e8;border:1px solid #ffb3b3;padding:10px;border-radius:10px;margin:10px 0}
    .ok{background:#e9ffe8;border:1px solid #b6ffb3;padding:10px;border-radius:10px;margin:10px 0}
    a{color:#111}
  </style>
  <link rel="manifest" href="./manifest.json">
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('./sw.js').then(r => console.log('Admin SW registered')).catch(e => console.log('Admin SW fail', e));
      });
    }
  </script>
</head>
<body>
  <div class="card">
    <h2>🔐 Cambiar contraseña</h2>
    <p><a href="/sweetpath/admin/orders.php">← Volver al admin</a></p>

    <?php if ($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

    <form method="post">
      <?= csrf_input() ?>
      <input type="password" name="current_password" placeholder="Contraseña actual" required>
      <input type="password" name="new_password" placeholder="Nueva contraseña (mín 8 caracteres)" required>
      <input type="password" name="new_password2" placeholder="Confirmar nueva contraseña" required>
      <button type="submit">Actualizar contraseña</button>
    </form>
  </div>
</body>
</html>