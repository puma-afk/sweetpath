<?php
require __DIR__ . '/_auth.php';
require_admin();
require __DIR__ . '/_csrf.php';
require __DIR__ . '/../db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$stmt = $pdo->query("SELECT * FROM config WHERE id=1 LIMIT 1");
$c = $stmt->fetch();
if (!$c) { http_response_code(500); exit("Falta config id=1"); }

$msg = trim($_GET['msg'] ?? '');
$err = trim($_GET['err'] ?? '');

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
  <title>ESENCIA · Configuración</title>
  <style>
    :root {
        --primary: #004f39;
        --bg: #fffaca;
        --text: #151613;
        --accent: #ffd32a;
        --card-bg: #ffffff;
        --success: #10b981;
        --danger: #ef4444;
    }
    
    html, body { overflow-x: hidden; width: 100%; max-width: 100vw; }
    body{font-family:'Inter',system-ui,Arial,sans-serif;margin:0;background:var(--bg);color:var(--text);line-height:1.5;}
    
    .admin-page-content { padding: 16px; max-width: 1000px; margin: 0 auto; padding-bottom: 40px; }
    
    h2 { color: var(--primary); font-weight: 800; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
    h3 { color: var(--primary); font-weight: 700; margin-top: 0; margin-bottom: 16px; font-size: 1.1rem; display: flex; align-items: center; gap: 8px; }

    .config-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 20px;
    }

    .card {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 24px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
        border: 1px solid rgba(0, 0, 0, 0.03);
        display: flex;
        flex-direction: column;
    }

    label {
        display: block;
        font-size: 13px;
        font-weight: 700;
        color: #475569;
        margin-bottom: 6px;
    }

    input[type="text"], input[type="number"], input[type="time"], input[type="datetime-local"], select, textarea {
        width: 100%;
        padding: 12px 16px;
        border-radius: 12px;
        border: 1px solid rgba(0,0,0,0.1);
        background: #f8fafc;
        font-size: 14px;
        font-family: inherit;
        color: var(--text);
        box-sizing: border-box;
        transition: border-color 0.2s, background 0.2s;
        margin-bottom: 16px;
    }

    input:focus, textarea:focus {
        outline: none;
        border-color: var(--primary);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(0, 79, 57, 0.1);
    }

    .row { display: flex; gap: 12px; flex-wrap: wrap; }
    .row > div { flex: 1; min-width: 120px; }

    .checkbox-container {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px;
        background: #f8fafc;
        border: 1px solid rgba(0,0,0,0.1);
        border-radius: 12px;
        margin-bottom: 16px;
        cursor: pointer;
    }
    .checkbox-container input { width: 18px; height: 18px; cursor: pointer; }
    .checkbox-container span { font-size: 14px; font-weight: 600; color: #334155; }

    button, .btn {
        padding: 12px 20px;
        border-radius: 12px;
        border: none;
        cursor: pointer;
        font-weight: 700;
        font-size: 14px;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
        width: 100%;
        margin-top: auto;
    }

    button.primary { background: var(--primary); color: #fffaca; box-shadow: 0 4px 12px rgba(0, 79, 57, 0.2); }
    button.primary:hover { background: #003d2b; transform: translateY(-2px); }

    .alert { padding: 15px 20px; border-radius: 16px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
    .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

    .qr-preview {
        width: 100%;
        max-width: 250px;
        height: auto;
        border-radius: 16px;
        border: 3px solid #e2e8f0;
        margin: 0 auto 20px auto;
        display: block;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }

    hr { border: none; border-top: 1px dashed rgba(0,0,0,0.1); margin: 25px 0; }
    .help-text { font-size: 12px; color: #64748b; margin-top: -12px; margin-bottom: 16px; display: block; }
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
<?php require __DIR__ . '/_navbar.php'; ?>

<main class="admin-page-content">

  <h2><i class="fas fa-cog"></i> Configuración del Sistema</h2>

  <?php if ($msg): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= h($msg) ?></div>
  <?php endif; ?>
  <?php if ($err): ?>
    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= h($err) ?></div>
  <?php endif; ?>

  <div class="config-grid">
    
    <!-- COLUMNA 1: Datos Operativos -->
    <div class="card">
      <h3><i class="fab fa-whatsapp"></i> Contacto y Horarios</h3>
      <form method="post" action="/admin/config_save.php" style="display: flex; flex-direction: column; height: 100%;">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="save_main">

        <label>Número WhatsApp</label>
        <span class="help-text">Sin '+', sin espacios (Ej: 59171234567)</span>
        <input type="text" name="whatsapp_number" required value="<?= h($c['whatsapp_number']) ?>" placeholder="591...">

        <div class="row">
          <div>
            <label>Hora Inicio</label>
            <input type="time" name="business_hours_start" required value="<?= h(substr($c['business_hours_start'],0,5)) ?>">
          </div>
          <div>
            <label>Hora Fin</label>
            <input type="time" name="business_hours_end" required value="<?= h(substr($c['business_hours_end'],0,5)) ?>">
          </div>
        </div>

        <label>Zona horaria del sistema</label>
        <input type="text" name="timezone" required value="<?= h($c['timezone']) ?>" placeholder="America/La_Paz">

        <hr>

        <h3 style="font-size: 1rem; color: #b00020;"><i class="fas fa-pause-circle"></i> Pausar Tienda</h3>
        <span class="help-text" style="margin-bottom: 10px;">Útil para vacaciones o alta demanda.</span>
        
        <label class="checkbox-container">
          <input type="checkbox" name="manual_pause" value="1" <?= ((int)$c['manual_pause']===1?'checked':'') ?>>
          <span>Activar Pausa Manual (No recibir pedidos)</span>
        </label>

        <label>Mensaje para el cliente</label>
        <textarea name="manual_pause_message" rows="2" placeholder="Hoy no atendemos. Volvemos mañana."><?= h($c['manual_pause_message'] ?? '') ?></textarea>

        <label>Pausar automáticamente hasta:</label>
        <span class="help-text" style="margin-bottom:6px;">(Opcional, dejar vacío para pausar indefinidamente)</span>
        <input type="datetime-local" name="manual_pause_until" value="<?= $c['manual_pause_until'] ? h(str_replace(' ', 'T', substr($c['manual_pause_until'],0,16))) : '' ?>">

        <button type="submit" class="primary"><i class="fas fa-save"></i> Guardar Operativa</button>
      </form>
    </div>

    <!-- COLUMNA 2: Pagos y QR -->
    <div style="display: flex; flex-direction: column; gap: 20px;">
      
      <div class="card">
        <h3><i class="fas fa-qrcode"></i> Código QR de Pagos</h3>
        
        <?php
          $currQR = null;
          if (!empty($c['qr_asset_id'])) {
              $qa = $pdo->prepare("SELECT path_original FROM assets WHERE id=?");
              $qa->execute([$c['qr_asset_id']]);
              $currQR = $qa->fetchColumn();
          }
        ?>

        <?php if ($currQR): ?>
          <img src="<?= h($currQR) ?>" class="qr-preview" alt="QR Actual">
          <div style="text-align: center; margin-bottom: 15px;">
            <span style="background:#dcfce7; color:#166534; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: bold;"><i class="fas fa-check"></i> QR Configurado Correctamente</span>
          </div>
        <?php else: ?>
          <div style="padding: 20px; background: #fff3e0; border: 1px dashed #f59e0b; border-radius: 16px; text-align: center; margin-bottom: 20px;">
            <i class="fas fa-image fa-3x" style="color: #fbd38d; margin-bottom: 10px;"></i>
            <p style="font-size:13px; color:#92400e; margin:0; font-weight: 600;">No hay código QR subido.</p>
          </div>
        <?php endif; ?>

        <form method="post" action="/admin/config_save.php" enctype="multipart/form-data" style="margin-top: auto;">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="upload_qr">
          
          <label>Cargar nuevo QR (JPG / PNG / WebP)</label>
          <input type="file" name="qr_image" accept="image/*" required style="padding: 10px; background: #fff; border: 1px dashed #cbd5e1;">
          
          <button type="submit" class="secondary" style="border: 1px solid var(--primary); color: var(--primary);"><i class="fas fa-cloud-upload-alt"></i> Subir Código QR</button>
        </form>
      </div>

      <div class="card">
        <h3><i class="fas fa-university"></i> Datos Bancarios</h3>
        <span class="help-text" style="margin-bottom: 10px;">Se mostrarán debajo del QR para depósitos o transferencias.</span>
        
        <form method="post" action="/admin/config_save.php" style="display: flex; flex-direction: column; height: 100%;">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="save_qr_info">
          
          <textarea style="flex: 1; min-height: 120px;" name="qr_account_info" placeholder="Ej:
Banco BNB
Cuenta: 1234567890
Titular: Esencia Repostería
CI/NIT: 1234567"><?= h($c['qr_account_info'] ?? '') ?></textarea>
          
          <button type="submit" class="primary"><i class="fas fa-save"></i> Guardar Datos de Cuenta</button>
        </form>
      </div>

    </div>

  </div>

</main>
</body>
</html>