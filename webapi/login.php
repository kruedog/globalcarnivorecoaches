<?php
/**
 * login.php — with role support + session status
 * Used by:
 *  - Coach login form (POST)
 *  - Visitor dashboard / manage_coaches auth check (GET)
 */

session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['username'])) {
        echo json_encode(['success' => false]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role'] ?? 'Coach',
        'coachName' => $_SESSION['coachName'] ?? $_SESSION['username']
    ]);
    exit;
}

// POST login handler (unchanged)
?>

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$COACHES_FILE = dirname(__DIR__) . '/uploads/coaches.json';

/**
 * Load coaches array from JSON
 */
function load_coaches($path) {
    if (!file_exists($path)) return [];
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

/**
 * Find coach by username (case-insensitive)
 */
function find_coach_by_username($coaches, $username) {
    $username = strtolower(trim($username));
    foreach ($coaches as $coach) {
        if (isset($coach['Username']) && strtolower($coach['Username']) === $username) {
            return $coach;
        }
    }
    return null;
}

/**
 * Normalize role with default
 */
function normalize_role($coach) {
    $role = $coach['Role'] ?? 'Coach';
    $role = $role ?: 'Coach';

    // Ensure special usernames always count as admin
    $u = strtolower($coach['Username'] ?? '');
    if (in_array($u, ['thor', 'admin'], true)) {
        $role = 'Admin';
    }
    return $role;
}

// =========================
// GET: Session status
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (empty($_SESSION['username'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Not logged in'
        ]);
        exit;
    }

    echo json_encode([
        'success'   => true,
        'username'  => $_SESSION['username'],
        'coachName' => $_SESSION['coachName'] ?? $_SESSION['username'],
        'role'      => $_SESSION['role'] ?? 'Coach'
    ]);
    exit;
}

// =========================
// POST: Perform login
// =========================
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

// Fallback if form-encoded
if (!is_array($input)) {
    $input = $_POST;
}

$username = trim($input['username'] ?? '');
$password = (string)($input['password'] ?? '');

if ($username === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username and password required']);
    exit;
}

$coaches = load_coaches($COACHES_FILE);
$coach   = find_coach_by_username($coaches, $username);

if (!$coach) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    exit;
}

// Ensure a password hash exists
if (empty($coach['Password'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Password not set for this account']);
    exit;
}

// Verify password
if (!password_verify($password, $coach['Password'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    exit;
}

// Success: set session
$role = normalize_role($coach);
$_SESSION['username']  = $coach['Username'];
$_SESSION['coachName'] = $coach['CoachName'] ?? $coach['Username'];
$_SESSION['role']      = $role;

// Log login to activity_log.json using helper
require_once __DIR__ . '/log_activity.php';
log_coach_activity('login');

// Response payload
echo json_encode([
    'success'   => true,
    'message'   => 'Login successful',
    'username'  => $_SESSION['username'],
    'coachName' => $_SESSION['coachName'],
    'role'      => $role
]);
