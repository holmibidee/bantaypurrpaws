<?php
/**
 * BantayPurrPaws — OTP Helper (MySQL)
 *
 * ROOT CAUSE OF "always invalid" BUG:
 * The otp_tokens.purpose column was ENUM('registration','password_reset',...).
 * MySQL silently stores '' for any value not in the ENUM list.
 * Purposes like 'profile_update', 'email_change_current', 'email_change_new'
 * were not in the ENUM, so every token was stored with purpose='' and every
 * verify query found nothing → returned 'invalid'.
 *
 * FIX: This file auto-migrates the column to VARCHAR(60) on first run so all
 * purpose strings work without needing a manual SQL migration.
 * All DB calls use direct PDO — no filter-string parsing that could silently
 * drop conditions.
 */

date_default_timezone_set('UTC');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

define('OTP_TTL_SECONDS', 900);  // 15 minutes
define('OTP_MAX_RESEND',  20);   // max sends per hour per email+purpose

// ── One-time schema fix: widen purpose to VARCHAR ────────────────────────────
function _otp_ensure_varchar_purpose(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db   = getDB();
        $cols = $db->query("SHOW COLUMNS FROM `otp_tokens` LIKE 'purpose'")->fetchAll();
        if (!empty($cols)) {
            $colType = strtolower($cols[0]['Type'] ?? '');
            // Only alter if it's still an ENUM (not already varchar/text)
            if (str_starts_with($colType, 'enum')) {
                $db->exec("ALTER TABLE `otp_tokens` MODIFY COLUMN `purpose` VARCHAR(60) NOT NULL DEFAULT 'registration'");
                error_log('[OTP] Migrated otp_tokens.purpose from ENUM to VARCHAR(60).');
            }
        }
    } catch (Throwable $e) {
        error_log('[OTP] Could not alter purpose column: ' . $e->getMessage());
    }
}

// ── Generate ─────────────────────────────────────────────────────────────────

function generateOtp(): string {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

// ── Create & store OTP ───────────────────────────────────────────────────────

/**
 * Store a new OTP (direct PDO, no filter strings).
 * Returns the plain OTP string, or false if rate-limited.
 */
function createOtp(string $email, string $purpose = 'registration'): string|false {
    _otp_ensure_varchar_purpose();

    $db = getDB();

    // Rate-limit: max OTP_MAX_RESEND sends per hour
    $oneHourAgo = gmdate('Y-m-d H:i:s', time() - 3600);
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM `otp_tokens`
         WHERE `email` = ? AND `purpose` = ? AND `created_at` >= ?'
    );
    $stmt->execute([$email, $purpose, $oneHourAgo]);
    if ((int) $stmt->fetchColumn() >= OTP_MAX_RESEND) {
        return false;
    }

    // Invalidate previous unused tokens for this email + purpose
    $db->prepare(
        'UPDATE `otp_tokens` SET `used` = 1
         WHERE `email` = ? AND `purpose` = ? AND `used` = 0'
    )->execute([$email, $purpose]);

    // Insert new token
    $otp       = generateOtp();
    $expiresAt = gmdate('Y-m-d H:i:s', time() + OTP_TTL_SECONDS);

    $db->prepare(
        'INSERT INTO `otp_tokens` (`email`, `otp_code`, `purpose`, `expires_at`, `used`)
         VALUES (?, ?, ?, ?, 0)'
    )->execute([$email, $otp, $purpose, $expiresAt]);

    return $otp;
}

// ── Verify OTP ───────────────────────────────────────────────────────────────

/**
 * Verify an OTP code (direct PDO).
 *
 * Returns:
 *   'valid'   — correct and not expired
 *   'expired' — correct but past expiry
 *   'invalid' — not found, already used, or code mismatch
 */
function verifyOtp(string $email, string $code, string $purpose = 'registration'): string {
    _otp_ensure_varchar_purpose();

    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT `id`, `otp_code`, `expires_at`
             FROM `otp_tokens`
             WHERE `email` = ? AND `purpose` = ? AND `used` = 0
             ORDER BY `created_at` DESC
             LIMIT 1'
        );
        $stmt->execute([$email, $purpose]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('[OTP] verifyOtp query error: ' . $e->getMessage());
        return 'invalid';
    }

    if (!$row) {
        return 'invalid';
    }

    // Compare — trim to handle any CHAR padding / whitespace
    $storedCode  = trim((string) ($row['otp_code'] ?? ''));
    $enteredCode = trim((string) $code);

    if (!hash_equals($storedCode, $enteredCode)) {
        return 'invalid';
    }

    // Check expiry (stored as UTC)
    $expiresTs = strtotime(trim($row['expires_at']) . ' UTC');
    if ($expiresTs === false || $expiresTs < time()) {
        // Consume expired token so it can't be retried
        $db->prepare('UPDATE `otp_tokens` SET `used` = 1 WHERE `id` = ?')
           ->execute([$row['id']]);
        return 'expired';
    }

    // Consume the valid token
    $db->prepare('UPDATE `otp_tokens` SET `used` = 1 WHERE `id` = ?')
       ->execute([$row['id']]);

    return 'valid';
}

// ── Issue & send ─────────────────────────────────────────────────────────────

/**
 * Issue and email a fresh OTP.
 * Returns true on success, or an error string on failure.
 */
function issueAndSendOtp(string $email, string $name, string $purpose = 'registration'): bool|string {
    $otp = createOtp($email, $purpose);

    if ($otp === false) {
        return 'Too many OTP requests. Please wait before requesting again.';
    }

    $sent = sendOtpEmail($email, $name, $otp, $purpose);

    if (!$sent) {
        // Invalidate so the phantom code can't be used
        try {
            getDB()->prepare(
                'UPDATE `otp_tokens` SET `used` = 1
                 WHERE `email` = ? AND `purpose` = ? AND `otp_code` = ? AND `used` = 0'
            )->execute([$email, $purpose, $otp]);
        } catch (Throwable) {}

        error_log('[OTP] Email send failed for ' . $email . ' (' . $purpose . '). Token invalidated.');
        return 'Failed to send OTP email. Please check your email address or try again later.';
    }

    return true;
}

// ── Cleanup (optional cron) ──────────────────────────────────────────────────

function cleanupOtpTokens(): void {
    $yesterday = gmdate('Y-m-d H:i:s', time() - 86400);
    try {
        getDB()->prepare('DELETE FROM `otp_tokens` WHERE `created_at` < ?')
               ->execute([$yesterday]);
    } catch (Throwable) {}
}
