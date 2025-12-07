<?php
/**
 * /webapi/update_coach.php
 * Update the logged-in coach's profile + image uploads
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

$username   = $_SESSION['username'];
$uploadsDir = __DIR__ . '/../uploads';
$coachesFile = $uploadsDir . '/coaches.json';

if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0775, true);
}

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

// Text fields
$coach['CoachName'] = trim($_POST['coachName'] ?? '');
$coach['Email']     = trim($_POST['email'] ?? '');
$coach['Phone']     = trim($_POST['phone'] ?? '');
$coach['Bio']       = $_POST['bio'] ?? "";

// Specializations (JSON array in POST field "specializations")
if (isset($_POST['specializations'])) {
    $raw = json_decode($_POST['specializations'], true);
    if (is_array($raw)) {
        $clean = array_map('trim', $raw);
        $clean = array_values(array_filter($clean, fn($v) => $v !== ''));
        $coach['Specializations'] = $clean;
    }
}

// Ensure Files array
if (empty($coach['Files']) || !is_array($coach['Files'])) {
    $coach['Files'] = [];
}

$fileSlots = ['Profile','Before','After','Certificate'];

// Handle deletions: deleteSlots[] array
$deleteSlots = $_POST['deleteSlots'] ?? [];
if (!is_array($deleteSlots)) {
    $deleteSlots = [$deleteSlots];
}
foreach ($deleteSlots as $slot) {
    $slot = trim($slot);
    if ($slot === '' || !isset($coach['Files'][$slot])) continue;
    $oldFile = $uploadsDir . '/' . basename($coach['Files'][$slot]);
    if (file_exists($oldFile)) {
        @unlink($oldFile);
    }
    unset($coach['Files'][$slot]);
}

// Helper to save uploaded image
function saveImageSlot($slot, &$coach, $uploadsDir, $username) {
    if (!isset($_FILES['files']['name'][$slot]) ||
        $_FILES['files']['error'][$slot] !== UPLOAD_ERR_OK) {
        return;
    }

    $tmp = $_FILES['files']['tmp_name'][$slot];
    $origName = $_FILES['files']['name'][$slot];
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
        return;
    }

    $newName = $username . '_' . $slot . '_' . time() . '.' . $ext;
    $dest = $uploadsDir . '/' . $newName;

    if (!move_uploaded_file($tmp, $dest)) {
        return;
    }

    // Delete old file if exists
    if (!empty($coach['Files'][$slot])) {
        $old = $uploadsDir . '/' . basename($coach['Files'][$slot]);
        if (file_exists($old)) @unlink($old);
    }

    $coach['Files'][$slot] = $newName;
}

// Process new uploads
foreach ($fileSlots as $slot) {
    saveImageSlot($slot, $coach, $uploadsDir, $username);
}

// Save JSON
if (file_put_contents($coachesFile, json_encode($coaches, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true, 'message' => 'Profile updated']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to write coaches.json']);
}
exit;
