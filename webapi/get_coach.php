<?php
// Returns a coach by username (stateless)

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

$username = isset($_GET['username']) ? trim($_GET['username']) : '';
if ($username === '') {
    echo json_encode(['success' => false, 'message' => 'Missing username']);
    exit;
}

$coachesFile = __DIR__ . '/coaches.json';
if (!file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'coaches.json missing']);
    exit;
}

$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) {
    echo json_encode(['success' => false, 'message' => 'Invalid coaches.json']);
    exit;
}

foreach ($coaches as $coach) {
    if (isset($coach['Username']) &&
        strtolower($coach['Username']) === strtolower($username)) {
        echo json_encode(['success' => true, 'coach' => $coach]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Coach not found']);
