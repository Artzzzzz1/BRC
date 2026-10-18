<?php
require_once '../config/auth.php';
require_once '../config/db.php';
requireWorker();
$activePage = 'all';
$workerId = currentUserId();

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
  $stmt = $pdo->query("SELECT jo.*, u.full_name AS worker_name FROM job_orders jo LEFT JOIN users u ON jo.worker_id = u.id WHERE jo.status IN ('in_progress','ready') ORDER BY jo.created_at DESC");
  $allJobs = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) { $allJobs = []; }

$stepLabels = ['received'=>'Received','stripping'=>'Stripping','coating'=>'Coating','curing'=>'Curing','ready'=>'Ready'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BRC Calamba - All Jobs</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../adminSide/admin.css">
</head>
<body>
<?php include '../config/worker-sidebar.php'; ?>
<div class="main">
  <div class="topbar">
  <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <line x1="3" y1="12" x2="21" y2="12"/>
      <line x1="3" y1="6" x2="21" y2="6"/>
      <line x1="3" y1="18" x2="21" y2="18"/>
    </svg>
  </button>
    <div class="page-title">
        <h1>All Active Jobs</h1>
        <p>Read-only overview of all current job orders</p>
    </div>
  </div>
  <div class="content">
    <div class="section-card">
      <div class="section-header"><h2>Active Job Orders</h2></div>
      <div style="padding:0;">
        <table class="data-table">
          <thead>
            <tr><th>JO #</th><th>Customer</th><th>Service</th><th>Rims</th><th>Current Step</th><th>Status</th><th>Assigned To</th><th>Est. Completion</th></tr>
          </thead>
          <tbody>
            <?php if (empty($allJobs)): ?>
            <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--muted);">No active job orders.</td></tr>
            <?php else: foreach ($allJobs as $jo): ?>
            <tr <?= $jo['worker_id'] == $workerId ? 'style="background:#fff8f8;"' : '' ?>>
              <td>
                <strong><?= htmlspecialchars($jo['jo_number']) ?></strong>
                <?php if ($jo['worker_id'] == $workerId): ?>
                <br><span style="font-size:.7rem;color:#CC0000;font-weight:700;">← Yours</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($jo['customer_name']) ?><br><small style="color:var(--muted);"><?= htmlspecialchars($jo['contact_number']) ?></small></td>
              <td><?= htmlspecialchars($jo['service']) ?></td>
              <td><?= htmlspecialchars($jo['rim_count'].' '.$jo['rim_size']) ?></td>
              <td><span class="badge badge-inprogress"><?= $stepLabels[$jo['current_step']] ?? ucfirst($jo['current_step']) ?></span></td>
              <td><span class="badge <?= $jo['status']==='ready'?'badge-completed':'badge-inprogress' ?>"><?= $jo['status']==='ready'?'Ready':'In Progress' ?></span></td>
              <td><?= $jo['worker_name'] ? htmlspecialchars($jo['worker_name']) : '<span style="color:var(--muted);">Unassigned</span>' ?></td>
              <td><?= $jo['est_completion'] ? date('M d, Y', strtotime($jo['est_completion'])) : '—' ?></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</body>
</html>
