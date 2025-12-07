<?php
// webapi/change_password.php
// POST JSON: { currentPassword, newPassword }

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://globalcarnivorecoaches.onrender.com');
header('Access-Control-Allow-Credentials: true);

function send_json(array $p): void {
    echo json_encode($p);
    exit;
}

if (empty($_SESSION['username'])) {
    send_json([
        'success' => false,
        'message' => 'Not logged in',
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json([
        'success' => false,
        'message' => 'POST required',
    ]);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    send_json([
        'success' => false,
        'message' => 'Invalid JSON body',
    ]);
}

$currentPassword = (string)($data['currentPassword'] ?? '');
$newPassword     = (string)($data['newPassword'] ?? '');

if ($currentPassword === '' || $newPassword === '') {
    send_json([
        'success' => false,
        'message' => 'Both current and new password are required',
    ]);
}

$username = $_SESSION['username'];
$path     = __DIR__ . '/../uploads/coaches.json';

if (!file_exists($path)) {
    send_json([
        'success' => false,
        'message' => 'coaches.json not found',
    ]);
}

$rawJson = file_get_contents($path);
$coaches = json_decode($rawJson, true);
if (!is_array($coaches)) {
    send_json([
        'success' => false,
        'message' => 'Invalid coaches.json',
    ]);
}

$foundIndex = null;
foreach ($coaches as $i => $coach) {
    $u = $coach['Username'] ?? $coach['username'] ?? '';
    if ($u !== '' && strcasecmp($u, $username) === 0) {
        $foundIndex = $i;
        break;
    }
}

if ($foundIndex === null) {
    send_json([
        'success' => false,
        'message' => 'Coach not found',
    ]);
}

$coach = $coaches[$foundIndex];
$storedHash = $coach['Password'] ?? $coach['password'] ?? '';

$valid = false;
if (is_string($storedHash) && $storedHash !== '' && str_starts_with($storedHash, '$')) {
    if (password_verify($currentPassword, $storedHash)) {
        $valid = true;
    }
} else {
    if ($currentPassword === $storedHash) {
        $valid = true;
    }
}

if (!$valid) {
    send_json([
        'success' => false,
        'message' => 'Current password is incorrect',
    ]);
}

// Set new hash
$coaches[$foundIndex]['Password'] = password_hash($newPassword, PASSWORD_DEFAULT);

// Save file
if (file_put_contents($path, json_encode($coaches, JSON_PRETTY_PRINT)) === false) {
    send_json([
        'success' => false,
        'message' => 'Failed to write coaches.json',
    ]);
}

send_json([
    'success' => true,
    'message' => 'Password updated',
]);
