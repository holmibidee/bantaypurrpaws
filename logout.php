<?php
require_once __DIR__ . '/includes/auth.php';
startSession();
session_destroy();
header('Location: ' . url('index.php'));
exit;
