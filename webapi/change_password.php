<?php
/**
 * /webapi/change_password.php
 * Change password for the logged-in coach
 */

header('Content-Type: application/json; charset=utf-8');

$origin = 'https://globalcarnivorecoaches.onrender.com';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

session_start();

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$current = $data['currentPassword'] ?? '';
$new     = $data['newPassword'] ?? '';

if ($current === '' || $new === '') {
    echo json_encode(['success' => false, 'message' => 'Both current and new passwords are required']);
    exit;
}
if (strlen($new) < 8) {
    echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters']);
    exit;
}

$username   = $_SESSION['username'];
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

// Find coach
$index = null;
foreach ($coaches as $i => $c) {
    if (strcasecmp($c['Username'] ?? '', $username) === 0) {
        $index = $i;
        break;
    }
}
if ($index === null) {
    echo json_encode(['success' => false, 'message' => 'Coach not found']);
    exit;
}

$coach =& $coaches[$index];

// Verify current password
if (empty($coach['Password']) || !password_verify($current, $coach['Password'])) {
    echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
    exit;
}

// Update hash
$coach['Password'] = password_hash($new, PASSWORD_DEFAULT);

if (file_put_contents($coachesFile, json_encode($coaches, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save password']);
}
exit;
