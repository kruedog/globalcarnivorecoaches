<?php
// webapi/login.php
// POST => login, GET => who am I?

declare(strict_types=1);

// MUST configure cookie BEFORE session_start()
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => 'globalcarnivorecoaches.onrender.com',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None'
]);

session_start();

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://globalcarnivorecoaches.onrender.com');
header('Access-Control-Allow-Credentials: true');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function send_json(array $payload): void {
    echo json_encode($payload);
    exit;
}

function load_coaches(): array {
    $path = '/data/uploads/coaches.json';
    if (!file_exists($path)) return [];
    $raw = file_get_contents($path);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function find_coach(string $username): ?array {
    $uLower = strtolower($username);
    foreach (load_coaches() as $coach) {
        $u = strtolower($coach['Username'] ?? '');
        if ($u === $uLower) return $coach;
    }
    return null;
}

// =======================================
// GET = who am I?
// =======================================
if ($method === 'GET') {
    if (!empty($_SESSION['username'])) {
        send_json([
            'success'  => true,
            'username' => $_SESSION['username'],
            'role'     => $_SESSION['role'] ?? 'coach'
        ]);
    }
    send_json(['success' => false, 'message' => 'Not logged in']);
}

// =======================================
// POST = login
// =======================================
if ($method !== 'POST') {
    send_json(['success' => false, 'message' => 'POST required']);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    send_json(['success' => false, 'message' => 'Invalid JSON body']);
}

$username = trim($data['username'] ?? '');
$password = (string)($data['password'] ?? '');

if ($username === '' || $password === '') {
    send_json(['success' => false, 'message' => 'Username and password required']);
}

$coach = find_coach($username);
if (!$coach) {
    send_json(['success' => false, 'message' => 'Invalid username or password']);
}

$storedHash = $coach['Password'] ?? '';
$valid =
    (is_string($storedHash) && str_starts_with($storedHash, '$') && password_verify($password, $storedHash))
    || $password === $storedHash;

if (!$valid) {
    send_json(['success' => false, 'message' => 'Invalid username or password']);
}

// SUCCESS — set SESSION
$_SESSION['username'] = $coach['Username'];
$_SESSION['role'] = $coach['Role'] ?? 'coach';

send_json([
    'success'  => true,
    'username' => $_SESSION['username'],
    'role'     => $_SESSION['role'],
    'message'  => 'Login successful'
]);
