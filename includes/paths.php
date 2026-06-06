<?php

function app_base(): string {
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $appRoot = realpath(dirname(__DIR__));

    if (!$docRoot || !$appRoot) {
        $base = '';
        return $base;
    }

    $docRoot = str_replace('\\', '/', $docRoot);
    $appRoot = str_replace('\\', '/', $appRoot);

    if (str_starts_with($appRoot, $docRoot)) {
        $base = rtrim(substr($appRoot, strlen($docRoot)), '/');
    } else {
        $base = '';
    }

    return $base;
}

function url(string $path = ''): string {
    $path = ltrim(str_replace('\\', '/', $path), '/');
    $base = app_base();

    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }

    return ($base === '' ? '' : $base) . '/' . $path;
}

/**
 * Current request scheme (https on production / reverse proxies).
 */
function request_scheme(): string {
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = strtolower(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]);
        return $proto === 'https' ? 'https' : 'http';
    }
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return 'https';
    }
    if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        return 'https';
    }
    // InfinityFree and similar hosts terminate TLS at the edge but may not set HTTPS=on
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (preg_match('/\.(infinityfree\.me|infinityfreeapp\.com|rf\.gd|42web\.io)$/i', $host)) {
        return 'https';
    }
    return 'http';
}

/**
 * Current request host (honours X-Forwarded-Host on InfinityFree, etc.).
 */
function request_host(): string {
    if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
        return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_HOST'])[0]);
    }
    if (!empty($_SERVER['HTTP_HOST'])) {
        return $_SERVER['HTTP_HOST'];
    }

    // Fall back to an explicit APP_URL or GOOGLE_REDIRECT_URI set in the environment
    $envHost = '';
    $appUrl = getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? false);
    if ($appUrl) {
        $envHost = parse_url($appUrl, PHP_URL_HOST) ?: '';
    }
    if ($envHost === '') {
        $gRedirect = getenv('GOOGLE_REDIRECT_URI') ?: ($_ENV['GOOGLE_REDIRECT_URI'] ?? false);
        if ($gRedirect) {
            $envHost = parse_url($gRedirect, PHP_URL_HOST) ?: '';
        }
    }

    return $envHost !== '' ? $envHost : 'sql103.infinityfree.com';
}

/**
 * Site origin, e.g. https://yoursite.infinityfreeapp.com
 * Override with APP_URL env for fixed production URL if auto-detection fails.
 */
function oauthConfig(): array {
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $file = __DIR__ . '/oauth-config.php';
    if (is_readable($file)) {
        $loaded = require $file;
        $cfg = is_array($loaded) ? $loaded : [];
    } else {
        $cfg = [];
    }
    return $cfg;
}

function app_origin(): string {
    static $origin = null;
    if ($origin !== null) {
        return $origin;
    }

    $env = getenv('APP_URL');
    if ($env !== false && $env !== '') {
        $origin = rtrim($env, '/');
        return $origin;
    }

    // Prefer the host the user actually opened (sql103.infinityfree.com vs InfinityFree, etc.)
    if (!empty($_SERVER['HTTP_HOST'])) {
        $origin = request_scheme() . '://' . request_host();
        return $origin;
    }

    $fromFile = oauthConfig()['app_url'] ?? '';
    if ($fromFile !== '') {
        $origin = rtrim($fromFile, '/');
        return $origin;
    }
    // Final fallback: try to construct origin from env variables, then default host.
    $envUrl = getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? false);
    if ($envUrl) {
        $origin = rtrim($envUrl, '/');
        return $origin;
    }

    $gRedirect = getenv('GOOGLE_REDIRECT_URI') ?: ($_ENV['GOOGLE_REDIRECT_URI'] ?? false);
    if ($gRedirect) {
        $parts = parse_url($gRedirect);
        if (!empty($parts['scheme']) && !empty($parts['host'])) {
            $origin = $parts['scheme'] . '://' . $parts['host'];
            if (!empty($parts['port'])) {
                $origin .= ':' . $parts['port'];
            }
            return $origin;
        }
    }

    $origin = 'http://sql103.infinityfree.com';
    return $origin;
}

/**
 * Full URL for redirects, OAuth callbacks, and external links.
 */
function absolute_url(string $path = ''): string {
    $relative = url($path);
    if ($relative === '' || $relative[0] !== '/') {
        $relative = '/' . ltrim($relative, '/');
    }
    return app_origin() . $relative;
}
