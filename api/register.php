<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$fullName = sanitize(trim($_POST['full_name'] ?? ''));
$email    = sanitize(trim($_POST['email']     ?? ''));
$password = $_POST['password'] ?? '';

if (!$fullName || !$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
    exit;
}

if (emailExists($email)) {
    echo json_encode(['success' => false, 'message' => 'An account with that email already exists.']);
    exit;
}

$userId = createUser($fullName, $email, password_hash($password, PASSWORD_DEFAULT));

if ($userId) {
    echo json_encode(['success' => true, 'message' => 'Account created successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not create account. Please try again.']);
}
