<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

session_start();
if (!isset($_SESSION['username'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$file = '/data/uploads/visits_history.json';

if (!file_exists($file)) {
    echo json_encode([
        'success' => false,
        'message' => 'visits_history.json not found'
    ]);
    exit;
}

$data = json_decode(file_get_contents($file), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid visits_history.json']);
    exit;
}

echo json_encode($data);
