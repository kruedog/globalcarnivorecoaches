<?php
/**
 * /webapi/get_coach.php
 * Returns the logged-in coach's full record
 */

header('Content-Type: application/json; charset=utf-8');

$origin = 'https://globalcarnivorecoaches.onrender.com';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

session_start();

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$username = $_SESSION['username'];

$coachesFile = __DIR__ . '/../uploads/coaches.json';
if (!file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'coaches.json missing']);
    exit;
}

$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) {
    echo json_encode(['success' => false, 'message' => 'Invalid coaches.json']);
    exit;
}

$coach = null;
foreach ($coaches as $c) {
    if (strcasecmp($c['Username'] ?? '', $username) === 0) {
        $coach = $c;
        break;
    }
}

if (!$coach) {
    echo json_encode(['success' => false, 'message' => 'Coach not found']);
    exit;
}

echo json_encode(['success' => true, 'coach' => $coach]);
exit;
