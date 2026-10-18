<?php
require_once '../config/auth.php';
require_once '../config/db.php';
requireOwner();
$activePage = 'users';
$msg = '';
$msgType = 'success';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ADD USER
if (isset($_POST['action']) && $_POST['action'] === 'add_user') {
  try {
    $name     = trim($_POST['full_name']);
    $email    = trim($_POST['email']);
    $contact  = trim($_POST['contact_number'] ?? '');
    $role     = $_POST['role'];
    $password = $_POST['password'];
    $validRoles = ['customer','worker','admin','owner'];

    if (!$name || !$email || !$password || !in_array($role, $validRoles)) {
      throw new Exception("Please fill in all required fields.");
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new Exception("Invalid email address.");
    }
    // Check duplicate email
    $dup = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $dup->execute([$email]);
    if ($dup->fetch()) throw new Exception("Email already exists.");

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (full_name,email,contact_number,password_hash,role) VALUES (?,?,?,?,?)")
        ->execute([$name, $email, $contact, $hash, $role]);
    $msg = "User '$name' created successfully as " . ucfirst($role) . ".";
  } catch (Exception $e) {
    $msg = $e->getMessage();
    $msgType = 'error';
  }
}

// UPDATE ROLE
if (isset($_POST['action']) && $_POST['action'] === 'update_role') {
  try {
    $uid  = (int)$_POST['user_id'];
    $role = $_POST['role'];
    $validRoles = ['customer','worker','admin','owner'];
    if (!in_array($role, $validRoles)) throw new Exception("Invalid role.");
    // Prevent owner from demoting themselves
    if ($uid === (int)currentUserId() && $role !== 'owner') {
      throw new Exception("You cannot change your own role.");
    }
    $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$role, $uid]);
    $msg = "Role updated successfully.";
  } catch (Exception $e) {
    $msg = $e->getMessage();
    $msgType = 'error';
  }
}

// RESET PASSWORD
if (isset($_POST['action']) && $_POST['action'] === 'reset_password') {
  try {
    $uid      = (int)$_POST['user_id'];
    $newPass  = $_POST['new_password'];
    if (strlen($newPass) < 6) throw new Exception("Password must be at least 6 characters.");
    $hash = password_hash($newPass, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $uid]);
    $msg = "Password reset successfully.";
  } catch (Exception $e) {
    $msg = $e->getMessage();
    $msgType = 'error';
  }
}

// DELETE USER
if (isset($_POST['action']) && $_POST['action'] === 'delete_user') {
  try {
    $uid = (int)$_POST['user_id'];
    if ($uid === (int)currentUserId()) throw new Exception("You cannot delete your own account.");
    // Unassign from job orders first
    $pdo->prepare("UPDATE job_orders SET worker_id=NULL WHERE worker_id=?")->execute([$uid]);
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
    $msg = "User deleted.";
  } catch (Exception $e) {
    $msg = $e->getMessage();
    $msgType = 'error';
  }
}

// Fetch all users
try {
  $stmt = $pdo->query("SELECT u.*, (SELECT COUNT(*) FROM job_orders jo WHERE jo.worker_id=u.id AND jo.status IN ('in_progress','ready')) AS active_jobs FROM users u ORDER BY FIELD(u.role,'owner','admin','worker','customer'), u.full_name");
  $allUsers = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) { $allUsers = []; }

$roleColors = ['owner'=>'badge-cancelled','admin'=>'badge-inprogress','worker'=>'badge-confirmed','customer'=>'badge-pending'];
$roleBg = ['owner'=>'#fff0f0','admin'=>'#dbeafe','worker'=>'#dcfce7','customer'=>'#f9fafb'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BRC Calamba - Manage Users</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../adminSide/admin.css">
  <style>
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;padding:20px;box-sizing:border-box}.modal-overlay.open{display:flex}
    .modal{background:#fff;border-radius:12px;padding:28px;width:100%;max-width:480px;box-shadow:0 20px 60px rgba(0,0,0,.2);max-height:90vh;overflow-y:auto;overflow-x:hidden;box-sizing:border-box}
    .modal h3{font-size:1rem;font-weight:700;margin-bottom:20px}
    .modal-footer{display:flex;gap:10px;margin-top:20px;justify-content:flex-end}
    .modal .fg{display:flex;flex-direction:column;gap:5px;margin-bottom:14px}
    .modal .fg label{font-size:.76rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}
    .modal .fg input,.modal .fg select{padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:'Montserrat',sans-serif;font-size:.84rem;color:var(--text);background:#fff;outline:none;width:100%;box-sizing:border-box}
    .modal .fg input:focus,.modal .fg select:focus{border-color:var(--red);box-shadow:0 0 0 3px rgba(204,0,0,.08)}
    .role-tag{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:700;text-transform:capitalize}
    .user-card{display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--border);gap:12px}
    .user-card:last-child{border-bottom:none}
    .user-av{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:'Bebas Neue',sans-serif;font-size:.9rem;color:white;flex-shrink:0}
    .user-actions{display:flex;gap:6px;flex-shrink:0}
    .btn-sm{padding:5px 12px;font-size:.72rem;border-radius:6px;font-family:'Montserrat',sans-serif;font-weight:700;cursor:pointer;border:1.5px solid var(--border);background:#fff;color:var(--text);transition:all .15s}
    .btn-sm:hover{background:var(--bg)}
    .btn-sm.danger{color:#be123c;border-color:#fecdd3}
    .btn-sm.danger:hover{background:#fff1f2}
  </style>
</head>
<body>
<?php include '../config/owner-sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
    <div class="page-title"><h1>Manage Users</h1><p>Add, edit roles, and manage all accounts</p></div>
    <div class="topbar-actions">
      <button class="btn btn-red" onclick="document.getElementById('addModal').classList.add('open')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add User
      </button>
    </div>
  </div>
  <div class="content">
    <?php if ($msg): ?>
    <div style="background:<?= $msgType==='error'?'#fff1f2':'#f0fdf4' ?>;color:<?= $msgType==='error'?'#be123c':'#15803d' ?>;border:1px solid <?= $msgType==='error'?'#fecdd3':'#bbf7d0' ?>;padding:12px 16px;border-radius:8px;font-size:.83rem;font-weight:600;margin-bottom:20px;">
      <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <!-- SUMMARY STATS -->
    <div class="stats-grid" style="margin-bottom:24px;">
      <?php
        $counts = ['owner'=>0,'admin'=>0,'worker'=>0,'customer'=>0];
        foreach ($allUsers as $u) $counts[$u['role']] = ($counts[$u['role']] ?? 0) + 1;
      ?>
      <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:#fff0f0;"><svg viewBox="0 0 24 24" fill="none" stroke="#CC0000" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 1 0-16 0"/></svg></div></div><div class="stat-number"><?= $counts['owner'] ?></div><div class="stat-label">Owners</div></div>
      <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:#dbeafe;"><svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 1 0-16 0"/></svg></div></div><div class="stat-number"><?= $counts['admin'] ?></div><div class="stat-label">Admins</div></div>
      <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:#dcfce7;"><svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 1 0-16 0"/></svg></div></div><div class="stat-number"><?= $counts['worker'] ?></div><div class="stat-label">Workers</div></div>
      <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:#f5f3ff;"><svg viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 1 0-16 0"/></svg></div></div><div class="stat-number"><?= $counts['customer'] ?></div><div class="stat-label">Customers</div></div>
    </div>

    <!-- USER LIST -->
    <div class="section-card">
      <div class="section-header"><h2>All Accounts (<?= count($allUsers) ?>)</h2></div>
      <div class="section-body">
        <?php if (empty($allUsers)): ?>
        <p style="color:var(--muted);">No users found.</p>
        <?php else: foreach ($allUsers as $u):
          $av = strtoupper(substr($u['full_name'],0,2));
          $avBg = ['owner'=>'#CC0000','admin'=>'#2563eb','worker'=>'#16a34a','customer'=>'#7c3aed'][$u['role']] ?? '#6b7280';
          $isSelf = $u['id'] == currentUserId();
        ?>
        <div class="user-card">
          <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0;">
            <div class="user-av" style="background:<?= $avBg ?>"><?= $av ?></div>
            <div style="min-width:0;">
              <div style="font-weight:700;font-size:.88rem;"><?= htmlspecialchars($u['full_name']) ?> <?= $isSelf ? '<span style="font-size:.7rem;color:var(--muted);">(you)</span>' : '' ?></div>
              <div style="font-size:.76rem;color:var(--muted);"><?= htmlspecialchars($u['email']) ?><?= $u['contact_number'] ? ' · '.$u['contact_number'] : '' ?></div>
              <div style="margin-top:4px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                <span class="badge <?= $roleColors[$u['role']] ?? '' ?>"><?= ucfirst($u['role']) ?></span>
                <?php if ($u['active_jobs'] > 0): ?>
                <span style="font-size:.7rem;color:var(--muted);"><?= $u['active_jobs'] ?> active job<?= $u['active_jobs']>1?'s':'' ?></span>
                <?php endif; ?>
                <span style="font-size:.7rem;color:var(--muted);">Joined <?= date('M Y', strtotime($u['created_at'])) ?></span>
              </div>
            </div>
          </div>
          <div class="user-actions">
            <button class="btn-sm" onclick="openRoleModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['full_name'])) ?>', '<?= $u['role'] ?>')">Change Role</button>
            <button class="btn-sm" onclick="openPassModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['full_name'])) ?>')">Reset Pass</button>
            <?php if (!$isSelf): ?>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($u['full_name'])) ?>? This cannot be undone.')">
              <input type="hidden" name="action" value="delete_user">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn-sm danger">Delete</button>
            </form>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ADD USER MODAL -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <h3>Add New User</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add_user">
      <div class="fg"><label>Full Name</label><input type="text" name="full_name" required placeholder="Enter full name"></div>
      <div class="fg"><label>Email Address</label><input type="email" name="email" required placeholder="email@example.com"></div>
      <div class="fg"><label>Contact Number</label><input type="tel" name="contact_number" placeholder="09XX XXX XXXX"></div>
      <div class="fg"><label>Role</label>
        <select name="role">
          <option value="customer">Customer</option>
          <option value="worker">Worker</option>
          <option value="admin">Admin</option>
          <option value="owner">Owner</option>
        </select>
      </div>
      <div class="fg"><label>Password</label><input type="password" name="password" required placeholder="Min. 6 characters"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('addModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-red">Create User</button>
      </div>
    </form>
  </div>
</div>

<!-- CHANGE ROLE MODAL -->
<div class="modal-overlay" id="roleModal">
  <div class="modal">
    <h3>Change Role — <span id="roleModalName"></span></h3>
    <form method="POST">
      <input type="hidden" name="action" value="update_role">
      <input type="hidden" name="user_id" id="roleUserId">
      <div class="fg"><label>New Role</label>
        <select name="role" id="roleSelect">
          <option value="customer">Customer</option>
          <option value="worker">Worker</option>
          <option value="admin">Admin</option>
          <option value="owner">Owner</option>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('roleModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-red">Save Role</button>
      </div>
    </form>
  </div>
</div>

<!-- RESET PASSWORD MODAL -->
<div class="modal-overlay" id="passModal">
  <div class="modal">
    <h3>Reset Password — <span id="passModalName"></span></h3>
    <form method="POST">
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="user_id" id="passUserId">
      <div class="fg"><label>New Password</label><input type="password" name="new_password" required placeholder="Min. 6 characters"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('passModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-red">Reset Password</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openRoleModal(id, name, role) {
    document.getElementById('roleUserId').value = id;
    document.getElementById('roleModalName').textContent = name;
    document.getElementById('roleSelect').value = role;
    document.getElementById('roleModal').classList.add('open');
  }
  function openPassModal(id, name) {
    document.getElementById('passUserId').value = id;
    document.getElementById('passModalName').textContent = name;
    document.getElementById('passModal').classList.add('open');
  }
  document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
  });
</script>
</body>
</html>
