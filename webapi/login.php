<?php
/**
 * login.php — FINAL
 * Global Carnivore Coaches — Session + Auth API
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

$coachesFile = __DIR__ . '/../uploads/coaches.json';
if (!file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'System error: coaches.json missing']);
    exit;
}

// Load DB
$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) {
    echo json_encode(['success' => false, 'message' => 'Invalid coaches database']);
    exit;
}

/*
|--------------------------------------------------------------------------
| 1️⃣ GET = Session status
|--------------------------------------------------------------------------
| Returns whether a user is authenticated.
| Used by profile.html, manage_coaches.html, dashboards...
*/
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_SESSION['username'])) {

        // Return coach profile fields for UI use
        echo json_encode([
            'success'    => true,
            'username'   => $_SESSION['username'],
            'coachName'  => $_SESSION['coachName'] ?? $_SESSION['username'],
            'role'       => $_SESSION['role'] ?? 'coach'
        ]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

/*
|--------------------------------------------------------------------------
| 2️⃣ POST = Attempt login
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON body']);
        exit;
    }

    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if ($username === '' || $password === '') {
        echo json_encode(['success' => false, 'message' => 'Username and password required']);
        exit;
    }

    // Find coach record
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

    // Validate password
    if (!isset($coach['Password']) || !password_verify($password, $coach['Password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
        exit;
    }

    // Auth Success --> Create Session
    $_SESSION['username']  = $coach['Username'];
    $_SESSION['coachName'] = $coach['CoachName'] ?? $coach['Username'];
    $_SESSION['role']      = $coach['Role'] ?? 'coach';  // default role

    // Update last_login timestamp
    $coaches[$foundIndex]['last_login'] = round(microtime(true) * 1000);
    file_put_contents($coachesFile, json_encode($coaches, JSON_PRETTY_PRINT));

    echo json_encode([
        'success'    => true,
        'message'    => 'Login successful',
        'username'   => $_SESSION['username'],
        'coachName'  => $_SESSION['coachName'],
        'role'       => $_SESSION['role']
    ]);
    exit;
}

// Unsupported request
echo json_encode(['success' => false, 'message' => 'Invalid request method']);
exit;
