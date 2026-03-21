<?php
/**
 * admin/limpiar_archivos.php
 * Elimina comprobantes de pago verificados que tengan más de N días.
 * Los QR nunca se eliminan automáticamente.
 *
 * Uso: visitar esta página desde el panel admin.
 * Se puede ejecutar manualmente cuando la dueña quiera limpiar.
 */
require __DIR__ . '/_auth.php';
require_admin();
require __DIR__ . '/../db.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---- Configuración ----
$DAYS_TO_KEEP = 180; // eliminar comprobantes verificados de más de 6 meses

$deleted = [];
$errors  = [];
$skipped = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'limpiar') {
    $cutoff = (new DateTime())->modify("-{$DAYS_TO_KEEP} days")->format('Y-m-d H:i:s');

    // Buscar comprobantes verificados más antiguos que el cutoff
    $stmt = $pdo->prepare("
        SELECT p.id AS payment_id, a.id AS asset_id, a.path_original
        FROM payments p
        JOIN assets a ON a.id = p.proof_asset_id
        WHERE p.verified = 1
          AND a.type = 'PROOF_IMAGE'
          AND p.created_at < ?
    ");
    $stmt->execute([$cutoff]);
    $rows = $stmt->fetchAll();

    $uploadDir = realpath(__DIR__ . '/../storage/uploads');

    foreach ($rows as $row) {
        $webPath = $row['path_original'];
        $fsPath  = realpath(__DIR__ . '/../' . ltrim(str_replace('/', '/', $webPath), '/'));

        // Seguridad: verificar que el archivo esté dentro de storage/uploads
        if (!$fsPath || strpos($fsPath, $uploadDir) !== 0) {
            $errors[] = "Ruta inválida ignorada: " . $webPath;
            continue;
        }

        $ok = true;
        if (file_exists($fsPath)) {
            $ok = unlink($fsPath);
        }

        if ($ok) {
            // Eliminar asset de la DB (solo el path, el registro de pago se conserva)
            $pdo->prepare("UPDATE assets SET path_original = NULL WHERE id = ?")
                ->execute([$row['asset_id']]);
            $deleted[] = basename($fsPath);
        } else {
            $errors[] = "No se pudo eliminar: " . basename($fsPath);
        }
    }
}

// Estadísticas actuales
$stats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN a.path_original IS NOT NULL THEN 1 ELSE 0 END) AS con_archivo,
        SUM(CASE WHEN p.verified = 1 THEN 1 ELSE 0 END) AS verificados
    FROM payments p
    LEFT JOIN assets a ON a.id = p.proof_asset_id
    WHERE a.type = 'PROOF_IMAGE' OR a.type IS NULL
")->fetch();

// Cuántos se eliminarían si se ejecutara ahora
$cutoffPreview = (new DateTime())->modify("-{$DAYS_TO_KEEP} days")->format('Y-m-d H:i:s');
$eligible = $pdo->prepare("
    SELECT COUNT(*) AS n
    FROM payments p
    JOIN assets a ON a.id = p.proof_asset_id
    WHERE p.verified = 1
      AND a.type = 'PROOF_IMAGE'
      AND a.path_original IS NOT NULL
      AND p.created_at < ?
");
$eligible->execute([$cutoffPreview]);
$eligibleCount = (int)($eligible->fetch()['n'] ?? 0);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin - Limpiar archivos</title>
  <style>
    body{font-family:system-ui,Arial;margin:16px;background:#fafafa}
    .card{background:#fff;border:1px solid #ddd;border-radius:14px;padding:14px;max-width:600px}
    button{padding:12px 14px;border-radius:10px;border:1px solid #b00;background:#b00020;color:#fff;cursor:pointer}
    button.safe{background:#111;border-color:#111}
    .ok{background:#e9ffe8;border:1px solid #b6ffb3;padding:10px;border-radius:10px;margin:10px 0}
    .err{background:#ffe8e8;border:1px solid #ffb3b3;padding:10px;border-radius:10px;margin:10px 0}
    .warn{background:#fff3cd;border:1px solid #ffeeba;padding:10px;border-radius:10px;margin:10px 0}
    table{width:100%;border-collapse:collapse;margin:10px 0}
    td,th{padding:8px;border-bottom:1px solid #eee;text-align:left}
    small{color:#666}
  </style>
  <link rel="manifest" href="./manifest.json" crossorigin="use-credentials">
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
  <h2>🗑️ Limpiar comprobantes antiguos</h2>
  <p><a href="/admin/payments.php">← Volver a pagos</a></p>

  <table>
    <tr><th>Estadística</th><th>Valor</th></tr>
    <tr><td>Total comprobantes en DB</td><td><?= h($stats['total'] ?? 0) ?></td></tr>
    <tr><td>Con archivo físico</td><td><?= h($stats['con_archivo'] ?? 0) ?></td></tr>
    <tr><td>Verificados por la dueña</td><td><?= h($stats['verificados'] ?? 0) ?></td></tr>
    <tr><td>Eliminables ahora (verificados &gt; <?= $DAYS_TO_KEEP ?> días)</td>
        <td><b><?= h($eligibleCount) ?></b></td></tr>
  </table>

  <?php if (!empty($deleted)): ?>
    <div class="ok">
      <b>✅ Eliminados <?= count($deleted) ?> archivos:</b><br>
      <?php foreach ($deleted as $f): ?><small><?= h($f) ?></small><br><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="err">
      <b>⚠️ Errores:</b><br>
      <?php foreach ($errors as $e): ?><small><?= h($e) ?></small><br><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($eligibleCount > 0): ?>
    <div class="warn">
      <b>Se eliminarán <?= h($eligibleCount) ?> comprobantes</b> que ya fueron verificados y tienen más de <?= $DAYS_TO_KEEP ?> días.<br>
      <small>Los registros de pago se conservan en la base de datos. Solo se elimina el archivo de imagen.</small>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="limpiar">
      <button type="submit">🗑️ Eliminar <?= h($eligibleCount) ?> archivos viejos</button>
    </form>
  <?php else: ?>
    <div class="ok">No hay archivos elegibles para eliminar en este momento.</div>
  <?php endif; ?>

  <p style="margin-top:16px"><small>Solo se eliminan comprobantes <b>verificados</b> con más de <b><?= $DAYS_TO_KEEP ?> días</b>. Los QR nunca se eliminan automáticamente.</small></p>
</div>
</body>
</html>
