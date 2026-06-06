<?php
/**
 * Shows the redirect URI your app sends to Google.
 * Visit after upload: /auth/oauth-setup.php
 * Remove or protect this file when OAuth works.
 */
require_once dirname(__DIR__) . '/includes/paths.php';
require_once dirname(__DIR__) . '/includes/google-oauth.php';

header('Content-Type: text/html; charset=UTF-8');
$redirectUri = googleRedirectUri();
$authSample  = googleAuthUrl('test-state-only');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Google OAuth setup</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 720px; margin: 40px auto; padding: 0 16px; line-height: 1.5; }
        code, pre { background: #f4f4f4; padding: 2px 6px; border-radius: 4px; word-break: break-all; }
        pre { padding: 12px; overflow-x: auto; }
        .uri { font-size: 15px; font-weight: 600; color: #0b57d0; }
    </style>
</head>
<body>
    <h1>Google OAuth redirect URI</h1>
    <p>Copy this <strong>exact</strong> value into Google Cloud Console → Credentials → your OAuth client → <em>Authorized redirect URIs</em>:</p>
    <p class="uri"><code><?= htmlspecialchars($redirectUri) ?></code></p>

    <h2>Auto-detected (for comparison)</h2>
    <ul>
        <li>Host: <code><?= htmlspecialchars(request_host()) ?></code></li>
        <li>Scheme: <code><?= htmlspecialchars(request_scheme()) ?></code></li>
        <li>App base path: <code><?= htmlspecialchars(app_base() ?: '/') ?></code></li>
        <li>Full callback: <code><?= htmlspecialchars(absolute_url('auth/google-callback.php')) ?></code></li>
    </ul>

    <h2>Steps in Google Cloud</h2>
    <ol>
        <li>Open <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">APIs &amp; Credentials</a>.</li>
        <li>Edit your <strong>Web application</strong> OAuth 2.0 Client ID.</li>
        <li>Under <strong>Authorized redirect URIs</strong>, add the URI in the blue box above (one line, no spaces).</li>
        <li>Save. Wait 1–5 minutes, then try Sign in with Google again.</li>
    </ol>

    <p><small>Override only if needed: env <code>GOOGLE_REDIRECT_URI</code> in <code>.env</code>.</small></p>
</body>
</html>
