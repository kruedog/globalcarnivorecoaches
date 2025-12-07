<?php
/**
 * get_coach.php
 * Returns the currently logged-in coach from uploads/coaches.json
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Origin: *');

ini_set('display_errors', '0');
error_reporting(E_ALL);

session_start();

// 1. Method check
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'success' => false,
        'message' => 'GET required'
    ]);
    exit;
}

// 2. Session check
if (empty($_SESSION['username'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Not logged in'
    ]);
    exit;
}

$username = strtolower(trim((string)$_SESSION['username']));

// 3. Load coaches.json (from /uploads)
$coachesFile = __DIR__ . '/../uploads/coaches.json';

if (!file_exists($coachesFile)) {
    echo json_encode([
        'success' => false,
        'message' => 'coaches.json not found'
    ]);
    exit;
}

$json = file_get_contents($coachesFile);
if ($json === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Unable to read coaches.json'
    ]);
    exit;
}

$coaches = json_decode($json, true);
if (!is_array($coaches)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid coaches.json format'
    ]);
    exit;
}

// 4. Find matching coach
$found = null;
foreach ($coaches as $coach) {
    $u = strtolower(trim((string)($coach['Username'] ?? '')));
    if ($u === $username) {
        $found = $coach;
        break;
    }
}

if (!$found) {
    echo json_encode([
        'success' => false,
        'message' => 'Coach not found for current session user'
    ]);
    exit;
}

// 5. Success
echo json_encode([
    'success' => true,
    'coach'   => $found
]);
exit;
