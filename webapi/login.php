<?php
header('Content-Type: application/json');
session_start();

// Accept JSON or Form POST
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);
if (!is_array($data)) $data = $_POST;

$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

if ($username === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Username and password required']);
    exit;
}

$coachesFile = __DIR__ . '/../coaches.json';
if (!file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'System error']);
    exit;
}
$coaches = json_decode(file_get_contents($coachesFile), true);

$found = null;
foreach ($coaches as $coach) {
    if (strtolower($coach['Username']) === strtolower($username)) {
        $found = $coach;
        break;
    }
}

if (!$found) {
    echo json_encode(['success' => false, 'message' => 'Coach not found']);
    exit;
}

if (!password_verify($password, $found['Password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid password']);
    exit;
}

$_SESSION['username'] = $found['Username'];

// REQUIRED for your frontend JS
echo json_encode([
    'success'   => true,
    'message'   => 'OK',
    'username'  => $found['Username'],
    'coachName' => $found['CoachName'] ?? $found['Username'],
    'requireAgreement' => $found['requireAgreement'] ?? false
]);
