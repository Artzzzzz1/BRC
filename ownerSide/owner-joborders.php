<?php
require_once '../config/auth.php';
require_once '../config/db.php';
require_once '../config/joborder-actions.php';
requireOwner();
$activePage = 'joborders';
$msg = '';
$actorId   = (int)userId();
$actorName = userName();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  switch ($_POST['action']) {
    case 'assign_worker':
      [, $msg] = jo_assign_worker($pdo, (int)($_POST['jo_id'] ?? 0), !empty($_POST['worker_id']) ? (int)$_POST['worker_id'] : null);
      break;
    case 'update_step':
      [, $msg] = jo_update_step($pdo, (int)($_POST['jo_id'] ?? 0), (string)($_POST['step'] ?? ''), $actorId, $actorName);
      break;
    case 'complete':
      [, $msg] = jo_complete($pdo, (int)($_POST['jo_id'] ?? 0), $_POST['amount'] ?? '', (string)($_POST['payment_method'] ?? ''), $actorId, $actorName);
      break;
  }
}

// Fetch all job orders
try {
  $stmtAll = $pdo->query("SELECT jo.*, q.queue_number, u.full_name AS worker_name FROM job_orders jo LEFT JOIN queue q ON jo.queue_id=q.id LEFT JOIN users u ON jo.worker_id=u.id ORDER BY jo.created_at DESC");
  $jobOrders = $stmtAll ? $stmtAll->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) { error_log('owner-joborders list: ' . $e->getMessage()); $jobOrders = []; $msg = "Could not load job orders."; }

$activeJOs  = array_values(array_filter($jobOrders, fn($j) => in_array($j['status'] ?? '', ['in_progress','ready'])));
$historyJOs = array_values(array_filter($jobOrders, fn($j) => in_array($j['status'] ?? '', ['completed','cancelled'])));

$stepOrder  = ['received'=>1,'stripping'=>2,'coating'=>3,'curing'=>4,'ready'=>5];
$stepLabels = ['received'=>'Received','stripping'=>'Stripping','coating'=>'Coating','curing'=>'Curing','ready'=>'Ready'];

try {
  $stmtW = $pdo->query("SELECT id, full_name FROM users WHERE role='worker' ORDER BY full_name");
  $workers = $stmtW ? $stmtW->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) { $workers = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BRC Calamba - Job Orders (Owner)</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../adminSide/admin.css">
  <style>
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;padding:20px;box-sizing:border-box}.modal-overlay.open{display:flex}.modal{background:#fff;border-radius:12px;padding:28px;width:100%;max-width:560px;box-shadow:0 20px 60px rgba(0,0,0,.2);max-height:90vh;overflow-y:auto;overflow-x:hidden;box-sizing:border-box}.modal h3{font-size:1.1rem;font-weight:700;margin-bottom:20px}.modal-footer{display:flex;gap:12px;margin-top:24px;justify-content:flex-end}
    .modal .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;width:100%;box-sizing:border-box}.modal .form-group{display:flex;flex-direction:column;gap:5px;min-width:0}.modal .form-group.span2{grid-column:span 2}.modal .form-group label{font-size:.8rem;font-weight:700;color:var(--text)}.modal .form-group input,.modal .form-group select{width:100%;box-sizing:border-box;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:'Montserrat',sans-serif;font-size:.83rem;color:var(--text);background:#fff;outline:none}
    .step-select{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}.step-btn{padding:6px 12px;border-radius:20px;border:1.5px solid var(--border);background:white;font-family:'Montserrat',sans-serif;font-size:.75rem;font-weight:600;cursor:pointer;color:var(--muted)}.step-btn.active{background:var(--red);border-color:var(--red);color:white}
  </style>
</head>
<body>
<?php include '../config/owner-sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
    <div class="page-title"><h1>Job Orders</h1><p>View all orders, assign workers, update progress</p></div>
  </div>
  <div class="content">
    <?php if ($msg): ?>
    <div style="background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;padding:12px 16px;border-radius:8px;font-size:.83rem;font-weight:600;margin-bottom:20px;"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- ACTIVE -->
    <?php if (empty($activeJOs)): ?>
    <div class="section-card" style="margin-bottom:24px;"><div class="section-body"><div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg><h3>No Active Job Orders</h3></div></div></div>
    <?php else: foreach ($activeJOs as $jo): $cur = $stepOrder[$jo['current_step']] ?? 1; ?>
    <div class="section-card" style="margin-bottom:20px;">
      <div class="section-header">
        <div>
          <h2><?= htmlspecialchars($jo['jo_number']) ?></h2>
          <div style="font-size:.78rem;color:var(--muted);"><?= htmlspecialchars($jo['customer_name']) ?> &middot; <?= htmlspecialchars($jo['service']) ?></div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
          <span class="badge <?= $jo['status']==='ready'?'badge-completed':'badge-inprogress' ?>"><?= $jo['status']==='ready'?'Ready for Pickup':'In Progress' ?></span>
          <?php if ($jo['status']==='ready'): ?>
          <button class="btn btn-red" style="padding:5px 12px;font-size:.75rem;" onclick="document.getElementById('payModal<?= $jo['id'] ?>').classList.add('open')">Complete &amp; Pay</button>
          <?php endif; ?>
        </div>
      </div>
      <div class="section-body">
        <div class="step-tracker">
          <?php foreach ($stepLabels as $key => $label): $n = $stepOrder[$key]; ?>
          <div class="step <?= $n < $cur ? 'done' : ($n===$cur?'active':'') ?>">
            <div class="step-circle"><?= $n < $cur ? '✓' : $n ?></div>
            <div class="step-label"><?= $label ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <form method="POST" style="margin-top:16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
          <input type="hidden" name="action" value="update_step">
          <input type="hidden" name="jo_id" value="<?= $jo['id'] ?>">
          <label style="font-size:.8rem;font-weight:700;">Update Step:</label>
          <div class="step-select">
            <?php foreach ($stepLabels as $key => $label): ?>
            <button type="submit" name="step" value="<?= $key ?>" class="step-btn <?= $jo['current_step']===$key?'active':'' ?>"><?= $label ?></button>
            <?php endforeach; ?>
          </div>
        </form>
        <div style="margin-top:12px;display:flex;gap:20px;flex-wrap:wrap;font-size:.78rem;color:var(--muted);">
          <?php if ($jo['contact_number']): ?><span>📞 <?= htmlspecialchars($jo['contact_number']) ?></span><?php endif; ?>
          <?php if ($jo['vehicle']): ?><span>🏍 <?= htmlspecialchars($jo['vehicle']) ?></span><?php endif; ?>
          <?php if ($jo['rim_count']): ?><span>🔩 <?= htmlspecialchars($jo['rim_count'].' '.$jo['rim_size']) ?></span><?php endif; ?>
          <?php if ($jo['color_finish']): ?><span>🎨 <?= htmlspecialchars($jo['color_finish']) ?></span><?php endif; ?>
          <?php if ($jo['est_completion']): ?><span>📅 Est: <?= date('M d, Y', strtotime($jo['est_completion'])) ?></span><?php endif; ?>
        </div>
        <!-- ASSIGN WORKER -->
        <?php if (!empty($workers)): ?>
        <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
          <label style="font-size:.8rem;font-weight:700;color:var(--muted);">Assigned Worker:</label>
          <form method="POST" style="display:flex;align-items:center;gap:8px;">
            <input type="hidden" name="action" value="assign_worker">
            <input type="hidden" name="jo_id" value="<?= $jo['id'] ?>">
            <select name="worker_id" style="padding:6px 10px;border:1.5px solid var(--border);border-radius:8px;font-family:'Montserrat',sans-serif;font-size:.8rem;outline:none;">
              <option value="">— Unassigned —</option>
              <?php foreach ($workers as $w): ?>
              <option value="<?= $w['id'] ?>" <?= $jo['worker_id']==$w['id']?'selected':'' ?>><?= htmlspecialchars($w['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-ghost" style="padding:6px 14px;font-size:.78rem;">Assign</button>
          </form>
          <?php if ($jo['worker_name']): ?>
          <span style="font-size:.76rem;background:#dcfce7;color:#15803d;padding:3px 10px;border-radius:20px;font-weight:700;">✓ <?= htmlspecialchars($jo['worker_name']) ?></span>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <!-- PAY MODAL -->
    <div class="modal-overlay" id="payModal<?= $jo['id'] ?>">
      <div class="modal">
        <h3>Complete &mdash; <?= htmlspecialchars($jo['jo_number']) ?></h3>
        <form method="POST">
          <input type="hidden" name="action" value="complete">
          <input type="hidden" name="jo_id" value="<?= $jo['id'] ?>">
          <div class="form-grid">
            <div class="form-group span2"><label>Amount Charged (₱)</label><input type="number" name="amount" step="0.01" min="0" placeholder="0.00" required></div>
            <div class="form-group span2"><label>Payment Method</label><select name="payment_method"><option value="cash">Cash</option><option value="gcash">GCash</option><option value="other">Other</option></select></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('payModal<?= $jo['id'] ?>').classList.remove('open')">Cancel</button>
            <button type="submit" class="btn btn-red">Confirm &amp; Complete</button>
          </div>
        </form>
      </div>
    </div>
    <?php endforeach; endif; ?>

    <!-- HISTORY -->
    <div class="section-card" style="margin-top:20px;">
      <div class="section-header"><h2>Job Order History</h2></div>
      <div style="padding:0;">
        <div class="table-wrapper">
<table class="data-table">
          <thead><tr><th>JO #</th><th>Customer</th><th>Service</th><th>Rims</th><th>Amount</th><th>Payment</th><th>Worker</th><th>Completed</th></tr></thead>
          <tbody>
            <?php if (empty($historyJOs)): ?>
            <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--muted);">No completed job orders yet.</td></tr>
            <?php else: foreach ($historyJOs as $jo): ?>
            <tr>
              <td><strong><?= htmlspecialchars($jo['jo_number']) ?></strong></td>
              <td><?= htmlspecialchars($jo['customer_name']) ?></td>
              <td><?= htmlspecialchars($jo['service']) ?></td>
              <td><?= htmlspecialchars($jo['rim_count'].' '.$jo['rim_size']) ?></td>
              <td><?= $jo['amount_charged'] ? '₱'.number_format($jo['amount_charged'],2) : '—' ?></td>
              <td><?= $jo['payment_method'] ? ucfirst($jo['payment_method']) : '—' ?></td>
              <td><?= $jo['worker_name'] ? htmlspecialchars($jo['worker_name']) : '—' ?></td>
              <td><?= $jo['completed_at'] ? date('M d, Y', strtotime($jo['completed_at'])) : '—' ?></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
</div>
      </div>
    </div>
  </div>
</div>
<script>
  document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); }));
</script>
</body>
</html>
