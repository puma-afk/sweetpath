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
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>ESENCIA · Estadísticas</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    *{box-sizing:border-box}
    body{font-family:system-ui,Arial;margin:0;padding:16px;background:#fffaca;color:#151613}
    h2{margin:0 0 4px}
    .topbar{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:16px}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:16px}
    .stat{background:#fff;border-radius:14px;padding:16px;border:1px solid #e0e0e0}
    .stat .label{font-size:12px;color:#666;margin-bottom:4px}
    .stat .value{font-size:26px;font-weight:700;line-height:1.1}
    .stat .sub{font-size:12px;color:#888;margin-top:4px}
    .stat.green{border-left:4px solid #22c55e}
    .stat.blue{border-left:4px solid #3b82f6}
    .stat.amber{border-left:4px solid #f59e0b}
    .stat.red{border-left:4px solid #ef4444}
    .stat.purple{border-left:4px solid #8b5cf6}
    .card{background:#fff;border-radius:14px;padding:16px;border:1px solid #e0e0e0;margin-bottom:12px}
    .card h3{margin:0 0 10px;font-size:15px}
    table{width:100%;border-collapse:collapse}
    th{text-align:left;font-size:12px;color:#666;padding:6px 4px;border-bottom:2px solid #f0f0f0}
    td{padding:7px 4px;border-bottom:1px solid #f5f5f5;font-size:14px}
    tr:last-child td{border-bottom:none}
    .pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;background:#eee}
    .pill.green{background:#dcfce7;color:#166534}
    .pill.blue{background:#dbeafe;color:#1e3a8a}
    .pill.amber{background:#fef3c7;color:#92400e}
    select,a.btn{padding:8px 12px;border-radius:10px;border:1px solid #ccc;background:#fff;cursor:pointer;font-size:14px;text-decoration:none;color:#111}
    .bar-wrap{background:#f0f0f0;border-radius:8px;height:8px;overflow:hidden;margin-top:4px}
    .bar-fill{height:100%;border-radius:8px;background:#3b82f6}
    .two-col{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    @media(max-width:600px){.two-col{grid-template-columns:1fr}}
    .chart-box{height:200px;position:relative}
    nav{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
  </style>
</head>
<body>

<div class="topbar">
  <div>
    <h2>📊 ESENCIA — Estadísticas</h2>
    <small style="color:#666">Panel · <?= h($validRanges[$range]) ?></small>
  </div>
  <nav>
    <a href="/sweetpath/admin/orders.php" class="btn">← Pedidos</a>
    <form method="get" style="display:inline">
      <select name="range" onchange="this.form.submit()">
        <?php foreach ($validRanges as $k => $v): ?>
          <option value="<?= h($k) ?>" <?= $k === $range ? 'selected' : '' ?>><?= h($v) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </nav>
</div>

<!-- KPIs principales -->
<div class="grid">
  <div class="stat green">
    <div class="label">Ingresos verificados</div>
    <div class="value">Bs <?= h(bs($paymentStats['verified_cents'] ?? 0)) ?></div>
    <div class="sub"><?= h($paymentStats['verificados'] ?? 0) ?> pagos confirmados</div>
  </div>
  <div class="stat amber">
    <div class="label">Cobros pendientes</div>
    <div class="value">Bs <?= h(bs($paymentStats['pending_cents'] ?? 0)) ?></div>
    <div class="sub"><?= h($paymentStats['pendientes'] ?? 0) ?> por verificar</div>
  </div>
  <div class="stat blue">
    <div class="label">Pedidos totales</div>
    <div class="value"><?= h($summaryOrders['total'] ?? 0) ?></div>
    <div class="sub"><?= h($summaryOrders['activos'] ?? 0) ?> activos · <?= h($summaryOrders['entregados'] ?? 0) ?> entregados</div>
  </div>
  <div class="stat red">
    <div class="label">Cancelados / Rechazados</div>
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

<!-- Productos más vendidos -->
<?php if (count($topProducts) > 0): ?>
<div class="card">
  <h3>🏆 Productos más vendidos (pedidos entregados)</h3>
  <?php
    $maxQty = max(array_column($topProducts, 'total_qty')) ?: 1;
  ?>
  <table>
    <thead>
      <tr><th>Producto</th><th>Unidades</th><th>Ingreso</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($topProducts as $tp): ?>
        <?php $pct = round(((int)$tp['total_qty']) / $maxQty * 100); ?>
        <tr>
          <td><?= h($tp['name']) ?></td>
          <td><b><?= h($tp['total_qty']) ?></b></td>
          <td>Bs <?= h(bs($tp['total_revenue_cents'] ?? 0)) ?></td>
          <td style="width:100px">
            <div class="bar-wrap"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- Pedidos por mes -->
<?php if (count($byMonth) > 0): ?>
<div class="card">
  <h3>📅 Pedidos por mes (últimos 6 meses)</h3>
  <table>
    <thead><tr><th>Mes</th><th>Total pedidos</th><th>Completados</th></tr></thead>
    <tbody>
      <?php foreach ($byMonth as $m): ?>
        <tr>
          <td><?= h($m['mes_label']) ?></td>
          <td><?= h($m['pedidos']) ?></td>
          <td><span class="pill green"><?= h($m['completados']) ?></span></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<script>
const ctx = document.getElementById('chartPagos').getContext('2d');
new Chart(ctx, {
  type: 'bar',
  data: {
    labels: <?= $chartDaysJson ?>,
    datasets: [{
      label: 'Ingresos (Bs)',
      data: <?= $chartAmountsJson ?>.map(c => c / 100),
      backgroundColor: 'rgba(59,130,246,0.7)',
      borderRadius: 6,
      borderSkipped: false,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: {
        beginAtZero: true,
        ticks: { callback: v => 'Bs ' + v.toFixed(2) }
      },
      x: { grid: { display: false } }
    }
  }
});
</script>

</body>
</html>
