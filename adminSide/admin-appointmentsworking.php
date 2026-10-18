<?php
require_once '../config/auth.php';
require_once '../config/db.php';
requireAdmin();
$activePage = 'appointments';

$msg = '';

// APPROVE appointment + assign queue number
if (isset($_POST['action']) && $_POST['action'] === 'approve') {
  $id   = (int)$_POST['appointment_id'];
  $date = date('Y-m-d');

  // Get next queue number for today
  $stmt = $pdo->prepare("SELECT COALESCE(MAX(queue_number),0)+1 FROM queue WHERE queue_date = ?");
  $stmt->execute([$date]);
  $qNum = $stmt->fetchColumn();

  // Get appointment details
  $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ?");
  $stmt->execute([$id]);
  $appt = $stmt->fetch();

  if ($appt) {
    // Add to queue
    $stmt = $pdo->prepare("INSERT INTO queue (appointment_id, user_id, customer_name, contact_number, customer_type, service, rim_count, rim_size, color_finish, vehicle, queue_number, status, queue_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,'waiting',?)");
    $stmt->execute([$id, $appt['user_id'], $appt['customer_name'], $appt['contact_number'], $appt['customer_type'], $appt['service'], $appt['rim_count'], $appt['rim_size'], $appt['color_finish'], $appt['vehicle'], $qNum, $date]);

    // Update appointment status + queue number
    $stmt = $pdo->prepare("UPDATE appointments SET status='confirmed', queue_number=? WHERE id=?");
    $stmt->execute([$qNum, $id]);
    $msg = "Appointment confirmed. Queue #$qNum assigned.";
  }
}

// REJECT appointment
if (isset($_POST['action']) && $_POST['action'] === 'reject') {
  $id = (int)$_POST['appointment_id'];
  $pdo->prepare("UPDATE appointments SET status='cancelled' WHERE id=?")->execute([$id]);
  $msg = "Appointment cancelled.";
}

// ADD WALK-IN
if (isset($_POST['action']) && $_POST['action'] === 'walkin') {
  $date = date('Y-m-d');
  $stmt = $pdo->prepare("SELECT COALESCE(MAX(queue_number),0)+1 FROM queue WHERE queue_date = ?");
  $stmt->execute([$date]);
  $qNum = $stmt->fetchColumn();

  // Insert appointment record
  $stmt = $pdo->prepare("INSERT INTO appointments (customer_name, contact_number, customer_type, service, rim_count, rim_size, color_finish, vehicle, notes, preferred_date, preferred_time, status, queue_number) VALUES (?,?,'walk-in',?,?,?,?,?,?,?,?,'confirmed',?)");
  $stmt->execute([$_POST['customer_name'], $_POST['contact_number'], $_POST['service'], $_POST['rim_count'], $_POST['rim_size'], $_POST['color_finish'], $_POST['vehicle'], $_POST['notes'] ?? '', $date, date('H:i'), $qNum]);
  $apptId = $pdo->lastInsertId();

  // Insert queue record
  $stmt = $pdo->prepare("INSERT INTO queue (appointment_id, customer_name, contact_number, customer_type, service, rim_count, rim_size, color_finish, vehicle, queue_number, status, queue_date) VALUES (?,?,?,'walk-in',?,?,?,?,?,?,'waiting',?)");
  $stmt->execute([$apptId, $_POST['customer_name'], $_POST['contact_number'], $_POST['service'], $_POST['rim_count'], $_POST['rim_size'], $_POST['color_finish'], $_POST['vehicle'], $qNum, $date]);
  $msg = "Walk-in added. Queue #$qNum assigned.";
}

// Fetch all appointments
$filter = $_GET['status'] ?? '';
$sql = "SELECT a.*, u.email FROM appointments a LEFT JOIN users u ON a.user_id = u.id";
if ($filter) $sql .= " WHERE a.status = " . $pdo->quote($filter);
$sql .= " ORDER BY a.created_at DESC";
$appointments = $pdo->query($sql)->fetchAll();

$pending = array_filter($appointments, fn($a) => $a['status'] === 'pending');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BRC Calamba - Appointments</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="admin.css">
  <style>
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center}.modal-overlay.open{display:flex}.modal{background:#fff;border-radius:12px;padding:32px;width:100%;max-width:500px;box-shadow:0 20px 60px rgba(0,0,0,.2);max-height:90vh;overflow-y:auto}.modal h3{font-size:1.1rem;font-weight:700;margin-bottom:20px}.modal-footer{display:flex;gap:12px;margin-top:24px;justify-content:flex-end}
    .filter-bar{display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap}.filter-bar select,.filter-bar input{padding:9px 14px;border:1.5px solid var(--border);border-radius:8px;font-family:'Montserrat',sans-serif;font-size:.82rem;outline:none;color:var(--text)}.filter-bar select:focus,.filter-bar input:focus{border-color:var(--red)}
  </style>
</head>
<body>
<?php include '../config/admin-sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
    <div class="page-title"><h1>Appointments</h1><p>Manage online bookings and walk-in requests</p></div>
    <div class="topbar-actions">
      <button class="btn btn-red" onclick="document.getElementById('walkInModal').classList.add('open')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add Walk-In
      </button>
    </div>
  </div>
  <div class="content">
    <?php if ($msg): ?><div style="background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;padding:12px 16px;border-radius:8px;font-size:.83rem;font-weight:600;margin-bottom:20px;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <!-- PENDING ONLINE BOOKINGS -->
    <?php if (!empty($pending)): ?>
    <div class="section-card" style="margin-bottom:24px;border:1.5px solid var(--warning);">
      <div class="section-header"><h2>Pending Online Bookings</h2><span class="badge badge-pending"><?= count($pending) ?> Pending</span></div>
      <div style="padding:0;">
        <div class="table-wrapper">
<table class="data-table">
          <thead><tr><th>Customer</th><th>Email</th><th>Service</th><th>Preferred Date</th><th>Rims</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($pending as $a): ?>
            <tr>
              <td><strong><?= htmlspecialchars($a['customer_name']) ?></strong><br><small style="color:var(--muted);"><?= htmlspecialchars($a['contact_number']) ?></small></td>
              <td><?= htmlspecialchars($a['email'] ?? '—') ?></td>
              <td><?= htmlspecialchars($a['service']) ?></td>
              <td><?= date('M d, Y', strtotime($a['preferred_date'])) ?> <?= date('h:i A', strtotime($a['preferred_time'])) ?></td>
              <td><?= htmlspecialchars($a['rim_count'] . ' ' . $a['rim_size']) ?></td>
              <td>
                <div style="display:flex;gap:6px;">
                  <form method="POST" style="display:inline;"><input type="hidden" name="action" value="approve"><input type="hidden" name="appointment_id" value="<?= $a['id'] ?>"><button type="submit" class="btn btn-red" style="padding:5px 12px;font-size:.75rem;">✓ Approve</button></form>
                  <form method="POST" style="display:inline;"><input type="hidden" name="action" value="reject"><input type="hidden" name="appointment_id" value="<?= $a['id'] ?>"><button type="submit" class="btn btn-ghost" style="padding:5px 12px;font-size:.75rem;" onclick="return confirm('Cancel this appointment?')">✕ Reject</button></form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
</div>
      </div>
    </div>
    <?php endif; ?>

    <!-- FILTER + ALL APPOINTMENTS -->
    <div class="section-card">
      <div class="section-header"><h2>All Appointments</h2></div>
      <div class="section-body" style="padding-bottom:0;">
        <div class="filter-bar">
          <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;">
            <select name="status" onchange="this.form.submit()">
              <option value="">All Statuses</option>
              <?php foreach (['pending','confirmed','in_progress','completed','cancelled'] as $s): ?>
              <option value="<?= $s ?>" <?= $filter===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </div>
      </div>
      <div style="padding:0;">
        <div class="table-wrapper">
<table class="data-table">
          <thead><tr><th>Customer</th><th>Type</th><th>Service</th><th>Date & Time</th><th>Color</th><th>Status</th><th>Queue #</th></tr></thead>
          <tbody>
            <?php if (empty($appointments)): ?>
            <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--muted);font-size:.85rem;">No appointments found.</td></tr>
            <?php else: ?>
            <?php foreach ($appointments as $a): ?>
            <?php $badges=['pending'=>'badge-pending','confirmed'=>'badge-confirmed','in_progress'=>'badge-inprogress','completed'=>'badge-completed','cancelled'=>'badge-cancelled']; ?>
            <tr>
              <td><strong><?= htmlspecialchars($a['customer_name']) ?></strong><br><small style="color:var(--muted);"><?= htmlspecialchars($a['contact_number']) ?></small></td>
              <td><span class="badge <?= $a['customer_type']==='walk-in'?'badge-walkin':'badge-online' ?>"><?= ucfirst($a['customer_type']) ?></span></td>
              <td><?= htmlspecialchars($a['service']) ?></td>
              <td><?= date('M d, Y', strtotime($a['preferred_date'])) ?><br><small><?= date('h:i A', strtotime($a['preferred_time'])) ?></small></td>
              <td><?= htmlspecialchars($a['color_finish'] ?: '—') ?></td>
              <td><span class="badge <?= $badges[$a['status']] ?? '' ?>"><?= ucfirst(str_replace('_',' ',$a['status'])) ?></span></td>
              <td><?= $a['queue_number'] ? '#'.$a['queue_number'] : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
</div>
      </div>
    </div>
  </div>
</div>

<!-- WALK-IN MODAL -->
<div class="modal-overlay" id="walkInModal">
  <div class="modal">
    <h3>Add Walk-In Appointment</h3>
    <form method="POST">
      <input type="hidden" name="action" value="walkin">
      <div class="form-grid">
        <div class="form-group span2"><label>Customer Name</label><input type="text" name="customer_name" required placeholder="Enter customer name"></div>
        <div class="form-group"><label>Contact Number</label><input type="tel" name="contact_number" placeholder="09XX XXX XXXX"></div>
        <div class="form-group"><label>Motorcycle</label><input type="text" name="vehicle" placeholder="e.g. Honda CBR 150R"></div>
        <div class="form-group span2"><label>Service</label>
          <select name="service" required><option value="" disabled selected>Select...</option><optgroup label="Powder Coating"><option>Powder Coating — Matte Finish</option><option>Powder Coating — Gloss Finish</option><option>Powder Coating — Metallic Finish</option><option>Powder Coating — Custom Color</option></optgroup><optgroup label="Rim Services"><option>Rim Repair</option><option>Rim Straightening</option><option>Rim Cleaning & Polishing</option></optgroup></select>
        </div>
        <div class="form-group"><label>Rims</label><select name="rim_count"><option>1 Rim (Front)</option><option>1 Rim (Rear)</option><option>2 Rims (Full Set)</option></select></div>
        <div class="form-group"><label>Rim Size</label><select name="rim_size"><option>14"</option><option>17"</option><option>18"</option><option>19"</option><option>21"</option></select></div>
        <div class="form-group"><label>Color / Finish</label><input type="text" name="color_finish" placeholder="e.g. Matte Black"></div>
        <div class="form-group"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('walkInModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-red">Save & Add to Queue</button>
      </div>
    </form>
  </div>
</div>
</body>
</html>
