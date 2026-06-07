<?php
/**
 * BantayPurrPaws — Enterprise Security Helper
 *
 * Handles: device fingerprinting, login attempt recording,
 * risk scoring, brute-force protection, session management,
 * number-matching challenge, and security event logging.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

// ── Constants ──────────────────────────────────────────────

define('SEC_MAX_FAILED_LOGINS',    5);     // lock after N failures
define('SEC_LOCKOUT_MINUTES',      15);    // lockout duration
define('SEC_CHALLENGE_TOKEN_TTL',  600);   // 10 min challenge link TTL
define('SEC_SESSION_TTL',          86400); // 24h session
define('SEC_BRUTE_WINDOW',         300);   // 5 min brute-force window
define('SEC_BRUTE_MAX',            10);    // max attempts in window

// ── Device Fingerprint ─────────────────────────────────────

function sec_parse_user_agent(string $ua): array {
    $browser = 'Unknown';
    $os      = 'Unknown';
    $device  = 'Desktop';

    // OS detection
    if (preg_match('/Windows NT ([\d.]+)/i', $ua, $m)) {
        $versions = ['10.0'=>'Windows 10','6.3'=>'Windows 8.1','6.2'=>'Windows 8','6.1'=>'Windows 7'];
        $os = $versions[$m[1]] ?? 'Windows';
    } elseif (preg_match('/Mac OS X ([\d_]+)/i', $ua, $m)) {
        $os = 'macOS ' . str_replace('_', '.', $m[1]);
    } elseif (preg_match('/Android ([\d.]+)/i', $ua, $m)) {
        $os = 'Android ' . $m[1]; $device = 'Mobile';
    } elseif (preg_match('/iPhone OS ([\d_]+)/i', $ua, $m)) {
        $os = 'iOS ' . str_replace('_', '.', $m[1]); $device = 'Mobile';
    } elseif (preg_match('/iPad/i', $ua)) {
        $os = 'iPadOS'; $device = 'Tablet';
    } elseif (preg_match('/Linux/i', $ua)) {
        $os = 'Linux';
    }

    // Browser detection (order matters)
    if (preg_match('/Edg\/([\d.]+)/i', $ua, $m))       $browser = 'Edge '       . $m[1];
    elseif (preg_match('/OPR\/([\d.]+)/i', $ua, $m))   $browser = 'Opera '      . $m[1];
    elseif (preg_match('/Chrome\/([\d.]+)/i', $ua, $m)) $browser = 'Chrome '    . explode('.', $m[1])[0];
    elseif (preg_match('/Firefox\/([\d.]+)/i', $ua, $m)) $browser = 'Firefox '  . explode('.', $m[1])[0];
    elseif (preg_match('/Safari\/([\d.]+)/i', $ua, $m)) $browser = 'Safari';
    elseif (preg_match('/MSIE ([\d.]+)/i', $ua, $m))   $browser = 'IE '         . $m[1];

    return ['browser' => $browser, 'os' => $os, 'device' => $device];
}

function sec_get_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_REAL_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

function sec_device_fingerprint(): string {
    $ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip   = sec_get_ip();
    $lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    // Use IP prefix for some locality, but not full IP (changes on mobile)
    $ipPrefix = implode('.', array_slice(explode('.', $ip), 0, 3));
    return hash('sha256', $ua . '|' . $ipPrefix . '|' . $lang);
}

// ── Risk Scoring ────────────────────────────────────────────

function sec_compute_risk(string $ip, string $fingerprint, string $email): string {
    $score = 0;

    // Check blocked IP
    try {
        $blocked = db_select_raw('SELECT id FROM blocked_ips WHERE ip_address = ? AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1', [$ip]);
        if ($blocked) return 'critical';
    } catch (Throwable $e) {}

    // Recent failed attempts from this IP
    $recentFails = db_select_raw(
        'SELECT COUNT(*) as cnt FROM login_attempts WHERE ip_address = ? AND status IN ("failed","blocked","suspicious") AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)',
        [$ip]
    );
    $score += min(40, ($recentFails[0]['cnt'] ?? 0) * 8);

    // Previous suspicious attempts from this fingerprint
    $suspAttempts = db_select_raw(
        'SELECT COUNT(*) as cnt FROM login_attempts WHERE device_fingerprint = ? AND is_suspicious = 1 AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)',
        [$fingerprint]
    );
    $score += min(30, ($suspAttempts[0]['cnt'] ?? 0) * 10);

    // Failed attempts for this email
    $emailFails = db_select_raw(
        'SELECT COUNT(*) as cnt FROM login_attempts WHERE email = ? AND status = "failed" AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)',
        [$email]
    );
    $score += min(30, ($emailFails[0]['cnt'] ?? 0) * 6);

    if ($score >= 70) return 'critical';
    if ($score >= 40) return 'high';
    if ($score >= 15) return 'medium';
    return 'low';
}

// ── Brute-Force Protection ──────────────────────────────────

function sec_is_brute_force(string $ip, string $email): bool {
    $windowStart = date('Y-m-d H:i:s', time() - SEC_BRUTE_WINDOW);
    $rows = db_select_raw(
        'SELECT COUNT(*) as cnt FROM login_attempts WHERE (ip_address = ? OR email = ?) AND created_at > ? AND status IN ("failed","blocked")',
        [$ip, $email, $windowStart]
    );
    return ($rows[0]['cnt'] ?? 0) >= SEC_BRUTE_MAX;
}

// ── Account Lockout ─────────────────────────────────────────

function sec_is_account_locked(int $userId): bool {
    $rows = db_select_raw('SELECT locked_until FROM users WHERE id = ? LIMIT 1', [$userId]);
    if (!$rows) return false;
    $until = $rows[0]['locked_until'] ?? null;
    if (!$until) return false;
    return strtotime($until) > time();
}

function sec_record_failed_login(int $userId): void {
    $pdo = getDB();
    $stmt = $pdo->prepare('UPDATE users SET failed_login_count = failed_login_count + 1, locked_until = IF(failed_login_count + 1 >= ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), locked_until) WHERE id = ?');
    $stmt->execute([SEC_MAX_FAILED_LOGINS, SEC_LOCKOUT_MINUTES, $userId]);
}

function sec_reset_failed_logins(int $userId): void {
    $pdo = getDB();
    $stmt = $pdo->prepare('UPDATE users SET failed_login_count = 0, locked_until = NULL, last_login_at = NOW(), last_login_ip = ? WHERE id = ?');
    $stmt->execute([sec_get_ip(), $userId]);
}

// ── Login Attempt ───────────────────────────────────────────

function sec_create_login_attempt(array $data): ?array {
    return db_insert_raw('login_attempts', $data);
}

function sec_get_attempt(string $token): ?array {
    $rows = db_select_raw('SELECT * FROM login_attempts WHERE challenge_token = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE) LIMIT 1', [$token]);
    return $rows[0] ?? null;
}

function sec_update_attempt(int $id, array $data): void {
    $sets = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
    $vals = array_values($data);
    $vals[] = $id;
    db_select_raw("UPDATE login_attempts SET $sets WHERE id = ?", $vals);
}

// ── Number Matching ─────────────────────────────────────────

function sec_generate_number_challenge(): array {
    $numbers  = [];
    while (count($numbers) < 3) {
        $n = random_int(10, 99);
        if (!in_array($n, $numbers, true)) $numbers[] = $n;
    }
    shuffle($numbers);
    $shown = $numbers[array_rand($numbers)];
    return ['shown' => $shown, 'options' => implode(',', $numbers)];
}

// ── Trusted Devices ─────────────────────────────────────────

function sec_is_trusted_device(int $userId, string $fingerprint): bool {
    $rows = db_select_raw(
        'SELECT id FROM trusted_devices WHERE user_id = ? AND device_fingerprint = ? AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1',
        [$userId, $fingerprint]
    );
    return !empty($rows);
}

function sec_trust_device(int $userId, string $fingerprint, array $info): void {
    $pdo  = getDB();
    $stmt = $pdo->prepare('INSERT INTO trusted_devices (user_id, device_fingerprint, device_name, browser, os, ip_address, expires_at) VALUES (?,?,?,?,?,?,DATE_ADD(NOW(), INTERVAL 30 DAY)) ON DUPLICATE KEY UPDATE last_used_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY)');
    $stmt->execute([$userId, $fingerprint, $info['device'] ?? 'Unknown', $info['browser'] ?? '', $info['os'] ?? '', sec_get_ip()]);
}

// ── Security Events ─────────────────────────────────────────

function sec_log_event(string $type, string $desc, string $severity = 'info', ?int $userId = null, array $meta = []): void {
    db_insert_raw('security_events', [
        'user_id'     => $userId,
        'event_type'  => $type,
        'severity'    => $severity,
        'ip_address'  => sec_get_ip(),
        'description' => $desc,
        'metadata'    => $meta ? json_encode($meta) : null,
    ]);
}

// ── Session Management ──────────────────────────────────────

function sec_create_session(int $userId, string $fingerprint, array $deviceInfo): string {
    $token = bin2hex(random_bytes(32));
    $ua    = $_SERVER['HTTP_USER_AGENT'] ?? '';
    db_insert_raw('user_sessions', [
        'user_id'            => $userId,
        'session_token'      => $token,
        'ip_address'         => sec_get_ip(),
        'device_fingerprint' => $fingerprint,
        'user_agent'         => substr($ua, 0, 500),
        'browser'            => $deviceInfo['browser'] ?? '',
        'os'                 => $deviceInfo['os'] ?? '',
        'expires_at'         => date('Y-m-d H:i:s', time() + SEC_SESSION_TTL),
    ]);
    return $token;
}

function sec_invalidate_user_sessions(int $userId): void {
    db_select_raw('UPDATE user_sessions SET is_active = 0 WHERE user_id = ?', [$userId]);
}

// ── Raw DB Helpers (bypass PostgREST-style layer for security tables) ───────

function db_select_raw(string $sql, array $params = []): array {
    try {
        $stmt = getDB()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('db_select_raw: ' . $e->getMessage());
        return [];
    }
}

function db_insert_raw(string $table, array $data): ?array {
    try {
        $cols  = implode(',', array_map(fn($c) => "`$c`", array_keys($data)));
        $ph    = implode(',', array_fill(0, count($data), '?'));
        $pdo   = getDB();
        $stmt  = $pdo->prepare("INSERT INTO `$table` ($cols) VALUES ($ph)");
        $vals  = array_map(fn($v) => is_bool($v) ? (int)$v : $v, array_values($data));
        $stmt->execute($vals);
        $id    = (int)$pdo->lastInsertId();
        if ($id > 0) {
            $r = db_select_raw("SELECT * FROM `$table` WHERE id = ? LIMIT 1", [$id]);
            return $r[0] ?? null;
        }
        return $data;
    } catch (Throwable $e) {
        error_log("db_insert_raw($table): " . $e->getMessage());
        return null;
    }
}

