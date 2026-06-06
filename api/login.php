<?php
/**
 * BantayPurrPaws — Login API
 *
 * FIX #4: This endpoint is called by two different clients:
 *   1. The Flutter app  — expects a JSON response, uses SharedPreferences,
 *                         does NOT use PHP sessions.
 *   2. The web browser  — uses PHP sessions for page-to-page auth.
 *
 * Previously, loginUser() always wrote to $_SESSION regardless of caller.
 * For the Flutter app this is harmless but wasteful, and it created a
 * confusing dual-auth system. Now:
 *   - If the request sends `Accept: application/json` (Flutter), we return
 *     JSON only and skip session setup.
 *   - If the request comes from a browser form, we set the session as before.
 *
 * JSON clients (e.g. mobile apps) receive user data without PHP sessions.
 */

// CORS headers must be FIRST before anything else
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$email    = $_POST['email']    ?? '';
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'Email and password required']);
    exit;
}

// Detect Flutter / JSON-only client
$acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
$isJsonClient = str_contains($acceptHeader, 'application/json')
             && !str_contains($acceptHeader, 'text/html');

if ($isJsonClient) {
    // FIX #4: JSON client (Flutter) — authenticate but do NOT touch $_SESSION.
    // Flutter stores the returned user object in SharedPreferences itself.
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

// Browser client — use full loginUser() which sets the session
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
