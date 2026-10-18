<?php
require_once '../config/auth.php';
require_once '../config/db.php';
requireUser();
$activePage = 'appointment';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $service        = trim($_POST['service'] ?? '');
  $preferred_date = trim($_POST['preferred_date'] ?? '');
  $preferred_time = trim($_POST['preferred_time'] ?? '');
  $rim_count      = trim($_POST['rim_count'] ?? '');
  $rim_size       = trim($_POST['rim_size'] ?? '');
  $color_finish   = trim($_POST['color_finish'] ?? '');
  $vehicle        = trim($_POST['vehicle'] ?? '');
  $notes          = trim($_POST['notes'] ?? '');

  // Phone number comes from the customer's account (session may predate the login fix, so fall back to the DB)
  $contact = $_SESSION['contact_number'] ?? '';
  if ($contact === '') {
    $c = $pdo->prepare("SELECT contact_number FROM users WHERE id = ?");
    $c->execute([userId()]);
    $contact = (string)$c->fetchColumn();
    $_SESSION['contact_number'] = $contact;
  }

  if (empty($service) || empty($preferred_date) || empty($preferred_time)) {
    $error = 'Please fill in all required fields.';
  } else {
    $stmt = $pdo->prepare("
      INSERT INTO appointments
        (user_id, customer_name, contact_number, customer_type, service, rim_count, rim_size, color_finish, vehicle, notes, preferred_date, preferred_time, status)
      VALUES (?, ?, ?, 'online', ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
      userId(), userName(),
      $contact,
      $service, $rim_count, $rim_size, $color_finish, $vehicle, $notes,
      $preferred_date, $preferred_time
    ]);
    $success = 'Appointment booked successfully! Please wait for confirmation.';
  }
}

$stmt = $pdo->prepare("SELECT * FROM appointments WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([userId()]);
$appointments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BRC Calamba - Book Appointment</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="user-dashboard.css">
</head>
<body>

<?php include '../config/user-sidebar.php'; ?>

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
      <h1>Book Appointment</h1>
      <p>Schedule your powder coating or rim service</p>
    </div>
  </div>

  <div class="content">

    <?php if ($error): ?>
    <div style="background:#fff1f2;color:#be123c;border:1px solid #fecdd3;padding:12px 16px;border-radius:8px;font-size:0.83rem;font-weight:600;margin-bottom:20px;">
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div style="background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;padding:12px 16px;border-radius:8px;font-size:0.83rem;font-weight:600;margin-bottom:20px;">
      <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:24px;align-items:start;">

      <!-- BOOKING FORM -->
      <div class="section-card">
        <div class="section-header"><h2>Appointment Details</h2></div>
        <div class="section-body">
          <form method="POST">
            <div class="form-grid">
              <div class="form-group span2">
                <label>Service Type <span style="color:var(--red)">*</span></label>
                <select name="service" required>
                  <option value="" disabled selected>Select a service...</option>
                  <optgroup label="Powder Coating">
                    <option>Powder Coating — Matte Finish</option>
                    <option>Powder Coating — Gloss Finish</option>
                    <option>Powder Coating — Metallic Finish</option>
                    <option>Powder Coating — Custom Color</option>
                  </optgroup>
                  <optgroup label="Rim Services">
                    <option>Rim Repair</option>
                    <option>Rim Straightening</option>
                    <option>Rim Cleaning & Polishing</option>
                  </optgroup>
                </select>
              </div>
              <div class="form-group">
                <label>Preferred Date <span style="color:var(--red)">*</span></label>
                <input type="date" name="preferred_date" min="<?= date('Y-m-d') ?>" required>
              </div>
              <div class="form-group">
                <label>Preferred Time <span style="color:var(--red)">*</span></label>
                <select name="preferred_time" required>
                  <option value="" disabled selected>Select a time...</option>
                  <option value="08:00">08:00 AM</option>
                  <option value="09:00">09:00 AM</option>
                  <option value="10:00">10:00 AM</option>
                  <option value="11:00">11:00 AM</option>
                  <option value="13:00">01:00 PM</option>
                  <option value="14:00">02:00 PM</option>
                  <option value="15:00">03:00 PM</option>
                  <option value="16:00">04:00 PM</option>
                </select>
              </div>
              <div class="form-group">
                <label>Number of Rims</label>
                <select name="rim_count">
                  <option value="" disabled selected>Select...</option>
                  <option>1 Rim (Front)</option>
                  <option>1 Rim (Rear)</option>
                  <option>2 Rims (Full Set)</option>
                </select>
              </div>
              <div class="form-group">
                <label>Rim Size</label>
                <select name="rim_size">
                  <option value="" disabled selected>Select size...</option>
                  <option>14"</option><option>17"</option><option>18"</option>
                  <option>19"</option><option>21"</option>
                </select>
              </div>
              <div class="form-group">
                <label>Color / Finish</label>
                <input type="text" name="color_finish" placeholder="e.g. Matte Black, Gloss Red">
              </div>
              <div class="form-group">
                <label>Motorcycle</label>
                <input type="text" name="vehicle" placeholder="e.g. Honda CBR 150R">
              </div>
              <div class="form-group span2">
                <label>Additional Notes</label>
                <textarea name="notes" rows="3" placeholder="Any special requests..."></textarea>
              </div>
            </div>
            <div style="display:flex;gap:12px;margin-top:24px;flex-wrap:wrap;">
              <button type="submit" class="btn btn-red">Confirm Booking</button>
              <button type="reset" class="btn btn-ghost">Clear Form</button>
            </div>
          </form>
        </div>
      </div>

      <!-- SIDEBAR INFO -->
      <div style="display:flex;flex-direction:column;gap:20px;">
        <div class="section-card">
          <div class="section-header"><h2>How It Works</h2></div>
          <div class="section-body">
            <div style="display:flex;flex-direction:column;gap:16px;">
              <?php foreach ([
                ['Book Online',    'Fill out the form and pick your preferred date & time.'],
                ['Bring Your Rims','Drop off your motorcycle rims on your scheduled appointment.'],
                ['Track Progress', 'Monitor your job order step by step from your dashboard.'],
                ['Pick Up',        'Get notified when your rims are ready for pickup.']
              ] as $i => $step): ?>
              <div style="display:flex;gap:14px;align-items:flex-start;">
                <div style="min-width:28px;height:28px;background:var(--red);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:0.75rem;font-weight:700;flex-shrink:0;"><?= $i+1 ?></div>
                <div>
                  <div style="font-size:0.85rem;font-weight:700;margin-bottom:3px;"><?= $step[0] ?></div>
                  <div style="font-size:0.78rem;color:var(--muted);"><?= $step[1] ?></div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="section-card">
          <div class="section-header"><h2>Business Hours</h2></div>
          <div class="section-body">
            <div style="display:flex;flex-direction:column;gap:8px;font-size:0.83rem;">
              <div style="display:flex;justify-content:space-between;"><span style="font-weight:600;">Mon – Fri</span><span>8:00 AM – 6:00 PM</span></div>
              <div style="display:flex;justify-content:space-between;"><span style="font-weight:600;">Saturday</span><span>8:00 AM – 5:00 PM</span></div>
              <div style="display:flex;justify-content:space-between;"><span style="font-weight:600;">Sunday</span><span style="color:var(--red);">Closed</span></div>
            </div>
            <div style="margin-top:16px;padding:12px;background:var(--red-light);border-radius:8px;font-size:0.78rem;color:var(--red);font-weight:600;">
              ⚠️ Rim borrowing available for customers still on queue at closing time.
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MY APPOINTMENTS TABLE -->
    <div class="section-card" style="margin-top:24px;">
      <div class="section-header"><h2>My Appointments</h2></div>
      <div style="padding:0;">
        <?php if (empty($appointments)): ?>
          <div class="section-body">
            <div class="empty-state">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              <h3>No Appointments Yet</h3>
              <p>Your appointment history will appear here.</p>
            </div>
          </div>
        <?php else: ?>
        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr>
                <th>Service</th>
                <th>Date &amp; Time</th>
                <th>Rims</th>
                <th>Status</th>
                <th>Queue #</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $badges = ['pending'=>'badge-pending','confirmed'=>'badge-confirmed','in_progress'=>'badge-inprogress','completed'=>'badge-completed','cancelled'=>'badge-cancelled'];
              $labels = ['pending'=>'Pending','confirmed'=>'Confirmed','in_progress'=>'In Progress','completed'=>'Completed','cancelled'=>'Cancelled'];
              foreach ($appointments as $a): ?>
              <tr>
                <td><strong><?= htmlspecialchars($a['service']) ?></strong></td>
                <td><?= date('M d, Y', strtotime($a['preferred_date'])) ?> &nbsp;·&nbsp; <?= date('h:i A', strtotime($a['preferred_time'])) ?></td>
                <td><?= htmlspecialchars($a['rim_count'] . ' ' . $a['rim_size']) ?></td>
                <td><span class="badge <?= $badges[$a['status']] ?? '' ?>"><?= $labels[$a['status']] ?? $a['status'] ?></span></td>
                <td><?= $a['queue_number'] ? '#' . $a['queue_number'] : '—' ?></td>
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
