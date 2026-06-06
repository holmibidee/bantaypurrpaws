<?php
/**
 * Structured application logging for notifications, announcements, and email.
 */

define('BPP_LOG_DIR', dirname(__DIR__) . '/logs');

function bpp_ensure_log_dir(): void {
    if (!is_dir(BPP_LOG_DIR)) {
        mkdir(BPP_LOG_DIR, 0755, true);
    }
    $htaccess = BPP_LOG_DIR . '/.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, "Deny from all\n");
    }
}

/**
 * @param array<string, mixed> $context
 */
function bpp_log(string $channel, string $level, string $message, array $context = []): void {
    bpp_ensure_log_dir();

    $entry = [
        'time'    => date('c'),
        'channel' => $channel,
        'level'   => strtoupper($level),
        'message' => $message,
    ];

    if ($context !== []) {
        $entry['context'] = $context;
    }

    $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($line === false) {
        $line = date('c') . " [{$channel}] {$level}: {$message}";
    }

    error_log("[BPP:{$channel}] {$level}: {$message}" . ($context ? ' ' . json_encode($context) : ''));
    file_put_contents(BPP_LOG_DIR . '/app.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}
