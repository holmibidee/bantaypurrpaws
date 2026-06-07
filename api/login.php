<?php
/**
 * BantayPurrPaws — Enterprise Login API
 *
 * Step 1: POST {email, password}
 *   → Validates credentials, creates login_attempt, sends challenge email
 *   → Returns {success, step:'challenge', token}
 *
 * Step 2: Email link clicked (approve/deny) — handled by auth/login-challenge.php
 *
 * Step 3: POST {action:'number_match', token, selected}
 *   → Validates number match
 *   → Returns {success, step:'otp'}
 *
 * Step 4: POST {action:'verify_otp', token, code}
 *   → Validates OTP, creates session
 *   → Returns {success, step:'complete', redirect}
 *
 * Legacy JSON client (Flutter) still supported via Accept: application/json
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/mailer_security.php';
require_once __DIR__ . '/../includes/otp.php';
require_once __DIR__ . '/../includes/users.php';
require_once __DIR__ . '/../includes/notifications.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
}

$input    = $_POST ?: (array)json_decode(file_get_contents('php://input'), true);
$action   = $input['action'] ?? 'login';
$ip       = sec_get_ip();
$ua       = $_SERVER['HTTP_USER_AGENT'] ?? '';
$fpData   = sec_parse_user_agent($ua);
$fp       = sec_device_fingerprint();

// ── Legacy Flutter JSON client ──────────────────────────────
$accept   = $_SERVER['HTTP_ACCEPT'] ?? '';
$isFlutter = str_contains($accept, 'application/json') && !str_contains($accept, 'text/html');
if ($isFlutter && $action === 'login') {
    $email    = $input['email']    ?? '';
    $password = $input['password'] ?? '';
    $user     = findUserByEmail($email);
    if (!$user || empty($user['password']) || !password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']); exit;
    }
    echo json_encode(['success' => true, 'user' => ['id' => $user['id'], 'full_name' => $user['full_name'], 'email' => $user['email'], 'role' => $user['role']]]); exit;
}

// ── Step 1: Credentials → Challenge ────────────────────────
if ($action === 'login') {
    $email    = trim($input['email']    ?? '');
    $password = $input['password'] ?? '';

    if (!$email || !$password) {
        echo json_encode(['success' => false, 'message' => 'Email and password are required.']); exit;
    }

    // Brute-force check
    if (sec_is_brute_force($ip, $email)) {
        sec_log_event('brute_force_blocked', "Brute-force blocked for $email from $ip", 'critical');
        echo json_encode(['success' => false, 'message' => 'Too many login attempts. Please wait 5 minutes.']); exit;
    }

    // Find user
    $user = findUserByEmail($email);

    // Always compute a risk level (even for unknown emails — prevents timing attack)
    $riskLevel = sec_compute_risk($ip, $fp, $email);

    if (!$user || empty($user['password'])) {
        // Record failed attempt
        sec_create_login_attempt([
            'email' => $email, 'ip_address' => $ip, 'user_agent' => substr($ua, 0, 500),
            'device_type' => $fpData['device'], 'browser' => $fpData['browser'], 'os' => $fpData['os'],
            'device_fingerprint' => $fp, 'risk_level' => $riskLevel, 'status' => 'failed', 'is_suspicious' => 1,
        ]);
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']); exit;
    }

    // Google-only accounts
    if (($user['auth_provider'] ?? '') === 'google' && empty($user['password'])) {
        echo json_encode(['success' => false, 'message' => 'This account uses Google Sign-In.']); exit;
    }

    // Account locked?
    if (sec_is_account_locked((int)$user['id'])) {
        echo json_encode(['success' => false, 'message' => 'Account temporarily locked. Please try again later.']); exit;
    }

    // Verify password
    if (!password_verify($password, $user['password'])) {
        sec_record_failed_login((int)$user['id']);
        sec_create_login_attempt([
            'user_id' => $user['id'], 'email' => $email, 'ip_address' => $ip,
            'user_agent' => substr($ua, 0, 500), 'device_type' => $fpData['device'],
            'browser' => $fpData['browser'], 'os' => $fpData['os'],
            'device_fingerprint' => $fp, 'risk_level' => $riskLevel, 'status' => 'failed', 'is_suspicious' => 0,
        ]);
        sec_log_event('login_failed', "Password mismatch for $email", 'warning', (int)$user['id']);
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']); exit;
    }

    // Credentials valid — create attempt record
    $token = bin2hex(random_bytes(24));
    $attempt = sec_create_login_attempt([
        'user_id' => $user['id'], 'email' => $email, 'ip_address' => $ip,
        'user_agent' => substr($ua, 0, 500), 'device_type' => $fpData['device'],
        'browser' => $fpData['browser'], 'os' => $fpData['os'],
        'device_fingerprint' => $fp, 'risk_level' => $riskLevel,
        'status' => 'pending', 'challenge_token' => $token, 'is_suspicious' => 0,
    ]);

    // Send challenge email
    $sent = sendLoginChallengeEmail($user['email'], $user['full_name'], array_merge($fpData, ['ip_address' => $ip]), $token);
    if (!$sent) {
        echo json_encode(['success' => false, 'message' => 'Failed to send verification email. Please try again.']); exit;
    }

    sec_log_event('login_challenge_sent', "Challenge email sent for $email", 'info', (int)$user['id']);
    echo json_encode(['success' => true, 'step' => 'challenge', 'token' => $token, 'message' => 'Check your email to verify this sign-in attempt.']);
    exit;
}

// ── Step 3: Number Match ────────────────────────────────────
if ($action === 'number_match') {
    $token    = $input['token']    ?? '';
    $selected = (int)($input['selected'] ?? 0);

    $attempt = sec_get_attempt($token);
    if (!$attempt || $attempt['status'] !== 'approved') {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired session. Please start over.']); exit;
    }

    // Validate selected number
    $shown   = (int)$attempt['number_shown'];
    $matched = $selected === $shown;

    sec_update_attempt((int)$attempt['id'], ['number_matched' => $matched ? 1 : 0, 'status' => $matched ? 'verified' : 'failed']);

    if (!$matched) {
        sec_log_event('number_match_failed', 'Number match failed', 'warning', (int)$attempt['user_id']);
        echo json_encode(['success' => false, 'message' => 'Incorrect number selected. Please try again.']); exit;
    }

    // Issue OTP
    $user   = findUserByEmail($attempt['email']);
    $result = issueAndSendOtp($attempt['email'], $user['full_name'] ?? '', 'login');
    if ($result !== true) {
        echo json_encode(['success' => false, 'message' => is_string($result) ? $result : 'Failed to send OTP.']); exit;
    }

    sec_log_event('number_match_success', 'Number match passed, OTP sent', 'info', (int)$attempt['user_id']);
    echo json_encode(['success' => true, 'step' => 'otp', 'token' => $token]);
    exit;
}

// ── Step 4: OTP Verification → Complete ────────────────────
if ($action === 'verify_otp') {
    $token = $input['token'] ?? '';
    $code  = trim($input['code']  ?? '');

    $attempt = sec_get_attempt($token);
    if (!$attempt || !in_array($attempt['status'], ['verified', 'otp_sent'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid session. Please start over.']); exit;
    }

    // Temporary debug — remove after fixing
    $debug = db_select_raw(
        'SELECT otp_code, purpose, used, expires_at FROM otp_tokens WHERE email = ? ORDER BY created_at DESC LIMIT 3',
        [$attempt['email']]
    );
    error_log('OTP DEBUG: ' . json_encode($debug));
    // TESTING

    $otpResult = verifyOtp($attempt['email'], $code, 'login');
    if ($otpResult === 'expired') {
        echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.']); exit;
    }
    if ($otpResult !== 'valid') {
        sec_log_event('otp_failed', 'OTP verification failed', 'warning', (int)$attempt['user_id']);
        echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']); exit;
    }

    // Mark attempt complete
    sec_update_attempt((int)$attempt['id'], ['status' => 'verified']);

    // Log in the user via PHP session
    $user = findUserByEmail($attempt['email']);
    if (!$user) { echo json_encode(['success' => false, 'message' => 'User not found.']); exit; }

    // Reset failed logins
    sec_reset_failed_logins((int)$user['id']);

    // Trust device (30 days)
    sec_trust_device((int)$user['id'], $fp, $fpData);

    // Set up session
    refreshUserSession($user);
    createSystemNotification('system', 'You logged in successfully.', null, null, (int)$user['id']);
    sec_log_event('login_success', 'Successful login via enterprise auth', 'info', (int)$user['id']);

    $redirect = in_array($user['role'], ['admin', 'staff']) ? url('admin/dashboard.php') : url('dashboard.php');
    echo json_encode(['success' => true, 'step' => 'complete', 'redirect' => $redirect]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
