<?php
/**
 * Optional production hints (CLI / emails). OAuth redirect URIs are built
 * automatically from the current host — see auth/oauth-setup.php to verify.
 *
 * Set APP_URL in .env to force a canonical site URL when needed.
 */
// Return canonical app URL for CLI or when auto-detection is undesirable.
// Prefer explicit `APP_URL` in the environment, fall back to `GOOGLE_REDIRECT_URI`,
// then to the historical hard-coded host.
$appUrl = getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? false);
if ($appUrl && $appUrl !== '') {
    return ['app_url' => rtrim($appUrl, '/')];
}

$g = getenv('GOOGLE_REDIRECT_URI') ?: ($_ENV['GOOGLE_REDIRECT_URI'] ?? false);
if ($g && $g !== '') {
    $u = rtrim($g, '/');
    $parts = parse_url($u);
    if (!empty($parts['scheme']) && !empty($parts['host'])) {
        $base = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $base .= ':' . $parts['port'];
        }
        return ['app_url' => $base];
    }
}

return [
    'app_url' => 'https://bantaypurrpaws.infinityfreeapp.com',
];
