<?php
require_once 'includes/auth.php';
require_once 'includes/users.php';

// Change these before running
$fullName = 'System Administrator';
$email    = 'anthony.domasig@evsu.edu.ph';
$password = 'YourPassword123!';

// Safety: only run once
if (emailExists($email)) {
    die('Account already exists for that email.');
}

$userId = createUser($fullName, $email, password_hash($password, PASSWORD_DEFAULT));
if (!$userId) {
    die('Failed to create user.');
}

updateUserFields($userId, ['role' => 'admin']);
echo "Done! Admin created with user ID: $userId";