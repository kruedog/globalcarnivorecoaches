<?php
/**
 * /webapi/login.php
 * - POST: login with username/password
 * - GET:  return current session info
 */
 */Debugger
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


header('Content-Type: application/json; charset=utf-8');

// CORS for same Render origin (adjust if needed)
$origin = 'https://globalcarnivorecoaches.onrender.com';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit; // preflight
}

session_start();

$coachesFile = __DIR__ . '/../uploads/coaches.json';
if (!file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'System error: coaches.json missing']);
    exit;
}

$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) {
    echo json_encode(['success' => false, 'message' => 'Invalid coaches.json']);
    exit;
}

/**
 * GET → Check session
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_SESSION['username'])) {
        echo json_encode([
            'success'   => true,
            'username'  => $_SESSION['username'],
            'coachName' => $_SESSION['coachName'] ?? $_SESSION['username'],
            'role'      => $_SESSION['role'] ?? 'coach'
        ]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

/**
 * POST → Login
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Accept JSON or form-data
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if ($username === '' || $password === '') {
        echo json_encode(['success' => false, 'message' => 'Username and password required']);
        exit;
    }

    // Find user
    $foundIndex = null;
    foreach ($coaches as $i => $coach) {
        if (strcasecmp($coach['Username'] ?? '', $username) === 0) {
            $foundIndex = $i;
            break;
        }
    }

    if ($foundIndex === null) {
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
        exit;
    }

    $coach = $coaches[$foundIndex];

    if (empty($coach['Password']) || !password_verify($password, $coach['Password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
        exit;
    }

    // Success → set session
    $_SESSION['username']  = $coach['Username'];
    $_SESSION['coachName'] = $coach['CoachName'] ?? $coach['Username'];
    $_SESSION['role']      = $coach['Role'] ?? 'coach';

    // Update last_login (ms timestamp)
    $coaches[$foundIndex]['last_login'] = round(microtime(true) * 1000);
    file_put_contents($coachesFile, json_encode($coaches, JSON_PRETTY_PRINT));

    echo json_encode([
        'success'   => true,
        'message'   => 'Login successful',
        'username'  => $_SESSION['username'],
        'coachName' => $_SESSION['coachName'],
        'role'      => $_SESSION['role']
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);
exit;
