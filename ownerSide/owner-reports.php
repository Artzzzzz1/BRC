<?php
require_once '../config/auth.php';
require_once '../config/db.php';
requireOwner();
$activePage = 'reports';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$period = $_GET['period'] ?? 'month';
$dateFilter = match($period) {
  'week'  => "AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
  'year'  => "AND YEAR(completed_at) = YEAR(NOW())",
  default => "AND MONTH(completed_at) = MONTH(NOW()) AND YEAR(completed_at) = YEAR(NOW())",
};

try {
  $r1 = $pdo->query("SELECT COALESCE(SUM(amount_charged),0) FROM job_orders WHERE payment_status='paid' $dateFilter");
  $revenue = $r1 ? (float)$r1->fetchColumn() : 0;

  $r2 = $pdo->query("SELECT COUNT(*) FROM job_orders WHERE status='completed' $dateFilter");
  $completed = $r2 ? (int)$r2->fetchColumn() : 0;

  $r3 = $pdo->query("SELECT COUNT(*) FROM job_orders WHERE status='in_progress'");
  $inProgress = $r3 ? (int)$r3->fetchColumn() : 0;

  $r4 = $pdo->query("SELECT COUNT(*) FROM job_orders WHERE status='cancelled' $dateFilter");
  $cancelled = $r4 ? (int)$r4->fetchColumn() : 0;

  $avgStmt = $pdo->query("SELECT AVG(amount_charged) FROM job_orders WHERE payment_status='paid' $dateFilter");
  $avgOrder = $avgStmt ? (float)$avgStmt->fetchColumn() : 0;

  // Revenue by month (12 months)
  $revStmt = $pdo->query("SELECT DATE_FORMAT(completed_at,'%b %Y') AS month, COALESCE(SUM(amount_charged),0) AS total, COUNT(*) AS cnt FROM job_orders WHERE payment_status='paid' AND completed_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) GROUP BY YEAR(completed_at), MONTH(completed_at) ORDER BY completed_at ASC");
  $revByMonth = $revStmt ? $revStmt->fetchAll(PDO::FETCH_ASSOC) : [];
  $maxRev = !empty($revByMonth) ? max(array_column($revByMonth,'total')) : 1;

  // Top services
  $svcStmt = $pdo->query("SELECT service, COUNT(*) AS cnt, COALESCE(SUM(amount_charged),0) AS revenue FROM job_orders WHERE status='completed' $dateFilter GROUP BY service ORDER BY cnt DESC");
  $topSvcs = $svcStmt ? $svcStmt->fetchAll(PDO::FETCH_ASSOC) : [];
  $maxSvc = !empty($topSvcs) ? max(array_column($topSvcs,'cnt')) : 1;

  // Worker performance
  $wrkStmt = $pdo->query("SELECT u.full_name, COUNT(jo.id) AS total, SUM(jo.status='completed') AS done, COALESCE(SUM(jo.amount_charged),0) AS revenue FROM users u LEFT JOIN job_orders jo ON jo.worker_id=u.id WHERE u.role='worker' GROUP BY u.id ORDER BY done DESC");
  $workerStats = $wrkStmt ? $wrkStmt->fetchAll(PDO::FETCH_ASSOC) : [];

  // Recent transactions
  $txnStmt = $pdo->query("SELECT jo.*, u.full_name AS worker_name FROM job_orders jo LEFT JOIN users u ON jo.worker_id=u.id WHERE jo.payment_status='paid' ORDER BY jo.completed_at DESC LIMIT 10");
  $transactions = $txnStmt ? $txnStmt->fetchAll(PDO::FETCH_ASSOC) : [];

} catch (Exception $e) {
  $revenue=$completed=$inProgress=$cancelled=$avgOrder=0;
  $revByMonth=$topSvcs=$workerStats=$transactions=[];
  $maxRev=$maxSvc=1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BRC Calamba - Reports</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../adminSide/admin.css">
  <style>
    .period-tabs{display:flex;gap:6px;margin-bottom:24px}
    .period-btn{padding:8px 18px;border-radius:8px;border:1.5px solid var(--border);background:#fff;font-family:'Montserrat',sans-serif;font-size:.8rem;font-weight:700;cursor:pointer;color:var(--muted);text-decoration:none;transition:all .15s}
    .period-btn.active{background:var(--red);border-color:var(--red);color:white}
    .period-btn:hover:not(.active){background:var(--bg)}
    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px}
    @media(max-width:900px){.grid-2{grid-template-columns:1fr}}
    .bar-wrap{display:flex;flex-direction:column;gap:10px}
    .bar-row{display:flex;align-items:center;gap:10px;font-size:.8rem}
    .bar-label{width:200px;flex-shrink:0;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .bar-track{flex:1;height:10px;background:var(--border);border-radius:99px;overflow:hidden}
    .bar-fill{height:100%;background:var(--red);border-radius:99px}
    .bar-count{width:30px;text-align:right;font-weight:700;color:var(--muted);font-size:.75rem}
    .month-bars{display:flex;align-items:flex-end;gap:6px;height:130px;padding-bottom:4px}
    .month-bar-wrap{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;height:100%;justify-content:flex-end}
    .month-bar{width:100%;background:var(--red);border-radius:4px 4px 0 0;min-height:4px;opacity:.8}
    .month-bar:hover{opacity:1}
    .month-label{font-size:.62rem;color:var(--muted);font-weight:600;text-align:center}
    .month-val{font-size:.62rem;color:var(--muted);font-weight:700}
  </style>
</head>
<body>
<?php include '../config/owner-sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
    <div class="page-title"><h1>Business Reports</h1><p>Revenue, performance, and analytics</p></div>
  </div>
  <div class="content">

    <!-- PERIOD SELECTOR -->
    <div class="period-tabs">
      <a href="?period=week"  class="period-btn <?= $period==='week' ?'active':'' ?>">This Week</a>
      <a href="?period=month" class="period-btn <?= $period==='month'?'active':'' ?>">This Month</a>
      <a href="?period=year"  class="period-btn <?= $period==='year' ?'active':'' ?>">This Year</a>
    </div>

    <!-- TOP STATS -->
    <div class="stats-grid" style="margin-bottom:24px;">
      <div class="stat-card">
        <div class="stat-top"><div class="stat-icon" style="background:#fff0f0;"><svg viewBox="0 0 24 24" fill="none" stroke="#CC0000" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div></div>
        <div class="stat-number">₱<?= number_format($revenue,2) ?></div>
        <div class="stat-label">Total Revenue</div>
      </div>
      <div class="stat-card">
        <div class="stat-top"><div class="stat-icon" style="background:#dcfce7;"><svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div></div>
        <div class="stat-number"><?= $completed ?></div>
        <div class="stat-label">Completed Orders</div>
      </div>
      <div class="stat-card">
        <div class="stat-top"><div class="stat-icon" style="background:#fef9c3;"><svg viewBox="0 0 24 24" fill="none" stroke="#854d0e" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></div>
        <div class="stat-number"><?= $inProgress ?></div>
        <div class="stat-label">In Progress</div>
      </div>
      <div class="stat-card">
        <div class="stat-top"><div class="stat-icon" style="background:#dbeafe;"><svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div></div>
        <div class="stat-number">₱<?= number_format($avgOrder,2) ?></div>
        <div class="stat-label">Avg. Order Value</div>
      </div>
    </div>

    <!-- REVENUE CHART + SERVICES -->
    <div class="grid-2">
      <div class="section-card">
        <div class="section-header"><h2>Revenue — Last 12 Months</h2></div>
        <div class="section-body">
          <?php if (empty($revByMonth)): ?>
          <p style="color:var(--muted);font-size:.83rem;">No revenue data yet.</p>
          <?php else: ?>
          <div class="month-bars">
            <?php foreach ($revByMonth as $rm): $h = $maxRev > 0 ? round(($rm['total']/$maxRev)*100) : 0; ?>
            <div class="month-bar-wrap">
              <div class="month-val">₱<?= number_format($rm['total'],0) ?></div>
              <div class="month-bar" style="height:<?= max($h,2) ?>%;"></div>
              <div class="month-label"><?= $rm['month'] ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="section-card">
        <div class="section-header"><h2>Top Services</h2></div>
        <div class="section-body">
          <?php if (empty($topSvcs)): ?>
          <p style="color:var(--muted);font-size:.83rem;">No data for this period.</p>
          <?php else: ?>
          <div class="bar-wrap">
            <?php foreach ($topSvcs as $s): $pct = round(($s['cnt']/$maxSvc)*100); ?>
            <div class="bar-row">
              <div class="bar-label" title="<?= htmlspecialchars($s['service']) ?>"><?= htmlspecialchars($s['service']) ?></div>
              <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;"></div></div>
              <div class="bar-count"><?= $s['cnt'] ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- WORKER STATS + TRANSACTIONS -->
    <div class="grid-2">
      <div class="section-card">
        <div class="section-header"><h2>Worker Performance</h2></div>
        <div style="padding:0;">
          <div class="table-wrapper">
<table class="data-table">
            <thead><tr><th>Worker</th><th>Assigned</th><th>Completed</th><th>Revenue</th></tr></thead>
            <tbody>
              <?php if (empty($workerStats)): ?>
              <tr><td colspan="4" style="text-align:center;padding:30px;color:var(--muted);">No workers yet.</td></tr>
              <?php else: foreach ($workerStats as $w): ?>
              <tr>
                <td><strong><?= htmlspecialchars($w['full_name']) ?></strong></td>
                <td><?= $w['total'] ?></td>
                <td><?= $w['done'] ?></td>
                <td>₱<?= number_format($w['revenue'],2) ?></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
</div>
        </div>
      </div>

      <div class="section-card">
        <div class="section-header"><h2>Recent Transactions</h2></div>
        <div style="padding:0;">
          <div class="table-wrapper">
<table class="data-table">
            <thead><tr><th>JO #</th><th>Customer</th><th>Amount</th><th>Method</th><th>Date</th></tr></thead>
            <tbody>
              <?php if (empty($transactions)): ?>
              <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--muted);">No transactions yet.</td></tr>
              <?php else: foreach ($transactions as $t): ?>
              <tr>
                <td><strong><?= htmlspecialchars($t['jo_number']) ?></strong></td>
                <td><?= htmlspecialchars($t['customer_name']) ?></td>
                <td>₱<?= number_format($t['amount_charged'],2) ?></td>
                <td><?= ucfirst($t['payment_method'] ?? '—') ?></td>
                <td><?= $t['completed_at'] ? date('M d, Y', strtotime($t['completed_at'])) : '—' ?></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
</div>
        </div>
      </div>
    </div>

  </div>
</div>
</body>
</html>
