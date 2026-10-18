<?php
// Usage: include '../config/admin-sidebar.php';
// Set $activePage before including, e.g. $activePage = 'dashboard';
$activePage = $activePage ?? '';
?>
<!-- Sidebar overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <a href="admin-dashboard.php"><img src="../public/assets/images/logo-icon.jpg" alt="BRC" class="sidebar-logo-icon"></a>
    <p>Admin Panel</p>
  </div>
  <nav class="sidebar-nav">
    <?php
    $nav = [
      'dashboard'    => ['admin-dashboard.php',    'Dashboard',    '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>'],
      'appointments' => ['admin-appointments.php', 'Appointments', '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'],
      'queue'        => ['admin-queue.php',        'Queue',        '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'],
      'joborders'    => ['admin-joborders.php',    'Job Orders',   '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'],
      'borrowing'    => ['admin-borrowing.php',    'Borrowing',    '<polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>'],
      'customers'    => ['admin-customers.php',    'Customers',    '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
      'reports'      => ['admin-reports.php',      'Reports',      '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'],
    ];
    foreach ($nav as $key => [$url, $label, $icon]):
      $active = $activePage === $key ? 'active' : '';
    ?>
    <a href="<?= $url ?>" class="nav-item <?= $active ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $icon ?></svg>
      <?= $label ?>
    </a>
    <?php endforeach; ?>

    <?php if (isOwner()): ?>
    <div style="margin:12px 0;border-top:1px solid var(--border);"></div>
    <p style="font-size:.68rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;padding:0 12px 6px;">Owner Access</p>
    <a href="../ownerSide/owner-dashboard.php" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Back to Owner Panel
    </a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-footer">
    <?php
      // Show who is REALLY logged in (the owner used to be shown as "Admin" here)
      $sbName    = userName() !== '' ? userName() : 'Admin';
      $sbRole    = isOwner() ? 'Owner' : 'Administrator';
      $sbBg      = isOwner() ? 'var(--red)' : 'var(--black)';
      $sbInitial = userName() !== '' ? userInitials() : 'AD';
    ?>
    <div class="user-info">
      <div class="user-avatar" style="background:<?= $sbBg ?>;"><?= htmlspecialchars($sbInitial) ?></div>
      <div class="user-details">
        <div class="user-name"><?= htmlspecialchars($sbName) ?></div>
        <div class="user-role"><?= $sbRole ?></div>
      </div>
    </div>
    <a href="../config/logout.php" class="logout-btn">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Log Out
    </a>
  </div>
</aside>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var sidebar = document.getElementById('sidebar');
  var overlay = document.getElementById('sidebarOverlay');
  var toggleBtn = document.getElementById('sidebarToggle');
  if (toggleBtn && sidebar && overlay) {
    toggleBtn.addEventListener('click', function() {
      sidebar.classList.toggle('open');
      overlay.classList.toggle('active');
    });
    overlay.addEventListener('click', function() {
      sidebar.classList.remove('open');
      overlay.classList.remove('active');
    });
    // Close sidebar on nav item click (mobile UX)
    sidebar.querySelectorAll('.nav-item').forEach(function(link) {
      link.addEventListener('click', function() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
      });
    });
  }
});
</script>
