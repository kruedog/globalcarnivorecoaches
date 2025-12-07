<?php
/**
 * update_coach.php — FINAL VERSION
 * Secure profile updates for logged-in coaches only
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

// Must be logged in
if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$username = $_SESSION['username'];

$uploadsDir = __DIR__ . '/../uploads';
$coachesFile = $uploadsDir . '/coaches.json';

// Ensure uploads folder exists
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0775, true);
}

// Validate payload
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Load coach DB
if (!file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'Database missing']);
    exit;
}

$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) {
    echo json_encode(['success' => false, 'message' => 'Invalid database']);
    exit;
}

// Find logged-in coach entry
$index = null;
foreach ($coaches as $i => $c) {
    if (strcasecmp($c['Username'], $username) === 0) {
        $index = $i;
        break;
    }
}

if ($index === null) {
    echo json_encode(['success' => false, 'message' => 'User not found in database']);
    exit;
}

$coach =& $coaches[$index];

// Update text fields
$coach['CoachName'] = trim($_POST['coachName'] ?? '');
$coach['Email'] = trim($_POST['email'] ?? '');
$coach['Phone'] = trim($_POST['phone'] ?? '');
$coach['Bio'] = $_POST['bio'] ?? "";

// Normalize specializations (array)
if (!empty($_POST['specializations'])) {
    $raw = json_decode($_POST['specializations'], true);
    if (is_array($raw)) {
        $coach['Specializations'] = array_values(
            array_filter(array_map('trim', $raw))
        );
    }
}

// Guarantee Files object
if (!isset($coach['Files']) || !is_array($coach['Files'])) {
    $coach['Files'] = [];
}

$fileSlots = ['Profile', 'Before', 'After', 'Certificate'];

/**
 * Save uploaded image
 */
function saveImage($slot, &$coach, $uploadsDir, $username)
{
    if (!isset($_FILES["files"]["name"][$slot]) ||
        $_FILES["files"]["error"][$slot] !== UPLOAD_ERR_OK) {
        return;
    }

    $tmp = $_FILES["files"]["tmp_name"][$slot];
    $ext = strtolower(pathinfo($_FILES["files"]["name"][$slot], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) return;

    // Unique file naming per coach & slot
    $newName = "{$username}_{$slot}_" . time() . ".{$ext}";
    $destPath = $uploadsDir . '/' . $newName;

    if (move_uploaded_file($tmp, $destPath)) {
        // Remove old file if exists
        if (!empty($coach['Files'][$slot])) {
            $oldPath = $uploadsDir . '/' . basename($coach['Files'][$slot]);
            if (file_exists($oldPath)) unlink($oldPath);
        }
        // Store relative path
        $coach['Files'][$slot] = $newName;
    }
}

/**
 * Remove file if requested
 */
if (!empty($_POST['DeleteFile'])) {
    $slot = $_POST['DeleteFile'];
    if (isset($coach['Files'][$slot])) {
        $fp = $uploadsDir . '/' . basename($coach['Files'][$slot]);
        if (file_exists($fp)) unlink($fp);
        unset($coach['Files'][$slot]);
    }
}

// Save new uploads
foreach ($fileSlots as $slot) {
    saveImage($slot, $coach, $uploadsDir, $username);
}

// Save database back to disk
if (file_put_contents($coachesFile, json_encode($coaches, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true, 'message' => 'Profile updated']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed writing database']);
}
exit;
