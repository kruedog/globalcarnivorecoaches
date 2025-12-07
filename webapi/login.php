<?php
// /webapi/login.php
// Session-based login + session check (GET)

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$COACHES_FILE = __DIR__ . '/../uploads/coaches.json';

/**
 * Load coaches array from JSON.
 *
 * @return array
 */
function load_coaches(string $path): array {
    if (!file_exists($path)) {
        return [];
    }
    $json = file_get_contents($path);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

/**
 * Save coaches array back to JSON.
 */
function save_coaches(string $path, array $coaches): void {
    file_put_contents(
        $path,
        json_encode($coaches, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}

/**
 * Find coach by username (case-insensitive).
 * Returns [index, coachArray] or [null, null].
 */
function find_coach_by_username(array $coaches, string $username): array {
    $needle = strtolower($username);
    foreach ($coaches as $i => $coach) {
        $u = strtolower($coach['Username'] ?? '');
        if ($u === $needle) {
            return [$i, $coach];
        }
    }
    return [null, null];
}

// ------------------ GET: session check ------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_SESSION['coach_username'])) {
        $username = $_SESSION['coach_username'];
        $role     = $_SESSION['coach_role'] ?? 'coach';
        echo json_encode([
            'success'  => true,
            'username' => $username,
            'role'     => $role
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Not logged in'
        ]);
    }
    exit;
}

// ------------------ POST: login attempt -----------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'POST required'
    ]);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON body'
    ]);
    exit;
}

$username = trim($data['username'] ?? '');
$password = (string)($data['password'] ?? '');

if ($username === '' || $password === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Username and password are required.'
    ]);
    exit;
}

$coaches = load_coaches($COACHES_FILE);
list($idx, $coach) = find_coach_by_username($coaches, $username);

if ($idx === null || $coach === null) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid username or password.'
    ]);
    exit;
}

// Determine which field stores the hashed password.
$hashField = null;
if (!empty($coach['PasswordHash'])) {
    $hashField = 'PasswordHash';
} elseif (!empty($coach['Password'])) {
    $hashField = 'Password';
}

if ($hashField === null || empty($coach[$hashField])) {
    echo json_encode([
        'success' => false,
        'message' => 'Password not set for this coach.'
    ]);
    exit;
}

if (!password_verify($password, $coach[$hashField])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid username or password.'
    ]);
    exit;
}

// Successful login: set session
$_SESSION['coach_username'] = $coach['Username'];
$_SESSION['coach_role']     = strtolower($coach['Role'] ?? 'coach');

// Optionally update last_login timestamp
$coaches[$idx]['last_login'] = time() * 1000;
save_coaches($COACHES_FILE, $coaches);

echo json_encode([
    'success'  => true,
    'message'  => 'Login successful.',
    'username' => $coach['Username'],
    'role'     => $_SESSION['coach_role']
]);
