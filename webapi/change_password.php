<?php
// /webapi/change_password.php
// Allows logged-in coach to change own password.

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'POST required'
    ]);
    exit;
}

if (empty($_SESSION['coach_username'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Not logged in'
    ]);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON body'
    ]);
    exit;
}

$currentPassword = (string)($data['currentPassword'] ?? '');
$newPassword     = (string)($data['newPassword'] ?? '');

if ($currentPassword === '' || $newPassword === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Current and new password are required.'
    ]);
    exit;
}

if (strlen($newPassword) < 8) {
    echo json_encode([
        'success' => false,
        'message' => 'New password must be at least 8 characters.'
    ]);
    exit;
}

$COACHES_FILE = __DIR__ . '/../uploads/coaches.json';

if (!file_exists($COACHES_FILE)) {
    echo json_encode([
        'success' => false,
        'message' => 'coaches.json not found'
    ]);
    exit;
}

$coaches = json_decode(file_get_contents($COACHES_FILE), true);
if (!is_array($coaches)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid coaches.json'
    ]);
    exit;
}

$username = strtolower($_SESSION['coach_username']);
$index    = null;

foreach ($coaches as $i => $c) {
    if (strtolower($c['Username'] ?? '') === $username) {
        $index = $i;
        break;
    }
}

if ($index === null) {
    echo json_encode([
        'success' => false,
        'message' => 'Coach not found'
    ]);
    exit;
}

$coach = $coaches[$index];

// Identify password hash field
$hashField = null;
if (!empty($coach['PasswordHash'])) {
    $hashField = 'PasswordHash';
} elseif (!empty($coach['Password'])) {
    $hashField = 'Password';
}

if ($hashField === null || empty($coach[$hashField])) {
    echo json_encode([
        'success' => false,
        'message' => 'Password not set for this coach.'
    ]);
    exit;
}

// Verify existing password
if (!password_verify($currentPassword, $coach[$hashField])) {
    echo json_encode([
        'success' => false,
        'message' => 'Current password is incorrect.'
    ]);
    exit;
}

// Set new hash (always store in PasswordHash)
$newHash = password_hash($newPassword, PASSWORD_DEFAULT);
$coach['PasswordHash'] = $newHash;
unset($coach['Password']); // normalize to PasswordHash only

$coaches[$index] = $coach;
file_put_contents(
    $COACHES_FILE,
    json_encode($coaches, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo json_encode([
    'success' => true,
    'message' => 'Password updated.'
]);
