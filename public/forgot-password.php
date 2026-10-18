<?php
session_start();
require_once '../config/db.php';
require_once '../config/mailer.php';

$msg     = '';
$msgType = '';
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');

  if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $msg     = 'Please enter a valid email address.';
    $msgType = 'error';
  } else {
    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Always show the same success message whether email exists or not
    // (security best practice — don't reveal if email is registered)
    if ($user) {
      // Delete any existing unused tokens for this email
      $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

      // Generate token
      $token     = bin2hex(random_bytes(32));
      $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

      $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)")
          ->execute([$email, $token, $expiresAt]);

      // Build reset link
      // Works on XAMPP (http://localhost/BRC/public/...) and on Railway (https://yourapp.up.railway.app/public/...).
      // Railway terminates HTTPS before PHP, so trust X-Forwarded-Proto for the scheme.
      $scheme    = (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || !empty($_SERVER['HTTPS'])) ? 'https' : 'http';
      $baseDir   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
      $resetLink = $scheme . '://' . $_SERVER['HTTP_HOST'] . $baseDir . '/reset-password.php?token=' . $token;

      // Send email (currently logs locally — see config/mailer.php)
      sendResetEmail($email, $resetLink);
    }

    $submitted = true;
    $msg     = "If that email is registered, a password reset link has been sent. Check your inbox.";
    $msgType = 'success';
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BRC Calamba - Forgot Password</title>
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

      <?php if (!$submitted): ?>
        <h3 style="font-size:1rem;font-weight:700;margin-bottom:6px;">Forgot your password?</h3>
        <p style="font-size:.82rem;color:#6b7280;margin-bottom:24px;">Enter your registered email and we'll send you a reset link.</p>
      <?php endif; ?>

      <?php if ($msg): ?>
      <div class="auth-alert auth-alert-<?= $msgType ?>">
        <?= htmlspecialchars($msg) ?>
      </div>
      <?php endif; ?>

      <?php if (!$submitted): ?>
      <form class="auth-form" method="POST">
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" placeholder="Enter your registered email" required
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <button type="submit" class="auth-btn">Send Reset Link</button>
      </form>
      <?php else: ?>
        <!-- Show this after submission -->
        <div style="text-align:center;padding:16px 0;">
          <svg viewBox="0 0 24 24" fill="none" stroke="#CC0000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width:48px;height:48px;margin-bottom:12px;">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
            <polyline points="22,6 12,13 2,6"/>
          </svg>
          <p style="font-size:.82rem;color:#6b7280;">Check your email inbox for the reset link.<br>The link expires in <strong>1 hour</strong>.</p>
        </div>
      <?php endif; ?>

      <p class="auth-switch" style="margin-top:20px;">
        Remembered your password? <a href="login.php">Back to Login</a>
      </p>

    </div>
  </div>

</body>
</html>
