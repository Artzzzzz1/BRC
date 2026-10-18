<?php
require_once '../config/auth.php';
require_once '../config/db.php';
requireAdmin();
$activePage = 'joborders';
$msg = '';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// CREATE JOB ORDER
if (isset($_POST['action']) && $_POST['action'] === 'create_jo') {
  try {
    $countStmt = $pdo->query("SELECT COUNT(*)+1 FROM job_orders");
    $joCount = $countStmt ? (int)$countStmt->fetchColumn() : 1;
    $joNum = 'JO-' . date('Y') . '-' . str_pad($joCount, 4, '0', STR_PAD_LEFT);
    $stmt = $pdo->prepare("INSERT INTO job_orders (jo_number,customer_name,contact_number,customer_type,vehicle,service,rim_count,rim_size,color_finish,notes,current_step,status,est_completion,user_id,queue_id) VALUES (?,?,?,?,?,?,?,?,?,?,'received','in_progress',?,?,?)");
    $stmt->execute([
      $joNum,
      $_POST['customer_name'],
      $_POST['contact_number'] ?? '',
      $_POST['customer_type'],
      $_POST['vehicle'] ?? '',
      $_POST['service'],
      $_POST['rim_count'] ?? '',
      $_POST['rim_size'] ?? '',
      $_POST['color_finish'] ?? '',
      $_POST['notes'] ?? '',
      !empty($_POST['est_completion']) ? $_POST['est_completion'] : null,
      !empty($_POST['user_id'])        ? $_POST['user_id']        : null,
      !empty($_POST['queue_id'])       ? $_POST['queue_id']       : null,
    ]);
    $joId = $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO job_order_logs (job_order_id,step,message) VALUES (?,'received','Job order created - rims received.')")->execute([$joId]);
    if (!empty($_POST['queue_id'])) {
      $pdo->prepare("UPDATE appointments SET status='in_progress' WHERE id=(SELECT appointment_id FROM queue WHERE id=?)")->execute([$_POST['queue_id']]);
    }
    $msg = "Job Order $joNum created successfully.";
  } catch (Exception $e) {
    $msg = "Error creating job order: " . $e->getMessage();
  }
}

// UPDATE STEP
if (isset($_POST['action']) && $_POST['action'] === 'update_step') {
  try {
    $id   = (int)$_POST['jo_id'];
    $step = $_POST['step'];
    $validSteps = ['received','stripping','coating','curing','ready'];
    if (in_array($step, $validSteps)) {
      $status = $step === 'ready' ? 'ready' : 'in_progress';
      $pdo->prepare("UPDATE job_orders SET current_step=?, status=? WHERE id=?")->execute([$step, $status, $id]);
      $labels = ['received'=>'Rims received','stripping'=>'Stripping old coating','coating'=>'Powder coating applied','curing'=>'Curing in oven','ready'=>'Rims ready for pickup'];
      $pdo->prepare("INSERT INTO job_order_logs (job_order_id,step,message) VALUES (?,?,?)")->execute([$id, $step, $labels[$step]]);
      if ($step === 'ready') {
        $pdo->prepare("UPDATE appointments SET status='completed' WHERE id=(SELECT appointment_id FROM queue WHERE id=(SELECT queue_id FROM job_orders WHERE id=?))")->execute([$id]);
      }
      $msg = "Step updated to: " . ucfirst($step);
    }
  } catch (Exception $e) {
    $msg = "Error updating step: " . $e->getMessage();
  }
}

// MARK COMPLETED + PAYMENT
if (isset($_POST['action']) && $_POST['action'] === 'complete') {
  try {
    $id = (int)$_POST['jo_id'];
    $pdo->prepare("UPDATE job_orders SET status='completed',payment_status='paid',amount_charged=?,payment_method=?,completed_at=NOW() WHERE id=?")->execute([$_POST['amount'], $_POST['payment_method'], $id]);
    $pdo->prepare("INSERT INTO job_order_logs (job_order_id,step,message) VALUES (?,'completed',?)")->execute([$id, 'Job completed. Payment received via ' . ucfirst($_POST['payment_method'])]);
    $msg = "Job order marked as completed.";
  } catch (Exception $e) {
    $msg = "Error completing job order: " . $e->getMessage();
  }
}

// Fetch ALL job orders safely
try {
  $stmtAll = $pdo->query("SELECT jo.*, q.queue_number FROM job_orders jo LEFT JOIN queue q ON jo.queue_id = q.id ORDER BY jo.created_at DESC");
  $jobOrders = $stmtAll ? $stmtAll->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {
  $jobOrders = [];
  $msg = "DB error: " . $e->getMessage();
}

$activeJOs  = array_values(array_filter($jobOrders, fn($j) => in_array($j['status'] ?? '', ['in_progress','ready'])));
$history = array_values(array_filter($jobOrders, fn($j) => in_array($j['status'] ?? '', ['completed','cancelled'])));

$stepOrder  = ['received'=>1,'stripping'=>2,'coating'=>3,'curing'=>4,'ready'=>5];
$stepLabels = ['received'=>'Received','stripping'=>'Stripping','coating'=>'Coating','curing'=>'Curing','ready'=>'Ready'];

// Queue entries without job orders
try {
  $stmtQ = $pdo->query("SELECT * FROM queue WHERE id NOT IN (SELECT COALESCE(queue_id,0) FROM job_orders WHERE queue_id IS NOT NULL) AND queue_date='".date('Y-m-d')."' ORDER BY queue_number");
  $queueWithoutJO = $stmtQ ? $stmtQ->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {
  $queueWithoutJO = [];
}

// ASSIGN WORKER action
if (isset($_POST['action']) && $_POST['action'] === 'assign_worker') {
  try {
    $id  = (int)$_POST['jo_id'];
    $wid = !empty($_POST['worker_id']) ? (int)$_POST['worker_id'] : null;
    $pdo->prepare("UPDATE job_orders SET worker_id=? WHERE id=?")->execute([$wid, $id]);
    $msg = $wid ? "Worker assigned." : "Worker unassigned.";
  } catch (Exception $e) { $msg = "Error: " . $e->getMessage(); }
}

// Fetch workers for assignment dropdown
try {
  $stmtW = $pdo->query("SELECT id, full_name FROM users WHERE role='worker' ORDER BY full_name");
  $workers = $stmtW ? $stmtW->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) { $workers = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BRC Calamba - Job Orders</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="admin.css">
  <style>
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;padding:20px;box-sizing:border-box}.modal-overlay.open{display:flex}.modal{background:#fff;border-radius:12px;padding:28px;width:100%;max-width:560px;box-shadow:0 20px 60px rgba(0,0,0,.2);max-height:90vh;overflow-y:auto;overflow-x:hidden;box-sizing:border-box}.modal h3{font-size:1.1rem;font-weight:700;margin-bottom:20px}.modal-footer{display:flex;gap:12px;margin-top:24px;justify-content:flex-end}
    .modal .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;width:100%;box-sizing:border-box}.modal .form-group{display:flex;flex-direction:column;gap:5px;min-width:0}.modal .form-group.span2{grid-column:span 2}.modal .form-group label{font-size:.8rem;font-weight:700;color:var(--text)}.modal .form-group input,.modal .form-group select,.modal .form-group textarea{width:100%;box-sizing:border-box;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:'Montserrat',sans-serif;font-size:.83rem;color:var(--text);background:#fff;outline:none;transition:border-color .2s,box-shadow .2s}.modal .form-group input:focus,.modal .form-group select:focus,.modal .form-group textarea:focus{border-color:var(--red);box-shadow:0 0 0 3px rgba(204,0,0,.08)}.modal .form-group textarea{resize:vertical}
    .step-select{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}.step-btn{padding:6px 12px;border-radius:20px;border:1.5px solid var(--border);background:white;font-family:'Montserrat',sans-serif;font-size:.75rem;font-weight:600;cursor:pointer;color:var(--muted)}.step-btn.active{background:var(--red);border-color:var(--red);color:white}
  </style>
</head>
<body>
<?php include '../config/admin-sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
    <div class="page-title"><h1>Job Orders</h1><p>Create and update job order progress</p></div>
    <div class="topbar-actions">
      <button class="btn btn-red" onclick="document.getElementById('newJOModal').classList.add('open')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> New Job Order
      </button>
    </div>
  </div>
  <div class="content">
    <?php if ($msg): ?>
    <div style="background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;padding:12px 16px;border-radius:8px;font-size:.83rem;font-weight:600;margin-bottom:20px;"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- ACTIVE JOB ORDERS -->
    <?php if (empty($activeJOs)): ?>
    <div class="section-card" style="margin-bottom:24px;">
      <div class="section-body">
        <div class="empty-state">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
          <h3>No Active Job Orders</h3>
          <p>Create a new job order using the button above.</p>
        </div>
      </div>
    </div>
    <?php else: ?>
    <?php foreach ($activeJOs as $jo): ?>
    <?php $cur = $stepOrder[$jo['current_step']] ?? 1; ?>
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
        <!-- STEP TRACKER -->
        <div class="step-tracker">
          <?php foreach ($stepLabels as $key => $label): $n = $stepOrder[$key]; ?>
          <div class="step <?= $n < $cur ? 'done' : ($n===$cur?'active':'') ?>">
            <div class="step-circle"><?= $n < $cur ? '✓' : $n ?></div>
            <div class="step-label"><?= $label ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <!-- UPDATE STEP -->
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
        <!-- JO details -->
        <div style="margin-top:12px;display:flex;gap:24px;flex-wrap:wrap;font-size:.78rem;color:var(--muted);">
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
              <option value="<?= $w['id'] ?>" <?= ($jo['worker_id'] ?? '') == $w['id'] ? 'selected' : '' ?>><?= htmlspecialchars($w['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-ghost" style="padding:6px 14px;font-size:.78rem;">Assign</button>
          </form>
          <?php if (!empty($jo['worker_id'])): ?>
          <span style="font-size:.76rem;background:#dcfce7;color:#15803d;padding:3px 10px;border-radius:20px;font-weight:700;">✓ Assigned</span>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <!-- PAY MODAL -->
    <div class="modal-overlay" id="payModal<?= $jo['id'] ?>">
      <div class="modal">
        <h3>Complete Job Order &mdash; <?= htmlspecialchars($jo['jo_number']) ?></h3>
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
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- HISTORY TABLE -->
    <div class="section-card" style="margin-top:20px;">
      <div class="section-header"><h2>Job Order History</h2></div>
      <div style="padding:0;">
        <div class="table-wrapper">
<table class="data-table">
          <thead>
            <tr><th>JO #</th><th>Customer</th><th>Service</th><th>Rims</th><th>Amount</th><th>Payment</th><th>Status</th><th>Completed</th></tr>
          </thead>
          <tbody>
            <?php if (empty($history)): ?>
            <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--muted);">No completed job orders yet.</td></tr>
            <?php else: foreach ($history as $jo): ?>
            <tr>
              <td><strong><?= htmlspecialchars($jo['jo_number']) ?></strong></td>
              <td><?= htmlspecialchars($jo['customer_name']) ?></td>
              <td><?= htmlspecialchars($jo['service']) ?></td>
              <td><?= htmlspecialchars($jo['rim_count'].' '.$jo['rim_size']) ?></td>
              <td><?= $jo['amount_charged'] ? '₱'.number_format($jo['amount_charged'],2) : '—' ?></td>
              <td><?= $jo['payment_method'] ? ucfirst($jo['payment_method']) : '—' ?></td>
              <td><span class="badge <?= $jo['status']==='completed'?'badge-completed':'badge-cancelled' ?>"><?= ucfirst($jo['status']) ?></span></td>
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

<!-- NEW JOB ORDER MODAL -->
<div class="modal-overlay" id="newJOModal">
  <div class="modal">
    <h3>Create New Job Order</h3>
    <form method="POST">
      <input type="hidden" name="action" value="create_jo">
      <div class="form-grid">
        <div class="form-group span2"><label>Customer Type</label><select name="customer_type"><option value="walk-in">Walk-In</option><option value="online">Registered User</option></select></div>
        <?php if (!empty($queueWithoutJO)): ?>
        <div class="form-group span2"><label>Link to Queue Entry (optional)</label>
          <select name="queue_id"><option value="">— Not linked —</option>
          <?php foreach ($queueWithoutJO as $q): ?><option value="<?= $q['id'] ?>">#<?= $q['queue_number'] ?> — <?= htmlspecialchars($q['customer_name']) ?> (<?= htmlspecialchars($q['service']) ?>)</option><?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="form-group span2"><label>Customer Name</label><input type="text" name="customer_name" required placeholder="Enter customer name"></div>
        <div class="form-group"><label>Contact Number</label><input type="tel" name="contact_number" placeholder="09XX XXX XXXX"></div>
        <div class="form-group"><label>Motorcycle</label><input type="text" name="vehicle" placeholder="e.g. Honda CBR 150R"></div>
        <div class="form-group span2"><label>Service</label>
          <select name="service" required>
            <option value="" disabled selected>Select...</option>
            <optgroup label="Powder Coating">
              <option>Powder Coating — Matte Finish</option>
              <option>Powder Coating — Gloss Finish</option>
              <option>Powder Coating — Metallic Finish</option>
              <option>Powder Coating — Custom Color</option>
            </optgroup>
            <optgroup label="Rim Services">
              <option>Rim Repair</option>
              <option>Rim Straightening</option>
              <option>Rim Cleaning &amp; Polishing</option>
            </optgroup>
          </select>
        </div>
        <div class="form-group"><label>Rims</label>
          <select name="rim_count">
            <option>1 Rim (Front)</option>
            <option>1 Rim (Rear)</option>
            <option>2 Rims (Full Set)</option>
          </select>
        </div>
        <div class="form-group"><label>Rim Size</label>
          <select name="rim_size">
            <option>14"</option><option>17"</option><option>18"</option><option>19"</option><option>21"</option>
          </select>
        </div>
        <div class="form-group"><label>Color / Finish</label><input type="text" name="color_finish" placeholder="e.g. Matte Black"></div>
        <div class="form-group"><label>Est. Completion</label><input type="date" name="est_completion"></div>
        <div class="form-group span2"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('newJOModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-red">Create Job Order</button>
      </div>
    </form>
  </div>
</div>

<script>
  // Close modals on backdrop click
  document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
      if (e.target === this) this.classList.remove('open');
    });
  });
</script>
</body>
</html>
