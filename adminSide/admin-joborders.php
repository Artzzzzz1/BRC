<?php
require_once '../config/auth.php';
require_once '../config/db.php';
require_once '../config/joborder-actions.php';
requireAdmin();
$activePage = 'joborders';
$msg = '';
$actorId   = (int)userId();
$actorName = userName();

// All write actions go through config/joborder-actions.php (shared with owner + worker pages)
// and run BEFORE the lists below are fetched, so the page always shows fresh data.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  switch ($_POST['action']) {
    case 'create_jo':
      [, $msg] = jo_create($pdo, $_POST, $actorId, $actorName);
      break;
    case 'update_step':
      [, $msg] = jo_update_step($pdo, (int)($_POST['jo_id'] ?? 0), (string)($_POST['step'] ?? ''), $actorId, $actorName);
      break;
    case 'complete':
      [, $msg] = jo_complete($pdo, (int)($_POST['jo_id'] ?? 0), $_POST['amount'] ?? '', (string)($_POST['payment_method'] ?? ''), $actorId, $actorName);
      break;
    case 'update_details':
      [, $msg] = jo_update_details($pdo, (int)($_POST['jo_id'] ?? 0), $_POST, $actorId, $actorName);
      break;
    case 'assign_worker':
      [, $msg] = jo_assign_worker($pdo, (int)($_POST['jo_id'] ?? 0), !empty($_POST['worker_id']) ? (int)$_POST['worker_id'] : null);
      break;
  }
}

// Fetch ALL job orders
try {
  $stmtAll = $pdo->query("SELECT jo.*, q.queue_number FROM job_orders jo LEFT JOIN queue q ON jo.queue_id = q.id ORDER BY jo.created_at DESC");
  $jobOrders = $stmtAll ? $stmtAll->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {
  error_log('admin-joborders list: ' . $e->getMessage());
  $jobOrders = [];
  $msg = "Could not load job orders.";
}

$activeJOs  = array_values(array_filter($jobOrders, fn($j) => in_array($j['status'] ?? '', ['in_progress','ready'])));
$history = array_values(array_filter($jobOrders, fn($j) => in_array($j['status'] ?? '', ['completed','cancelled'])));

$stepOrder  = ['received'=>1,'stripping'=>2,'coating'=>3,'curing'=>4,'ready'=>5];
$stepLabels = ['received'=>'Received','stripping'=>'Stripping','coating'=>'Coating','curing'=>'Curing','ready'=>'Ready'];

// Queue entries without job orders (today)
try {
  // LEFT JOIN appointments so the customer's notes (e.g. the 3D-preview rim design) come along too
  $stmtQ = $pdo->prepare("SELECT q.*, a.notes AS appt_notes FROM queue q LEFT JOIN appointments a ON a.id = q.appointment_id WHERE q.id NOT IN (SELECT queue_id FROM job_orders WHERE queue_id IS NOT NULL) AND q.queue_date = ? ORDER BY q.queue_number");
  $stmtQ->execute([date('Y-m-d')]);
  $queueWithoutJO = $stmtQ->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $queueWithoutJO = [];
}

// Fetch workers for assignment dropdown
try {
  $stmtW = $pdo->query("SELECT id, full_name FROM users WHERE role='worker' ORDER BY full_name");
  $workers = $stmtW ? $stmtW->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) { $workers = []; }

// Data used by the "Create Job Order" form to autofill itself from the picked queue entry
$queueAutofill = [];
foreach ($queueWithoutJO as $q) {
  $queueAutofill[(int)$q['id']] = [
    'queue_number'   => $q['queue_number'] ?? '',
    'customer_type'  => $q['customer_type'] ?? '',
    'user_id'        => $q['user_id'] ?? '',
    'customer_name'  => $q['customer_name'] ?? '',
    'contact_number' => $q['contact_number'] ?? '',
    'vehicle'        => $q['vehicle'] ?? '',
    'service'        => $q['service'] ?? '',
    'rim_count'      => $q['rim_count'] ?? '',
    'rim_size'       => $q['rim_size'] ?? '',
    'color_finish'   => $q['color_finish'] ?? '',
    'notes'          => $q['appt_notes'] ?? '',
  ];
}

// Registered customers for the "Create Job Order" dropdown
try {
  $stmtC = $pdo->query("SELECT id, full_name, contact_number FROM users WHERE role='customer' ORDER BY full_name");
  $customers = $stmtC ? $stmtC->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) { $customers = []; }
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
          <button class="btn btn-ghost" style="padding:5px 12px;font-size:.75rem;" onclick="document.getElementById('editModal<?= $jo['id'] ?>').classList.add('open')">Edit Details</button>
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
          <?php if (!empty($jo['notes'])): ?><span>📝 <?= htmlspecialchars($jo['notes']) ?></span><?php endif; ?>
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
    <?php include '../config/jo-edit-modal.php'; ?>
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
          <select name="queue_id" id="joQueueSelect"><option value="">— Not linked —</option>
          <?php foreach ($queueWithoutJO as $q): ?><option value="<?= $q['id'] ?>">#<?= $q['queue_number'] ?> — <?= htmlspecialchars($q['customer_name']) ?> (<?= htmlspecialchars($q['service']) ?>)</option><?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="form-group span2"><label>Registered Customer (optional)</label>
          <select name="user_id"><option value="">— Walk-in / not registered —</option>
          <?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['full_name']) ?><?= $c['contact_number'] ? ' — ' . htmlspecialchars($c['contact_number']) : '' ?></option><?php endforeach; ?>
          </select>
        </div>
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
<script>
  // ── Autofill the Create Job Order form from the selected queue entry ──
  // Everything stays editable, so staff/owner can change anything if the customer changed their mind.
  const QUEUE_AUTOFILL = <?= json_encode($queueAutofill, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  (function () {
    var sel = document.getElementById('joQueueSelect');
    if (!sel) return;
    var form = sel.form;
    var note = document.createElement('div');
    note.style.cssText = 'display:none;margin-top:8px;padding:8px 12px;border-radius:8px;background:#eff6ff;color:#1d4ed8;font-size:.76rem;font-weight:600;';
    sel.parentNode.appendChild(note);

    function setField(name, val) {
      var el = form.elements[name];
      if (!el) return;
      val = (val === null || val === undefined) ? '' : String(val);
      if (el.tagName === 'SELECT') {
        if (val === '') return;                      // nothing to fill: keep the default
        for (var i = 0; i < el.options.length; i++) {
          if (el.options[i].value === val) { el.selectedIndex = i; return; }
        }
        var o = document.createElement('option');    // customer wrote something not in the list: keep it
        o.value = val; o.text = val;
        el.add(o); el.value = val;
      } else {
        el.value = val;
      }
    }

    function fill() {
      var d = QUEUE_AUTOFILL[sel.value];
      if (!d) { note.style.display = 'none'; return; }
      setField('customer_type', d.user_id ? 'online' : (d.customer_type || 'walk-in'));
      setField('user_id', d.user_id);
      setField('customer_name', d.customer_name);
      setField('contact_number', d.contact_number);
      setField('vehicle', d.vehicle);
      setField('service', d.service);
      setField('rim_count', d.rim_count);
      setField('rim_size', d.rim_size);
      setField('color_finish', d.color_finish);
      setField('notes', d.notes);
      note.textContent = 'Filled in from queue #' + d.queue_number + ' (' + d.customer_name + '). You can change anything before saving.';
      note.style.display = 'block';
    }
    sel.addEventListener('change', fill);

    // Deep link from the queue page: admin-joborders.php?queue_id=12 opens the form already filled
    var qid = new URLSearchParams(window.location.search).get('queue_id');
    if (qid && QUEUE_AUTOFILL[qid]) {
      sel.value = qid;
      fill();
      document.getElementById('newJOModal').classList.add('open');
    }
  })();
</script>
</body>
</html>
