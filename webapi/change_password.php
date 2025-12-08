<?php
// webapi/change_password.php
// Change logged-in coach’s password

declare(strict_types=1);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => 'globalcarnivorecoaches.onrender.com',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None'
]);

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://globalcarnivorecoaches.onrender.com');
header('Access-Control-Allow-Credentials: true');

function send_json(array $data): void {
    echo json_encode($data);
    exit;
}

if (empty($_SESSION['username'])) {
    send_json(['success' => false, 'message' => 'Not logged in']);
}

$username = $_SESSION['username'];

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$current = (string)($data['currentPassword'] ?? '');
$new     = (string)($data['newPassword'] ?? '');

if ($current === '' || $new === '') {
    send_json(['success' => false, 'message' => 'Missing fields']);
}
if (strlen($new) < 8) {
    send_json(['success' => false, 'message' => 'New password too short (8+ chars)']);
}

$path = __DIR__ . '/../uploads/coaches.json';
if (!file_exists($path)) {
    send_json(['success' => false, 'message' => 'System error (no coaches.json)']);
}

$coaches = json_decode(file_get_contents($path), true);
if (!is_array($coaches)) {
    send_json(['success' => false, 'message' => 'Invalid coaches.json']);
}

$index = null;
foreach ($coaches as $i => $coach) {
    $u = $coach['Username'] ?? '';
    if ($u !== '' && strcasecmp($u, $username) === 0) {
        $index = $i;
        break;
    }
}
if ($index === null) {
    send_json(['success' => false, 'message' => 'Coach not found']);
}

$storedHash = $coaches[$index]['Password'] ?? '';
if (!is_string($storedHash) || $storedHash === '' || !str_starts_with($storedHash, '$')) {
    if ($current !== $storedHash) {
        send_json(['success' => false, 'message' => 'Current password incorrect']);
    }
} else {
    if (!password_verify($current, $storedHash)) {
        send_json(['success' => false, 'message' => 'Current password incorrect']);
    }
}

$newHash = password_hash($new, PASSWORD_DEFAULT);
if ($newHash === false) {
    send_json(['success' => false, 'message' => 'Password hash failed']);
}

$coaches[$index]['Password'] = $newHash;
if (file_put_contents($path, json_encode($coaches, JSON_PRETTY_PRINT)) === false) {
    send_json(['success' => false, 'message' => 'Write failure']);
}

send_json(['success' => true, 'message' => 'Password updated']);
