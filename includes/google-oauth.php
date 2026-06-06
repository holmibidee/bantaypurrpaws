<?php
/**
 * BantayPurrPaws — Google OAuth 2.0 Helper
 *
 * No Composer required. Uses PHP's file_get_contents / curl for HTTP.
 *
 * Setup (Google Cloud Console):
 *   1. Create an OAuth 2.0 Client ID (Web application).
 *   2. Add Authorized redirect URIs for each environment you use, e.g.:
 *        https://your-production-domain.com/auth/google-callback.php
 *        http://localhost:8000/auth/google-callback.php
 *      Visit /auth/oauth-setup.php on each host to copy the exact URI Google expects.
 *   3. Set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI in .env
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/sensitive-data.php';
require_once __DIR__ . '/users.php';

/** Set APP_DEBUG=1 in hosting env to surface PHP errors on OAuth pages. */
function googleOAuthDebugErrors(): void {
    if (getenv('APP_DEBUG') === '1' || getenv('APP_DEBUG') === 'true') {
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);
    }
}

// ── Configuration (override via env: GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI)
define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID') ?: '');
define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? getenv('GOOGLE_CLIENT_SECRET') ?: '');

/**
 * OAuth redirect URI for the current request (must match Google Cloud Console exactly).
 * Override with GOOGLE_REDIRECT_URI in .env only if auto-detection is wrong.
 */
function googleRedirectUri(): string {
    static $uri = null;
    if ($uri !== null) {
        return $uri;
    }

    $env = getenv('GOOGLE_REDIRECT_URI');
    if ($env !== false && $env !== '') {
        $uri = rtrim($env, '/');
        return $uri;
    }

    $uri = absolute_url('auth/google-callback.php');
    return $uri;
}

define('GOOGLE_AUTH_URL',  'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_INFO_URL',  'https://www.googleapis.com/oauth2/v3/userinfo');

/**
 * Build the Google sign-in URL the user should be redirected to.
 */
function googleAuthUrl(string $state = ''): string {
    $params = [
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => googleRedirectUri(),
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'access_type'   => 'online',
        'prompt'        => 'select_account',
    ];
    if ($state !== '') {
        $params['state'] = $state;
    }
    return GOOGLE_AUTH_URL . '?' . http_build_query($params);
}

/**
 * Exchange an auth code for tokens.
 * Returns the token array or null on failure.
 */
function googleHttpPost(string $url, string $body, array $headers = []): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response !== false ? $response : null;
    }

    $headerLines = implode("\r\n", $headers);
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => $headerLines,
            'content' => $body,
            'timeout' => 15,
        ],
    ]);
    $response = @file_get_contents($url, false, $ctx);
    return $response !== false ? $response : null;
}

function googleHttpGet(string $url, array $headers = []): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response !== false ? $response : null;
    }

    $headerLines = implode("\r\n", $headers);
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'header'  => $headerLines,
            'timeout' => 15,
        ],
    ]);
    $response = @file_get_contents($url, false, $ctx);
    return $response !== false ? $response : null;
}

function googleExchangeCode(string $code): ?array {
    $postData = http_build_query([
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => googleRedirectUri(),
        'grant_type'    => 'authorization_code',
    ]);

    $response = googleHttpPost(
        GOOGLE_TOKEN_URL,
        $postData,
        ['Content-Type: application/x-www-form-urlencoded']
    );

    $data = json_decode($response ?: '', true);
    if (empty($data['access_token'])) {
        error_log('Google token exchange failed: ' . ($response ?: 'empty response'));
        return null;
    }
    return $data;
}

/**
 * Fetch the Google user-info using an access token.
 * Returns ['sub', 'email', 'name', 'picture'] or null.
 */
function googleUserInfo(string $accessToken): ?array {
    $response = googleHttpGet(GOOGLE_INFO_URL, ['Authorization: Bearer ' . $accessToken]);
    $data = json_decode($response ?: '', true);
    return (!empty($data['sub'])) ? $data : null;
}

/**
 * Find a user by Google subject id.
 */
function findUserByGoogleId(string $googleId): ?array {
    $user = db_select('users', 'google_id=eq.' . urlencode($googleId), true);
    return $user ? hydrateUserSensitiveFields($user) : null;
}

/**
 * Main entry point called from the OAuth callback.
 *
 * Given a Google user-info array this function:
 *   a) If a local user with the same google_id exists → log them in.
 *   b) If a local user with the same email exists but no google_id → link and log in.
 *   c) If no user exists → create a new 'user' account and log in.
 *
 * Returns ['success' => true, 'user' => [...]] or ['success' => false, 'error' => '...'].
 */
function handleGoogleLogin(array $googleUser): array {
    $googleId  = $googleUser['sub']     ?? '';
    $email     = $googleUser['email']   ?? '';
    $name      = $googleUser['name']    ?? 'Google User';
    $avatar    = $googleUser['picture'] ?? null;

    if (!$googleId || !$email) {
        return ['success' => false, 'error' => 'Invalid Google account data.'];
    }

    $user = findUserByGoogleId($googleId);

    if (!$user) {
        $user = findUserByEmail($email);

        if ($user) {
            db_update('users', [
                'google_id'       => $googleId,
                'avatar_url'      => $avatar,
                'email_verified'  => true,
                'auth_provider'   => 'google',
            ], 'id=eq.' . (int) $user['id']);

            $user['google_id']      = $googleId;
            $user['avatar_url']     = $avatar;
            $user['email_verified'] = true;
            $user['auth_provider']  = 'google';

            createSystemNotification(
                'system',
                'Your Google account was linked to your BantayPurrPaws profile.',
                null,
                null,
                (int) $user['id']
            );
        } else {
            $user = insertUserRecord([
                'full_name'      => $name,
                'email'          => $email,
                'password'       => null,
                'role'           => 'user',
                'google_id'      => $googleId,
                'avatar_url'     => $avatar,
                'email_verified' => true,
                'auth_provider'  => 'google',
            ]);

            if (!$user) {
                return ['success' => false, 'error' => 'Could not create your account. Please try again.'];
            }

            createSystemNotification(
                'system',
                'Welcome to BantayPurrPaws! Your account has been created via Google.',
                null,
                null,
                (int) $user['id']
            );
        }
    }

    if ($avatar && ($user['avatar_url'] ?? '') !== $avatar) {
        db_update('users', ['avatar_url' => $avatar], 'id=eq.' . (int) $user['id']);
        $user['avatar_url'] = $avatar;
    }

    return ['success' => true, 'user' => $user];
}

/**
 * Start session and notify after Google auth is complete.
 */
function finalizeGoogleSession(array $user, string $message = 'You signed in with Google.'): void {
    require_once __DIR__ . '/users.php';
    refreshUserSession($user);

    createSystemNotification(
        'system',
        $message,
        null,
        null,
        (int) $user['id']
    );
}

/**
 * Dashboard URL for a user role after Google sign-in.
 */
function googlePostLoginUrl(array $user): string {
    return in_array($user['role'] ?? 'user', ['admin', 'staff'], true)
        ? absolute_url('admin/dashboard.php')
        : absolute_url('dashboard.php');
}
