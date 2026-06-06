<?php
/**
 * BantayPurrPaws — OTP Helper (MySQL)
 */

date_default_timezone_set('UTC'); // ensure gmdate() and time() match

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

define('OTP_TTL_SECONDS', 900);   // 15 minutes (Brevo free tier can be slow)
define('OTP_MAX_RESEND',  100);     // max sends per hour

// ── Generate ──────────────────────────────────────────────

function generateOtp(): string {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

// ── Create & store OTP ────────────────────────────────────

/**
 * Store a new OTP, invalidating previous ones for same email + purpose.
 * Returns the plain OTP string or false if rate-limited.
 */
function createOtp(string $email, string $purpose = 'registration'): string|false {
    // ── Rate-limit: max OTP_MAX_RESEND sends per hour ─────
    // Get all tokens for this email+purpose in the last hour
    $oneHourAgo = date('Y-m-d H:i:s', time() - 3600);
    $recent = db_select(
        'otp_tokens',
        'email=eq.' . urlencode($email)
        . '&purpose=eq.' . $purpose
        . '&created_at=gte.' . $oneHourAgo
    );
    if (count($recent) >= OTP_MAX_RESEND) {
        return false;
    }

    // ── Invalidate previous unused tokens ─────────────────
    db_update(
        'otp_tokens',
        ['used' => true],
        'email=eq.' . urlencode($email) . '&purpose=eq.' . $purpose . '&used=eq.false'
    );

    // ── Insert new token ──────────────────────────────────
    $otp       = generateOtp();
    $expiresAt = gmdate('Y-m-d H:i:s', time() + OTP_TTL_SECONDS);

    db_insert('otp_tokens', [
        'email'      => $email,
        'otp_code'   => $otp,
        'purpose'    => $purpose,
        'expires_at' => $expiresAt,
        'used'       => false,
    ]);

    return $otp;
}

// ── Verify OTP ────────────────────────────────────────────

/**
 * Verify an OTP code.
 *
 * Returns:
 *   'valid'   — correct and not expired
 *   'expired' — correct but past expiry
 *   'invalid' — not found or already used
 *
 * NOTE: $db parameter removed — pass email, code, purpose only.
 */
function verifyOtp(string $email, string $code, string $purpose = 'registration'): string {
    // Find the most recent unused token for this email+purpose
    $row = db_select(
        'otp_tokens',
        'email=eq.' . urlencode($email)
        . '&purpose=eq.' . $purpose
        . '&used=eq.false'
        . '&order=created_at.desc'
        . '&limit=1',
        true
    );

    if (!$row) {
        return 'invalid';
    }

    // Trim both values to handle CHAR(6) padding and whitespace
    $storedCode = trim($row['otp_code']);
    $enteredCode = trim($code);

    // Debug logging before comparison
    // Compare codes in PHP to avoid DB-side numeric casting (preserve leading zeros)
    if (!hash_equals($storedCode, $enteredCode)) {
        return 'invalid';
    }

    $expiresUtc = strtotime($row['expires_at'] . ' UTC');
    if ($expiresUtc < time()) {
        db_update('otp_tokens', ['used' => true], 'id=eq.' . $row['id']);
        return 'expired';
    }

    // Consume the token
    db_update('otp_tokens', ['used' => true], 'id=eq.' . $row['id']);

    return 'valid';
}

// ── Issue & send ──────────────────────────────────────────

/**
 * Issue and email a fresh OTP.
 * Returns true on success, or an error string.
 *
 * NOTE: $db parameter removed — call as issueAndSendOtp($email, $name, $purpose)
 */
function issueAndSendOtp(string $email, string $name, string $purpose = 'registration'): bool|string {
    $otp = createOtp($email, $purpose);

    if ($otp === false) {
        return 'Too many OTP requests. Please wait before requesting again.';
    }

    $sent = sendOtpEmail($email, $name, $otp, $purpose);

    if (!$sent) {
        // Invalidate the token so a phantom code cannot be used if email never arrived
        db_update(
            'otp_tokens',
            ['used' => true],
            'email=eq.' . urlencode($email)
            . '&purpose=eq.' . $purpose
            . '&otp_code=eq.' . urlencode($otp)
            . '&used=eq.false'
        );
        error_log('[OTP] Email send failed for ' . $email . ' (' . $purpose . '). Token invalidated.');
        return 'Failed to send OTP email. Please check your email address or try again later.';
    }

    return true;
}

// ── Cleanup (optional cron) ───────────────────────────────

/**
 * Delete all expired/used tokens older than 24 hours.
 * You can call this occasionally to keep the table clean.
 */
function cleanupOtpTokens(): void {
    $yesterday = date('Y-m-d H:i:s', time() - 86400);
    db_delete('otp_tokens', 'created_at=lt.' . $yesterday);
}