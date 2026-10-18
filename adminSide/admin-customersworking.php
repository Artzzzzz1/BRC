<?php
require_once '../config/auth.php';
require_once '../config/db.php';
requireAdmin();
$activePage = 'customers';
$msg = '';

// ADD WALK-IN RECORD (manual customer log)
if (isset($_POST['action']) && $_POST['action'] === 'add_walkin') {
  // We store walk-in info in appointments table as a simple record
  $stmt = $pdo->prepare("INSERT INTO appointments (customer_name,contact_number,customer_type,service,vehicle,notes,preferred_date,preferred_time,status) VALUES (?,?,'walk-in','Walk-In Record',?,?,'".date('Y-m-d')."','".date('H:i')."','completed')");
  $stmt->execute([$_POST['customer_name'],$_POST['contact_number'],$_POST['vehicle']??'',$_POST['notes']??'']);
  $msg = "Walk-in record saved.";
}

// SET USER STATUS (active/inactive)
if (isset($_POST['action']) && $_POST['action'] === 'set_status') {
  $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$_POST['role'],(int)$_POST['user_id']]);
  $msg = "User status updated.";
}

$tab = $_GET['tab'] ?? 'registered';

// Registered users
$users = $pdo->query("SELECT u.*, COUNT(DISTINCT a.id) as total_orders FROM users u LEFT JOIN appointments a ON u.id=a.user_id WHERE u.role IN ('customer','admin') GROUP BY u.id ORDER BY u.created_at DESC")->fetchAll();

// Walk-in records (appointments with no user_id)
$walkIns = $pdo->query("SELECT *, MAX(created_at) as last_visit, COUNT(*) as total_visits FROM appointments WHERE customer_type='walk-in' GROUP BY customer_name, contact_number ORDER BY last_visit DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BRC Calamba - Customers</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="admin.css">
  <style>
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center}.modal-overlay.open{display:flex}.modal{background:#fff;border-radius:12px;padding:32px;width:100%;max-width:480px;box-shadow:0 20px 60px rgba(0,0,0,.2)}.modal h3{font-size:1.1rem;font-weight:700;margin-bottom:20px}.modal-footer{display:flex;gap:12px;margin-top:24px;justify-content:flex-end}
    .tab-row{display:flex;gap:0;border-bottom:2px solid var(--border);margin-bottom:20px}.tab-item{padding:10px 20px;font-size:.85rem;font-weight:600;color:var(--muted);cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;text-decoration:none}.tab-item.active{color:var(--red);border-bottom-color:var(--red)}
  </style>
</head>
<body>
<?php include '../config/admin-sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
    <div class="page-title"><h1>Customers</h1><p>Registered users and walk-in records</p></div>
    <div class="topbar-actions"><button class="btn btn-red" onclick="document.getElementById('walkInModal').classList.add('open')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add Walk-In Record</button></div>
  </div>
  <div class="content">
    <?php if ($msg): ?><div style="background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;padding:12px 16px;border-radius:8px;font-size:.83rem;font-weight:600;margin-bottom:20px;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="stats-grid" style="margin-bottom:24px;">
      <div class="stat-card"><div class="stat-number"><?= count($users) ?></div><div class="stat-label">Registered Users</div></div>
      <div class="stat-card"><div class="stat-number"><?= count($walkIns) ?></div><div class="stat-label">Walk-In Records</div></div>
      <div class="stat-card"><div class="stat-number"><?= count($users) + count($walkIns) ?></div><div class="stat-label">Total Customers</div></div>
    </div>

    <div class="section-card">
      <div class="section-body" style="padding-bottom:0;">
        <div class="tab-row">
          <a href="?tab=registered" class="tab-item <?= $tab==='registered'?'active':'' ?>">Registered Users</a>
          <a href="?tab=walkin"     class="tab-item <?= $tab==='walkin'?'active':'' ?>">Walk-In Records</a>
        </div>

        <?php if ($tab === 'registered'): ?>
        <div class="table-wrapper">
<table class="data-table">
          <thead><tr><th>Name</th><th>Email</th><th>Contact</th><th>Registered</th><th>Total Orders</th><th>Role</th><th>Action</th></tr></thead>
          <tbody>
            <?php if (empty($users)): ?><tr><td colspan="7" style="text-align:center;padding:40px;color:var(--muted);">No registered users yet.</td></tr>
            <?php else: foreach ($users as $u): ?>
            <tr>
              <td><strong><?= htmlspecialchars($u['full_name']) ?></strong></td>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td><?= htmlspecialchars($u['contact_number'] ?: '—') ?></td>
              <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
              <td><?= $u['total_orders'] ?></td>
              <td><span class="badge <?= $u['role']==='admin'?'badge-confirmed':'badge-completed' ?>"><?= ucfirst($u['role']) ?></span></td>
              <td>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="set_status">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <select name="role" onchange="this.form.submit()" style="padding:4px 8px;border:1.5px solid var(--border);border-radius:6px;font-family:'Montserrat',sans-serif;font-size:.75rem;">
                    <option value="customer" <?= $u['role']==='customer'?'selected':'' ?>>Customer</option>
                    <option value="admin"    <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
                  </select>
                </form>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
</div>
        <?php else: ?>
        <div class="table-wrapper">
<table class="data-table">
          <thead><tr><th>Name</th><th>Contact</th><th>Motorcycle</th><th>Last Visit</th><th>Total Visits</th></tr></thead>
          <tbody>
            <?php if (empty($walkIns)): ?><tr><td colspan="5" style="text-align:center;padding:40px;color:var(--muted);">No walk-in records yet.</td></tr>
            <?php else: foreach ($walkIns as $w): ?>
            <tr>
              <td><strong><?= htmlspecialchars($w['customer_name']) ?></strong></td>
              <td><?= htmlspecialchars($w['contact_number'] ?: '—') ?></td>
              <td><?= htmlspecialchars($w['vehicle'] ?: '—') ?></td>
              <td><?= date('M d, Y', strtotime($w['last_visit'])) ?></td>
              <td><?= $w['total_visits'] ?></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="modal-overlay" id="walkInModal">
  <div class="modal">
    <h3>Add Walk-In Customer Record</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add_walkin">
      <div class="form-grid">
        <div class="form-group span2"><label>Customer Name</label><input type="text" name="customer_name" required placeholder="Enter full name"></div>
        <div class="form-group"><label>Contact Number</label><input type="tel" name="contact_number" placeholder="09XX XXX XXXX"></div>
        <div class="form-group"><label>Motorcycle</label><input type="text" name="vehicle" placeholder="e.g. Honda CBR 150R"></div>
        <div class="form-group span2"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('walkInModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-red">Save Record</button>
      </div>
    </form>
  </div>
</div>
</body>
</html>
