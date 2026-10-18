<?php
require_once '../config/auth.php';
require_once '../config/db.php';
require_once '../config/joborder-actions.php';
requireWorker();
$activePage = 'dashboard';
$msg = '';
$workerId = currentUserId();

// UPDATE STEP — only allowed on jobs assigned to this worker (enforced inside jo_update_step)
if (isset($_POST['action']) && $_POST['action'] === 'update_step') {
  [, $msg] = jo_update_step($pdo, (int)($_POST['jo_id'] ?? 0), (string)($_POST['step'] ?? ''), (int)$workerId, userName(), (int)$workerId);
}

// Fetch jobs assigned to this worker
try {
  $stmtMine = $pdo->prepare("SELECT jo.*, q.queue_number FROM job_orders jo LEFT JOIN queue q ON jo.queue_id = q.id WHERE jo.worker_id = ? AND jo.status IN ('in_progress','ready') ORDER BY jo.created_at DESC");
  $stmtMine->execute([$workerId]);
  $myJobs = $stmtMine->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $myJobs = []; }

// Stats
$totalAssigned   = count($myJobs);
$readyCount      = count(array_filter($myJobs, fn($j) => $j['status'] === 'ready'));
$inProgressCount = count(array_filter($myJobs, fn($j) => $j['status'] === 'in_progress'));

try {
  $stmtDone = $pdo->prepare("SELECT COUNT(*) FROM job_orders WHERE worker_id = ? AND status = 'completed'");
  $stmtDone->execute([$workerId]);
  $completedCount = (int)$stmtDone->fetchColumn();
} catch (Exception $e) { $completedCount = 0; }

$stepOrder  = ['received'=>1,'stripping'=>2,'coating'=>3,'curing'=>4,'ready'=>5];
$stepLabels = ['received'=>'Received','stripping'=>'Stripping','coating'=>'Coating','curing'=>'Curing','ready'=>'Ready'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BRC Calamba - My Jobs</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../adminSide/admin.css">
  <style>
    .step-select {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
      margin-top: 8px;
    }
    .step-btn {
      padding: 6px 14px;
      border-radius: 20px;
      border: 1.5px solid var(--border);
      background: white;
      font-family: 'Montserrat', sans-serif;
      font-size: .75rem;
      font-weight: 600;
      cursor: pointer;
      color: var(--muted);
      transition: all .15s;
    }
    .step-btn.active {
      background: var(--red);
      border-color: var(--red);
      color: white;
    }
    .step-btn:hover:not(.active) {
      background: var(--bg);
      border-color: #ccc;
    }
    .assigned-badge {
      background: #fff0f0;
      color: #CC0000;
      border: 1px solid #fecdd3;
      padding: 3px 10px;
      border-radius: 20px;
      font-size: .7rem;
      font-weight: 700;
    }
    .job-meta {
      margin-top: 12px;
      display: flex;
      gap: 16px;
      flex-wrap: wrap;
      font-size: .78rem;
      color: var(--muted);
    }
    .update-form {
      margin-top: 16px;
      display: flex;
      align-items: flex-start;
      gap: 10px;
      flex-wrap: wrap;
    }
    .update-form label {
      font-size: .8rem;
      font-weight: 700;
      padding-top: 6px;
      white-space: nowrap;
    }

    /* ── Responsive tweaks specific to this page ── */
    @media (max-width: 600px) {
      .job-header-inner {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 8px;
      }
      .step-btn {
        padding: 6px 10px;
        font-size: .7rem;
      }
      .update-form {
        flex-direction: column;
        gap: 6px;
      }
    }
  </style>
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
      <h1>My Assigned Jobs</h1>
      <p>Jobs assigned to you — update progress as you work</p>
    </div>
  </div>

  <div class="content">

    <?php if ($msg): ?>
    <div style="background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;padding:12px 16px;border-radius:8px;font-size:.83rem;font-weight:600;margin-bottom:20px;">
      <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-grid" style="margin-bottom:24px;">
      <div class="stat-card">
        <div class="stat-top">
          <div class="stat-icon" style="background:#fff0f0;">
            <svg viewBox="0 0 24 24" fill="none" stroke="#CC0000" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
          </div>
        </div>
        <div class="stat-number"><?= $totalAssigned ?></div>
        <div class="stat-label">Active Assignments</div>
      </div>
      <div class="stat-card">
        <div class="stat-top">
          <div class="stat-icon" style="background:#fef9c3;">
            <svg viewBox="0 0 24 24" fill="none" stroke="#854d0e" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </div>
        </div>
        <div class="stat-number"><?= $inProgressCount ?></div>
        <div class="stat-label">In Progress</div>
      </div>
      <div class="stat-card">
        <div class="stat-top">
          <div class="stat-icon" style="background:#dcfce7;">
            <svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
          </div>
        </div>
        <div class="stat-number"><?= $readyCount ?></div>
        <div class="stat-label">Ready for Pickup</div>
      </div>
      <div class="stat-card">
        <div class="stat-top">
          <div class="stat-icon" style="background:#ede9fe;">
            <svg viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          </div>
        </div>
        <div class="stat-number"><?= $completedCount ?></div>
        <div class="stat-label">Completed Total</div>
      </div>
    </div>

    <!-- ASSIGNED JOBS -->
    <?php if (empty($myJobs)): ?>
    <div class="section-card">
      <div class="section-body">
        <div class="empty-state">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
          <h3>No Jobs Assigned Yet</h3>
          <p>The admin will assign jobs to you. Check back soon.</p>
        </div>
      </div>
    </div>

    <?php else: ?>
    <?php foreach ($myJobs as $jo): $cur = $stepOrder[$jo['current_step']] ?? 1; ?>
    <div class="section-card" style="margin-bottom:20px;">
      <div class="section-header">
        <div class="job-header-inner" style="display:flex;align-items:center;justify-content:space-between;width:100%;gap:10px;flex-wrap:wrap;">
          <div>
            <h2 style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
              <?= htmlspecialchars($jo['jo_number']) ?>
              <span class="assigned-badge">Assigned to you</span>
            </h2>
            <div style="font-size:.78rem;color:var(--muted);margin-top:4px;">
              <?= htmlspecialchars($jo['customer_name']) ?> &middot; <?= htmlspecialchars($jo['service']) ?>
            </div>
          </div>
          <span class="badge <?= $jo['status']==='ready' ? 'badge-completed' : 'badge-inprogress' ?>">
            <?= $jo['status']==='ready' ? 'Ready for Pickup' : 'In Progress' ?>
          </span>
        </div>
      </div>

      <div class="section-body">
        <!-- STEP TRACKER -->
        <div class="step-tracker">
          <?php foreach ($stepLabels as $key => $label): $n = $stepOrder[$key]; ?>
          <div class="step <?= $n < $cur ? 'done' : ($n === $cur ? 'active' : '') ?>">
            <div class="step-circle"><?= $n < $cur ? '✓' : $n ?></div>
            <div class="step-label"><?= $label ?></div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- UPDATE STEP -->
        <form method="POST" class="update-form">
          <input type="hidden" name="action" value="update_step">
          <input type="hidden" name="jo_id" value="<?= $jo['id'] ?>">
          <label>Update Step:</label>
          <div class="step-select">
            <?php foreach ($stepLabels as $key => $label): ?>
            <button type="submit" name="step" value="<?= $key ?>"
              class="step-btn <?= $jo['current_step'] === $key ? 'active' : '' ?>">
              <?= $label ?>
            </button>
            <?php endforeach; ?>
          </div>
        </form>

        <!-- Job Details -->
        <div class="job-meta">
          <?php if ($jo['contact_number']): ?><span>📞 <?= htmlspecialchars($jo['contact_number']) ?></span><?php endif; ?>
          <?php if ($jo['vehicle']): ?><span>🏍 <?= htmlspecialchars($jo['vehicle']) ?></span><?php endif; ?>
          <?php if ($jo['rim_count']): ?><span>🔩 <?= htmlspecialchars($jo['rim_count'].' '.$jo['rim_size']) ?></span><?php endif; ?>
          <?php if ($jo['color_finish']): ?><span>🎨 <?= htmlspecialchars($jo['color_finish']) ?></span><?php endif; ?>
          <?php if ($jo['est_completion']): ?><span>📅 Est: <?= date('M d, Y', strtotime($jo['est_completion'])) ?></span><?php endif; ?>
          <?php if ($jo['notes']): ?><span>📝 <?= htmlspecialchars($jo['notes']) ?></span><?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
