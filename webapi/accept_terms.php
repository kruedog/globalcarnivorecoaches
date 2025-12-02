<?php
/**
 * accept_terms.php — FINAL VERSION
 * Saves coach acceptance of Terms, Privacy Policy, and Photo Agreement.
 * Global Carnivore Coaches | 2025
 */

header('Content-Type: application/json');

// Path to coaches.json
$FILE = __DIR__ . '/coaches.json';

// Read incoming JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['username'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing username'
    ]);
    exit;
}

$username = trim($input['username']);

// Load coaches.json
if (!file_exists($FILE)) {
    echo json_encode([
        'success' => false,
        'message' => 'coaches.json not found'
    ]);
    exit;
}

$raw = file_get_contents($FILE);
$coaches = json_decode($raw, true);

if (!is_array($coaches)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON structure'
    ]);
    exit;
}

$updated = false;
$timestamp = date('Y-m-d H:i:s');
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Loop through coaches and update the matching entry
foreach ($coaches as &$coach) {

    if (isset($coach['Username']) && strcasecmp($coach['Username'], $username) === 0) {

        // Update flags
        $coach['requireAgreement'] = false;
        $coach['agreement_accepted_on'] = $timestamp;
        $coach['agreement_ip'] = $ip;

        $updated = true;
        break;
    }
}

if (!$updated) {
    echo json_encode([
        'success' => false,
        'message' => 'Coach not found'
    ]);
    exit;
}

// Encode back to JSON
$jsonOut = json_encode($coaches, JSON_PRETTY_PRINT);

if ($jsonOut === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to encode JSON'
    ]);
    exit;
}

// Write with exclusive lock to prevent corruption
$fp = fopen($FILE, 'c+');
if (!$fp) {
    echo json_encode([
        'success' => false,
        'message' => 'Cannot write to coaches.json'
    ]);
    exit;
}

flock($fp, LOCK_EX);
ftruncate($fp, 0);
fwrite($fp, $jsonOut);
flock($fp, LOCK_UN);
fclose($fp);

// Return success
echo json_encode([
    'success' => true,
    'username' => $username,
    'agreement_accepted_on' => $timestamp,
    'ip' => $ip
]);
exit;
?>
