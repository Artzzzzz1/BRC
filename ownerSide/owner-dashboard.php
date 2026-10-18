<?php
require_once '../config/auth.php';
require_once '../config/db.php';
requireOwner();
$activePage = 'dashboard';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ── Stats ──
try {
  $r1 = $pdo->query("SELECT COUNT(*) FROM job_orders WHERE status='in_progress'");
  $activeJOs = $r1 ? (int)$r1->fetchColumn() : 0;

  $r2 = $pdo->query("SELECT COUNT(*) FROM job_orders WHERE status='completed'");
  $completedJOs = $r2 ? (int)$r2->fetchColumn() : 0;

  $r3 = $pdo->query("SELECT COALESCE(SUM(amount_charged),0) FROM job_orders WHERE payment_status='paid'");
  $totalRevenue = $r3 ? (float)$r3->fetchColumn() : 0;

  $r4 = $pdo->query("SELECT COALESCE(SUM(amount_charged),0) FROM job_orders WHERE payment_status='paid' AND MONTH(completed_at)=MONTH(NOW()) AND YEAR(completed_at)=YEAR(NOW())");
  $monthRevenue = $r4 ? (float)$r4->fetchColumn() : 0;

  $r5 = $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'");
  $totalCustomers = $r5 ? (int)$r5->fetchColumn() : 0;

  $r6 = $pdo->query("SELECT COUNT(*) FROM users WHERE role='worker'");
  $totalWorkers = $r6 ? (int)$r6->fetchColumn() : 0;

  $r7 = $pdo->query("SELECT COUNT(*) FROM borrowings WHERE status IN ('borrowed','overdue')");
  $activeBorrowings = $r7 ? (int)$r7->fetchColumn() : 0;

  $r8 = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status='pending'");
  $pendingAppts = $r8 ? (int)$r8->fetchColumn() : 0;
} catch (Exception $e) {
  $activeJOs=$completedJOs=$totalCustomers=$totalWorkers=$activeBorrowings=$pendingAppts=0;
  $totalRevenue=$monthRevenue=0;
}

// ── Revenue by month (last 6 months) ──
try {
  $revenueStmt = $pdo->query("SELECT DATE_FORMAT(completed_at,'%b %Y') AS month, COALESCE(SUM(amount_charged),0) AS total FROM job_orders WHERE payment_status='paid' AND completed_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY YEAR(completed_at), MONTH(completed_at) ORDER BY completed_at ASC");
  $revenueByMonth = $revenueStmt ? $revenueStmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) { $revenueByMonth = []; }

// ── Top services ──
try {
  $svcStmt = $pdo->query("SELECT service, COUNT(*) AS cnt FROM job_orders GROUP BY service ORDER BY cnt DESC LIMIT 5");
  $topServices = $svcStmt ? $svcStmt->fetchAll(PDO::FETCH_ASSOC) : [];
  $maxSvc = !empty($topServices) ? max(array_column($topServices,'cnt')) : 1;
} catch (Exception $e) { $topServices = []; $maxSvc = 1; }

// ── Workers performance ──
try {
  $wrkStmt = $pdo->query("SELECT u.full_name, COUNT(jo.id) AS total, SUM(jo.status='completed') AS done FROM users u LEFT JOIN job_orders jo ON jo.worker_id=u.id WHERE u.role='worker' GROUP BY u.id ORDER BY done DESC");
  $workers = $wrkStmt ? $wrkStmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) { $workers = []; }

// ── Recent completed JOs ──
try {
  $recentStmt = $pdo->query("SELECT jo.*, u.full_name AS worker_name FROM job_orders jo LEFT JOIN users u ON jo.worker_id=u.id WHERE jo.status='completed' ORDER BY jo.completed_at DESC LIMIT 8");
  $recentCompleted = $recentStmt ? $recentStmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) { $recentCompleted = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BRC Calamba - Owner Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../adminSide/admin.css">
  <style>
    .stats-grid-6 { display:grid; grid-template-columns:repeat(4,1fr); gap:20px; margin-bottom:28px; }
    @media(max-width:1100px){ .stats-grid-6{ grid-template-columns:repeat(2,1fr); } }
    .revenue-row { display:grid; grid-template-columns:1.6fr 1fr; gap:20px; margin-bottom:24px; }
    @media(max-width:900px){ .revenue-row{ grid-template-columns:1fr; } }
    .bar-wrap { display:flex; flex-direction:column; gap:10px; }
    .bar-row { display:flex; align-items:center; gap:10px; font-size:.8rem; }
    .bar-label { width:180px; flex-shrink:0; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .bar-track { flex:1; height:10px; background:var(--border); border-radius:99px; overflow:hidden; }
    .bar-fill { height:100%; background:var(--red); border-radius:99px; transition:width .5s; }
    .bar-count { width:30px; text-align:right; font-weight:700; color:var(--muted); font-size:.75rem; }
    .month-bars { display:flex; align-items:flex-end; gap:8px; height:120px; padding-bottom:4px; }
    .month-bar-wrap { flex:1; display:flex; flex-direction:column; align-items:center; gap:4px; height:100%; justify-content:flex-end; }
    .month-bar { width:100%; background:var(--red); border-radius:4px 4px 0 0; min-height:4px; opacity:.85; transition:height .4s; }
    .month-bar:hover { opacity:1; }
    .month-label { font-size:.65rem; color:var(--muted); font-weight:600; text-align:center; }
    .worker-row { display:flex; align-items:center; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--border); }
    .worker-row:last-child { border-bottom:none; }
    .worker-avatar { width:34px; height:34px; background:var(--red); border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; font-family:'Bebas Neue',sans-serif; font-size:.85rem; flex-shrink:0; }
  </style>
</head>
<body>
<?php include '../config/owner-sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
    <div class="page-title">
      <h1>Owner Dashboard</h1>
      <p>Business overview — <?= date('F d, Y') ?></p>
    </div>
  </div>
  <div class="content">

    <!-- TOP STATS -->
    <div class="stats-grid-6">
      <div class="stat-card">
        <div class="stat-top"><div class="stat-icon" style="background:#fff0f0;"><svg viewBox="0 0 24 24" fill="none" stroke="#CC0000" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div></div>
        <div class="stat-number">₱<?= number_format($monthRevenue,0) ?></div>
        <div class="stat-label">This Month's Revenue</div>
      </div>
      <div class="stat-card">
        <div class="stat-top"><div class="stat-icon" style="background:#dcfce7;"><svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div></div>
        <div class="stat-number">₱<?= number_format($totalRevenue,0) ?></div>
        <div class="stat-label">Total Revenue</div>
      </div>
      <div class="stat-card">
        <div class="stat-top"><div class="stat-icon" style="background:#fef9c3;"><svg viewBox="0 0 24 24" fill="none" stroke="#854d0e" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg></div></div>
        <div class="stat-number"><?= $activeJOs ?></div>
        <div class="stat-label">Active Job Orders</div>
      </div>
      <div class="stat-card">
        <div class="stat-top"><div class="stat-icon" style="background:#dbeafe;"><svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div></div>
        <div class="stat-number"><?= $completedJOs ?></div>
        <div class="stat-label">Completed Orders</div>
      </div>
      <div class="stat-card">
        <div class="stat-top"><div class="stat-icon" style="background:#ede9fe;"><svg viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div></div>
        <div class="stat-number"><?= $totalCustomers ?></div>
        <div class="stat-label">Total Customers</div>
      </div>
      <div class="stat-card">
        <div class="stat-top"><div class="stat-icon" style="background:#f0fdf4;"><svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div></div>
        <div class="stat-number"><?= $totalWorkers ?></div>
        <div class="stat-label">Workers</div>
      </div>
      <div class="stat-card">
        <div class="stat-top"><div class="stat-icon" style="background:#fef3c7;"><svg viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/></svg></div></div>
        <div class="stat-number"><?= $activeBorrowings ?></div>
        <div class="stat-label">Active Borrowings</div>
      </div>
      <div class="stat-card">
        <div class="stat-top"><div class="stat-icon" style="background:#fff1f2;"><svg viewBox="0 0 24 24" fill="none" stroke="#be123c" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div></div>
        <div class="stat-number"><?= $pendingAppts ?></div>
        <div class="stat-label">Pending Appointments</div>
      </div>
    </div>

    <!-- REVENUE CHART + TOP SERVICES -->
    <div class="revenue-row">
      <div class="section-card">
        <div class="section-header"><h2>Revenue — Last 6 Months</h2></div>
        <div class="section-body">
          <?php if (empty($revenueByMonth)): ?>
          <p style="color:var(--muted);font-size:.83rem;">No revenue data yet.</p>
          <?php else:
            $maxRev = max(array_column($revenueByMonth,'total')) ?: 1;
          ?>
          <div class="month-bars">
            <?php foreach ($revenueByMonth as $rm): $h = round(($rm['total']/$maxRev)*100); ?>
            <div class="month-bar-wrap">
              <div style="font-size:.68rem;color:var(--muted);font-weight:700;">₱<?= number_format($rm['total'],0) ?></div>
              <div class="month-bar" style="height:<?= $h ?>%;"></div>
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
          <?php if (empty($topServices)): ?>
          <p style="color:var(--muted);font-size:.83rem;">No data yet.</p>
          <?php else: ?>
          <div class="bar-wrap">
            <?php foreach ($topServices as $svc): $pct = round(($svc['cnt']/$maxSvc)*100); ?>
            <div class="bar-row">
              <div class="bar-label" title="<?= htmlspecialchars($svc['service']) ?>"><?= htmlspecialchars($svc['service']) ?></div>
              <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;"></div></div>
              <div class="bar-count"><?= $svc['cnt'] ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- WORKERS + RECENT COMPLETED -->
    <div style="display:grid;grid-template-columns:1fr 1.6fr;gap:20px;margin-bottom:24px;">
      <div class="section-card">
        <div class="section-header"><h2>Worker Performance</h2></div>
        <div class="section-body">
          <?php if (empty($workers)): ?>
          <p style="color:var(--muted);font-size:.83rem;">No workers yet. Add workers in Manage Users.</p>
          <?php else: foreach ($workers as $w): ?>
          <div class="worker-row">
            <div style="display:flex;align-items:center;gap:10px;">
              <div class="worker-avatar"><?= strtoupper(substr($w['full_name'],0,2)) ?></div>
              <div>
                <div style="font-weight:700;font-size:.85rem;"><?= htmlspecialchars($w['full_name']) ?></div>
                <div style="font-size:.75rem;color:var(--muted);"><?= $w['total'] ?> assigned</div>
              </div>
            </div>
            <div style="text-align:right;">
              <div style="font-size:1.1rem;font-weight:800;color:var(--success);"><?= $w['done'] ?></div>
              <div style="font-size:.7rem;color:var(--muted);">completed</div>
            </div>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <div class="section-card">
        <div class="section-header"><h2>Recently Completed</h2></div>
        <div style="padding:0;">
          <div class="table-wrapper">
<table class="data-table">
            <thead><tr><th>JO #</th><th>Customer</th><th>Service</th><th>Amount</th><th>Worker</th><th>Date</th></tr></thead>
            <tbody>
              <?php if (empty($recentCompleted)): ?>
              <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--muted);">No completed orders yet.</td></tr>
              <?php else: foreach ($recentCompleted as $jo): ?>
              <tr>
                <td><strong><?= htmlspecialchars($jo['jo_number']) ?></strong></td>
                <td><?= htmlspecialchars($jo['customer_name']) ?></td>
                <td><?= htmlspecialchars($jo['service']) ?></td>
                <td><?= $jo['amount_charged'] ? '₱'.number_format($jo['amount_charged'],2) : '—' ?></td>
                <td><?= $jo['worker_name'] ? htmlspecialchars($jo['worker_name']) : '<span style="color:var(--muted);">—</span>' ?></td>
                <td><?= $jo['completed_at'] ? date('M d', strtotime($jo['completed_at'])) : '—' ?></td>
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
