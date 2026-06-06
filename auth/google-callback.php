<?php
/**
 * auth/google-callback.php
 * Google OAuth 2.0 callback — exchange code, then OTP (new/link) or sign in (returning).
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/google-oauth.php';
require_once dirname(__DIR__) . '/includes/otp.php';

googleOAuthDebugErrors();
startSession();

if (isLoggedIn()) {
    header('Location: ' . url(isAdmin() ? 'admin/dashboard.php' : 'dashboard.php'));
    exit;
}

$error = '';

do {
    $state         = $_GET['state'] ?? '';
    $expectedState = $_SESSION['oauth_state'] ?? '';
    unset($_SESSION['oauth_state']);

    if ($state !== $expectedState || $expectedState === '') {
        $error = 'Invalid OAuth state. Please try again.';
        break;
    }

    if (!empty($_GET['error'])) {
        $error = 'Google sign-in was cancelled or denied.';
        break;
    }

    $code = $_GET['code'] ?? '';
    if (!$code) {
        $error = 'No authorization code received from Google.';
        break;
    }

    $tokens = googleExchangeCode($code);
    if (!$tokens) {
        $error = 'Failed to exchange authorization code. Check that the redirect URI in Google Cloud Console matches: ' . googleRedirectUri();
        break;
    }

    $googleUser = googleUserInfo($tokens['access_token']);
    if (!$googleUser) {
        $error = 'Failed to retrieve Google account information.';
        break;
    }

    $googleId = $googleUser['sub']   ?? '';
    $email    = $googleUser['email'] ?? '';
    $name     = $googleUser['name']  ?? 'Google User';

    if (!$googleId || !$email) {
        $error = 'Invalid Google account data.';
        break;
    }

    // Returning user with this Google account — no OTP needed
    $existing = findUserByGoogleId($googleId);

    if ($existing) {
        finalizeGoogleSession($existing);
        header('Location: ' . googlePostLoginUrl($existing));
        exit;
    }

    // New account or link to existing email — verify via OTP first
    $_SESSION['google_pending'] = [
        'sub'     => $googleId,
        'email'   => $email,
        'name'    => $name,
        'picture' => $googleUser['picture'] ?? null,
    ];

    $localUser = findUserByEmail($email);

    $purpose = $localUser ? 'google_link' : 'registration';
    $otpName = $localUser ? $localUser['full_name'] : $name;

    $result = issueAndSendOtp($email, $otpName, $purpose);
    if ($result !== true) {
        unset($_SESSION['google_pending']);
        $error = is_string($result) ? $result : 'Could not send verification code. Please try again.';
        break;
    }

    $_SESSION['google_otp_purpose'] = $purpose;
    header('Location: ' . url('auth/google-verify.php'));
    exit;

} while (false);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Google Sign-In — BantayPurrPaws</title>
    <link rel="stylesheet" href="<?= url('css/style.css') ?>">
</head>
<body>
<div class="auth-page">
    <div class="auth-panel fade-in">
        <div class="auth-logo">
            <img src="<?= url('assets/logo.png') ?>" alt="Bantay PurrPaws" class="auth-logo-img">
        </div>
        <div class="alert alert-error">✕ <?= sanitize($error) ?></div>
        <div class="auth-footer">
            <a href="<?= url('login.php') ?>">← Back to Login</a>
        </div>
    </div>
</div>
</body>
</html>
