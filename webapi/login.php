<?php
// login.php — Stateless login with JSON body only

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Read JSON input
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

if ($username === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Username & password required']);
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

$found = null;
foreach ($coaches as $coach) {
    if (isset($coach['Username']) &&
        strtolower($coach['Username']) === strtolower($username)) {
        $found = $coach;
        break;
    }
}

if (!$found || !password_verify($password, $found['Password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    exit;
}

unset($found['Password']); // never send hash back

echo json_encode(['success' => true, 'coach' => $found]);
