<?php
require_once __DIR__ . '/includes/paths.php';

// If Google Console redirect URI points at the site root, forward to the real callback.
if (!empty($_GET['code']) && isset($_GET['state'])) {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . absolute_url('auth/google-callback.php') . ($qs !== '' ? '?' . $qs : ''));
    exit;
}

require_once __DIR__ . '/includes/auth.php';
startSession();

if (isLoggedIn()) {
    header('Location: ' . dashboardHomeUrl());
    exit;
}

header('Location: ' . url('login.php'));
exit;