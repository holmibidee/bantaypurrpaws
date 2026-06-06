<?php
/**
 * Protect sensitive user data at rest (HMAC lookup + AES-256-GCM encryption).
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/logger.php';

load_env_file(dirname(__DIR__) . '/.env');

define('SENSITIVE_ENC_PREFIX', 'enc:v1:');

function sensitiveDataKey(): string {
    static $key = null;
    if ($key !== null) {
        return $key;
    }

    $raw = '756a4d85b65ae06da2c6127bd3997d1e100de9af23d441d62e47e2501d3d2889';
    if ($raw === '') {
        $raw = hash('sha256', (
            ($_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: 'bpp')
            . '|'
            . ($_ENV['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY') ?: 'bpp')
            . '|bantaypurrpaws'
        ), true);
        bpp_log('security', 'warning', 'DATA_ENCRYPTION_KEY not set; using derived fallback key.');
    } elseif (str_starts_with($raw, 'base64:')) {
        $decoded = base64_decode(substr($raw, 7), true);
        $raw     = ($decoded !== false && strlen($decoded) >= 32) ? $decoded : hash('sha256', $raw, true);
    } else {
        $raw = hash('sha256', $raw, true);
    }

    $key = substr($raw, 0, 32);
    return $key;
}

function sensitiveLookupHash(string $value): string {
    $normalized = strtolower(trim($value));
    return hash_hmac('sha256', $normalized, sensitiveDataKey());
}

function isEncryptedValue(?string $value): bool {
    return is_string($value) && str_starts_with($value, SENSITIVE_ENC_PREFIX);
}

function encryptSensitiveValue(string $plaintext): string {
    if ($plaintext === '') {
        return '';
    }

    if (!function_exists('sodium_crypto_secretbox')) {
        bpp_log('security', 'error', 'libsodium unavailable; storing value as HMAC-only hash.');
        return 'hash:v1:' . sensitiveLookupHash($plaintext);
    }

    $key   = sensitiveDataKey();
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cipher = sodium_crypto_secretbox($plaintext, $nonce, $key);

    return SENSITIVE_ENC_PREFIX . base64_encode($nonce . $cipher);
}

function decryptSensitiveValue(string $stored): string {
    if ($stored === '') {
        return '';
    }

    if (str_starts_with($stored, 'hash:v1:')) {
        return '';
    }

    if (!isEncryptedValue($stored)) {
        return $stored;
    }

    if (!function_exists('sodium_crypto_secretbox_open')) {
        bpp_log('security', 'error', 'Cannot decrypt value: libsodium unavailable.');
        return '';
    }

    $payload = base64_decode(substr($stored, strlen(SENSITIVE_ENC_PREFIX)), true);
    if ($payload === false || strlen($payload) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
        bpp_log('security', 'error', 'Invalid encrypted payload.');
        return '';
    }

    $nonce  = substr($payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cipher = substr($payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain  = sodium_crypto_secretbox_open($cipher, $nonce, sensitiveDataKey());

    if ($plain === false) {
        bpp_log('security', 'error', 'Decryption failed.');
        return '';
    }

    return $plain;
}

function protectEmail(string $email): array {
    $email = strtolower(trim($email));
    return [
        'email'      => encryptSensitiveValue($email),
        'email_hash' => sensitiveLookupHash($email),
    ];
}

function protectPhone(?string $phone): ?string {
    $phone = trim((string) $phone);
    if ($phone === '') {
        return null;
    }
    return encryptSensitiveValue($phone);
}

function revealEmailFromRow(array $row): string {
    if (!empty($row['email_plain'])) {
        return (string) $row['email_plain'];
    }
    return decryptSensitiveValue((string) ($row['email'] ?? ''));
}

function revealPhoneFromRow(array $row): ?string {
    $phone = $row['phone_number'] ?? null;
    if ($phone === null || $phone === '') {
        return null;
    }
    $revealed = decryptSensitiveValue((string) $phone);
    return $revealed !== '' ? $revealed : null;
}

/**
 * Ensure email_hash column exists and migrate plaintext rows when possible.
 */
function ensureSensitiveDataSchema(): void {
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

        if (!isset($existing['email_hash'])) {
            $pdo->exec('ALTER TABLE `users` ADD COLUMN `email_hash` VARCHAR(64) DEFAULT NULL AFTER `email`');
            try {
                $pdo->exec('CREATE UNIQUE INDEX `uk_users_email_hash` ON `users` (`email_hash`)');
            } catch (Throwable $e) {
                bpp_log('security', 'warning', 'Could not create email_hash index.', ['error' => $e->getMessage()]);
            }
        }

        try {
            $pdo->exec('ALTER TABLE `users` MODIFY COLUMN `email` VARCHAR(512) NOT NULL');
            $pdo->exec('ALTER TABLE `users` MODIFY COLUMN `phone_number` VARCHAR(512) DEFAULT NULL');
            $pdo->exec('ALTER TABLE `adoption_applications` MODIFY COLUMN `email` VARCHAR(512) NOT NULL');
            $pdo->exec('ALTER TABLE `adoption_applications` MODIFY COLUMN `contact_number` VARCHAR(512) NOT NULL');
            $pdo->exec('ALTER TABLE `rescue_reports` MODIFY COLUMN `contact_number` VARCHAR(512) NOT NULL');
        } catch (Throwable $e) {
            bpp_log('security', 'warning', 'Could not widen sensitive columns.', ['error' => $e->getMessage()]);
        }

        migratePlaintextUserSensitiveData();
    } catch (Throwable $e) {
        bpp_log('security', 'error', 'ensureSensitiveDataSchema failed.', ['error' => $e->getMessage()]);
    }
}

function migratePlaintextUserSensitiveData(): void {
    try {
        $pdo  = getDB();
        $stmt = $pdo->query(
            'SELECT id, email, phone_number, email_hash FROM users
             WHERE email_hash IS NULL OR email_hash = \'\''
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            return;
        }

        $update = $pdo->prepare(
            'UPDATE users SET email = ?, email_hash = ?, phone_number = ? WHERE id = ?'
        );

        foreach ($rows as $row) {
            $email = decryptSensitiveValue((string) ($row['email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $protected = protectEmail($email);
            $phone     = revealPhoneFromRow($row);
            $encPhone  = protectPhone($phone);

            $update->execute([
                $protected['email'],
                $protected['email_hash'],
                $encPhone,
                (int) $row['id'],
            ]);
        }

        if ($rows !== []) {
            bpp_log('security', 'info', 'Migrated plaintext user sensitive data.', ['count' => count($rows)]);
        }
    } catch (Throwable $e) {
        bpp_log('security', 'error', 'migratePlaintextUserSensitiveData failed.', ['error' => $e->getMessage()]);
    }
}

function prepareUserRowForStorage(array $fields): array {
    ensureSensitiveDataSchema();

    if (isset($fields['email'])) {
        $protected = protectEmail((string) $fields['email']);
        $fields['email']      = $protected['email'];
        $fields['email_hash'] = $protected['email_hash'];
    }

    if (array_key_exists('phone_number', $fields)) {
        $fields['phone_number'] = protectPhone($fields['phone_number']);
    }

    return $fields;
}

function hydrateUserSensitiveFields(array $user): array {
    $user['email']        = revealEmailFromRow($user);
    $user['phone_number'] = revealPhoneFromRow($user);
    return $user;
}

function findUserByEmail(string $email): ?array {
    ensureSensitiveDataSchema();
    $email = strtolower(trim($email));
    $hash  = sensitiveLookupHash($email);

    $user = db_select('users', 'email_hash=eq.' . urlencode($hash) . '&limit=1', true);
    if (!$user) {
        $user = db_select('users', 'email=eq.' . urlencode($email) . '&limit=1', true);
    }

    return $user ? hydrateUserSensitiveFields($user) : null;
}

function protectSubmissionEmail(string $email): string {
    return encryptSensitiveValue(strtolower(trim($email)));
}

function protectSubmissionPhone(string $phone): string {
    return encryptSensitiveValue(preg_replace('/\D+/', '', trim($phone)));
}

function revealSubmissionEmail(array $row): string {
    return decryptSensitiveValue((string) ($row['email'] ?? ''));
}

function revealSubmissionPhone(array $row): string {
    return decryptSensitiveValue((string) ($row['contact_number'] ?? ''));
}

function hydrateAdoptionApplication(array $app): array {
    $app['email']           = revealSubmissionEmail($app);
    $app['contact_number']  = revealSubmissionPhone($app);
    return $app;
}

function hydrateRescueReport(array $report): array {
    $report['contact_number'] = revealSubmissionPhone($report);
    return $report;
}
