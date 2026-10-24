<?php
// config/mailer.php
// =====================================================
// MAILER — sends the password-reset email through Brevo's HTTPS API.
//
// Why not SMTP/Gmail? Railway blocks outbound SMTP on the Free/Trial/Hobby
// plans, so the email goes over HTTPS (port 443) instead.
//
// Railway variables on the BRC service:
//   BREVO_API_KEY       (required)  the API key from Brevo (SMTP & API > API Keys)
//   BREVO_SENDER_EMAIL  (required)  a sender you verified in Brevo
//   BREVO_SENDER_NAME   (optional)  defaults to "BRC Calamba"
//
// If the key/sender are missing (e.g. on your own XAMPP) it falls back to just
// logging the reset link, exactly like before.
// =====================================================

function sendResetEmail(string $email, string $resetLink): bool {
  $apiKey      = getenv('BREVO_API_KEY') ?: '';
  $senderEmail = getenv('BREVO_SENDER_EMAIL') ?: '';
  $senderName  = getenv('BREVO_SENDER_NAME') ?: 'BRC Calamba';

  $log = date('Y-m-d H:i:s') . " | TO: $email | LINK: $resetLink\n";

  // ── No email service configured: log the link (Railway: service > Logs) ──
  if ($apiKey === '' || $senderEmail === '') {
    error_log('PASSWORD RESET (email not configured) ' . trim($log));
    // Only write the file on your own PC, never on the public server.
    if (getenv('MYSQLHOST') === false) {
      file_put_contents(__DIR__ . '/reset_log.txt', $log, FILE_APPEND);
    }
    return true;
  }

  if (!function_exists('curl_init')) {
    error_log('Mailer: PHP curl extension is missing. PASSWORD RESET ' . trim($log));
    return false;
  }

  $safeLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');
  $html = "
    <div style='font-family:Arial,sans-serif;max-width:480px;margin:auto;'>
      <h2 style='color:#CC0000;'>BRC Calamba</h2>
      <p>You requested a password reset. Click the button below to set a new password.</p>
      <p><a href='$safeLink' style='display:inline-block;padding:12px 28px;background:#CC0000;color:#ffffff;border-radius:6px;text-decoration:none;font-weight:700;'>Reset Password</a></p>
      <p style='color:#666;font-size:13px;'>Or copy this link into your browser:<br>$safeLink</p>
      <p style='color:#999;font-size:12px;margin-top:24px;'>This link expires in 1 hour. If you did not request this, you can ignore this email.</p>
    </div>";

  $payload = json_encode([
    'sender'      => ['name' => $senderName, 'email' => $senderEmail],
    'to'          => [['email' => $email]],
    'subject'     => 'BRC Calamba - Password Reset',
    'htmlContent' => $html,
  ]);

  $ch = curl_init('https://api.brevo.com/v3/smtp/email');
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
      'accept: application/json',
      'content-type: application/json',
      'api-key: ' . $apiKey,
    ],
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 10,
  ]);
  $response = curl_exec($ch);
  $code     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlErr  = curl_error($ch);
  curl_close($ch);

  if ($code >= 200 && $code < 300) {
    return true;
  }

  // Failed: say why, and keep the link in the logs so the reset can still be done by hand.
  error_log('Brevo send failed: HTTP ' . $code . ' ' . $curlErr . ' ' . substr((string)$response, 0, 300));
  error_log('PASSWORD RESET (email failed) ' . trim($log));
  return false;
}
