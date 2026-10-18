<?php
// Usage: include '../config/user-sidebar.php';
// Set $activePage before including, e.g. $activePage = 'dashboard';
$activePage = $activePage ?? '';
?>
<aside class="sidebar">
  <div class="sidebar-brand">
    <a href="user-dashboard.php">BRC <span>Calamba</span></a>
    <p>Customer Portal</p>
  </div>
  <nav class="sidebar-nav">
    <?php
    $nav = [
      'dashboard'   => ['user-dashboard.php',   'Dashboard',       '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>'],
      'appointment' => ['user-appointment.php',  'Book Appointment','<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'],
      'joborder'    => ['user-joborder.php',     'My Job Orders',   '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
      'queue'       => ['user-queue.php',        'Queue Status',    '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'],
      'borrowing'   => ['user-borrowing.php',    'Rim Borrowing',   '<polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>'],
      '3dpreview'   => ['user-rimselect.php',    '3D Rim Preview',  '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'],
    ];
    foreach ($nav as $key => [$url, $label, $icon]):
      $active = $activePage === $key ? 'active' : '';
    ?>
    <a href="<?= $url ?>" class="nav-item <?= $active ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $icon ?></svg>
      <?= $label ?>
    </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar"><?php echo userInitials(); ?></div>
      <div class="user-details">
        <div class="user-name"><?php echo htmlspecialchars(userName()); ?></div>
        <div class="user-role">Customer</div>
      </div>
    </div>
    <a href="../config/logout.php" class="logout-btn">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Log Out
    </a>
  </div>
</aside>
