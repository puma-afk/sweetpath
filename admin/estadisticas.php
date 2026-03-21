<?php
require __DIR__ . '/_auth.php';
require_admin();
require __DIR__ . '/../db.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bs($c) { return number_format(((int)$c) / 100, 2, '.', ''); }

// --- Rango de fechas (filtro) ---
$range = $_GET['range'] ?? '30';
$validRanges = ['7' => 'Últimos 7 días', '30' => 'Últimos 30 días', '90' => 'Últimos 3 meses', '180' => 'Últimos 6 meses', '365' => 'Este año', 'all' => 'Todo el tiempo'];
if (!isset($validRanges[$range])) $range = '30';

$dateFromClause = $range === 'all' ? '' : "AND o.created_at >= DATE_SUB(NOW(), INTERVAL {$range} DAY)";
$dateFromClauseP = $range === 'all' ? '' : "AND p.created_at >= DATE_SUB(NOW(), INTERVAL {$range} DAY)";

// ====================== CONSULTAS ======================

// 1. Resumen general de pedidos
$summaryOrders = $pdo->query("
  SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN status = 'ENTREGADO' THEN 1 ELSE 0 END) AS entregados,
    SUM(CASE WHEN status IN ('CREATED','SOLICITADO','APROBADO_PARA_PAGO','EN_PRODUCCION','LISTO') THEN 1 ELSE 0 END) AS activos,
    SUM(CASE WHEN status IN ('RECHAZADO','CANCELADO') THEN 1 ELSE 0 END) AS cancelados,
    SUM(CASE WHEN type = 'EXPRESS' THEN 1 ELSE 0 END) AS express,
    SUM(CASE WHEN type = 'CUSTOM' THEN 1 ELSE 0 END) AS custom,
    SUM(CASE WHEN type = 'PACK' THEN 1 ELSE 0 END) AS pack
  FROM orders o WHERE 1=1 {$dateFromClause}
")->fetch();

// 2. Ingresos verificados (por método de pago)
$paymentStats = $pdo->query("
  SELECT
    SUM(p.amount_cents) AS total_cents,
    SUM(CASE WHEN p.method = 'QR' THEN p.amount_cents ELSE 0 END) AS qr_cents,
    SUM(CASE WHEN p.method = 'CASH' THEN p.amount_cents ELSE 0 END) AS cash_cents,
    COUNT(*) AS total_pagos,
    SUM(CASE WHEN p.verified = 1 THEN 1 ELSE 0 END) AS verificados,
    SUM(CASE WHEN p.verified = 0 THEN 1 ELSE 0 END) AS pendientes,
    SUM(CASE WHEN p.verified = 1 THEN p.amount_cents ELSE 0 END) AS verified_cents,
    SUM(CASE WHEN p.verified = 0 THEN p.amount_cents ELSE 0 END) AS pending_cents
  FROM payments p WHERE 1=1 {$dateFromClauseP}
")->fetch();

// 3. Pedidos por mes (últimos 6 meses)
$byMonth = $pdo->query("
  SELECT
    DATE_FORMAT(o.created_at, '%Y-%m') AS mes,
    DATE_FORMAT(o.created_at, '%b %Y') AS mes_label,
    COUNT(*) AS pedidos,
    SUM(CASE WHEN status = 'ENTREGADO' THEN 1 ELSE 0 END) AS completados
  FROM orders o
  WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
  GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
  ORDER BY mes ASC
")->fetchAll();

// 4. Produtos más pedidos (EXPRESS)
$topProducts = $pdo->query("
  SELECT
    p.name,
    SUM(oi.quantity) AS total_qty,
    SUM(oi.quantity * oi.unit_price_cents) AS total_revenue_cents
  FROM order_items oi
  JOIN products p ON p.id = oi.product_id
  JOIN orders o ON o.id = oi.order_id
  WHERE o.status = 'ENTREGADO' {$dateFromClause}
  GROUP BY p.id, p.name
  ORDER BY total_qty DESC
  LIMIT 8
")->fetchAll();

// 5. Pagos por día (últimos 14 días para mini gráfico)
$dailyPayments = $pdo->query("
  SELECT
    DATE(p.created_at) AS dia,
    SUM(p.amount_cents) AS total_cents,
    COUNT(*) AS pagos
  FROM payments p
  WHERE p.verified = 1
    AND p.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
  GROUP BY DATE(p.created_at)
  ORDER BY dia ASC
")->fetchAll();

// Preparar datos para el gráfico
$chartDays = [];
$chartAmounts = [];
$tempDate = new DateTime('-13 days');
for ($i = 0; $i < 14; $i++) {
    $d = $tempDate->format('Y-m-d');
    $chartDays[] = $tempDate->format('d/m');
    $chartAmounts[$d] = 0;
    $tempDate->modify('+1 day');
}
foreach ($dailyPayments as $dp) {
    if (isset($chartAmounts[$dp['dia']])) {
        $chartAmounts[$dp['dia']] = (int)$dp['total_cents'];
    }
}
$chartAmountsJson = json_encode(array_values($chartAmounts));
$chartDaysJson    = json_encode($chartDays);

// 6. Nuevas Métricas: Total clientes únicos
$userStats = $pdo->query("
  SELECT COUNT(DISTINCT o.customer_phone) as total_clientes
  FROM orders o WHERE o.customer_phone != '' {$dateFromClause}
")->fetch();

// 7. Nuevas Métricas: Top Clientes
$topClients = $pdo->query("
  SELECT o.customer_name, o.customer_phone, COUNT(*) as total_pedidos, SUM(o.total_final_cents) as total_gastado
  FROM orders o
  WHERE o.status = 'ENTREGADO' AND o.customer_name != '' {$dateFromClause}
  GROUP BY o.customer_phone, o.customer_name
  ORDER BY total_gastado DESC
  LIMIT 5
")->fetchAll();

// 8. Nuevas Métricas: Ticket Promedio
$ticketStats = $pdo->query("
  SELECT AVG(o.total_final_cents) as ticket_promedio
  FROM orders o WHERE o.status = 'ENTREGADO' {$dateFromClause}
")->fetch();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
  <title>ESENCIA · Estadísticas</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    *{box-sizing:border-box}
    html, body { overflow-x: hidden; width: 100%; max-width: 100vw; }
    body{font-family:'Inter',system-ui,Arial,sans-serif;margin:0;background:var(--bg,#fffaca);color:var(--text,#151613);line-height:1.5;}
    .admin-page-content { padding: 16px; max-width: 1200px; margin: 0 auto; padding-bottom: 40px; }
    h2{margin:0 0 10px; color:#004f39; font-family: 'Playfair Display', serif;}
    h3{margin:0 0 15px; color:#004f39; font-weight:800; font-size:1.1rem; display:flex; align-items:center; gap:8px;}
    .stat{background:#fff;border-radius:20px;padding:24px;border:1px solid rgba(0,0,0,0.03); box-shadow: 0 10px 30px rgba(0,0,0,0.04); position:relative; overflow:hidden;}
    .stat::before { content:''; position:absolute; top:0; left:0; width:6px; height:100%; }
    .stat.green::before { background:#10b981; }
    .stat.amber::before { background:#f59e0b; }
    .stat.blue::before { background:#3b82f6; }
    .stat.red::before { background:#ef4444; }
    .stat .label { font-size:13px; font-weight:800; color:#64748b; text-transform:uppercase; margin-bottom:8px; display:flex; align-items:center; gap:6px;}
    .stat .value { font-size:32px; font-weight:800; color:#0f172a; margin-bottom:4px; line-height:1;}
    .stat .sub { font-size:13px; color:#64748b; font-weight:500;}
    .card{background:#fff;border-radius:24px;padding:28px;border:1px solid rgba(0,0,0,0.03);margin-bottom:24px; box-shadow: 0 10px 30px rgba(0,0,0,0.04);}
    select, .btn{padding:12px 16px;border-radius:12px;border:1px solid rgba(0,0,0,0.1);background:#f8fafc;cursor:pointer;font-size:14px;text-decoration:none;color:#151613; font-weight: 700; display:inline-flex; align-items:center; gap:8px; transition:all 0.2s;}
    .btn:hover, select:hover{background:#f1f5f9;}
    .btn.primary{background:#004f39; color:#fffaca; border-color:#004f39; box-shadow: 0 4px 12px rgba(0,79,57,0.15);}
    .btn.primary:hover{background:#003d2b; transform:translateY(-1px);}
    
    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:20px; margin-bottom:24px; }
    .two-col { display:grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap:24px; margin-bottom:24px; }
    
    table { width:100%; border-collapse:collapse; font-size:14px; }
    th { text-align:left; padding:12px 10px; color:#64748b; font-weight:800; text-transform:uppercase; font-size:11px; border-bottom:1px solid #e2e8f0; }
    td { padding:14px 10px; border-bottom:1px solid #f1f5f9; vertical-align:middle; font-weight:600;}
    .pill { display:inline-flex; align-items:center; gap:4px; padding:6px 12px; border-radius:999px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.5px;}
    .pill.green { background:#dcfce7; color:#166534; }
    .pill.blue { background:#dbeafe; color:#1e40af; }
    .pill.amber { background:#fef3c7; color:#92400e; }
    
    .bar-wrap { background:#f1f5f9; border-radius:10px; height:8px; width:100%; overflow:hidden; }
    .bar-wrap .bar-fill { background:var(--primary); height:100%; border-radius:10px; }
    .chart-box{position:relative;height:300px;width:100%}

    /* MODAL STYLES */
    .modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.6); backdrop-filter: blur(5px);
        display: flex; align-items: center; justify-content: center;
        opacity: 0; visibility: hidden; transition: all 0.3s; z-index: 3000;
        padding: 20px;
    }
    .modal-overlay.active { opacity: 1; visibility: visible; }
    .modal-box {
        background: #fff; border-radius: 24px; width: 100%; max-width: 500px;
        max-height: 85vh; display: flex; flex-direction: column;
        box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        transform: translateY(20px); transition: transform 0.3s;
    }
    .modal-overlay.active .modal-box { transform: translateY(0); }
    .modal-header { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
    .modal-header h3 { margin: 0; font-size: 1.25rem; }
    .modal-close { background: none; border: none; font-size: 24px; color: #94a3b8; cursor: pointer; padding: 0; display:flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; transition:0.2s; }
    .modal-close:hover { background: #f1f5f9; color: #0f172a; }
    .modal-body { padding: 24px; overflow-y: auto; }
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
<?php require __DIR__ . '/_navbar.php'; ?>

<div class="admin-page-content">

<div class="topbar" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
  <div>
    <h2>📊 ESENCIA — Estadísticas</h2>
    <small style="color:#666"><?= h($validRanges[$range]) ?></small>
  </div>
  <form method="get">
    <select name="range" onchange="this.form.submit()">
      <?php foreach ($validRanges as $k => $v): ?>
        <option value="<?= h($k) ?>" <?= $k === $range ? 'selected' : '' ?>><?= h($v) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<!-- KPIs principales -->
<div class="grid">
  <div class="stat green">
    <div class="label"><i class="fas fa-check-double"></i> Ingresos verificados</div>
    <div class="value">Bs <?= h(bs($paymentStats['verified_cents'] ?? 0)) ?></div>
    <div class="sub"><?= h($paymentStats['verificados'] ?? 0) ?> pagos confirmados</div>
  </div>
  <div class="stat blue">
    <div class="label"><i class="fas fa-box-open"></i> Pedidos totales</div>
    <div class="value"><?= h($summaryOrders['total'] ?? 0) ?></div>
    <div class="sub"><?= h($summaryOrders['activos'] ?? 0) ?> activos · <?= h($summaryOrders['entregados'] ?? 0) ?> entregados</div>
  </div>
  <div class="stat" style="border-left: 6px solid #8b5cf6;">
    <div class="label" style="color: #6d28d9;"><i class="fas fa-users"></i> Clientes Únicos</div>
    <div class="value"><?= h($userStats['total_clientes'] ?? 0) ?></div>
    <div class="sub">Han realizado al menos 1 pedido</div>
  </div>
  <div class="stat" style="border-left: 6px solid #0ea5e9;">
    <div class="label" style="color: #0284c7;"><i class="fas fa-receipt"></i> Ticket Promedio</div>
    <div class="value">Bs <?= h(bs($ticketStats['ticket_promedio'] ?? 0)) ?></div>
    <div class="sub">Gasto promedio por pedido entregado</div>
  </div>
  <div class="stat amber">
    <div class="label"><i class="fas fa-hourglass-half"></i> Cobros pendientes</div>
    <div class="value">Bs <?= h(bs($paymentStats['pending_cents'] ?? 0)) ?></div>
    <div class="sub"><?= h($paymentStats['pendientes'] ?? 0) ?> por verificar</div>
  </div>
  <div class="stat red">
    <div class="label"><i class="fas fa-times-circle"></i> Cancelados / Rechazados</div>
    <div class="value"><?= h($summaryOrders['cancelados'] ?? 0) ?></div>
    <div class="sub">de <?= h($summaryOrders['total'] ?? 0) ?> pedidos totales</div>
  </div>
</div>

<div class="two-col">

  <!-- Método de pago -->
  <div class="card">
    <h3>💳 Método de pago (verificados)</h3>
    <?php
      $qr   = (int)($paymentStats['qr_cents'] ?? 0);
      $cash = (int)($paymentStats['cash_cents'] ?? 0);
      $total_pay = $qr + $cash;
      $qr_pct   = $total_pay > 0 ? round($qr / $total_pay * 100) : 0;
      $cash_pct = 100 - $qr_pct;
    ?>
    <table>
      <tr>
        <td><span class="pill blue">QR</span></td>
        <td>Bs <?= h(bs($qr)) ?></td>
        <td><?= h($qr_pct) ?>%</td>
      </tr>
      <tr>
        <td><span class="pill amber">Efectivo</span></td>
        <td>Bs <?= h(bs($cash)) ?></td>
        <td><?= h($cash_pct) ?>%</td>
      </tr>
    </table>
    <div class="bar-wrap" style="margin-top:8px">
      <div class="bar-fill" style="width:<?= h($qr_pct) ?>%"></div>
    </div>
    <small style="color:#666">Azul = QR · resto = Efectivo</small>
  </div>

  <!-- Pedidos por tipo -->
  <div class="card">
    <h3>🧁 Pedidos por tipo</h3>
    <?php
      $express = (int)($summaryOrders['express'] ?? 0);
      $custom  = (int)($summaryOrders['custom'] ?? 0);
      $pack    = (int)($summaryOrders['pack'] ?? 0);
      $totalT  = $express + $custom + $pack ?: 1;
    ?>
    <table>
      <tr>
        <td><span class="pill green">EXPRESS</span></td>
        <td><?= h($express) ?> pedidos</td>
        <td><?= round($express/$totalT*100) ?>%</td>
      </tr>
      <tr>
        <td><span class="pill blue">CUSTOM</span></td>
        <td><?= h($custom) ?> pedidos</td>
        <td><?= round($custom/$totalT*100) ?>%</td>
      </tr>
      <tr>
        <td><span class="pill amber">PACK</span></td>
        <td><?= h($pack) ?> pedidos</td>
        <td><?= round($pack/$totalT*100) ?>%</td>
      </tr>
    </table>
  </div>

</div>

<!-- Gráfico de ingresos (14 días) -->
<div class="card">
  <h3>📈 Ingresos verificados — últimos 14 días (Bs)</h3>
  <div class="chart-box">
    <canvas id="chartPagos"></canvas>
  </div>
</div>

<!-- Modales (Ventanas Emergentes) -->
<div class="two-col" style="margin-top: 10px;">
  <!-- Botón Productos -->
  <div class="card" style="display:flex; justify-content:space-between; align-items:center;">
    <div>
      <h3 style="margin-bottom:4px;">🏆 Top Productos</h3>
      <div style="font-size:13px; color:#64748b;">Los productos más pedidos y rentables</div>
    </div>
    <button class="btn primary" onclick="openModal('modalProducts')">Ver detalle <i class="fas fa-arrow-right"></i></button>
  </div>

  <!-- Botón Meses -->
  <div class="card" style="display:flex; justify-content:space-between; align-items:center;">
    <div>
      <h3 style="margin-bottom:4px;">📅 Historial Mensual</h3>
      <div style="font-size:13px; color:#64748b;">Rendimiento de los últimos 6 meses</div>
    </div>
    <button class="btn primary" onclick="openModal('modalMonths')">Ver detalle <i class="fas fa-arrow-right"></i></button>
  </div>
  
  <!-- Botón Mejores Clientes -->
  <div class="card" style="display:flex; justify-content:space-between; align-items:center;">
    <div>
      <h3 style="margin-bottom:4px;">👑 Clientes Frecuentes</h3>
      <div style="font-size:13px; color:#64748b;">Top 5 mejores clientes</div>
    </div>
    <button class="btn primary" onclick="openModal('modalClients')">Ver detalle <i class="fas fa-arrow-right"></i></button>
  </div>
</div>

</div><!-- Cierre .admin-page-content -->

<!-- MODAL: Clientes -->
<div class="modal-overlay" id="modalClients" onclick="closeModal(event, this)">
  <div class="modal-box">
    <div class="modal-header">
      <h3>👑 Top 5 Clientes</h3>
      <button class="modal-close" onclick="closeModal(event, 'modalClients')">&times;</button>
    </div>
    <div class="modal-body">
      <?php if (count($topClients) > 0): ?>
      <table>
        <thead><tr><th>Cliente</th><th>Pedidos</th><th>Total Gastado</th></tr></thead>
        <tbody>
          <?php foreach ($topClients as $tc): ?>
            <tr>
              <td>
                <b><?= h($tc['customer_name'] ?: 'Sin nombre') ?></b><br>
                <small style="color:#64748b"><?= h($tc['customer_phone']) ?></small>
              </td>
              <td><span class="pill blue"><?= h($tc['total_pedidos']) ?> entregados</span></td>
              <td><b>Bs <?= h(bs($tc['total_gastado'])) ?></b></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <p style="color:#64748b;">No hay clientes frecuentes registrados aún en este periodo.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- MODAL: Productos -->
<div class="modal-overlay" id="modalProducts" onclick="closeModal(event, this)">
  <div class="modal-box">
    <div class="modal-header">
      <h3>🏆 Productos más vendidos</h3>
      <button class="modal-close" onclick="closeModal(event, 'modalProducts')">&times;</button>
    </div>
    <div class="modal-body">
      <?php if (count($topProducts) > 0): ?>
      <?php $maxQty = max(array_column($topProducts, 'total_qty')) ?: 1; ?>
      <table>
        <thead><tr><th>Producto</th><th>Cant.</th><th>Ingreso</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($topProducts as $tp): ?>
            <?php $pct = round(((int)$tp['total_qty']) / $maxQty * 100); ?>
            <tr>
              <td><?= h($tp['name']) ?></td>
              <td><b><?= h($tp['total_qty']) ?></b></td>
              <td>Bs <?= h(bs($tp['total_revenue_cents'] ?? 0)) ?></td>
              <td style="width:60px"><div class="bar-wrap"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <p style="color:#64748b;">No hay datos en este rango de tiempo.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- MODAL: Meses -->
<div class="modal-overlay" id="modalMonths" onclick="closeModal(event, this)">
  <div class="modal-box">
    <div class="modal-header">
      <h3>📅 Pedidos por Mes</h3>
      <button class="modal-close" onclick="closeModal(event, 'modalMonths')">&times;</button>
    </div>
    <div class="modal-body">
      <?php if (count($byMonth) > 0): ?>
      <table>
        <thead><tr><th>Mes</th><th>Total</th><th>Completados</th></tr></thead>
        <tbody>
          <?php foreach ($byMonth as $m): ?>
            <tr>
              <td><b><?= h($m['mes_label']) ?></b></td>
              <td><?= h($m['pedidos']) ?> pedidos</td>
              <td><span class="pill green"><?= h($m['completados']) ?> entregados</span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <p style="color:#64748b;">No hay historial en los últimos 6 meses.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeModal(e, idOrElement) {
    if (e.target.classList.contains('modal-overlay') || typeof idOrElement === 'string') {
        const el = typeof idOrElement === 'string' ? document.getElementById(idOrElement) : idOrElement;
        el.classList.remove('active');
        document.body.style.overflow = '';
    }
}

const ctx = document.getElementById('chartPagos').getContext('2d');
new Chart(ctx, {
  type: 'bar',
  data: {
    labels: <?= $chartDaysJson ?>,
    datasets: [{
      label: 'Ingresos (Bs)',
      data: <?= $chartAmountsJson ?>.map(c => c / 100),
      backgroundColor: 'rgba(59,130,246,0.85)',
      hoverBackgroundColor: 'rgba(37,99,235,1)',
      borderRadius: 6,
      borderSkipped: false,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { 
      legend: { display: false },
      tooltip: {
        backgroundColor: '#1e293b',
        padding: 12,
        titleFont: { size: 13, family: 'Inter' },
        bodyFont: { size: 14, weight: 'bold', family: 'Inter' },
        callbacks: {
          label: function(context) { return 'Bs ' + context.parsed.y.toFixed(2); }
        }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: { color: 'rgba(0,0,0,0.05)' },
        ticks: { callback: v => 'Bs ' + v, font: { family: 'Inter', size: 11 }, color: '#64748b' },
        border: { display: false }
      },
      x: { 
        grid: { display: false },
        ticks: { font: { family: 'Inter', size: 11 }, color: '#64748b' },
        border: { display: false }
      }
    }
  }
});
</script>

</body>
</html>
