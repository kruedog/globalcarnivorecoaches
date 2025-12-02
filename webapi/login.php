<?php
/**
 * login.php — Updated with Agreement Check + Timestamp Support
 * Global Carnivore Coaches | 2025
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

session_start();

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

if ($username === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Username and password required']);
    exit;
}

// Load coaches.json
$coachesFile = __DIR__ . '/coaches.json';
if (!file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'System error: coaches.json missing']);
    exit;
}

$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) {
    echo json_encode(['success' => false, 'message' => 'Corrupted coaches data']);
    exit;
}

// Locate coach (case-insensitive)
$coachIndex = null;
foreach ($coaches as $i => $c) {
    if (isset($c['Username']) && strcasecmp($c['Username'], $username) === 0) {
        $coachIndex = $i;
        break;
    }
}

if ($coachIndex === null) {
    echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    exit;
}

$coach = $coaches[$coachIndex];

// Password check
if (!password_verify($password, $coach['Password'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    exit;
}

// ================================
// AGREEMENT CHECK
// ================================
$requiresAgreement = $coach['requireAgreement'] ?? true;

// If they have NOT accepted yet → do NOT log them in
if ($requiresAgreement === true) {
    echo json_encode([
        'success' => false,
        'requireAgreement' => true,
        'username' => $coach['Username'],
        'message' => 'Legal agreements must be accepted'
    ]);
    exit;
}

// ================================
// Successful login (agreements already accepted)
// ================================

$coachName = $coach['CoachName'] ?? $coach['Username'];

$_SESSION['username']  = $coach['Username'];
$_SESSION['coachName'] = $coachName;
$_SESSION['email']     = $coach['Email'] ?? '';

// Log success
require_once __DIR__ . '/log_activity.php';
log_coach_activity('login');

echo json_encode([
    'success'   => true,
    'username'  => $coach['Username'],
    'coachName' => $coachName,
    'email'     => $coach['Email'] ?? '',
    'agreement_accepted_on' => $coach['agreement_accepted_on'] ?? null
]);

exit;
?>
