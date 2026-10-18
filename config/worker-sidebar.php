<?php
// config/worker-sidebar.php
if (session_status() === PHP_SESSION_NONE) session_start();
$activePage = $activePage ?? '';
$workerName = $_SESSION['full_name'] ?? 'Worker';
$initials = strtoupper(substr($workerName, 0, 2));

$nav = [
    'dashboard' => ['url' => 'worker-dashboard.php', 'label' => 'My Jobs',    'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'],
    'all'       => ['url' => 'worker-alljobs.php', 'label' => 'All Jobs',     'icon' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>'],
];
?>

<!-- Sidebar overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <a href="worker-dashboard.php"><img src="../public/assets/images/logo-icon.jpg" alt="BRC" class="sidebar-logo-icon"></a>
    <p>Worker Panel</p>
  </div>
  <nav class="sidebar-nav">
    <?php foreach ($nav as $key => $item): $active = $activePage === $key ? 'active' : ''; ?>
    <a href="<?= $item['url'] ?>" class="nav-item <?= $active ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $item['icon'] ?></svg>
      <?= $item['label'] ?>
    </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar"><?= $initials ?></div>
      <div class="user-details">
        <div class="user-name"><?= htmlspecialchars($workerName) ?></div>
        <div class="user-role">Worker</div>
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
