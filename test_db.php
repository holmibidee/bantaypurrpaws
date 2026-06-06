<?php
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/html; charset=utf-8');
echo '<h3>Testing MySQL connection...</h3>';

try {
    $pdo    = getDB();
    $users  = db_select('users', 'limit=5');
    $count  = count($users);
    echo '<p style="color:green;">Connected. Found ' . (int) $count . ' user row(s) (max 5 shown).</p>';
    if ($count > 0) {
        echo '<pre>' . htmlspecialchars(json_encode($users, JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8') . '</pre>';
    }
} catch (Throwable $e) {
    echo '<p style="color:red;">Connection failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p>Check .env (copy from .env.example) and import <code>sql/schema.sql</code>.</p>';
}
