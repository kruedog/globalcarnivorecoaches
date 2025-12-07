<?php
// login.php — CLEAN + FIXED VERSION (no stray characters)
// Global Carnivore Coaches — February 2025

// === ERROR VISIBILITY DURING DEBUG ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
session_start();

$DIR = __DIR__;
$coachesFile = "$DIR/coaches.json";
$loginLogFile = "$DIR/login_log.json";
$activityLogFile = "$DIR/activity_log.json";

// === REQUIRE JSON POST ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

// === READ BODY ===
$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body']);
    exit;
}

$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

if ($username === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Username & password required']);
    exit;
}

// === LOAD COACHES ===
if (!file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'System missing coaches.json']);
    exit;
}
$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) {
    echo json_encode(['success' => false, 'message' => 'Invalid coaches.json']);
    exit;
}

// === FIND USER ===
$found = null;
foreach ($coaches as $c) {
    if (strcasecmp($c['Username'], $username) === 0) {
        $found = $c;
        break;
    }
}

if (!$found) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

// === CHECK PASSWORD ===
if (!isset($found['Password']) || !password_verify($password, $found['Password'])) {
    echo json_encode(['success' => false, 'message' => 'Incorrect password']);
    exit;
}

// === LOGIN SUCCESS ===
$_SESSION['username'] = $found['Username'];
$_SESSION['role']     = $found['Role'] ?? 'coach';

// === LOG LOGIN EVENT ===
$event = [
    'time' => time() * 1000,
    'type' => 'login',
    'user' => $found['Username'],
    'role' => $_SESSION['role']
];

$log = file_exists($activityLogFile) ? json_decode(file_get_contents($activityLogFile), true) : [];
if (!is_array($log)) $log = [];
array_unshift($log, $event);
file_put_contents($activityLogFile, json_encode($log, JSON_PRETTY_PRINT));

// === OUTPUT ===
echo json_encode([
    'success' => true,
    'username' => $found['Username'],
    'role' => $_SESSION['role']
]);
exit;
