<?php
$code = htmlspecialchars($_GET['code'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Comprobante enviado</title>
<style>
  body{font-family:system-ui,Arial;margin:16px;background:#fafafa}
  .card{background:#fff;border:1px solid #ddd;border-radius:14px;padding:14px;max-width:520px}
</style>
</head>
<body>
  <div class="card">
    <h2>✅ Comprobante enviado</h2>
    <p>Pedido: <b><?= $code ?></b></p>
    <p>La dueña revisará tu comprobante y te confirmará por WhatsApp 😊</p>
  </div>
</body>
</html>