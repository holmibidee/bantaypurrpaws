<?php
require_once __DIR__ . '/../includes/otp.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
startSession();

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$email  = trim($_POST['email'] ?? $_GET['email'] ?? '');

if (!$action || !$email) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

// Normalize email
$email = filter_var($email, FILTER_SANITIZE_EMAIL);

// Allow callers to specify purpose; default to 'login' since this endpoint
// is primarily used by the login flow. Registration OTPs should pass
// purpose=registration explicitly.
$purpose = $_POST['purpose'] ?? $_GET['purpose'] ?? 'login';
// Whitelist valid purposes to prevent abuse
if (!in_array($purpose, ['login', 'registration', 'password_reset'], true)) {
    $purpose = 'login';
}

if ($action === 'issue') {
    // Ensure user exists so we can address the email
    $user = findUserByEmail($email);
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'No account found for that email.']);
        exit;
    }

    $name   = $user['full_name'] ?? '';
    $result = issueAndSendOtp($email, $name, $purpose);
    if ($result === true) {
        echo json_encode(['success' => true, 'message' => 'OTP sent']);
    } else {
        echo json_encode(['success' => false, 'message' => (string) $result]);
    }
    exit;
}

if ($action === 'verify') {
    $code = trim($_POST['code'] ?? $_GET['code'] ?? '');
    if (empty($code)) {
        echo json_encode(['success' => false, 'message' => 'OTP code required']);
        exit;
    }

    $status = verifyOtp($email, $code, $purpose);
    if ($status === 'valid') {
        echo json_encode(['success' => true, 'message' => 'valid']);
    } elseif ($status === 'expired') {
        echo json_encode(['success' => false, 'message' => 'expired']);
    } else {
        echo json_encode(['success' => false, 'message' => 'invalid']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
