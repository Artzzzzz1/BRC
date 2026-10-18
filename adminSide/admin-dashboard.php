<?php
require_once '../config/auth.php';
require_once '../config/db.php';
requireAdmin();
$activePage = 'dashboard';
$today = date('Y-m-d');

$todayAppts   = $pdo->query("SELECT COUNT(*) FROM appointments WHERE preferred_date='$today'")->fetchColumn();
$activeQueue  = $pdo->query("SELECT COUNT(*) FROM queue WHERE queue_date='$today' AND status IN ('waiting','serving')")->fetchColumn();
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$borrowed     = $pdo->query("SELECT COUNT(*) FROM borrowings WHERE status IN ('borrowed','overdue')")->fetchColumn();

$pending  = $pdo->query("SELECT a.*, u.email FROM appointments a LEFT JOIN users u ON a.user_id=u.id WHERE a.status='pending' ORDER BY a.created_at ASC LIMIT 5")->fetchAll();
$queue    = $pdo->query("SELECT * FROM queue WHERE queue_date='$today' AND status IN ('waiting','serving') ORDER BY queue_number ASC LIMIT 8")->fetchAll();
$activeJOs= $pdo->query("SELECT * FROM job_orders WHERE status IN ('in_progress','ready') ORDER BY created_at DESC LIMIT 5")->fetchAll();
$stepLabels = ['received'=>'Received','stripping'=>'Stripping','coating'=>'Coating','curing'=>'Curing','ready'=>'Ready'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BRC Calamba - Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="admin.css">
</head>
<body>
<?php include '../config/admin-sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
    <div class="page-title"><h1>Dashboard</h1><p>Welcome back! Here's what's happening today — <?= date('F d, Y') ?></p></div>
    <div class="topbar-actions">
      <a href="admin-queue.php" class="btn btn-red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Add to Queue</a>
    </div>
  </div>
  <div class="content">

    <div class="stats-grid">
      <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:#dbeafe;"><svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div></div><div class="stat-number"><?= $todayAppts ?></div><div class="stat-label">Today's Appointments</div></div>
      <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:#fef3c7;"><svg viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></div><div class="stat-number"><?= $activeQueue ?></div><div class="stat-label">Active Queue</div></div>
      <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:#dcfce7;"><svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div></div><div class="stat-number"><?= $totalCustomers ?></div><div class="stat-label">Total Customers</div></div>
      <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:#ede9fe;"><svg viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/></svg></div></div><div class="stat-number"><?= $borrowed ?></div><div class="stat-label">Borrowed Rims</div></div>
    </div>

    <div class="two-panel">
      <!-- PENDING APPOINTMENTS -->
      <div class="section-card">
        <div class="section-header"><h2>Pending Bookings</h2><a href="admin-appointments.php" class="btn btn-ghost" style="padding:6px 14px;font-size:.78rem;">View All</a></div>
        <div style="padding:0;">
          <?php if (empty($pending)): ?>
            <div class="section-body"><div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/></svg><h3>No Pending Bookings</h3></div></div>
          <?php else: ?>
          <div class="table-wrapper">
<table class="data-table">
            <thead><tr><th>Customer</th><th>Service</th><th>Date</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($pending as $a): ?>
              <tr>
                <td><strong><?= htmlspecialchars($a['customer_name']) ?></strong></td>
                <td style="font-size:.78rem;"><?= htmlspecialchars($a['service']) ?></td>
                <td style="font-size:.78rem;"><?= date('M d', strtotime($a['preferred_date'])) ?></td>
                <td><a href="admin-appointments.php" class="btn btn-ghost" style="padding:4px 10px;font-size:.73rem;">Review</a></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- TODAY'S QUEUE -->
      <div class="section-card">
        <div class="section-header"><h2>Today's Queue</h2><a href="admin-queue.php" class="btn btn-ghost" style="padding:6px 14px;font-size:.78rem;">Manage</a></div>
        <div style="padding:0;">
          <?php if (empty($queue)): ?>
            <div class="section-body"><div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><h3>Queue is Empty</h3></div></div>
          <?php else: ?>
          <div class="table-wrapper">
<table class="data-table">
            <thead><tr><th>#</th><th>Customer</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($queue as $q): ?>
              <tr>
                <td><strong style="font-family:'Bebas Neue',sans-serif;font-size:1.1rem;color:var(--red);">#<?= $q['queue_number'] ?></strong></td>
                <td><?= htmlspecialchars($q['customer_name']) ?></td>
                <td><span class="badge <?= $q['status']==='serving'?'badge-confirmed':'badge-pending' ?>"><?= ucfirst($q['status']) ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ACTIVE JOB ORDERS -->
    <?php if (!empty($activeJOs)): ?>
    <div class="section-card" style="margin-top:20px;">
      <div class="section-header"><h2>Active Job Orders</h2><a href="admin-joborders.php" class="btn btn-ghost" style="padding:6px 14px;font-size:.78rem;">View All</a></div>
      <div style="padding:0;">
        <div class="table-wrapper">
<table class="data-table">
          <thead><tr><th>JO #</th><th>Customer</th><th>Service</th><th>Current Step</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($activeJOs as $jo): ?>
            <tr>
              <td><strong><?= htmlspecialchars($jo['jo_number']) ?></strong></td>
              <td><?= htmlspecialchars($jo['customer_name']) ?></td>
              <td style="font-size:.8rem;"><?= htmlspecialchars($jo['service']) ?></td>
              <td><span class="badge badge-inprogress"><?= $stepLabels[$jo['current_step']] ?? ucfirst($jo['current_step']) ?></span></td>
              <td><span class="badge <?= $jo['status']==='ready'?'badge-completed':'badge-inprogress' ?>"><?= $jo['status']==='ready'?'Ready for Pickup':'In Progress' ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
</div>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
