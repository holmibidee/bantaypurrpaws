<?php
/**
 * BantayPurrPaws — Email Helper
 *
 * Sends mail via the Brevo (Sendinblue) Transactional Email API over HTTPS.
 * Works on InfinityFree and other shared hosts where outbound SMTP may be blocked.
 *
 * Setup: add BREVO_API_KEY and MAIL_FROM to your .env file.
 */

require_once __DIR__ . '/env.php';
load_env_file(dirname(__DIR__) . '/.env');
require_once __DIR__ . '/logger.php';

define('BREVO_API_KEY',    $_ENV['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY') ?: '');
define('MAIL_FROM',        $_ENV['MAIL_FROM'] ?? getenv('MAIL_FROM') ?: '');
define('MAIL_FROM_NAME',   $_ENV['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME') ?: 'BantayPurrPaws');
define('APP_NAME',         'BantayPurrPaws');
define('APP_COLOR',        '#7c6f5b'); // matches CSS --primary

define('BREVO_API_URL', 'https://api.brevo.com/v3/smtp/email');

/**
 * Send one email through Brevo. Returns true on success, false on failure.
 */
function sendRawEmail(string $to, string $subject, string $htmlBody, string $toName = ''): bool {
    if (BREVO_API_KEY === '' || MAIL_FROM === '') {
        bpp_log('mailer', 'error', 'Brevo not configured: set BREVO_API_KEY and MAIL_FROM.');
        return false;
    }

    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var(MAIL_FROM, FILTER_VALIDATE_EMAIL)) {
        bpp_log('mailer', 'error', 'Invalid sender or recipient email address.', ['to' => $to]);
        return false;
    }

    $recipient = ['email' => $to];
    if ($toName !== '') {
        $recipient['name'] = $toName;
    }

    $payload = [
        'sender'      => ['email' => MAIL_FROM, 'name' => MAIL_FROM_NAME],
        'to'          => [$recipient],
        'subject'     => $subject,
        'htmlContent' => $htmlBody,
        'textContent' => trim(preg_replace('/\s+/', ' ', strip_tags($htmlBody))),
    ];

    $ch = curl_init(BREVO_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'api-key: ' . BREVO_API_KEY,
            'content-type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        bpp_log('mailer', 'error', 'Brevo cURL error.', ['error' => $curlError, 'to' => $to]);
        return false;
    }

    if ($httpCode >= 400) {
        bpp_log('mailer', 'error', 'Brevo API error.', ['http_code' => $httpCode, 'response' => $response, 'to' => $to]);
        return false;
    }

    bpp_log('mailer', 'info', 'Email sent.', ['to' => $to, 'subject' => $subject]);
    return true;
}

/**
 * Wraps content in a branded email shell.
 */
function emailShell(string $title, string $innerHtml): string {
    $color  = APP_COLOR;
    $name   = APP_NAME;
    $year   = date('Y');
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$title}</title>
</head>
<body style="margin:0;padding:0;background:#f5f2ef;font-family:Georgia,'Times New Roman',serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f2ef;padding:32px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);">

        <!-- Header -->
        <tr>
          <td style="background:{$color};padding:28px 36px;text-align:center;">
            <span style="font-size:22px;font-weight:700;color:#fff;letter-spacing:.5px;">{$name}</span>
            <div style="font-size:12px;color:rgba(255,255,255,.75);margin-top:4px;letter-spacing:1px;text-transform:uppercase;">Stray Animal Rescue &amp; Adoption System</div>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:36px 40px;color:#3d3530;font-size:15px;line-height:1.65;">
            {$innerHtml}
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#faf8f6;border-top:1px solid #ede9e4;padding:20px 40px;text-align:center;font-size:12px;color:#9c8f84;">
            &copy; {$year} {$name} &mdash; This is an automated message, please do not reply.<br>
            If you did not request this email, you can safely ignore it.
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}

/**
 * Send a 6-digit OTP email.
 *
 * @param string $to      Recipient email
 * @param string $name    Recipient display name
 * @param string $otp     6-digit code
 * @param string $purpose 'registration' | 'password_reset' | 'google_link'
 */
function sendOtpEmail(string $to, string $name, string $otp, string $purpose = 'registration'): bool {
    $purposeLabel = match ($purpose) {
        'password_reset' => 'reset your password',
        'google_link'    => 'link your Google account',
        default          => 'verify your email address',
    };

    $color = APP_COLOR;
    $inner = <<<HTML
<h2 style="margin:0 0 8px;font-size:20px;color:#2d2520;">Hello, {$name}!</h2>
<p style="margin:0 0 24px;color:#6b5f56;">
  You requested to {$purposeLabel} on <strong style="color:{$color};">BantayPurrPaws</strong>.
  Use the one-time code below — it expires in <strong>15 minutes</strong>.
</p>

<!-- OTP Code Box -->
<table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 28px;">
  <tr>
    <td align="center">
      <div style="display:inline-block;background:#faf5f0;border:2px dashed {$color};border-radius:10px;padding:20px 40px;">
        <span style="font-family:'Courier New',monospace;font-size:38px;font-weight:700;letter-spacing:12px;color:{$color};">{$otp}</span>
      </div>
    </td>
  </tr>
</table>

<p style="margin:0 0 8px;font-size:13px;color:#9c8f84;">
  &bull; Do <strong>not</strong> share this code with anyone.<br>
  &bull; If you didn&rsquo;t request this, please ignore this email.
</p>
HTML;

    $subject = match ($purpose) {
        'password_reset' => APP_NAME . ' — Password Reset OTP',
        'google_link'    => APP_NAME . ' — Google Account Link OTP',
        default          => APP_NAME . ' — Email Verification OTP',
    };

    return sendRawEmail($to, $subject, emailShell($subject, $inner), $name);
}

function sendReportApprovedEmail(string $to, string $name, string $reportCode): bool {
    $color = APP_COLOR;
    $inner = <<<HTML
<h2 style="margin:0 0 8px;font-size:20px;color:#2d2520;">Hello, {$name}!</h2>
<p style="margin:0 0 16px;color:#6b5f56;">
  Your rescue report <strong style="color:{$color};">{$reportCode}</strong> has been marked as
  <strong>rescued</strong>. Thank you for helping us save a stray animal.
</p>
<p style="margin:0;font-size:13px;color:#9c8f84;">
  You can sign in to BantayPurrPaws anytime to view your report history.
</p>
HTML;

    return sendRawEmail($to, APP_NAME . ' — Rescue Report Approved', emailShell('Rescue Report Approved', $inner), $name);
}

function sendReportRejectedEmail(string $to, string $name, string $reportCode): bool {
    $color = APP_COLOR;
    $inner = <<<HTML
<h2 style="margin:0 0 8px;font-size:20px;color:#2d2520;">Hello, {$name}!</h2>
<p style="margin:0 0 16px;color:#6b5f56;">
  Your rescue report <strong style="color:{$color};">{$reportCode}</strong> could not be completed
  and has been marked as <strong>failed</strong>.
</p>
<p style="margin:0;font-size:13px;color:#9c8f84;">
  If you have questions, please contact the BantayPurrPaws team.
</p>
HTML;

    return sendRawEmail($to, APP_NAME . ' — Rescue Report Update', emailShell('Rescue Report Update', $inner), $name);
}

function sendPetSubmissionApprovedEmail(string $to, string $name, string $petName): bool {
    $color = APP_COLOR;
    $inner = <<<HTML
<h2 style="margin:0 0 8px;font-size:20px;color:#2d2520;">Congratulations, {$name}!</h2>
<p style="margin:0 0 16px;color:#6b5f56;">
  Your adoption application for <strong style="color:{$color};">{$petName}</strong> has been
  <strong>approved</strong>. Our team will contact you with next steps.
</p>
<p style="margin:0;font-size:13px;color:#9c8f84;">
  Thank you for choosing adoption through BantayPurrPaws.
</p>
HTML;

    return sendRawEmail($to, APP_NAME . ' — Adoption Application Approved', emailShell('Adoption Approved', $inner), $name);
}

function sendPetSubmissionRejectedEmail(string $to, string $name, string $petName): bool {
    $color = APP_COLOR;
    $inner = <<<HTML
<h2 style="margin:0 0 8px;font-size:20px;color:#2d2520;">Hello, {$name}!</h2>
<p style="margin:0 0 16px;color:#6b5f56;">
  Thank you for your interest in adopting <strong style="color:{$color};">{$petName}</strong>.
  After review, your application was <strong>not approved</strong> at this time.
</p>
<p style="margin:0;font-size:13px;color:#9c8f84;">
  You may browse other pets available for adoption on BantayPurrPaws.
</p>
HTML;

    return sendRawEmail($to, APP_NAME . ' — Adoption Application Update', emailShell('Adoption Application Update', $inner), $name);
}

function sendAnnouncementEmail(string $to, string $name, string $message, string $linkUrl = 'announcements.php'): bool {
    $color   = APP_COLOR;
    $safeMsg = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    $link    = absolute_url(ltrim($linkUrl, '/'));
    $inner   = <<<HTML
<h2 style="margin:0 0 8px;font-size:20px;color:#2d2520;">Hello, {$name}!</h2>
<p style="margin:0 0 16px;color:#6b5f56;">
  The BantayPurrPaws team has posted a new announcement:
</p>
<div style="background:#faf8f6;border-left:4px solid {$color};padding:16px 20px;margin:0 0 24px;border-radius:4px;">
  {$safeMsg}
</div>
<p style="margin:0 0 16px;color:#6b5f56;">
  <a href="{$link}" style="color:{$color};font-weight:600;">View on BantayPurrPaws</a>
</p>
HTML;

    return sendRawEmail($to, APP_NAME . ' — New Announcement', emailShell('New Announcement', $inner), $name);
}
function sendStaffInviteEmail(string $to, array $permissions, string $setupLink): bool {
    $color = APP_COLOR;
    $permLabels = [
        'manage_reports'     => 'Manage Rescue Reports',
        'manage_pets'        => 'Manage Pet Listings',
        'review_adoptions'   => 'Review Adoption Applications',
        'view_adoptions'     => 'View Adoption Queue',
        'manage_users'       => 'Manage Regular Users',
        'post_announcements' => 'Post Announcements',
    ];
    $permList = '';
    if (!empty($permissions)) {
        $items = '';
        foreach ($permissions as $key) {
            $label = $permLabels[$key] ?? $key;
            $items .= "<li style=\"margin-bottom:4px;\">{$label}</li>";
        }
        $permList = "<p style=\"margin:0 0 8px;color:#6b5f56;\">You have been granted access to:</p><ul style=\"margin:0 0 24px;padding-left:20px;color:#6b5f56;\">{$items}</ul>";
    }
    $inner = <<<HTML
<h2 style="margin:0 0 8px;font-size:20px;color:#2d2520;">You've been invited to join BantayPurrPaws!</h2>
<p style="margin:0 0 16px;color:#6b5f56;">
  An administrator has created a staff account for you on <strong style="color:{$color};">BantayPurrPaws</strong>. Click the button below to complete your account setup.
</p>
{$permList}
<table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 28px;">
  <tr><td align="center">
    <a href="{$setupLink}" style="display:inline-block;background:{$color};color:#fff;font-weight:600;font-size:15px;padding:14px 32px;border-radius:8px;text-decoration:none;">
      Complete My Staff Account
    </a>
  </td></tr>
</table>
<p style="margin:0 0 8px;font-size:13px;color:#9c8f84;">
  &bull; This invitation link expires in <strong>24 hours</strong>.<br>
  &bull; If you did not expect this email, you can safely ignore it.
</p>
HTML;
    return sendRawEmail($to, APP_NAME . ' — Staff Account Invitation', emailShell('Staff Invitation', $inner), '');
}

function sendPasswordChangedEmail(string $to, string $name): bool {
    $color = APP_COLOR;
    $inner = <<<HTML
<h2 style="margin:0 0 8px;font-size:20px;color:#2d2520;">Hello, {$name}!</h2>
<p style="margin:0 0 16px;color:#6b5f56;">
  Your <strong style="color:{$color};">BantayPurrPaws</strong> account password was recently changed.
</p>
<p style="margin:0 0 8px;color:#6b5f56;">
  If you made this change, no further action is needed.
</p>
<p style="margin:0;color:#ef4444;font-size:13px;">
  If you did <strong>not</strong> make this change, please contact us immediately or reset your password.
</p>
HTML;
    return sendRawEmail($to, APP_NAME . ' — Your Password Was Changed', emailShell('Password Changed', $inner), $name);
}

