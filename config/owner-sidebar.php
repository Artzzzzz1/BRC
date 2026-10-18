<?php
// config/owner-sidebar.php
if (session_status() === PHP_SESSION_NONE) session_start();
$activePage = $activePage ?? '';
$ownerName = $_SESSION['full_name'] ?? 'Owner';
$initials = strtoupper(substr($ownerName, 0, 2));

$nav = [
  'dashboard' => ['url' => 'owner-dashboard.php',  'label' => 'Dashboard',  'icon' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>'],
  'joborders' => ['url' => 'owner-joborders.php',  'label' => 'Job Orders', 'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/>'],
  'reports'   => ['url' => 'owner-reports.php',    'label' => 'Reports',    'icon' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'],
  'users'     => ['url' => 'owner-users.php',      'label' => 'Manage Users','icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
];
?>

<!-- Sidebar overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <a href="owner-dashboard.php"><img src="../public/assets/images/logo-icon.jpg" alt="BRC" class="sidebar-logo-icon"></a>
    <p>Owner Panel</p>
  </div>
  <nav class="sidebar-nav">
    <?php foreach ($nav as $key => $item): $activeClass = $activePage === $key ? 'active' : ''; ?>
    <a href="<?= $item['url'] ?>" class="nav-item <?= $activeClass ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $item['icon'] ?></svg>
      <?= $item['label'] ?>
    </a>
    <?php endforeach; ?>

    <div style="margin:12px 0;border-top:1px solid var(--border);"></div>
    <p style="font-size:.68rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;padding:0 12px 6px;">Admin Access</p>
    <a href="../adminSide/admin-dashboard.php" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
      Go to Admin Panel
    </a>
  </nav>
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar" style="background:var(--red);"><?= $initials ?></div>
      <div class="user-details">
        <div class="user-name"><?= htmlspecialchars($ownerName) ?></div>
        <div class="user-role">Owner</div>
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
