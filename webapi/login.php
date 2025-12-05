<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
ini_set('display_errors', 0); // Prevent HTML warnings from breaking JSON

session_start();

$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Missing login details']);
    exit;
}

// Read from persistent disk
$coachesFile = "/data/coaches.json";
if (!file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'coaches.json missing']);
    exit;
}

$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) {
    echo json_encode(['success' => false, 'message' => 'Invalid coaches data']);
    exit;
}

// Find coach by email
$found = null;
foreach ($coaches as $coach) {
    if (isset($coach['Email']) && strcasecmp($coach['Email'], $email) === 0) {
        $found = $coach;
        break;
    }
}

if (!$found) {
    echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    exit;
}

// Secure password verification (supports hashed passwords)
if (!isset($found['Password']) || !password_verify($password, $found['Password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    exit;
}

// Login success — store session
$_SESSION['email'] = $found['Email'];

echo json_encode([
    'success' => true,
    'coach'   => $found
], JSON_UNESCAPED_SLASHES);
exit;
?>
