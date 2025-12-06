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

// Resolve coaches.json location
$possibleFiles = [
    __DIR__ . '/../coaches.json',     // normal
    '/data/coaches.json',            // common mount
    '/data/uploads/coaches.json',    // some render configs
];

$coachesFile = null;
foreach ($possibleFiles as $f) {
    if (file_exists($f)) { $coachesFile = $f; break; }
}

if (!$coachesFile) {
    echo json_encode([
        'success' => false,
        'message' => 'System error: coaches.json not found'
    ]);
    exit;
}

$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid coaches.json'
    ]);
    exit;
}

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

echo json_encode([
    'success'   => true,
    'message'   => 'OK',
    'username'  => $found['Username'],
    'coachName' => $found['CoachName'] ?? $found['Username'],
    'requireAgreement' => $found['requireAgreement'] ?? false
]);
