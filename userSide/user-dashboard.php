<?php
require_once '../config/auth.php';
require_once '../config/db.php';
requireUser();
$activePage = 'dashboard';

$uid = userId();
$today = date('Y-m-d');

// Stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE user_id = ? AND status NOT IN ('cancelled','completed')"); $stmt->execute([$uid]); $totalAppointments = $stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT * FROM queue WHERE user_id = ? AND queue_date = ? LIMIT 1"); $stmt->execute([$uid, $today]); $myQueue = $stmt->fetch();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM job_orders WHERE user_id = ? AND status IN ('in_progress','ready')"); $stmt->execute([$uid]); $totalJobs = $stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM borrowings WHERE user_id = ? AND status IN ('borrowed','overdue')"); $stmt->execute([$uid]); $totalBorrowed = $stmt->fetchColumn();

// Recent appointments
$stmt = $pdo->prepare("SELECT * FROM appointments WHERE user_id = ? ORDER BY created_at DESC LIMIT 3"); $stmt->execute([$uid]); $recentAppointments = $stmt->fetchAll();

// Active job orders
$stmt = $pdo->prepare("SELECT * FROM job_orders WHERE user_id = ? AND status IN ('in_progress','ready') ORDER BY created_at DESC LIMIT 3"); $stmt->execute([$uid]); $activeJobs = $stmt->fetchAll();

$stepLabels = ['received'=>'Received','stripping'=>'Stripping','coating'=>'Coating','curing'=>'Curing','ready'=>'Ready for Pickup'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BRC Calamba - Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="user-dashboard.css">
</head>
<body>
<?php include '../config/user-sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
    <div class="page-title">
      <h1>Dashboard</h1>
      <p>Welcome back, <?= htmlspecialchars(userName()) ?>! Here's your activity overview.</p>
    </div>
    <div class="topbar-actions">
      <a href="user-appointment.php" class="btn btn-red">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Book Appointment
      </a>
    </div>
  </div>
  <div class="content">

    <div class="stats-grid">
      <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:#dbeafe;"><svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div></div><div class="stat-number"><?= $totalAppointments ?></div><div class="stat-label">Active Appointments</div></div>
      <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:#fef3c7;"><svg viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></div><div class="stat-number"><?= $myQueue ? '#'.$myQueue['queue_number'] : '—' ?></div><div class="stat-label">Queue Number Today</div></div>
      <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:#dcfce7;"><svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg></div></div><div class="stat-number"><?= $totalJobs ?></div><div class="stat-label">Active Job Orders</div></div>
      <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:#ede9fe;"><svg viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/></svg></div></div><div class="stat-number"><?= $totalBorrowed ?></div><div class="stat-label">Borrowed Rims</div></div>
    </div>

    <div class="two-panel">
      <div class="section-card">
        <div class="section-header"><h2>Recent Appointments</h2><a href="user-appointment.php" class="btn btn-ghost" style="padding:6px 14px;font-size:0.78rem;">View All</a></div>
        <div style="padding:0;">
          <?php if (empty($recentAppointments)): ?>
            <div class="section-body"><div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/></svg><h3>No Appointments Yet</h3><p><a href="user-appointment.php" style="color:var(--red);">Book your first appointment</a></p></div></div>
          <?php else: ?>
          <div class="table-wrapper">
<table class="data-table">
            <thead><tr><th>Service</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($recentAppointments as $a): ?>
              <?php $badges = ['pending'=>'badge-pending','confirmed'=>'badge-confirmed','in_progress'=>'badge-inprogress','completed'=>'badge-completed','cancelled'=>'badge-cancelled']; ?>
              <tr><td><?= htmlspecialchars($a['service']) ?></td><td><?= date('M d, Y', strtotime($a['preferred_date'])) ?></td><td><span class="badge <?= $badges[$a['status']] ?? '' ?>"><?= ucfirst(str_replace('_',' ',$a['status'])) ?></span></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="section-card">
        <div class="section-header"><h2>My Queue Today</h2><a href="user-queue.php" class="btn btn-ghost" style="padding:6px 14px;font-size:0.78rem;">Live View</a></div>
        <div class="section-body" style="text-align:center;padding:32px;">
          <?php if ($myQueue): ?>
            <div style="font-family:'Bebas Neue',sans-serif;font-size:5rem;color:var(--red);line-height:1;">#<?= $myQueue['queue_number'] ?></div>
            <span class="badge <?= $myQueue['status'] === 'waiting' ? 'badge-pending' : ($myQueue['status'] === 'serving' ? 'badge-confirmed' : 'badge-completed') ?>" style="margin-top:10px;"><?= ucfirst($myQueue['status']) ?></span>
          <?php else: ?>
            <div style="color:var(--muted);font-size:0.85rem;">Not in queue today</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if (!empty($activeJobs)): ?>
    <div class="section-card" style="margin-top:20px;">
      <div class="section-header"><h2>Active Job Orders</h2><a href="user-joborder.php" class="btn btn-ghost" style="padding:6px 14px;font-size:0.78rem;">View All</a></div>
      <div class="section-body" style="display:flex;flex-direction:column;gap:16px;">
        <?php
        $steps = ['received'=>1,'stripping'=>2,'coating'=>3,'curing'=>4,'ready'=>5];
        foreach ($activeJobs as $jo):
          $cur = $steps[$jo['current_step']] ?? 1;
        ?>
        <div style="border:1px solid var(--border);border-radius:10px;padding:16px;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <div><strong><?= htmlspecialchars($jo['jo_number']) ?></strong><span style="font-size:0.78rem;color:var(--muted);margin-left:10px;"><?= htmlspecialchars($jo['service']) ?></span></div>
            <span class="badge <?= $jo['status'] === 'ready' ? 'badge-completed' : 'badge-inprogress' ?>"><?= $jo['status'] === 'ready' ? 'Ready for Pickup' : 'In Progress' ?></span>
          </div>
          <div class="step-tracker" style="--step-count:5;">
            <?php foreach ($steps as $key => $num): ?>
            <div class="step <?= $num < $cur ? 'completed' : ($num === $cur ? 'active' : '') ?>">
              <div class="step-circle"><?= $num < $cur ? '✓' : $num ?></div>
              <div class="step-label"><?= $stepLabels[$key] ?></div>
            </div>
            <?php if ($num < 5): ?><div class="step-line <?= $num < $cur ? 'completed' : '' ?>"></div><?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
