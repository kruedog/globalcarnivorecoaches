<?php
/**
 * login.php — FINAL HYBRID HANDLER
 * Accepts JSON OR form-data login safely
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');
session_start();

// Load request body safely
$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true);

// If JSON decode failed → maybe FORM submission
if (!is_array($input)) {
    $input = $_POST;
}

// Now extract fields
$username = trim($input['username'] ?? '');
$password = trim($input['password'] ?? '');

if ($username === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Username and password required']);
    exit;
}

// Database
$coachesFile = __DIR__ . '/../uploads/coaches.json';
if (!file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'System error: coaches database missing']);
    exit;
}

$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) {
    echo json_encode(['success' => false, 'message' => 'Invalid coaches.json']);
    exit;
}

// Find matching user
$match = null;
foreach ($coaches as $c) {
    if (strcasecmp($c['Username'], $username) === 0) {
        $match = $c;
        break;
    }
}

if (!$match) {
    echo json_encode(['success' => false, 'message' => 'Invalid login']);
    exit;
}

// Verify password
if (!isset($match['Password']) || !password_verify($password, $match['Password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid login']);
    exit;
}

// Logged-in!
$_SESSION['username'] = $match['Username'];
$_SESSION['role']     = strtolower($match['Role'] ?? 'coach');

// Success response
echo json_encode([
    'success' => true,
    'coach' => [
        'Username'  => $match['Username'],
        'CoachName' => $match['CoachName'] ?? $match['Username'],
        'Email'     => $match['Email'] ?? '',
        'Role'      => $_SESSION['role'],
    ]
]);
exit;
