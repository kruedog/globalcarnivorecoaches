<?php
/**
 * accept_terms.php — FIXED VERSION (Dec 2025)
 * Prevents JSON corruption that breaks image paths
 */
header('Content-Type: application/json');

$FILE = __DIR__ . '/coaches.json';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['username'])) {
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
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$updated = false;
$timestamp = date('Y-m-d H:i:s');
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

foreach ($coaches as &$coach) {
    if (isset($coach['Username']) && strcasecmp($coach['Username'], $username) === 0) {
        $coach['requireAgreement'] = false;
        $coach['agreement_accepted_on'] = $timestamp;
        $coach['agreement_ip'] = $ip;
        $updated = true;
        break;
    }
}

if (!$updated) {
    echo json_encode(['success' => false, 'message' => 'Coach not found']);
    exit;
}

// THIS LINE WAS THE BUG — NOW FIXED
$jsonOut = json_encode($coaches, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

file_put_contents($FILE, $jsonOut);

echo json_encode([
    'success' => true,
    'username' => $username,
    'agreement_accepted_on' => $timestamp
]);
?>