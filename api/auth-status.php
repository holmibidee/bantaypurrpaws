<?php
/**
 * Poll endpoint — frontend polls this to check challenge status
 * GET ?token=xxx
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/security.php';

$token = $_GET['token'] ?? '';
if (!$token) { echo json_encode(['status' => 'invalid']); exit; }

$attempt = sec_get_attempt($token);
if (!$attempt) { echo json_encode(['status' => 'expired']); exit; }

echo json_encode(['status' => $attempt['status']]);
