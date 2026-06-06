<?php
/**
 * User account helpers — profile, staff creation, deletion.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/sensitive-data.php';

define('PROFILE_UPLOAD_DIR',  dirname(__DIR__) . '/uploads/profiles');
define('PROFILE_UPLOAD_URL',  'uploads/profiles');
define('PROFILE_MAX_BYTES',   5 * 1024 * 1024);
define('PROFILE_ALLOWED_MIMES', ['image/jpeg', 'image/jpg', 'image/png', 'image/webp']);
define('PROFILE_ALLOWED_EXT',   ['jpg', 'jpeg', 'png', 'webp']);

/**
 * Ensure profile-related columns exist (older databases may lack them).
 */
function ensureUserProfileSchema(): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $pdo      = getDB();
        $existing = [];
        foreach ($pdo->query('SHOW COLUMNS FROM `users`')->fetchAll(PDO::FETCH_ASSOC) as $col) {
            $existing[$col['Field']] = true;
        }

        $alters = [];
        if (!isset($existing['username'])) {
            $alters[] = 'ADD COLUMN `username` VARCHAR(50) DEFAULT NULL';
        }
        if (!isset($existing['phone_number'])) {
            $alters[] = 'ADD COLUMN `phone_number` VARCHAR(20) DEFAULT NULL';
        }
        if (!isset($existing['profile_picture'])) {
            $alters[] = 'ADD COLUMN `profile_picture` VARCHAR(512) DEFAULT NULL';
        }
        if (!isset($existing['permissions_changed_at'])) {
            $alters[] = 'ADD COLUMN `permissions_changed_at` DATETIME DEFAULT NULL';
        }

        if ($alters !== []) {
            $pdo->exec('ALTER TABLE `users` ' . implode(', ', $alters));
        }

        $hasUsername = isset($existing['username']);
        if (!$hasUsername) {
            foreach ($pdo->query('SHOW COLUMNS FROM `users`')->fetchAll(PDO::FETCH_ASSOC) as $col) {
                if ($col['Field'] === 'username') {
                    $hasUsername = true;
                    break;
                }
            }
        }

        if ($hasUsername) {
            $indexes = $pdo->query('SHOW INDEX FROM `users` WHERE Key_name = \'uk_users_username\'')->fetchAll();
            if ($indexes === []) {
                try {
                    $pdo->exec('CREATE UNIQUE INDEX `uk_users_username` ON `users` (`username`)');
                } catch (Throwable $e) {
                    error_log('ensureUserProfileSchema index: ' . $e->getMessage());
                }
            }
        }
    } catch (Throwable $e) {
        error_log('ensureUserProfileSchema: ' . $e->getMessage());
    }
}

// ── Password policy (matches register.php) ─────────────────

function validatePasswordPolicy(string $password): array {
    $errors = [];
    if (strlen($password) < 12)                                            $errors[] = 'at least 12 characters';
    if (!preg_match('/[A-Z]/', $password))                                 $errors[] = 'an uppercase letter (A-Z)';
    if (!preg_match('/[a-z]/', $password))                                 $errors[] = 'a lowercase letter (a-z)';
    if (!preg_match('/[0-9]/', $password))                                 $errors[] = 'a number (0-9)';
    if (!preg_match('/[!@#$%^&*()\-_=+\[\]{};:,\.?\/]/', $password))  $errors[] = 'a special character';
    return $errors;
}

function passwordPolicyMessage(array $errors): string {
    return 'Password must contain: ' . implode(', ', $errors) . '.';
}

// ── User lookup ───────────────────────────────────────────

function getUserById(int $id): ?array {
    if ($id <= 0) {
        return null;
    }

    ensureUserProfileSchema();

    try {
        $stmt = getDB()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? normalizeUserRecord(hydrateUserSensitiveFields($row)) : null;
    } catch (Throwable $e) {
        error_log('getUserById(' . $id . '): ' . $e->getMessage());
        $row = db_select('users', 'id=eq.' . $id . '&limit=1', true);
        return $row ? normalizeUserRecord(hydrateUserSensitiveFields($row)) : null;
    }
}

/** Ensure expected user columns exist with safe defaults. */
function normalizeUserRecord(array $user, ?array $fallback = null): array {
    $fallback = $fallback ?? [];

    return [
        'id'              => (int) ($user['id'] ?? $fallback['id'] ?? 0),
        'full_name'       => (string) ($user['full_name'] ?? $fallback['full_name'] ?? $fallback['name'] ?? ''),
        'email'           => (string) ($user['email'] ?? $fallback['email'] ?? ''),
        'password'        => $user['password'] ?? null,
        'role'            => (string) ($user['role'] ?? $fallback['role'] ?? 'user'),
        'username'        => $user['username'] ?? $fallback['username'] ?? null,
        'phone_number'    => $user['phone_number'] ?? $fallback['phone'] ?? null,
        'profile_picture' => $user['profile_picture'] ?? null,
        'avatar_url'      => $user['avatar_url'] ?? null,
        'google_id'       => $user['google_id'] ?? null,
        'email_verified'  => $user['email_verified'] ?? 0,
        'auth_provider'   => $user['auth_provider'] ?? 'local',
        'created_at'      => $user['created_at'] ?? null,
        'updated_at'      => $user['updated_at'] ?? null,
    ];
}

function usernameExists(string $username, ?int $excludeId = null): bool {
    $username = trim($username);
    if ($username === '') {
        return false;
    }
    $user = db_select('users', 'username=eq.' . urlencode($username) . '&limit=1', true);
    if (!$user) {
        return false;
    }
    return $excludeId === null || (int) $user['id'] !== $excludeId;
}

function refreshUserSession(array $user): void {
    startSession();
    $user = normalizeUserRecord($user);
    $_SESSION['user_id']           = $user['id'];
    $_SESSION['full_name']         = $user['full_name'];
    $_SESSION['email']             = $user['email'];
    $_SESSION['role']              = $user['role'];
    $_SESSION['avatar']            = profilePictureUrl($user);
    $_SESSION['username']          = $user['username'] ?? '';
    $_SESSION['phone']             = $user['phone_number'] ?? '';
    if (empty($_SESSION['session_started_at'])) {
        $_SESSION['session_started_at'] = time();
    }

    // RBAC: load staff_permissions into session (null for admin/user — uses role defaults)
    $raw = $user['staff_permissions'] ?? null;
    if ($raw !== null && is_string($raw)) {
        $decoded = json_decode($raw, true);
        $_SESSION['staff_permissions'] = is_array($decoded) ? $decoded : null;
    } else {
        $_SESSION['staff_permissions'] = is_array($raw) ? $raw : null;
    }
}

/**
 * Update user columns via PDO (reliable for NULL and optional fields).
 */
function updateUserFields(int $userId, array $fields): bool {
    ensureUserProfileSchema();

    $allowed = [
        'full_name', 'email', 'email_hash', 'password', 'phone_number', 'username',
        'profile_picture', 'avatar_url', 'role', 'email_verified', 'auth_provider', 'staff_permissions', 'permissions_changed_at',
    ];

    $sets   = [];
    $params = [];

    $fields = prepareUserRowForStorage($fields);

    foreach ($fields as $key => $value) {
        if (!in_array($key, $allowed, true)) {
            continue;
        }
        $sets[]   = '`' . $key . '` = ?';
        $params[] = $value;
    }

    if ($sets === []) {
        return false;
    }

    $params[] = $userId;
    $sql      = 'UPDATE `users` SET ' . implode(', ', $sets) . ' WHERE `id` = ?';

    try {
        $stmt = getDB()->prepare($sql);
        return $stmt->execute($params);
    } catch (Throwable $e) {
        error_log('updateUserFields(' . $userId . '): ' . $e->getMessage());
        return false;
    }
}

/**
 * Insert a user row and return the created record.
 */
function insertUserRecord(array $data): ?array {
    $allowed = [
        'full_name', 'email', 'email_hash', 'password', 'role', 'username', 'phone_number',
        'email_verified', 'auth_provider', 'google_id', 'avatar_url', 'profile_picture',
    ];

    $columns = [];
    $values  = [];
    $params  = [];

    $data = prepareUserRowForStorage($data);

    foreach ($data as $key => $value) {
        if (!in_array($key, $allowed, true)) {
            continue;
        }
        $columns[] = '`' . $key . '`';
        $values[]  = '?';
        $params[]  = $value;
    }

    if ($columns === []) {
        return null;
    }

    try {
        $pdo = getDB();
        $sql = 'INSERT INTO `users` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $id = (int) $pdo->lastInsertId();
        if ($id <= 0) {
            return null;
        }

        return getUserById($id) ?? array_merge($data, ['id' => $id]);
    } catch (Throwable $e) {
        error_log('insertUserRecord: ' . $e->getMessage());
        return null;
    }
}

// ── Profile picture ───────────────────────────────────────

function ensureProfileUploadDir(): void {
    if (!is_dir(PROFILE_UPLOAD_DIR)) {
        mkdir(PROFILE_UPLOAD_DIR, 0755, true);
    }
}

function userHasProfilePicture(?array $user): bool {
    if (!$user) {
        return false;
    }
    $path = $user['profile_picture'] ?? $user['avatar_url'] ?? null;
    return $path !== null && trim((string) $path) !== '';
}

function profilePictureUrl(?array $user): string {
    if (!$user) {
        return '';
    }
    $path = $user['profile_picture'] ?? $user['avatar_url'] ?? null;
    if (!$path) {
        return '';
    }
    if (str_starts_with($path, 'http')) {
        return $path;
    }
    return url(ltrim($path, '/'));
}

function deleteProfilePictureFile(?string $path): void {
    if (!$path || str_starts_with($path, 'http')) {
        return;
    }
    $relative = ltrim(str_replace('\\', '/', $path), '/');
    if (!str_starts_with($relative, PROFILE_UPLOAD_URL)) {
        return;
    }
    $full = dirname(__DIR__) . '/' . $relative;
    if (is_file($full)) {
        @unlink($full);
    }
}

function uploadProfilePicture(array $file): array {
    ensureProfileUploadDir();
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Image upload failed. Please try again.'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, PROFILE_ALLOWED_EXT, true)) {
        return ['ok' => false, 'error' => 'Only JPG, JPEG, PNG, and WEBP images are allowed.'];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, PROFILE_ALLOWED_MIMES, true)) {
        return ['ok' => false, 'error' => 'Invalid image file type.'];
    }
    if (($file['size'] ?? 0) > PROFILE_MAX_BYTES) {
        return ['ok' => false, 'error' => 'Image must be under 5MB.'];
    }
    $filename = uniqid('profile_', true) . '.' . $ext;
    $dest     = PROFILE_UPLOAD_DIR . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'error' => 'Could not save uploaded image.'];
    }
    return ['ok' => true, 'path' => PROFILE_UPLOAD_URL . '/' . $filename];
}

// ── Staff creation ────────────────────────────────────────

function createStaffAccount(string $fullName, string $email, string $password, ?string $username = null): array {
    $fullName = trim($fullName);
    $email    = trim(strtolower($email));
    $username = $username !== null ? trim($username) : '';

    if ($fullName === '' || $email === '') {
        return ['ok' => false, 'error' => 'Full name and email are required.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Please enter a valid email address.'];
    }
    if (emailExists($email)) {
        return ['ok' => false, 'error' => 'An account with that email already exists.'];
    }
    if ($username !== '' && usernameExists($username)) {
        return ['ok' => false, 'error' => 'That username is already taken.'];
    }

    $pwErrors = validatePasswordPolicy($password);
    if (!empty($pwErrors)) {
        return ['ok' => false, 'error' => passwordPolicyMessage($pwErrors)];
    }

    $data = [
        'full_name'      => $fullName,
        'email'          => $email,
        'password'       => password_hash($password, PASSWORD_DEFAULT),
        'role'           => 'staff',
        'email_verified' => 1,
        'auth_provider'  => 'local',
    ];
    if ($username !== '') {
        $data['username'] = $username;
    }

    $user = insertUserRecord($data);
    if (!$user) {
        return ['ok' => false, 'error' => 'Could not create staff account. Check that the database schema is up to date.'];
    }

    return ['ok' => true, 'user' => normalizeUserRecord($user)];
}

// ── Account deletion ──────────────────────────────────────

function canDeleteUserAccount(array $target, array $actor): array {
    $targetId = (int) $target['id'];
    $actorId  = (int) $actor['id'];

    if ($targetId === $actorId) {
        return ['ok' => false, 'error' => 'You cannot delete your own account while logged in.'];
    }
    if (($target['role'] ?? '') === 'admin') {
        return ['ok' => false, 'error' => 'Administrator accounts cannot be deleted.'];
    }
    if (($actor['role'] ?? '') !== 'admin') {
        return ['ok' => false, 'error' => 'Only administrators can delete accounts.'];
    }

    return ['ok' => true];
}

function deleteUserAccount(int $targetId, array $actor): array {
    $target = getUserById($targetId);
    if (!$target) {
        return ['ok' => false, 'error' => 'User not found.'];
    }

    $check = canDeleteUserAccount($target, $actor);
    if (!$check['ok']) {
        return $check;
    }

    $role = $target['role'] ?? '';

    deleteProfilePictureFile($target['profile_picture'] ?? null);

    if (!db_delete('users', 'id=eq.' . $targetId)) {
        return ['ok' => false, 'error' => 'Could not delete the account. Please try again.'];
    }

    return ['ok' => true, 'user' => $target];
}

function promoteUserToStaff(int $targetId, array $actor): array {
    if (($actor['role'] ?? '') !== 'admin') {
        return ['ok' => false, 'error' => 'Only administrators can promote accounts.'];
    }

    if ($targetId === (int) $actor['id']) {
        return ['ok' => false, 'error' => 'You cannot promote your own account.'];
    }

    $target = getUserById($targetId);
    if (!$target) {
        return ['ok' => false, 'error' => 'User not found.'];
    }
    if (($target['role'] ?? '') !== 'user') {
        return ['ok' => false, 'error' => 'Only regular user accounts can be promoted from this page.'];
    }

    if (!updateUserFields($targetId, ['role' => 'staff'])) {
        return ['ok' => false, 'error' => 'Could not promote account. Please try again.'];
    }

    $updated = getUserById($targetId);
    return ['ok' => true, 'user' => $updated ?? $target];
}

// ── Profile update ────────────────────────────────────────

function updateOwnProfile(int $userId, array $fields): array {
    $user = getUserById($userId);
    if (!$user) {
        return ['ok' => false, 'error' => 'Account not found.'];
    }

    $fullName = trim($fields['full_name'] ?? '');
    $email    = trim(strtolower($fields['email'] ?? ''));
    $phone    = trim($fields['phone_number'] ?? '');
    $username = array_key_exists('username', $fields) ? trim($fields['username'] ?? '') : ($user['username'] ?? '');

    if ($fullName === '' || $email === '') {
        return ['ok' => false, 'error' => 'Full name and email are required.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Please enter a valid email address.'];
    }

    $existing = findUserByEmail($email);
    if ($existing && (int) $existing['id'] !== $userId) {
        return ['ok' => false, 'error' => 'That email is already used by another account.'];
    }

    if ($username !== '' && usernameExists($username, $userId)) {
        return ['ok' => false, 'error' => 'That username is already taken.'];
    }

    if ($phone !== '') {
        $phoneCheck = validatePhoneNumber($phone, false);
        if (!$phoneCheck['ok']) {
            return ['ok' => false, 'error' => $phoneCheck['error']];
        }
        $phone = $phoneCheck['value'];
    }

    $update = [
        'full_name'    => $fullName,
        'email'        => $email,
        'phone_number' => $phone !== '' ? $phone : null,
    ];
    if (array_key_exists('username', $fields)) {
        $update['username'] = $username !== '' ? $username : null;
    }

    if (!updateUserFields($userId, $update)) {
        return ['ok' => false, 'error' => 'Could not update profile. Please try again.'];
    }

    $updated = getUserById($userId);
    if (!$updated) {
        $updated = normalizeUserRecord(array_merge($user, $update, ['id' => $userId]));
    }
    refreshUserSession($updated);

    return ['ok' => true, 'user' => $updated];
}

function changeOwnPassword(int $userId, string $currentPassword, string $newPassword, string $confirmPassword): array {
    $user = getUserById($userId);
    if (!$user) {
        return ['ok' => false, 'error' => 'Account not found.'];
    }

    if (($user['auth_provider'] ?? 'local') === 'google' && empty($user['password'])) {
        return ['ok' => false, 'error' => 'Google-only accounts cannot change password here. Use Google account settings.'];
    }

    if (!password_verify($currentPassword, $user['password'])) {
        return ['ok' => false, 'error' => 'Current password is incorrect.'];
    }

    if ($newPassword !== $confirmPassword) {
        return ['ok' => false, 'error' => 'New passwords do not match.'];
    }

    $pwErrors = validatePasswordPolicy($newPassword);
    if (!empty($pwErrors)) {
        return ['ok' => false, 'error' => passwordPolicyMessage($pwErrors)];
    }

    if (!updateUserFields($userId, ['password' => password_hash($newPassword, PASSWORD_DEFAULT)])) {
        return ['ok' => false, 'error' => 'Could not update password. Please try again.'];
    }

    return ['ok' => true];
}

function updateOwnProfilePicture(int $userId, array $file): array {
    $user = getUserById($userId);
    if (!$user) {
        return ['ok' => false, 'error' => 'Account not found.'];
    }

    $upload = uploadProfilePicture($file);
    if (!$upload['ok']) {
        return $upload;
    }

    deleteProfilePictureFile($user['profile_picture'] ?? null);

    if (!updateUserFields($userId, ['profile_picture' => $upload['path']])) {
        deleteProfilePictureFile($upload['path']);
        return ['ok' => false, 'error' => 'Could not save profile picture. Please try again.'];
    }

    $updated = getUserById($userId);
    if (!$updated) {
        return ['ok' => false, 'error' => 'Picture saved but could not reload account. Please refresh the page.'];
    }
    refreshUserSession($updated);

    return ['ok' => true, 'user' => $updated];
}
