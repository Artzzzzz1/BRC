<?php
// config/mailer.php
// =====================================================
// MAILER CONFIGURATION
// Currently: logs the reset link locally (no email sent)
// Later:     swap the sendResetEmail() body with PHPMailer + Gmail SMTP
// =====================================================

function sendResetEmail(string $email, string $resetLink): bool {
  // ── PHASE 1: Local / No email (current) ──────────────
  // Writes the reset link to a local log file so you can test without Gmail.
  // Delete this block when you go live.
  $log = date('Y-m-d H:i:s') . " | TO: $email | LINK: $resetLink\n";
  // Always send it to the server log (on Railway: service > Logs tab).
  error_log('PASSWORD RESET ' . trim($log));
  // Only write the file on your own PC. On a public server a token file under
  // /config would be a risk, and Railway's disk is wiped on every deploy anyway.
  $onServer = getenv('MYSQLHOST') !== false;
  if (!$onServer) {
    file_put_contents(__DIR__ . '/reset_log.txt', $log, FILE_APPEND);
  }
  return true;

  // ── PHASE 2: Gmail via PHPMailer (uncomment when going live) ─────
  // 1. Install PHPMailer: composer require phpmailer/phpmailer
  //    OR download from https://github.com/PHPMailer/PHPMailer and put in /vendor/
  // 2. Create a Gmail App Password at https://myaccount.google.com/apppasswords
  // 3. Uncomment the block below and fill in your Gmail + App Password

  /*
  require_once __DIR__ . '/../vendor/autoload.php';
  use PHPMailer\PHPMailer\PHPMailer;
  use PHPMailer\PHPMailer\Exception;

  $mail = new PHPMailer(true);
  try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'your_gmail@gmail.com';   // ← your Gmail here
    $mail->Password   = 'your_app_password_here'; // ← Gmail App Password here
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    $mail->setFrom('your_gmail@gmail.com', 'BRC Calamba');
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = 'BRC Calamba — Password Reset';
    $mail->Body    = "
      <div style='font-family:Montserrat,sans-serif;max-width:480px;margin:auto;'>
        <h2 style='color:#CC0000;'>BRC Calamba</h2>
        <p>You requested a password reset. Click the button below to set a new password.</p>
        <a href='$resetLink' style='display:inline-block;padding:12px 28px;background:#CC0000;color:white;border-radius:6px;text-decoration:none;font-weight:700;'>Reset Password</a>
        <p style='color:#999;font-size:12px;margin-top:24px;'>This link expires in 1 hour. If you did not request this, ignore this email.</p>
      </div>
    ";
    $mail->send();
    return true;
  } catch (Exception $e) {
    return false;
  }
  */
}
