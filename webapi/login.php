<?php
/**
 * login.php — FINAL VERSION WITH UNIVERSAL LOGGING
 * Global Carnivore Coaches | November 2025
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

session_start();

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

if ($username === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Username and password required']);
    exit;
}

// Load coaches
$coachesFile = __DIR__ . '/coaches.json';
if (!file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'System error: coaches.json missing']);
    exit;
}

$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) {
    echo json_encode(['success' => false, 'message' => 'Corrupted coaches data']);
    exit;
}

// Find coach by username (case-insensitive)
$coach = null;
foreach ($coaches as $c) {
    if (isset($c['Username']) && strcasecmp($c['Username'], $username) === 0) {
        $coach = $c;
        break;
    }
}

if (!$coach || !password_verify($password, $coach['Password'] ?? '')) {
    // Log failed attempt (optional — you can remove if too noisy)
    // require_once __DIR__ . '/log_activity.php';
    // log_coach_activity('login_failed', "Username: $username from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    exit;
}

// SUCCESS — Set session
$coachName = $coach['CoachName'] ?? $coach['Username'];
$_SESSION['username']  = $coach['Username'];
$_SESSION['coachName'] = $coachName;
$_SESSION['email']     = $coach['Email'] ?? '';

// LOG SUCCESSFUL LOGIN
require_once __DIR__ . '/log_activity.php';
log_coach_activity('login');

echo json_encode([
    'success'   => true,
    'username'  => $coach['Username'],
    'coachName' => $coachName,
    'email'     => $coach['Email'] ?? ''
]);
exit;
?>