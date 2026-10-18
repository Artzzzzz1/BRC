<?php
require_once '../config/auth.php';
require_once '../config/db.php';
requireUser();
$activePage = 'joborder';

// Active job orders for this user (by user_id OR by contact number match)
$userContact = '';
try {
  $cStmt = $pdo->prepare("SELECT contact_number FROM users WHERE id = ?");
  $cStmt->execute([userId()]);
  $userContact = $cStmt->fetchColumn() ?: '';
} catch (Exception $e) {}

try {
  $stmt = $pdo->prepare("SELECT * FROM job_orders WHERE (user_id = ? OR (contact_number = ? AND ? != '')) AND status IN ('in_progress','ready') ORDER BY created_at DESC");
  $stmt->execute([userId(), $userContact, $userContact]);
  $activeJobs = $stmt->fetchAll();
} catch (Exception $e) { $activeJobs = []; }

// History
try {
  $stmt2 = $pdo->prepare("SELECT * FROM job_orders WHERE (user_id = ? OR (contact_number = ? AND ? != '')) AND status IN ('completed','cancelled') ORDER BY created_at DESC");
  $stmt2->execute([userId(), $userContact, $userContact]);
  $history = $stmt2->fetchAll();
} catch (Exception $e) { $history = []; }

$steps = ['received' => 1, 'stripping' => 2, 'coating' => 3, 'curing' => 4, 'ready' => 5];
$stepLabels = ['received' => 'Received', 'stripping' => 'Stripping', 'coating' => 'Coating', 'curing' => 'Curing', 'ready' => 'Ready'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BRC Calamba - My Job Orders</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="user-dashboard.css">
</head>
<body>
<?php include '../config/user-sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
    <div class="page-title"><h1>My Job Orders</h1><p>Track your rim powder coating progress</p></div>
  </div>
  <div class="content">

    <?php if (empty($activeJobs)): ?>
    <div class="section-card" style="margin-bottom:24px;">
      <div class="section-body"><div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        <h3>No Active Job Orders</h3><p>Your job orders will appear here once admin creates them for your appointment.</p>
      </div></div>
    </div>
    <?php else: ?>
      <?php foreach ($activeJobs as $jo): ?>
      <?php $currentStep = $steps[$jo['current_step']] ?? 1; ?>
      <div class="section-card" style="margin-bottom:24px;">
        <div class="section-header">
          <div>
            <h2><?= htmlspecialchars($jo['jo_number']) ?></h2>
            <div style="font-size:0.78rem;color:var(--muted);margin-top:2px;"><?= htmlspecialchars($jo['service']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($jo['rim_count'] . ' ' . $jo['rim_size']) ?></div>
          </div>
          <span class="badge <?= $jo['status'] === 'ready' ? 'badge-completed' : 'badge-inprogress' ?>"><?= $jo['status'] === 'ready' ? 'Ready for Pickup' : 'In Progress' ?></span>
        </div>
        <div class="section-body">
          <?php if ($jo['status'] === 'ready'): ?>
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px 20px;color:#15803d;font-weight:700;font-size:0.88rem;margin-bottom:20px;">🎉 Your rims are ready for pickup! Please visit our shop.</div>
          <?php endif; ?>
          <!-- STEP TRACKER -->
          <div class="step-tracker">
            <?php foreach ($stepLabels as $key => $label):
              $stepNum = $steps[$key];
              $cls = $stepNum < $currentStep ? 'done' : ($stepNum === $currentStep ? 'active' : '');
            ?>
            <div class="step <?= $cls ?>">
              <div class="step-circle"><?= $stepNum < $currentStep ? '✓' : $stepNum ?></div>
              <div class="step-label"><?= $label ?></div>
            </div>
            <?php if ($stepNum < 5): ?><div class="step-line <?= $stepNum < $currentStep ? 'done' : '' ?>"></div><?php endif; ?>
            <?php endforeach; ?>
          </div>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:20px;">
            <div style="background:var(--bg);border-radius:8px;padding:12px;"><div style="font-size:0.72rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Motorcycle</div><div style="font-size:0.85rem;font-weight:600;margin-top:4px;"><?= htmlspecialchars($jo['vehicle'] ?: '—') ?></div></div>
            <div style="background:var(--bg);border-radius:8px;padding:12px;"><div style="font-size:0.72rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Color / Finish</div><div style="font-size:0.85rem;font-weight:600;margin-top:4px;"><?= htmlspecialchars($jo['color_finish'] ?: '—') ?></div></div>
            <div style="background:var(--bg);border-radius:8px;padding:12px;"><div style="font-size:0.72rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Est. Completion</div><div style="font-size:0.85rem;font-weight:600;margin-top:4px;"><?= $jo['est_completion'] ? date('M d, Y', strtotime($jo['est_completion'])) : '—' ?></div></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <div class="section-card">
      <div class="section-header"><h2>Job Order History</h2></div>
      <div style="padding:0;">
        <?php if (empty($history)): ?>
          <div class="section-body"><div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg><h3>No History Yet</h3></div></div>
        <?php else: ?>
        <div class="table-wrapper">
<table class="data-table">
          <thead><tr><th>JO #</th><th>Service</th><th>Rims</th><th>Color</th><th>Completed</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($history as $jo): ?>
            <tr>
              <td><strong><?= htmlspecialchars($jo['jo_number']) ?></strong></td>
              <td><?= htmlspecialchars($jo['service']) ?></td>
              <td><?= htmlspecialchars($jo['rim_count'] . ' ' . $jo['rim_size']) ?></td>
              <td><?= htmlspecialchars($jo['color_finish'] ?: '—') ?></td>
              <td><?= $jo['completed_at'] ? date('M d, Y', strtotime($jo['completed_at'])) : '—' ?></td>
              <td><span class="badge <?= $jo['status'] === 'completed' ? 'badge-completed' : 'badge-cancelled' ?>"><?= ucfirst($jo['status']) ?></span></td>
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
</body>
</html>