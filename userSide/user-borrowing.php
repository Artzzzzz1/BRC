<?php
require_once '../config/auth.php';
require_once '../config/db.php';
requireUser();
$activePage = 'borrowing';

$stmt = $pdo->prepare("SELECT b.*, jo.jo_number FROM borrowings b LEFT JOIN job_orders jo ON b.job_order_id = jo.id WHERE b.user_id = ? ORDER BY b.created_at DESC");
$stmt->execute([userId()]);
$borrowings = $stmt->fetchAll();

$active  = array_filter($borrowings, fn($b) => in_array($b['status'], ['borrowed','overdue']));
$history = array_filter($borrowings, fn($b) => $b['status'] === 'returned');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BRC Calamba - Rim Borrowing</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="user-dashboard.css">
</head>
<body>
<?php include '../config/user-sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
    <div class="page-title"><h1>Rim Borrowing</h1><p>Track your loaner rim status</p></div>
  </div>
  <div class="content">

    <div style="background:#fff8f0;border:1px solid #fed7aa;border-radius:10px;padding:14px 20px;font-size:0.83rem;font-weight:600;color:#c2410c;margin-bottom:24px;">
      ⚠️ Loaner rims must be returned within <strong>7 days</strong> after your job order is completed. Late returns may incur fees.
    </div>

    <div class="section-card" style="margin-bottom:24px;">
      <div class="section-header"><h2>Active Borrowing</h2><span class="badge badge-borrowed">Loaner Out</span></div>
      <div style="padding:0;">
        <?php if (empty($active)): ?>
          <div class="section-body"><div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/></svg><h3>No Active Borrowing</h3><p>You currently have no loaner rims issued.</p></div></div>
        <?php else: ?>
          <?php foreach ($active as $b): ?>
          <div style="padding:20px 24px;border-bottom:1px solid var(--border);">
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
              <div><div style="font-size:0.72rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Reference #</div><div style="font-weight:700;"><?= htmlspecialchars($b['br_number']) ?></div></div>
              <div><div style="font-size:0.72rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Loaner Rim</div><div style="font-weight:600;"><?= htmlspecialchars($b['loaner_rim']) ?></div></div>
              <div><div style="font-size:0.72rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Return By</div>
                <?php $overdue = strtotime($b['return_deadline']) < time(); ?>
                <div style="font-weight:700;color:<?= $overdue ? 'var(--red)' : 'var(--success)' ?>;"><?= date('M d, Y', strtotime($b['return_deadline'])) ?> <?= $overdue ? '⚠️ Overdue' : '' ?></div>
              </div>
              <div><div style="font-size:0.72rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Your Rims Left</div><div style="font-size:0.83rem;"><?= htmlspecialchars($b['rims_left_behind']) ?></div></div>
              <div><div style="font-size:0.72rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Job Order</div><div style="font-size:0.83rem;"><?= htmlspecialchars($b['jo_number'] ?? '—') ?></div></div>
              <div><div style="font-size:0.72rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Status</div><span class="badge <?= $b['status'] === 'overdue' ? 'badge-cancelled' : 'badge-borrowed' ?>"><?= ucfirst($b['status']) ?></span></div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="section-card">
      <div class="section-header"><h2>Borrowing History</h2></div>
      <div style="padding:0;">
        <?php if (empty($history)): ?>
          <div class="section-body"><div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="17 1 21 5 17 9"/></svg><h3>No History Yet</h3></div></div>
        <?php else: ?>
        <div class="table-wrapper">
<table class="data-table">
          <thead><tr><th>Reference #</th><th>Loaner Rim</th><th>Your Rims Left</th><th>Issue Date</th><th>Returned</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($history as $b): ?>
            <tr>
              <td><strong><?= htmlspecialchars($b['br_number']) ?></strong></td>
              <td><?= htmlspecialchars($b['loaner_rim']) ?></td>
              <td><?= htmlspecialchars($b['rims_left_behind']) ?></td>
              <td><?= date('M d, Y', strtotime($b['issue_date'])) ?></td>
              <td><?= $b['returned_at'] ? date('M d, Y', strtotime($b['returned_at'])) : '—' ?></td>
              <td><span class="badge badge-completed">Returned</span></td>
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
