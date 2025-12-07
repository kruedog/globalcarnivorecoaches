<?php
// webapi/login.php
// - POST  = login
// - GET   = session check ("who am I?")

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

session_start();

header('Content-Type: application/json; charset=utf-8');
// same-origin (Render) -> this is safe, and allows credentials
header('Access-Control-Allow-Origin: https://globalcarnivorecoaches.onrender.com');
header('Access-Control-Allow-Credentials: true');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/**
 * Helper: send JSON and exit
 */
function send_json(array $payload): void {
    echo json_encode($payload);
    exit;
}

/**
 * Helper: load coaches.json as array
 */
function load_coaches(): array {
    $path = __DIR__ . '/../uploads/coaches.json';
    if (!file_exists($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Helper: find coach by username (case-insensitive)
 */
function find_coach(string $username): ?array {
    $usernameLower = strtolower($username);
    foreach (load_coaches() as $coach) {
        $u = $coach['Username'] ?? $coach['username'] ?? '';
        if ($u !== '' && strtolower($u) === $usernameLower) {
            return $coach;
        }
    }
    return null;
}

// =======================================
// GET  =>  "who am I?"
// =======================================
if ($method === 'GET') {
    if (!empty($_SESSION['username'])) {
        send_json([
            'success'  => true,
            'username' => $_SESSION['username'],
            'role'     => $_SESSION['role'] ?? 'coach',
        ]);
    } else {
        send_json([
            'success' => false,
            'message' => 'Not logged in',
        ]);
    }
}

// =======================================
// POST => login
// =======================================
if ($method !== 'POST') {
    send_json([
        'success' => false,
        'message' => 'POST required',
    ]);
}

// Read JSON body from fetch()
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    send_json([
        'success' => false,
        'message' => 'Invalid JSON body',
    ]);
}

$username = trim($data['username'] ?? '');
$password = (string)($data['password'] ?? '');

if ($username === '' || $password === '') {
    send_json([
        'success' => false,
        'message' => 'Username and password required',
    ]);
}

// Look up coach
$coach = find_coach($username);
if ($coach === null) {
    send_json([
        'success' => false,
        'message' => 'Invalid username or password',
    ]);
}

$storedHash = $coach['Password'] ?? $coach['password'] ?? '';

$valid = false;

// Normal case: password is a hash
if (is_string($storedHash) && $storedHash !== '' && str_starts_with($storedHash, '$')) {
    if (password_verify($password, $storedHash)) {
        $valid = true;
    }
} else {
    // Fallback (if some old record is still plain text)
    if ($password === $storedHash) {
        $valid = true;
    }
}

if (!$valid) {
    send_json([
        'success' => false,
        'message' => 'Invalid username or password',
    ]);
}

// At this point: LOGIN OK
$_SESSION['username'] = $coach['Username'] ?? $username;
$_SESSION['role']     = $coach['Role'] ?? $coach['role'] ?? 'coach';

// (Optional) log the login into login_log.json or activity_log.json here

send_json([
    'success'  => true,
    'username' => $_SESSION['username'],
    'role'     => $_SESSION['role'],
    'message'  => 'Login successful',
]);
