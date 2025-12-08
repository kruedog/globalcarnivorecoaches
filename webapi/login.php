<?php
/**
 * login.php — FINAL STABLE VERSION
 * Global Carnivore Coaches — December 2025
 *
 * - POST = login
 * - GET  = who am I? (session check)
 */

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');

// === CORS: allow only your authorized domains ===
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origins = [
    'http://kruedog.ddns.net:8080',
    'https://globalcarnivorecoaches.onrender.com'
];

if (in_array($origin, $allowed_origins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
}

// No caching of auth responses
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');


/**
 * Helper: send JSON response + exit
 */
function send_json(array $payload): void {
    echo json_encode($payload);
    exit;
}


/**
 * Returns decoded coaches.json as array
 */
function load_coaches(): array {
    $path = __DIR__ . '/../uploads/coaches.json';
    if (!file_exists($path)) return [];
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}


/**
 * Locate coach by Username (case-insensitive)
 */
function find_coach(string $username): ?array {
    $username = strtolower($username);
    foreach (load_coaches() as $coach) {
        $u = strtolower($coach['Username'] ?? '');
        if ($u === $username) return $coach;
    }
    return null;
}


// ======================================
// GET  = check logged-in status
// ======================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_SESSION['Username'])) {
        send_json([
            'success'  => true,
            'username' => $_SESSION['Username'],
            'role'     => $_SESSION['role'] ?? 'coach',
        ]);
    } else {
        send_json([
            'success' => false,
            'message' => 'Not logged in'
        ]);
    }
}


// ======================================
// POST = login
// ======================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json([
        'success' => false,
        'message' => 'POST required'
    ]);
}


// Parse JSON body from fetch()
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    send_json([
        'success' => false,
        'message' => 'Invalid JSON'
    ]);
}

$username = trim($input['username'] ?? '');
$password = (string)($input['password'] ?? '');

if ($username === '' || $password === '') {
    send_json([
        'success' => false,
        'message' => 'Username and password required'
    ]);
}


// Find coach record
$coach = find_coach($username);
if (!$coach) {
    send_json([
        'success' => false,
        'message' => 'Invalid username or password'
    ]);
}

$stored = $coach['Password'] ?? '';
$valid = false;

// Modern hash
if (is_string($stored) && str_starts_with($stored, '$')) {
    $valid = password_verify($password, $stored);
}
// Legacy fallback (old plain-text accounts)
else {
    $valid = ($password === $stored);
}

if (!$valid) {
    send_json([
        'success' => false,
        'message' => 'Invalid username or password'
    ]);
}


// ======================================
// LOGIN SUCCESS — Set session correctly
// ======================================

$_SESSION['Username'] = $coach['Username'];  // <-- Standardized key for update_coach.php
$_SESSION['role']     = $coach['Role'] ?? 'coach';

// Optionally add login timestamp if needed:
// $_SESSION['logged_in_at'] = time();


send_json([
    'success'  => true,
    'username' => $_SESSION['Username'],
    'role'     => $_SESSION['role'],
    'message'  => 'Login successful'
]);
