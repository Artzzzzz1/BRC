<?php
session_start();
require_once '../config/db.php';

$token   = trim($_GET['token'] ?? '');
$msg     = '';
$msgType = '';
$valid   = false;
$done    = false;

// Validate token
if ($token) {
  $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 LIMIT 1");
  $stmt->execute([$token]);
  $reset = $stmt->fetch(PDO::FETCH_ASSOC);
  $valid = (bool)$reset;
}

if (!$token || !$valid) {
  $msg     = 'This reset link is invalid or has already expired. Please request a new one.';
  $msgType = 'error';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
  $password = $_POST['password'] ?? '';
  $confirm  = $_POST['confirm_password'] ?? '';

  if (strlen($password) < 8) {
    $msg     = 'Password must be at least 8 characters.';
    $msgType = 'error';
  } elseif ($password !== $confirm) {
    $msg     = 'Passwords do not match.';
    $msgType = 'error';
  } else {
    $hash = password_hash($password, PASSWORD_BCRYPT);

    // Update user password
    $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?")
        ->execute([$hash, $reset['email']]);

    // Mark token as used
    $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?")
        ->execute([$token]);

    $done    = true;
    $msg     = 'Your password has been reset successfully. You can now log in.';
    $msgType = 'success';
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BRC Calamba - Reset Password</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="login.css">
</head>
<body>

  <nav>
    <a href="index.php" class="nav-brand">BRC <span>Calamba</span></a>
    <ul class="nav-links">
      <li><a href="index.php">Home</a></li>
      <li><a href="about.html">About Us</a></li>
      <li><a href="services.html">Our Services</a></li>
      <li><a href="contact.html">Contact Us</a></li>
      <li><a href="faqs.html">FAQs</a></li>
    </ul>
    <a href="login.php" class="btn-login">Login/Register</a>
  </nav>

  <div class="auth-wrapper">
    <div class="auth-card">

      <div class="auth-brand">
        <h2>BRC <span>Calamba</span></h2>
        <p>Motorcycle Rim Powder Coating</p>
      </div>

      <?php if ($msg): ?>
      <div class="auth-alert auth-alert-<?= $msgType ?>">
        <?= htmlspecialchars($msg) ?>
      </div>
      <?php endif; ?>

      <?php if ($valid && !$done): ?>
        <h3 style="font-size:1rem;font-weight:700;margin-bottom:6px;">Set a new password</h3>
        <p style="font-size:.82rem;color:#6b7280;margin-bottom:24px;">Choose a strong password for your account.</p>
        <form class="auth-form" method="POST">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
          <div class="form-group">
            <label>New Password</label>
            <input type="password" name="password" placeholder="At least 8 characters" required>
          </div>
          <div class="form-group">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" placeholder="Repeat your password" required>
          </div>
          <button type="submit" class="auth-btn">Reset Password</button>
        </form>
      <?php elseif ($done): ?>
        <div style="text-align:center;padding:16px 0;">
          <svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width:48px;height:48px;margin-bottom:12px;">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
            <polyline points="22 4 12 14.01 9 11.01"/>
          </svg>
          <p style="font-size:.82rem;color:#6b7280;">Your password has been updated successfully.</p>
          <a href="login.php" class="auth-btn" style="display:inline-block;margin-top:20px;text-decoration:none;text-align:center;">Back to Login</a>
        </div>
      <?php else: ?>
        <div style="text-align:center;padding:16px 0;">
          <p style="font-size:.82rem;color:#6b7280;margin-bottom:20px;">Request a new reset link below.</p>
          <a href="forgot-password.php" class="auth-btn" style="display:inline-block;text-decoration:none;text-align:center;">Request New Link</a>
        </div>
      <?php endif; ?>

      <p class="auth-switch" style="margin-top:20px;">
        <a href="login.php">← Back to Login</a>
      </p>

    </div>
  </div>

</body>
</html>
