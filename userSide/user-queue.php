<?php
require_once '../config/auth.php';
require_once '../config/db.php';
requireUser();
$activePage = 'queue';

$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT * FROM queue WHERE user_id = ? AND queue_date = ? LIMIT 1");
$stmt->execute([userId(), $today]);
$myQueue = $stmt->fetch();

// Count people ahead
$ahead = 0;
if ($myQueue && $myQueue['status'] === 'waiting') {
  $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM queue WHERE queue_date = ? AND status = 'waiting' AND queue_number < ?");
  $stmt2->execute([$today, $myQueue['queue_number']]);
  $ahead = $stmt2->fetchColumn();
}

// Full queue list today
$stmt3 = $pdo->prepare("SELECT * FROM queue WHERE queue_date = ? ORDER BY queue_number ASC");
$stmt3->execute([$today]);
$fullQueue = $stmt3->fetchAll();

// Serving now
$stmt4 = $pdo->prepare("SELECT * FROM queue WHERE queue_date = ? AND status = 'serving' LIMIT 1");
$stmt4->execute([$today]);
$serving = $stmt4->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BRC Calamba - Queue Status</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="user-dashboard.css">
</head>
<body>
<?php include '../config/user-sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
    <div class="page-title"><h1>Queue Status</h1><p>Today's live queue — <?= date('F d, Y') ?></p></div>
  </div>
  <div class="content">

    <?php if ($myQueue): ?>
    <div class="section-card" style="margin-bottom:24px;border:2px solid var(--red);">
      <div class="section-header"><h2>Your Queue Position</h2><span class="badge <?= $myQueue['status'] === 'waiting' ? 'badge-pending' : ($myQueue['status'] === 'serving' ? 'badge-confirmed' : 'badge-completed') ?>"><?= ucfirst($myQueue['status']) ?></span></div>
      <div class="section-body" style="text-align:center;padding:32px;">
        <div style="font-family:'Bebas Neue',sans-serif;font-size:6rem;color:var(--red);line-height:1;">#<?= $myQueue['queue_number'] ?></div>
        <div style="font-size:0.9rem;color:var(--muted);margin-top:8px;"><?= htmlspecialchars($myQueue['service']) ?></div>
        <?php if ($myQueue['status'] === 'waiting'): ?>
          <div style="margin-top:20px;padding:14px;background:var(--bg);border-radius:10px;display:inline-block;">
            <span style="font-size:0.85rem;color:var(--muted);">People ahead of you: </span>
            <strong style="color:var(--text);font-size:1rem;"><?= $ahead ?></strong>
          </div>
        <?php elseif ($myQueue['status'] === 'serving'): ?>
          <div style="margin-top:20px;padding:14px 24px;background:#f0fdf4;border-radius:10px;border:1px solid #bbf7d0;display:inline-block;color:#15803d;font-weight:700;">🎉 It's your turn! Please proceed to the counter.</div>
        <?php elseif ($myQueue['status'] === 'done'): ?>
          <div style="margin-top:20px;padding:14px 24px;background:#f0fdf4;border-radius:10px;border:1px solid #bbf7d0;display:inline-block;color:#15803d;font-weight:700;">✅ Service complete. Thank you!</div>
        <?php endif; ?>
      </div>
    </div>
    <?php else: ?>
    <div class="section-card" style="margin-bottom:24px;">
      <div class="section-body"><div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <h3>You're Not in Today's Queue</h3>
        <p>You'll appear here once admin adds you to today's queue.</p>
      </div></div>
    </div>
    <?php endif; ?>

    <div class="two-col-grid">
      <div class="section-card">
        <div class="section-header"><h2>Now Serving</h2></div>
        <div class="section-body" style="text-align:center;padding:24px;">
          <?php if ($serving): ?>
            <div style="font-family:'Bebas Neue',sans-serif;font-size:4rem;color:var(--info);line-height:1;">#<?= $serving['queue_number'] ?></div>
            <div style="font-size:0.85rem;color:var(--muted);margin-top:6px;"><?= htmlspecialchars($serving['customer_name']) ?></div>
          <?php else: ?>
            <div style="color:var(--muted);font-size:0.85rem;">No one being served right now</div>
          <?php endif; ?>
        </div>
      </div>
      <div class="section-card">
        <div class="section-header"><h2>Queue Summary</h2></div>
        <div class="section-body" style="display:flex;flex-direction:column;gap:12px;">
          <?php
          $waiting = array_filter($fullQueue, fn($q) => $q['status'] === 'waiting');
          $done    = array_filter($fullQueue, fn($q) => $q['status'] === 'done');
          ?>
          <div style="display:flex;justify-content:space-between;font-size:0.85rem;"><span style="color:var(--muted);">Total in queue</span><strong><?= count($fullQueue) ?></strong></div>
          <div style="display:flex;justify-content:space-between;font-size:0.85rem;"><span style="color:var(--muted);">Waiting</span><strong><?= count($waiting) ?></strong></div>
          <div style="display:flex;justify-content:space-between;font-size:0.85rem;"><span style="color:var(--muted);">Served today</span><strong><?= count($done) ?></strong></div>
        </div>
      </div>
    </div>

    <div class="section-card" style="margin-top:20px;">
      <div class="section-header"><h2>Full Queue List</h2></div>
      <div style="padding:0;">
        <?php if (empty($fullQueue)): ?>
          <div class="section-body"><div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><h3>Queue is Empty</h3><p>No customers in queue today.</p></div></div>
        <?php else: ?>
        <div class="table-wrapper">
<table class="data-table">
          <thead><tr><th>Queue #</th><th>Customer</th><th>Service</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($fullQueue as $q): ?>
            <tr <?= $q['user_id'] == userId() ? 'style="background:#fff8f8;"' : '' ?>>
              <td><strong style="font-family:'Bebas Neue',sans-serif;font-size:1.2rem;color:var(--red);">#<?= $q['queue_number'] ?></strong></td>
              <td><?= $q['user_id'] == userId() ? '<strong>You</strong>' : htmlspecialchars($q['customer_name']) ?></td>
              <td><?= htmlspecialchars($q['service']) ?></td>
              <td><span class="badge <?= $q['status'] === 'waiting' ? 'badge-pending' : ($q['status'] === 'serving' ? 'badge-confirmed' : 'badge-completed') ?>"><?= ucfirst($q['status']) ?></span></td>
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
