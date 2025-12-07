<?php
// webapi/get_coach.php
// Returns currently logged-in coach using SESSION
// NO PARAMS REQUIRED — session only

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

session_start();

// Must allow cookie-based auth for fetch()
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://globalcarnivorecoaches.onrender.com');
header('Access-Control-Allow-Credentials: true');

function send_json(array $payload): void {
    echo json_encode($payload);
    exit;
}

// Must be logged in
if (empty($_SESSION['username'])) {
    send_json([
        'success' => false,
        'message' => 'Not logged in'
    ]);
}

$username = $_SESSION['username'];

// Load coaches.json from uploads folder
$path = __DIR__ . '/../uploads/coaches.json';
if (!file_exists($path)) {
    send_json([
        'success' => false,
        'message' => 'coaches.json missing'
    ]);
}

$raw = file_get_contents($path);
$data = json_decode($raw, true);
if (!is_array($data)) {
    send_json([
        'success' => false,
        'message' => 'Invalid JSON in coaches.json'
    ]);
}

// Find coach matching session username
$coachFound = null;
foreach ($data as $coach) {
    $u = $coach['Username'] ?? $coach['username'] ?? '';
    if ($u !== '' && strcasecmp($u, $username) === 0) {
        $coachFound = $coach;
        break;
    }
}

if ($coachFound === null) {
    send_json([
        'success' => false,
        'message' => 'Coach not found'
    ]);
}

// Security: remove passwords before sending back
unset($coachFound['Password'], $coachFound['password']);

send_json([
    'success' => true,
    'coach' => $coachFound
]);
