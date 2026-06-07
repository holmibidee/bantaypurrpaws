<?php
/**
 * BantayPurrPaws — Login API
 *
 * Clients:
 *   1. Flutter app      — Accept: application/json  → returns JSON, no session
 *   2. Web browser      — action=login              → sets PHP session + returns JSON
 *   3. Web MFA step 1   — action=credentials_only   → verify password only, NO session
 *                          (OTP is sent separately; session is created after OTP passes)
 */

// CORS headers must be FIRST
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$email    = trim($_POST['email']    ?? '');
$password = $_POST['password'] ?? '';
$action   = $_POST['action']   ?? 'login';

if (!$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'Email and password required']);
    exit;
}

// ── credentials_only: verify password, do NOT start a session ────────────────
// Used by the web login page step 1 (before OTP is verified).
if ($action === 'credentials_only') {
    $user = findUserByEmail($email);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password. Please try again.']);
        exit;
    }
    if (empty($user['password']) && ($user['auth_provider'] ?? '') === 'google') {
        echo json_encode(['success' => false, 'message' => 'This account uses Google Sign-In. Please click "Sign in with Google".']);
        exit;
    }
    if (!password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password. Please try again.']);
        exit;
    }

    echo json_encode(['success' => true]);
    exit;
}

// ── Detect Flutter / JSON-only client ────────────────────────────────────────
$acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
$isJsonClient = str_contains($acceptHeader, 'application/json')
             && !str_contains($acceptHeader, 'text/html');

if ($isJsonClient) {
    // Flutter: verify credentials, return user data, skip PHP session
    $user = findUserByEmail($email);
    if (!$user || empty($user['password']) || !password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }
    echo json_encode([
        'success' => true,
        'user'    => [
            'id'        => $user['id'],
            'full_name' => $user['full_name'],
            'email'     => $user['email'],
            'role'      => $user['role'],
        ],
    ]);
    exit;
}

// ── Browser: full login — verify credentials AND set session ─────────────────
$result = loginUser($email, $password);

if (is_array($result)) {
    echo json_encode([
        'success' => true,
        'user'    => [
            'id'        => $result['id'],
            'full_name' => $result['full_name'],
            'email'     => $result['email'],
            'role'      => $result['role'],
        ],
    ]);
} else {
    echo json_encode(['success' => false, 'message' => $result]);
}