<?php
require_once '../config/auth.php';
require_once '../config/db.php';
requireAdmin();
$activePage = 'reports';

$range = $_GET['range'] ?? 'month';
$where = match($range) {
  'today' => "DATE(completed_at) = CURDATE()",
  'week'  => "completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
  'year'  => "YEAR(completed_at) = YEAR(NOW())",
  default => "MONTH(completed_at) = MONTH(NOW()) AND YEAR(completed_at) = YEAR(NOW())",
};

$revenue     = $pdo->query("SELECT COALESCE(SUM(amount_charged),0) FROM job_orders WHERE payment_status='paid' AND $where")->fetchColumn();
$completed   = $pdo->query("SELECT COUNT(*) FROM job_orders WHERE status='completed' AND $where")->fetchColumn();
$totalAppts  = $pdo->query("SELECT COUNT(*) FROM appointments WHERE DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$newCustomers= $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer' AND DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

// Services breakdown
$services = $pdo->query("SELECT service, COUNT(*) as cnt, SUM(amount_charged) as rev FROM job_orders WHERE status='completed' AND $where GROUP BY service ORDER BY cnt DESC")->fetchAll();

// Daily revenue for this month
$dailyRevenue = $pdo->query("SELECT DATE(completed_at) as day, SUM(amount_charged) as rev, COUNT(*) as cnt FROM job_orders WHERE payment_status='paid' AND MONTH(completed_at)=MONTH(NOW()) AND YEAR(completed_at)=YEAR(NOW()) GROUP BY DATE(completed_at) ORDER BY day ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BRC Calamba - Reports</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="admin.css">
</head>
<body>
<?php include '../config/admin-sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
    <div class="page-title"><h1>Reports</h1><p>Revenue, services, and customer overview</p></div>
    <div class="topbar-actions">
      <form method="GET" style="display:flex;gap:8px;">
        <select name="range" onchange="this.form.submit()" style="padding:9px 14px;border:1.5px solid var(--border);border-radius:8px;font-family:'Montserrat',sans-serif;font-size:.82rem;outline:none;">
          <option value="today" <?= $range==='today'?'selected':'' ?>>Today</option>
          <option value="week"  <?= $range==='week'?'selected':'' ?>>This Week</option>
          <option value="month" <?= $range==='month'?'selected':'' ?>>This Month</option>
          <option value="year"  <?= $range==='year'?'selected':'' ?>>This Year</option>
        </select>
      </form>
    </div>
  </div>
  <div class="content">
    <div class="stats-grid" style="margin-bottom:28px;">
      <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:#dcfce7;"><svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div></div><div class="stat-number">₱<?= number_format($revenue, 2) ?></div><div class="stat-label">Total Revenue</div></div>
      <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:#dbeafe;"><svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg></div></div><div class="stat-number"><?= $completed ?></div><div class="stat-label">Completed Services</div></div>
      <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:#fef3c7;"><svg viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div></div><div class="stat-number"><?= $newCustomers ?></div><div class="stat-label">New Customers (30d)</div></div>
      <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:#ede9fe;"><svg viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/></svg></div></div><div class="stat-number"><?= $totalAppts ?></div><div class="stat-label">Total Appointments (30d)</div></div>
    </div>

    <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:24px;margin-bottom:24px;">
      <!-- DAILY REVENUE TABLE -->
      <div class="section-card">
        <div class="section-header"><h2>Daily Revenue This Month</h2></div>
        <div style="padding:0;">
          <?php if (empty($dailyRevenue)): ?>
            <div class="section-body" style="text-align:center;color:var(--muted);font-size:.85rem;">No revenue data yet.</div>
          <?php else: ?>
          <div class="table-wrapper">
<table class="data-table">
            <thead><tr><th>Date</th><th>Orders</th><th>Revenue</th></tr></thead>
            <tbody>
              <?php foreach ($dailyRevenue as $d): ?>
              <tr>
                <td><?= date('M d, Y', strtotime($d['day'])) ?></td>
                <td><?= $d['cnt'] ?></td>
                <td><strong>₱<?= number_format($d['rev'], 2) ?></strong></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- SERVICES BREAKDOWN -->
      <div class="section-card">
        <div class="section-header"><h2>Services Breakdown</h2></div>
        <div style="padding:0;">
          <?php if (empty($services)): ?>
            <div class="section-body" style="text-align:center;color:var(--muted);font-size:.85rem;">No data yet.</div>
          <?php else: ?>
          <div class="table-wrapper">
<table class="data-table">
            <thead><tr><th>Service</th><th>Orders</th><th>Revenue</th></tr></thead>
            <tbody>
              <?php foreach ($services as $s): ?>
              <tr>
                <td style="font-size:.8rem;"><?= htmlspecialchars($s['service']) ?></td>
                <td><?= $s['cnt'] ?></td>
                <td>₱<?= number_format($s['rev'] ?? 0, 2) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>
</body>
</html>
