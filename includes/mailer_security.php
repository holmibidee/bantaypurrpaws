<?php
/**
 * BantayPurrPaws — Security Email Templates
 * Login challenge, number-match, OTP, suspicious activity
 */

require_once __DIR__ . '/mailer.php';

// ── Login Challenge Email (Yes/No buttons) ──────────────────

function sendLoginChallengeEmail(string $to, string $name, array $attemptInfo, string $token): bool {
    $base    = absolute_url('');
    $yesUrl  = absolute_url("auth/login-challenge.php?action=approve&token=$token");
    $noUrl   = absolute_url("auth/login-challenge.php?action=deny&token=$token");

    $ip       = htmlspecialchars($attemptInfo['ip_address'] ?? 'Unknown');
    $browser  = htmlspecialchars($attemptInfo['browser']    ?? 'Unknown');
    $os       = htmlspecialchars($attemptInfo['os']         ?? 'Unknown');
    $device   = htmlspecialchars($attemptInfo['device_type']?? 'Unknown');
    $time     = date('F j, Y g:i A T');
    $color    = APP_COLOR;

    $inner = <<<HTML
<h2 style="margin:0 0 8px;font-size:20px;color:#2d2520;">Sign-In Attempt Detected</h2>
<p style="margin:0 0 20px;color:#6b5f56;">
  Hello <strong>{$name}</strong>, we detected a login attempt on your <strong style="color:{$color};">BantayPurrPaws</strong> account.
  If this was you, please confirm below. If not, deny the request immediately.
</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;background:#faf5f0;border-radius:10px;border:1px solid #e8ddd5;">
  <tr><td style="padding:20px;">
    <table width="100%" cellpadding="4" cellspacing="0">
      <tr><td style="color:#9c8f84;font-size:13px;width:110px;">📍 IP Address</td><td style="color:#2d2520;font-weight:600;">{$ip}</td></tr>
      <tr><td style="color:#9c8f84;font-size:13px;">🖥️ Device</td><td style="color:#2d2520;font-weight:600;">{$device} — {$os}</td></tr>
      <tr><td style="color:#9c8f84;font-size:13px;">🌐 Browser</td><td style="color:#2d2520;font-weight:600;">{$browser}</td></tr>
      <tr><td style="color:#9c8f84;font-size:13px;">🕐 Time</td><td style="color:#2d2520;font-weight:600;">{$time}</td></tr>
    </table>
  </td></tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
  <tr>
    <td align="center" style="padding-right:8px;">
      <a href="{$yesUrl}" style="display:inline-block;background:#10b981;color:#fff;text-decoration:none;padding:14px 32px;border-radius:8px;font-size:15px;font-weight:700;">✓ Yes, It's Me</a>
    </td>
    <td align="center" style="padding-left:8px;">
      <a href="{$noUrl}" style="display:inline-block;background:#ef4444;color:#fff;text-decoration:none;padding:14px 32px;border-radius:8px;font-size:15px;font-weight:700;">✕ No, It's Not Me</a>
    </td>
  </tr>
</table>

<p style="margin:0;font-size:12px;color:#9c8f84;text-align:center;">
  This link expires in 10 minutes. Do not share this email.
</p>
HTML;

    return sendRawEmail($to, APP_NAME . ' — Verify Your Sign-In Attempt', emailShell('Sign-In Verification', $inner), $name);
}

// ── Suspicious Activity Blocked Email ──────────────────────

function sendSuspiciousBlockedEmail(string $to, string $name, array $attemptInfo): bool {
    $ip      = htmlspecialchars($attemptInfo['ip_address'] ?? 'Unknown');
    $browser = htmlspecialchars($attemptInfo['browser']    ?? 'Unknown');
    $os      = htmlspecialchars($attemptInfo['os']         ?? 'Unknown');
    $time    = date('F j, Y g:i A T');
    $base    = absolute_url('');
    $color   = APP_COLOR;

    $inner = <<<HTML
<h2 style="margin:0 0 8px;font-size:20px;color:#dc2626;">⚠️ Unauthorized Login Blocked</h2>
<p style="margin:0 0 20px;color:#6b5f56;">
  Hello <strong>{$name}</strong>, an unauthorized login attempt to your <strong style="color:{$color};">BantayPurrPaws</strong> account was <strong>blocked</strong>. The IP address has been flagged.
</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;background:#fef2f2;border-radius:10px;border:1px solid #fecaca;">
  <tr><td style="padding:20px;">
    <table width="100%" cellpadding="4" cellspacing="0">
      <tr><td style="color:#9c8f84;font-size:13px;width:110px;">📍 IP Address</td><td style="color:#2d2520;font-weight:600;">{$ip}</td></tr>
      <tr><td style="color:#9c8f84;font-size:13px;">🌐 Browser</td><td style="color:#2d2520;font-weight:600;">{$browser} on {$os}</td></tr>
      <tr><td style="color:#9c8f84;font-size:13px;">🕐 Time</td><td style="color:#2d2520;font-weight:600;">{$time}</td></tr>
    </table>
  </td></tr>
</table>

<p style="margin:0 0 16px;color:#6b5f56;">
  If you do not recognize this activity, we recommend changing your password immediately.
</p>

<p style="margin:0;font-size:12px;color:#9c8f84;">
  Your account remains secure. The attempt has been logged and monitored.
</p>
HTML;

    return sendRawEmail($to, APP_NAME . ' — Unauthorized Login Attempt Blocked', emailShell('Security Alert', $inner), $name);
}

// ── Number Match Email ──────────────────────────────────────

function sendNumberMatchEmail(string $to, string $name, int $shownNumber, array $allOptions): bool {
    $color   = APP_COLOR;
    $numbers = implode(' &nbsp;&nbsp; ', array_map(fn($n) => "<strong style='font-size:28px;color:{$color};'>$n</strong>", $allOptions));

    $inner = <<<HTML
<h2 style="margin:0 0 8px;font-size:20px;color:#2d2520;">Number Matching Required</h2>
<p style="margin:0 0 20px;color:#6b5f56;">
  Hello <strong>{$name}</strong>, to complete your sign-in, select the number shown on your login screen from the options below.
</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;background:#faf5f0;border-radius:10px;border:2px dashed {$color};">
  <tr><td style="padding:24px;text-align:center;">
    <p style="margin:0 0 8px;font-size:13px;color:#9c8f84;">Your login screen shows:</p>
    <div style="font-family:'Courier New',monospace;font-size:48px;font-weight:700;color:{$color};letter-spacing:4px;">{$shownNumber}</div>
    <p style="margin:12px 0 0;font-size:13px;color:#9c8f84;">Select this number on the login page from: {$numbers}</p>
  </td></tr>
</table>

<p style="margin:0;font-size:12px;color:#9c8f84;text-align:center;">
  This challenge expires in 5 minutes. Do not share this code.
</p>
HTML;

    return sendRawEmail($to, APP_NAME . ' — Number Matching Verification', emailShell('Number Matching', $inner), $name);
}

