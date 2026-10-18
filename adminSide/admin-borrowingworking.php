<?php
require_once '../config/auth.php';
require_once '../config/db.php';
requireAdmin();
$activePage = 'borrowing';
$msg = '';

// Enable PDO error reporting so failures are visible
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Auto-update overdue (safe - won't crash if table empty)
try {
  $pdo->query("UPDATE borrowings SET status='overdue' WHERE status='borrowed' AND return_deadline < CURDATE()");
} catch (Exception $e) { /* table may be empty, ignore */ }

// ISSUE LOANER
if (isset($_POST['action']) && $_POST['action'] === 'issue') {
  try {
    $countResult = $pdo->query("SELECT COUNT(*)+1 FROM borrowings");
    $brCount = $countResult ? (int)$countResult->fetchColumn() : 1;
    $brNum = 'BR-' . date('Y') . '-' . str_pad($brCount, 4, '0', STR_PAD_LEFT);
    $stmt = $pdo->prepare("INSERT INTO borrowings (br_number,job_order_id,user_id,customer_name,contact_number,customer_type,loaner_rim,rims_left_behind,issue_date,return_deadline,status,notes) VALUES (?,?,?,?,?,?,?,?,?,?,'borrowed',?)");
    $stmt->execute([$brNum,$_POST['job_order_id']??null,$_POST['user_id']??null,$_POST['customer_name'],$_POST['contact_number'],$_POST['customer_type'],$_POST['loaner_rim'],$_POST['rims_left_behind'],$_POST['issue_date'],$_POST['return_deadline'],$_POST['notes']??'']);
    $msg = "Loaner rims issued. Reference: $brNum";
  } catch (Exception $e) {
    $msg = "Error issuing loaner: " . $e->getMessage();
  }
}

// MARK RETURNED
if (isset($_POST['action']) && $_POST['action'] === 'return') {
  try {
    $id = (int)$_POST['borrow_id'];
    $pdo->prepare("UPDATE borrowings SET status='returned', returned_at=NOW() WHERE id=?")->execute([$id]);
    $msg = "Loaner rims marked as returned.";
  } catch (Exception $e) {
    $msg = "Error updating return: " . $e->getMessage();
  }
}

// Fetch all borrowings — always returns array even if table is empty
try {
  $stmtB = $pdo->query("SELECT b.*, jo.jo_number FROM borrowings b LEFT JOIN job_orders jo ON b.job_order_id = jo.id ORDER BY b.created_at DESC");
  $borrowings = $stmtB ? $stmtB->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {
  $borrowings = [];
}
// Ensure $borrowings is always an array before using it
if (!is_array($borrowings)) {
  $borrowings = [];
}
$activeBorrowings  = is_array($borrowings) ? array_filter($borrowings, fn($b) => is_array($b) && in_array($b['status'] ?? '', ['borrowed','overdue'])) : [];
$borrowingHistory = is_array($borrowings) ? array_filter($borrowings, fn($b) => is_array($b) && ($b['status'] ?? '') === 'returned') : [];

// Ensure $activeBorrowings and $borrowingHistory are always arrays
if (!is_array($activeBorrowings)) $activeBorrowings = [];
if (!is_array($borrowingHistory)) $borrowingHistory = [];

// Fetch active job orders for issue modal
try {
  $stmtJ = $pdo->query("SELECT id, jo_number, customer_name FROM job_orders WHERE status IN ('in_progress','ready') ORDER BY created_at DESC");
  $activeJOs = $stmtJ ? $stmtJ->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {
  $activeJOs = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BRC Calamba - Borrowing</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="admin.css">
  <style>.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center}.modal-overlay.open{display:flex}.modal{background:#fff;border-radius:12px;padding:32px;width:100%;max-width:500px;box-shadow:0 20px 60px rgba(0,0,0,.2);max-height:90vh;overflow-y:auto}.modal h3{font-size:1.1rem;font-weight:700;margin-bottom:20px}.modal-footer{display:flex;gap:12px;margin-top:24px;justify-content:flex-end}</style>
</head>
<body>
<?php include '../config/admin-sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
    <div class="page-title"><h1>Rim Borrowing</h1><p>Issue and manage loaner rims for customers</p></div>
    <div class="topbar-actions"><button class="btn btn-red" onclick="document.getElementById('issueModal').classList.add('open')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Issue Loaner Rims</button></div>
  </div>
  <div class="content">
    <?php if ($msg): ?><div style="background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;padding:12px 16px;border-radius:8px;font-size:.83rem;font-weight:600;margin-bottom:20px;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="stats-grid" style="margin-bottom:24px;">
      <?php $overdue = is_array($activeBorrowings) ? array_filter($activeBorrowings, fn($b) => is_array($b) && ($b['status'] ?? '') === 'overdue') : []; $dueThisWeek = is_array($activeBorrowings) ? array_filter($activeBorrowings, fn($b) => is_array($b) && strtotime($b['return_deadline'] ?? '') <= strtotime('+7 days') && ($b['status'] ?? '') === 'borrowed') : []; ?>
      <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:#ede9fe;"><svg viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/></svg></div></div><div class="stat-number"><?= count(is_array($activeBorrowings) ? $activeBorrowings : []) ?></div><div class="stat-label">Active Borrowings</div></div>
      <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:#fef3c7;"><svg viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></div><div class="stat-number"><?= count(is_array($dueThisWeek) ? $dueThisWeek : []) ?></div><div class="stat-label">Due This Week</div></div>
      <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:#fff1f2;"><svg viewBox="0 0 24 24" fill="none" stroke="#be123c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div></div><div class="stat-number"><?= count(is_array($overdue) ? $overdue : []) ?></div><div class="stat-label">Overdue</div></div>
      <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:#dcfce7;"><svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div></div><div class="stat-number"><?= count(is_array($borrowingHistory) ? $borrowingHistory : []) ?></div><div class="stat-label">Returned Total</div></div>
    </div>

    <!-- ACTIVE -->
    <div class="section-card" style="margin-bottom:24px;">
      <div class="section-header"><h2>Active Borrowings</h2></div>
      <div style="padding:0;">
        <?php if (empty($activeBorrowings)): ?>
          <div class="section-body"><div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="17 1 21 5 17 9"/></svg><h3>No Active Borrowings</h3></div></div>
        <?php else: ?>
        <div class="table-wrapper">
<table class="data-table">
          <thead><tr><th>Ref #</th><th>Customer</th><th>Loaner Rim</th><th>Rims Left Behind</th><th>JO #</th><th>Return By</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($activeBorrowings as $b): $od = strtotime($b['return_deadline']) < time(); ?>
            <tr>
              <td><strong><?= htmlspecialchars($b['br_number']) ?></strong></td>
              <td><?= htmlspecialchars($b['customer_name']) ?><br><small style="color:var(--muted);"><?= htmlspecialchars($b['contact_number']) ?></small></td>
              <td><?= htmlspecialchars($b['loaner_rim']) ?></td>
              <td><?= htmlspecialchars($b['rims_left_behind']) ?></td>
              <td><?= htmlspecialchars($b['jo_number'] ?? '—') ?></td>
              <td style="color:<?= $od?'var(--red)':'inherit' ?>;font-weight:<?= $od?'700':'400' ?>;"><?= date('M d, Y', strtotime($b['return_deadline'])) ?> <?= $od?'⚠️':'' ?></td>
              <td><span class="badge <?= $b['status']==='overdue'?'badge-cancelled':'badge-borrowed' ?>"><?= ucfirst($b['status']) ?></span></td>
              <td>
                <form method="POST"><input type="hidden" name="action" value="return"><input type="hidden" name="borrow_id" value="<?= $b['id'] ?>"><button type="submit" class="btn btn-ghost" style="padding:5px 12px;font-size:.75rem;" onclick="return confirm('Mark as returned?')">Mark Returned</button></form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- HISTORY -->
    <div class="section-card">
      <div class="section-header"><h2>Borrowing History</h2></div>
      <div style="padding:0;">
        <div class="table-wrapper">
<table class="data-table">
          <thead><tr><th>Ref #</th><th>Customer</th><th>Loaner Rim</th><th>Issue Date</th><th>Returned</th><th>Status</th></tr></thead>
          <tbody>
            <?php if (empty($borrowingHistory)): ?><tr><td colspan="6" style="text-align:center;padding:40px;color:var(--muted);">No history yet.</td></tr>
            <?php else: foreach ($borrowingHistory as $b): ?>
            <tr>
              <td><strong><?= htmlspecialchars($b['br_number']) ?></strong></td>
              <td><?= htmlspecialchars($b['customer_name']) ?></td>
              <td><?= htmlspecialchars($b['loaner_rim']) ?></td>
              <td><?= date('M d, Y', strtotime($b['issue_date'])) ?></td>
              <td><?= $b['returned_at'] ? date('M d, Y', strtotime($b['returned_at'])) : '—' ?></td>
              <td><span class="badge badge-completed">Returned</span></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
</div>
      </div>
    </div>

  </div>
</div>

<!-- ISSUE MODAL -->
<div class="modal-overlay" id="issueModal">
  <div class="modal">
    <h3>Issue Loaner Rims</h3>
    <form method="POST">
      <input type="hidden" name="action" value="issue">
      <div class="form-grid">
        <div class="form-group span2"><label>Customer Type</label><select name="customer_type"><option value="walk-in">Walk-In</option><option value="online">Registered User</option></select></div>
        <div class="form-group span2"><label>Customer Name</label><input type="text" name="customer_name" required placeholder="Enter customer name"></div>
        <div class="form-group"><label>Contact Number</label><input type="tel" name="contact_number" placeholder="09XX XXX XXXX"></div>
        <div class="form-group"><label>Link to Job Order</label>
          <select name="job_order_id"><option value="">— None —</option>
          <?php foreach ($activeJOs as $jo): ?><option value="<?= $jo['id'] ?>"><?= htmlspecialchars($jo['jo_number'].' — '.$jo['customer_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group span2"><label>Rims Left Behind by Customer</label><input type="text" name="rims_left_behind" required placeholder="e.g. 2 Rims (17&quot;) under powder coating"></div>
        <div class="form-group span2"><label>Loaner Rim Issued</label><input type="text" name="loaner_rim" required placeholder="e.g. BRC Loaner #07 — 17&quot; Silver Spare"></div>
        <div class="form-group"><label>Issue Date</label><input type="date" name="issue_date" value="<?= date('Y-m-d') ?>" required></div>
        <div class="form-group"><label>Return Deadline</label><input type="date" name="return_deadline" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required></div>
        <div class="form-group span2"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('issueModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-red">Issue Loaner Rims</button>
      </div>
    </form>
  </div>
</div>
</body>
</html>
