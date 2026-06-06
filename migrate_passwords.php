<?php
/**
 * migrate_passwords.php
 * ---------------------
 * One-time script to detect and bcrypt-hash any plain-text passwords
 * that may exist in the users table.
 *
 * HOW TO USE:
 *   1. Upload this file to the server (same root as index.php).
 *   2. Run it ONCE via CLI:  php migrate_passwords.php
 *      OR visit it in a browser while logged in as admin.
 *   3. DELETE this file from the server immediately after running.
 *
 * SAFETY:
 *   - Bcrypt hashes always start with "$2y$".
 *   - Any password that does NOT start with "$2y$" is treated as plain-text
 *     and re-saved as a bcrypt hash.
 *   - Passwords that are already bcrypt hashes are left untouched.
 *   - Google-only accounts (password IS NULL) are skipped.
 */

// ── Bootstrap ──────────────────────────────────────────────────────────────
define('SECURE_MIGRATION', true);   // guard against accidental re-runs

$isWeb = php_sapi_name() !== 'cli';

if ($isWeb) {
    // Web access: require admin session
    require_once __DIR__ . '/includes/auth.php';
    startSession();
    if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo '<p>Access denied. Log in as admin first.</p>';
        exit;
    }
    echo '<pre>';
}

require_once __DIR__ . '/includes/db.php';

$db = getDB();

// ── Fetch all local accounts ───────────────────────────────────────────────
$stmt = $db->query("SELECT id, email, password FROM users WHERE password IS NOT NULL AND auth_provider = 'local'");
$users = $stmt->fetchAll();

$migratedCount = 0;
$skippedCount  = 0;

echo "BantayPurrPaws — Password Migration\n";
echo str_repeat('-', 40) . "\n";

foreach ($users as $user) {
    $id  = (int) $user['id'];
    $pwd = $user['password'];

    // Already a valid bcrypt hash → skip
    if (password_needs_rehash($pwd, PASSWORD_DEFAULT) === false && str_starts_with($pwd, '$2y$')) {
        $skippedCount++;
        echo "  [SKIP]    user #{$id} ({$user['email']}) — already bcrypt\n";
        continue;
    }

    // Plain-text or outdated hash → re-hash
    $hashed = password_hash($pwd, PASSWORD_DEFAULT);
    $upd    = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
    $upd->execute([$hashed, $id]);

    $migratedCount++;
    echo "  [MIGRATED] user #{$id} ({$user['email']}) — password hashed\n";
}

echo str_repeat('-', 40) . "\n";
echo "Done. Migrated: {$migratedCount}  |  Already hashed: {$skippedCount}\n";
echo "\n⚠  DELETE this file from the server now!\n";

if ($isWeb) {
    echo '</pre>';
}
