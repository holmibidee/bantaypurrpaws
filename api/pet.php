<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/adoption.php';

header('Content-Type: application/json');

requireLogin();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid pet ID.']);
    exit;
}

$pet = getPetById($id);

if (!$pet) {
    http_response_code(404);
    echo json_encode(['error' => 'Pet not found.']);
    exit;
}

echo json_encode(['pet' => petToJson($pet)]);
