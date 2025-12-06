<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

session_start();

// Try to read JSON first
$input = json_decode(file_get_contents('php://input'), true);

// If JSON missing, fallback to POST form fields
if (!is_array($input)) {
    $input = $_POST;
}

$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

if ($username === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Username and password required']);
    exit;
}

// Load coaches file — use persistent JSON location
$coachesFile = realpath(__DIR__ . '/../coaches.json');
if (!$coachesFile || !file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'System error: missing data']);
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

// Login success
$_SESSION['username'] = $found['Username'];

echo json_encode([
    'success' => true,
    'message' => 'Login OK',
    'coach' => [
        'Username' => $found['Username'],
        'CoachName'=> $found['CoachName'] ?? '',
        'Email'    => $found['Email'] ?? '',
    ]
]);
