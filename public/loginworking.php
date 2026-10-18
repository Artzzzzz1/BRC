<?php
session_start();
require_once '../config/db.php';

// If already logged in, redirect to correct dashboard
if (isset($_SESSION['user_id'])) {
  switch ($_SESSION['role']) {
    case 'owner':  header("Location: ../ownerSide/owner-dashboard.php"); break;
    case 'admin':  header("Location: ../adminSide/admin-dashboard.php"); break;
    case 'worker': header("Location: ../workerSide/worker-dashboard.php"); break;
    default:       header("Location: ../userSide/user-dashboard.php"); break;
  }
  exit();
}

$error   = '';
$success = '';

// ============================================
// HANDLE REGISTER
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {

  $full_name      = trim($_POST['full_name'] ?? '');
  $email          = trim($_POST['email'] ?? '');
  $contact_number = trim($_POST['contact_number'] ?? '');
  $password       = $_POST['password'] ?? '';
  $confirm        = $_POST['confirm_password'] ?? '';

  if (empty($full_name) || empty($email) || empty($password) || empty($confirm)) {
    $error = 'Please fill in all required fields.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Please enter a valid email address.';
  } elseif (strlen($password) < 8) {
    $error = 'Password must be at least 8 characters.';
  } elseif ($password !== $confirm) {
    $error = 'Passwords do not match.';
  } else {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->fetch()) {
      $error = 'An account with that email already exists.';
    } else {
      $password_hash = password_hash($password, PASSWORD_BCRYPT);
      $stmt = $pdo->prepare("INSERT INTO users (full_name, email, contact_number, password_hash, role) VALUES (?, ?, ?, ?, 'customer')");
      $stmt->execute([$full_name, $email, $contact_number, $password_hash]);
      $success = 'Account created successfully! You can now log in.';
    }
  }
}

// ============================================
// HANDLE LOGIN
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {

  $email    = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';

  if (empty($email) || empty($password)) {
    $error = 'Please enter your email and password.';
  } else {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
      $_SESSION['user_id']   = $user['id'];
      $_SESSION['full_name'] = $user['full_name'];
      $_SESSION['email']     = $user['email'];
      $_SESSION['role']      = $user['role'];

      switch ($user['role']) {
        case 'owner':  header("Location: ../ownerSide/owner-dashboard.php"); break;
        case 'admin':  header("Location: ../adminSide/admin-dashboard.php"); break;
        case 'worker': header("Location: ../workerSide/worker-dashboard.php"); break;
        default:       header("Location: ../userSide/user-dashboard.php"); break;
      }
      exit();
    } else {
      $error = 'Incorrect email or password.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BRC Calamba - Login / Register</title>
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

      <div class="auth-tabs">
        <button class="auth-tab <?= !isset($_GET['tab']) || $_GET['tab'] === 'login' ? 'active' : '' ?>" onclick="switchTab('login')">Login</button>
        <button class="auth-tab <?= isset($_GET['tab']) && $_GET['tab'] === 'register' ? 'active' : '' ?>" onclick="switchTab('register')">Register</button>
      </div>

      <?php if ($error): ?>
        <div class="auth-alert auth-alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="auth-alert auth-alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <!-- LOGIN FORM -->
      <form class="auth-form" id="form-login" method="POST" action="login.php"
            style="<?= isset($_GET['tab']) && $_GET['tab'] === 'register' ? 'display:none' : '' ?>">
        <input type="hidden" name="action" value="login">

        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" placeholder="Enter your email" required
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" placeholder="Enter your password" required>
        </div>

        <div style="text-align:right;margin-top:-8px;margin-bottom:16px;">
          <a href="forgot-password.php" style="font-size:.8rem;color:#CC0000;font-weight:600;text-decoration:none;">Forgot password?</a>
        </div>

        <button type="submit" class="auth-btn">Login</button>

        <p class="auth-switch">
          Don't have an account?
          <a href="#" onclick="switchTab('register')">Register here</a>
        </p>
      </form>

      <!-- REGISTER FORM -->
      <form class="auth-form" id="form-register" method="POST" action="login.php?tab=register"
            style="<?= !isset($_GET['tab']) || $_GET['tab'] === 'login' ? 'display:none' : '' ?>">
        <input type="hidden" name="action" value="register">

        <div class="form-group">
          <label>Full Name <span style="color:#cc0000;">*</span></label>
          <input type="text" name="full_name" placeholder="Enter your full name" required
                 value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label>Email Address <span style="color:#cc0000;">*</span></label>
          <input type="email" name="email" placeholder="Enter your email" required
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label>Contact Number</label>
          <input type="tel" name="contact_number" placeholder="09XX XXX XXXX"
                 value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label>Password <span style="color:#cc0000;">*</span></label>
          <input type="password" name="password" placeholder="At least 8 characters" required>
        </div>

        <div class="form-group">
          <label>Confirm Password <span style="color:#cc0000;">*</span></label>
          <input type="password" name="confirm_password" placeholder="Repeat your password" required>
        </div>

        <button type="submit" class="auth-btn">Create Account</button>

        <p class="auth-switch">
          Already have an account?
          <a href="#" onclick="switchTab('login')">Login here</a>
        </p>
      </form>

    </div>
  </div>

  <script>
    function switchTab(tab) {
      const loginForm    = document.getElementById('form-login');
      const registerForm = document.getElementById('form-register');
      const tabs         = document.querySelectorAll('.auth-tab');
      if (tab === 'login') {
        loginForm.style.display    = 'block';
        registerForm.style.display = 'none';
        tabs[0].classList.add('active');
        tabs[1].classList.remove('active');
      } else {
        loginForm.style.display    = 'none';
        registerForm.style.display = 'block';
        tabs[0].classList.remove('active');
        tabs[1].classList.add('active');
      }
    }
  </script>

</body>
</html>
