<?php
header('Content-Type: application/json');
ini_set('log_errors', 1);
ini_set('error_log', '/data/uploads/php_errors.log');

// Validate input
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$username = trim($_POST['username'] ?? '');
if ($username === '') {
    echo json_encode(['success' => false, 'message' => 'Username required']);
    exit;
}

$coachesFile = __DIR__ . '/coaches.json';
if (!file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'coaches.json not found']);
    exit;
}

$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) {
    echo json_encode(['success' => false, 'message' => 'Invalid coaches.json']);
    exit;
}

// Find matching coach
$foundIndex = null;
foreach ($coaches as $i => $coach) {
    if ($coach['Username'] === $username) {
        $foundIndex = $i;
        break;
    }
}

if ($foundIndex === null) {
    echo json_encode(['success' => false, 'message' => 'Coach not found']);
    exit;
}

// Update allowed fields
$fields = ['CoachName', 'Email', 'Bio', 'Certifications', 'Specialties'];
foreach ($fields as $field) {
    if (isset($_POST[$field])) {
        $coaches[$foundIndex][$field] = trim($_POST[$field]);
    }
}

// Save JSON back
if (file_put_contents($coachesFile, json_encode($coaches, JSON_PRETTY_PRINT)) === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to save data']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Profile updated']);
exit;
