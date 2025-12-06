<?php
// login.php — Accept BOTH JSON and regular POST

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Default (empty)
$username = '';
$password = '';

// Try JSON first
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (is_array($data)) {
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';
}

// Fall back to normal form POST
if ($username === '' && !empty($_POST)) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
}

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

unset($found['Password']); // Never expose password hash

echo json_encode(['success' => true, 'coach' => $found]);
