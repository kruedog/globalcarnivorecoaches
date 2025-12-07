<?php
// /webapi/login.php — FINAL & SESSION-SECURE VERSION

header('Content-Type: application/json; charset=utf-8');

session_set_cookie_params([
    'lifetime' => 86400, // 24 hours
    'path'     => '/',
    'secure'   => false, // set true when HTTPS only
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// GET = check session
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['username'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    echo json_encode([
        'success' => true,
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role'] ?? 'coach'
    ]);
    exit;
}

// POST = login attempt
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

if ($username === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Missing credentials']);
    exit;
}

$coachesFile = __DIR__ . '/coaches.json';
if (!file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'System missing coaches.json']);
    exit;
}

$coaches = json_decode(file_get_contents($coachesFile), true);
$user = null;
foreach ($coaches as $c) {
    if (strcasecmp($c['Username'], $username) === 0) {
        $user = $c;
        break;
    }
}

if (!$user || !password_verify($password, $user['Password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid login']);
    exit;
}

// SUCCESS — Create session
$_SESSION['username'] = $user['Username'];
$_SESSION['role'] = $user['Role'] ?? 'coach';

echo json_encode([
    'success' => true,
    'username' => $user['Username'],
    'role' => $_SESSION['role']
]);
exit;
