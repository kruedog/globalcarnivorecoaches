<?php
/**
 * login.php — Session-based login for coaches/admin
 * POST: authenticate
 * GET:  return current session info
 */

session_start();
header('Content-Type: application/json');

// Helper: load coaches
function load_coaches() {
    $path = __DIR__ . '/../uploads/coaches.json';
    if (!file_exists($path)) {
        return null;
    }
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Return session info
    if (!isset($_SESSION['username'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Not logged in',
            'code'    => 'NO_SESSION'
        ]);
        exit;
    }

    echo json_encode([
        'success'  => true,
        'username' => $_SESSION['username'],
        'role'     => $_SESSION['role'] ?? 'coach'
    ]);
    exit;
}

if ($method !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid method',
        'code'    => 'BAD_METHOD'
    ]);
    exit;
}

// POST: login attempt
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON',
        'code'    => 'BAD_JSON'
    ]);
    exit;
}

$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

if ($username === '' || $password === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Username and password required',
        'code'    => 'MISSING_FIELDS'
    ]);
    exit;
}

// Load coaches
$coaches = load_coaches();
if ($coaches === null) {
    echo json_encode([
        'success' => false,
        'message' => 'System error: coaches.json missing or invalid',
        'code'    => 'NO_COACHES_FILE'
    ]);
    exit;
}

// Find coach by Username (case-insensitive)
$found = null;
foreach ($coaches as $c) {
    if (strcasecmp($c['Username'] ?? '', $username) === 0) {
        $found = $c;
        break;
    }
}

if (!$found) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid username or password',
        'code'    => 'BAD_LOGIN'
    ]);
    exit;
}

$hash = $found['Password'] ?? '';
if (!$hash || !password_verify($password, $hash)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid username or password',
        'code'    => 'BAD_LOGIN'
    ]);
    exit;
}

// Success — set session
$_SESSION['username'] = $found['Username'];
$_SESSION['role']     = strtolower($found['Role'] ?? 'coach');

echo json_encode([
    'success'  => true,
    'message'  => 'Login successful',
    'username' => $_SESSION['username'],
    'role'     => $_SESSION['role']
]);
