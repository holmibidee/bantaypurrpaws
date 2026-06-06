<?php
/**
 * Optional site hints (copy to oauth-config.php on the server).
 *
 * OAuth redirect URIs are detected from the current host automatically.
 * Open /auth/oauth-setup.php on each environment and add that URI in
 * Google Cloud → Credentials → OAuth client → Authorized redirect URIs.
 */
return [
    // Optional canonical URL for CLI; web requests use the real HTTP_HOST instead.
    // Set APP_URL in .env for production deployments.
    'app_url' => 'https://yourdomain.example.com',
];
