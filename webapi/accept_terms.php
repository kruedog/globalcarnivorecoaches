<?php
/**
 * accept_terms.php — FINAL WORKING VERSION (Dec 2025)
 * Fixes: 
 *   • JSON corruption (escaped slashes)
 *   • Terms popup every login (no session created)
 *   • Photos disappear after accept
 */
header('Content-Type: application/json');

// Start session immediately so user stays logged in after accepting
session_start();

$FILE = __DIR__ . '/coaches.json';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['username'])) {
    echo json_encode(['success' => false, 'message' => 'Missing username']);
    exit;
}

$username = trim($input['username']);

if (!file_exists($FILE)) {
    echo json_encode(['success' => false, 'message' => 'coaches.json not found']);
    exit;
}

$coaches = json_decode(file_get_contents($FILE), true);
if (!is_array($coaches)) {
    $coaches = [];
}

$updated = false;
$timestamp = date('Y-m-d H:i:s');
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

foreach ($coaches as &$coach) {
    if (isset($coach['Username']) && strcasecmp($coach['Username'], $username) === 0) {
        $coach['requireAgreement'] = false;
        $coach['agreement_accepted_on'] = $timestamp;
        $coach['agreement_ip'] = $ip;

        // CRITICAL: Create real login session so popup never returns
        $_SESSION['username']  = $coach['Username'];
        $_SESSION['coachName'] = $coach['CoachName'] ?? $coach['Username'];
        $_SESSION['email']     = $coach['Email'] ?? '';

        $updated = true;
        break;
    }
}

if (!$updated) {
    echo json_encode(['success' => false, 'message' => 'Coach not found']);
    exit;
}

// THIS LINE PREVENTS ALL JSON CORRUPTION — NEVER REMOVE IT
file_put_contents(
    $FILE,
    json_encode($coaches, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo json_encode([
    'success' => true,
    'message' => 'Welcome! You are now logged in.'
]);
exit;
?>