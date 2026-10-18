<?php
require_once '../config/auth.php';
require_once '../config/db.php';
require_once '../config/queue-helpers.php';
requireAdmin();
$activePage = 'queue';
$today = date('Y-m-d');
$msg = '';

// CALL NEXT (set to serving) - only a WAITING entry of today can be called
if (isset($_POST['action']) && $_POST['action'] === 'call') {
  $id = (int)($_POST['queue_id'] ?? 0);
  try {
    $pdo->beginTransaction();
    $chk = $pdo->prepare("SELECT status FROM queue WHERE id=? AND queue_date=? FOR UPDATE");
    $chk->execute([$id, $today]);
    $st = $chk->fetchColumn();
    if ($st !== 'waiting') {
      $msg = "That entry is not waiting in today's queue.";
    } else {
      // Set current serving to done first
      $pdo->prepare("UPDATE queue SET status='done', done_at=NOW() WHERE queue_date=? AND status='serving'")->execute([$today]);
      // Call this one
      $pdo->prepare("UPDATE queue SET status='serving', called_at=NOW() WHERE id=?")->execute([$id]);
      $msg = "Queue entry called.";
    }
    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('queue call: ' . $e->getMessage());
    $msg = "Could not call that entry. Please try again.";
  }
}

// MARK DONE
if (isset($_POST['action']) && $_POST['action'] === 'done') {
  $id = (int)($_POST['queue_id'] ?? 0);
  $pdo->prepare("UPDATE queue SET status='done', done_at=NOW() WHERE id=? AND status IN ('waiting','serving')")->execute([$id]);
  $msg = "Marked as done.";
}

// SKIP — the customer is not in the shop yet.
// They stay in today's queue but go to the BACK of the waiting line (they get the next free
// number), so the next person can be called instead. Their queue number changes accordingly.
if (isset($_POST['action']) && $_POST['action'] === 'skip') {
  $id = (int)($_POST['queue_id'] ?? 0);
  try {
    $pdo->beginTransaction();
    $chk = $pdo->prepare("SELECT id, status, appointment_id, queue_number, customer_name FROM queue WHERE id=? AND queue_date=? FOR UPDATE");
    $chk->execute([$id, $today]);
    $row = $chk->fetch();
    if (!$row || $row['status'] !== 'waiting') {
      $msg = "Only a waiting entry from today's queue can be skipped.";
    } else {
      $cnt = $pdo->prepare("SELECT COUNT(*) FROM queue WHERE queue_date=? AND status='waiting' AND id<>?");
      $cnt->execute([$today, $id]);
      if ((int)$cnt->fetchColumn() === 0) {
        $msg = "Nobody else is waiting, so there is no one to skip to.";
      } else {
        $newNum = next_queue_number($pdo, $today);
        $pdo->prepare("UPDATE queue SET queue_number=? WHERE id=?")->execute([$newNum, $id]);
        if (!empty($row['appointment_id'])) {
          $pdo->prepare("UPDATE appointments SET queue_number=? WHERE id=?")->execute([$newNum, $row['appointment_id']]);
        }
        $msg = $row['customer_name'] . " (was #" . $row['queue_number'] . ") moved to the back of the line as #" . $newNum . ".";
      }
    }
    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('queue skip: ' . $e->getMessage());
    $msg = "Could not skip that entry. Please try again.";
  }
}

// ADD TO QUEUE (walk-in direct)
if (isset($_POST['action']) && $_POST['action'] === 'add_queue') {
  $name    = trim($_POST['customer_name'] ?? '');
  $service = trim($_POST['service'] ?? '');
  if ($name === '' || $service === '') {
    $msg = "Customer name and service are required.";
  } else {
    $f = fn($k) => trim($_POST[$k] ?? '');
    $type = in_array($_POST['customer_type'] ?? '', ['walk-in','online'], true) ? $_POST['customer_type'] : 'walk-in';
    // user_id must be a real customer account, never a raw POST value
    $uid = null;
    if (!empty($_POST['user_id'])) {
      $u = $pdo->prepare("SELECT id FROM users WHERE id=? AND role='customer'");
      $u->execute([(int)$_POST['user_id']]);
      $uid = $u->fetchColumn() ?: null;
    }
    try {
      $pdo->beginTransaction();
      $qNum = next_queue_number($pdo, $today);
      $pdo->prepare("INSERT INTO queue (customer_name,contact_number,customer_type,service,rim_count,rim_size,color_finish,vehicle,queue_number,status,queue_date,user_id) VALUES (?,?,?,?,?,?,?,?,?,'waiting',?,?)")
          ->execute([$name,$f('contact_number'),$type,$service,$f('rim_count'),$f('rim_size'),$f('color_finish'),$f('vehicle'),$qNum,$today,$uid]);
      $pdo->commit();
      $msg = "Added to queue as #$qNum.";
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      error_log('add_queue: ' . $e->getMessage());
      $msg = "Could not add to the queue. Please try again.";
    }
  }
}

// Which day are we looking at? Default = today. Actions (call / skip / done) only work on today.
$viewDate = $today;
if (!empty($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) $viewDate = $_GET['date'];
$isToday = ($viewDate === $today);

$queue = $pdo->prepare("SELECT * FROM queue WHERE queue_date=? ORDER BY queue_number ASC");
$queue->execute([$viewDate]);
$allQueue = $queue->fetchAll();

// Approved customers booked for LATER days (they join the live queue on their day)
$upcomingStmt = $pdo->prepare("SELECT queue_date, COUNT(*) AS c FROM queue WHERE queue_date > ? AND status='waiting' GROUP BY queue_date ORDER BY queue_date ASC LIMIT 14");
$upcomingStmt->execute([$today]);
$upcoming = $upcomingStmt->fetchAll();

$waiting = array_filter($allQueue, fn($q) => $q['status']==='waiting');
$serving = array_filter($allQueue, fn($q) => $q['status']==='serving');
$done    = array_filter($allQueue, fn($q) => $q['status']==='done');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BRC Calamba - Queue</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="admin.css">
  <style>
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center}.modal-overlay.open{display:flex}.modal{background:#fff;border-radius:12px;padding:32px;width:100%;max-width:480px;box-shadow:0 20px 60px rgba(0,0,0,.2);max-height:90vh;overflow-y:auto}.modal h3{font-size:1.1rem;font-weight:700;margin-bottom:20px}.modal-footer{display:flex;gap:12px;margin-top:24px;justify-content:flex-end}
    .queue-board{display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-bottom:28px}
    .queue-col{background:var(--bg);border-radius:10px;padding:16px;min-height:120px}
    .queue-col-header{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:12px;display:flex;align-items:center;gap:8px}
    .queue-col-header .dot{width:8px;height:8px;border-radius:50%}
    .q-card{background:white;border:1.5px solid var(--border);border-radius:10px;padding:14px;margin-bottom:10px}
    .q-card-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
    .q-num{font-family:'Bebas Neue',sans-serif;font-size:1.6rem;color:var(--red);line-height:1}
    .q-card h4{font-size:.85rem;font-weight:700;margin-bottom:2px}
    .q-card p{font-size:.75rem;color:var(--muted)}
    .q-card-actions{display:flex;gap:6px;margin-top:10px}
  </style>
</head>
<body>
<?php include '../config/admin-sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
    <div class="page-title"><h1>Queue Management</h1><p><?= $isToday ? 'Today' : 'Viewing' ?> — <?= date('F d, Y', strtotime($viewDate)) ?></p></div>
    <div class="topbar-actions">
      <button class="btn btn-red" onclick="document.getElementById('addQModal').classList.add('open')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add to Queue
      </button>
    </div>
  </div>
  <div class="content">
    <?php if ($msg): ?><div style="background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;padding:12px 16px;border-radius:8px;font-size:.83rem;font-weight:600;margin-bottom:20px;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:20px;">
      <form method="GET" style="display:flex;align-items:center;gap:8px;margin:0;">
        <label style="font-size:.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">View date</label>
        <input type="date" name="date" value="<?= htmlspecialchars($viewDate) ?>" style="padding:6px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:.82rem;">
        <button type="submit" class="btn btn-ghost" style="padding:6px 14px;font-size:.78rem;">Go</button>
      </form>
      <?php if (!$isToday): ?><a href="admin-queue.php" class="btn btn-red" style="padding:6px 14px;font-size:.78rem;text-decoration:none;">← Back to today</a><?php endif; ?>
      <?php if ($upcoming): ?>
        <span style="font-size:.75rem;color:var(--muted);font-weight:600;margin-left:6px;">Upcoming:</span>
        <?php foreach ($upcoming as $u): ?>
          <a href="admin-queue.php?date=<?= htmlspecialchars($u['queue_date']) ?>" style="font-size:.75rem;font-weight:600;background:#fef3c7;color:#92400e;border-radius:20px;padding:4px 12px;text-decoration:none;"><?= date('M d', strtotime($u['queue_date'])) ?> · <?= (int)$u['c'] ?> approved</a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php if (!$isToday): ?>
      <div style="background:#fffbeb;color:#92400e;border:1px solid #fde68a;padding:10px 16px;border-radius:8px;font-size:.8rem;font-weight:600;margin-bottom:20px;">You are viewing a different day. Calling, skipping and finishing are only available on today's queue.</div>
    <?php endif; ?>

    <div class="stats-grid" style="margin-bottom:28px;">
      <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:#dbeafe;"><svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></div><div class="stat-number"><?= count($allQueue) ?></div><div class="stat-label">Total in Queue</div></div>
      <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:#fef3c7;"><svg viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div></div><div class="stat-number"><?= count($waiting) ?></div><div class="stat-label">Waiting</div></div>
      <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:#dcfce7;"><svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div></div><div class="stat-number"><?= count($done) ?></div><div class="stat-label">Served Today</div></div>
      <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:#ede9fe;"><svg viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></div><div class="stat-number"><?= count($serving) > 0 ? '#'.reset($serving)['queue_number'] : '—' ?></div><div class="stat-label">Now Serving</div></div>
    </div>

    <div class="queue-board">
      <!-- NOW SERVING -->
      <div>
        <div class="queue-col-header"><span class="dot" style="background:#2563eb;"></span>Now Serving</div>
        <div class="queue-col" style="background:#eff6ff;border:1px solid #bfdbfe;">
          <?php if (empty($serving)): ?>
            <div style="text-align:center;color:var(--muted);font-size:.8rem;padding:20px 0;">No one being served</div>
          <?php else: foreach ($serving as $q): ?>
          <div class="q-card">
            <div class="q-card-top"><span class="q-num" style="color:#2563eb;">#<?= $q['queue_number'] ?></span><span class="badge badge-confirmed">Serving</span></div>
            <h4><?= htmlspecialchars($q['customer_name']) ?></h4>
            <p><?= htmlspecialchars($q['service']) ?></p>
            <?php if ($isToday): ?>
            <div class="q-card-actions">
              <form method="POST"><input type="hidden" name="action" value="done"><input type="hidden" name="queue_id" value="<?= $q['id'] ?>"><button type="submit" class="btn btn-red" style="padding:5px 12px;font-size:.75rem;">✓ Done</button></form>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <!-- WAITING -->
      <div>
        <div class="queue-col-header"><span class="dot" style="background:#d97706;"></span>Waiting (<?= count($waiting) ?>)</div>
        <div class="queue-col">
          <?php if (empty($waiting)): ?>
            <div style="text-align:center;color:var(--muted);font-size:.8rem;padding:20px 0;">Queue is empty</div>
          <?php else: foreach ($waiting as $q): ?>
          <div class="q-card">
            <div class="q-card-top"><span class="q-num">#<?= $q['queue_number'] ?></span><span class="badge badge-pending">Waiting</span></div>
            <h4><?= htmlspecialchars($q['customer_name']) ?></h4>
            <p><?= htmlspecialchars($q['service']) ?> · <?= htmlspecialchars($q['rim_count']) ?></p>
            <?php if ($isToday): ?>
            <div class="q-card-actions">
              <form method="POST"><input type="hidden" name="action" value="call"><input type="hidden" name="queue_id" value="<?= $q['id'] ?>"><button type="submit" class="btn btn-red" style="padding:5px 12px;font-size:.75rem;">Call</button></form>
              <form method="POST" onsubmit="return confirm('Customer not in the shop? They will move to the back of the line.');"><input type="hidden" name="action" value="skip"><input type="hidden" name="queue_id" value="<?= $q['id'] ?>"><button type="submit" class="btn btn-ghost" style="padding:5px 12px;font-size:.75rem;">Not here — skip</button></form>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <!-- DONE -->
      <div>
        <div class="queue-col-header"><span class="dot" style="background:#16a34a;"></span>Done Today (<?= count($done) ?>)</div>
        <div class="queue-col">
          <?php if (empty($done)): ?>
            <div style="text-align:center;color:var(--muted);font-size:.8rem;padding:20px 0;">None completed yet</div>
          <?php else: foreach ($done as $q): ?>
          <div class="q-card" style="opacity:.7;">
            <div class="q-card-top"><span class="q-num" style="color:#16a34a;">#<?= $q['queue_number'] ?></span><span class="badge badge-completed">Done</span></div>
            <h4><?= htmlspecialchars($q['customer_name']) ?></h4>
            <p><?= htmlspecialchars($q['service']) ?></p>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- ADD TO QUEUE MODAL -->
<div class="modal-overlay" id="addQModal">
  <div class="modal">
    <h3>Add Customer to Queue</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add_queue">
      <div class="form-grid">
        <div class="form-group span2"><label>Customer Type</label><select name="customer_type"><option value="walk-in">Walk-In (No Account)</option><option value="online">Registered User</option></select></div>
        <div class="form-group span2"><label>Customer Name</label><input type="text" name="customer_name" required placeholder="Enter customer name"></div>
        <div class="form-group"><label>Contact Number</label><input type="tel" name="contact_number" placeholder="09XX XXX XXXX"></div>
        <div class="form-group"><label>Motorcycle</label><input type="text" name="vehicle" placeholder="e.g. Honda Click 125i"></div>
        <div class="form-group span2"><label>Service</label><select name="service" required><option value="" disabled selected>Select...</option><optgroup label="Powder Coating"><option>Powder Coating — Matte Finish</option><option>Powder Coating — Gloss Finish</option><option>Powder Coating — Metallic Finish</option><option>Powder Coating — Custom Color</option></optgroup><optgroup label="Rim Services"><option>Rim Repair</option><option>Rim Straightening</option><option>Rim Cleaning & Polishing</option></optgroup></select></div>
        <div class="form-group"><label>Rims</label><select name="rim_count"><option>1 Rim (Front)</option><option>1 Rim (Rear)</option><option>2 Rims (Full Set)</option></select></div>
        <div class="form-group"><label>Rim Size</label><select name="rim_size"><option>14"</option><option>17"</option><option>18"</option><option>19"</option><option>21"</option></select></div>
        <div class="form-group"><label>Color / Finish</label><input type="text" name="color_finish" placeholder="e.g. Matte Black"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('addQModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-red">Add to Queue</button>
      </div>
    </form>
  </div>
</div>
</body>
</html>
