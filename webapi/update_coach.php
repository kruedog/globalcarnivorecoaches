<?php
/**
 * update_coach.php — FINAL STABLE VERSION
 * Global Carnivore Coaches — December 2025
 *
 * Saves:
 * ✔ CoachName / Email / Phone / Bio / Specializations
 * ✔ Profile / Before / After / Certificate images
 * ✔ Deletes replaced images
 *
 * Reads/writes: /data/uploads/coaches.json (persistent disk)
 */

header('Content-Type: application/json');

// === CORS SAFETY (restrict to known origins) ===
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origins = [
    'http://kruedog.ddns.net:8080',
    'https://globalcarnivorecoaches.onrender.com'
];

if (in_array($origin, $allowed_origins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
}

// Required for login session access
session_start();

// === AUTH CHECK ===
if (empty($_SESSION['Username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Logged-in username is the ONLY username allowed to update
$username = $_SESSION['Username'];

// === DEBUG LOGS (Optional: comment out in production) ===
ini_set('log_errors', 1);
ini_set('error_log', '/data/uploads/php_errors.log');
// file_put_contents('/data/uploads/debug_post.log', print_r($_POST, true), FILE_APPEND);
// file_put_contents('/data/uploads/debug_files.log', print_r($_FILES, true), FILE_APPEND);

// Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$coachesFile = '/data/uploads/coaches.json';
if (!file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'coaches.json missing']);
    exit;
}

$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) {
    echo json_encode(['success' => false, 'message' => 'Invalid coaches.json']);
    exit;
}

// === Locate this coach in JSON ===
$index = null;
foreach ($coaches as $i => $coach) {
    if (($coach['Username'] ?? '') === $username) {
        $index = $i;
        break;
    }
}

if ($index === null) {
    echo json_encode(['success' => false, 'message' => 'Coach not found']);
    exit;
}

// === TEXT FIELDS ===
$map = [
    'coachName' => 'CoachName',
    'email'     => 'Email',
    'phone'     => 'Phone',
    'bio'       => 'Bio',
];

foreach ($map as $posted => $field) {
    if (isset($_POST[$posted])) {
        $coaches[$index][$field] = trim($_POST[$posted]);
    }
}

// Specializations stored as array
if (isset($_POST['specializations'])) {
    $specs = json_decode($_POST['specializations'], true);
    if (is_array($specs)) {
        $coaches[$index]['Specializations'] = array_values($specs);
    }
}

// Ensure Files array exists
if (!isset($coaches[$index]['Files']) || !is_array($coaches[$index]['Files'])) {
    $coaches[$index]['Files'] = [
        'Profile' => null,
        'Before' => null,
        'After' => null,
        'Certificate' => null
    ];
}

// Upload directory
$uploadDir = '/data/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

// Helper to extract grouped file (files[Slot])
function get_upload($slot) {
    if (!empty($_FILES['files']['name'][$slot] ?? null)) {
        return [
            'name' => $_FILES['files']['name'][$slot],
            'tmp'  => $_FILES['files']['tmp_name'][$slot],
            'err'  => $_FILES['files']['error'][$slot],
        ];
    }
    return null;
}

// === IMAGE UPLOAD HANDLING ===
$allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
$slots = ['Profile', 'Before', 'After', 'Certificate'];
$updatedSlots = [];

foreach ($slots as $slot) {
    $file = get_upload($slot);
    if (!$file) continue;

    if ($file['err'] === UPLOAD_ERR_OK && is_uploaded_file($file['tmp'])) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext)) continue;

        $newName = $username . '_' . $slot . '_' . time() . '.' . $ext;
        $target = $uploadDir . $newName;

        if (move_uploaded_file($file['tmp'], $target)) {

            // Delete old file if exists
            $old = $coaches[$index]['Files'][$slot] ?? null;
            if ($old) {
                $oldPath = $uploadDir . $old;
                if (file_exists($oldPath)) @unlink($oldPath);
            }

            $coaches[$index]['Files'][$slot] = $newName;
            $updatedSlots[] = $slot;
        }
    }
}

// === SAVE BACK TO DISK ===
if (file_put_contents($coachesFile, json_encode($coaches, JSON_PRETTY_PRINT)) === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to update file']);
    exit;
}

// === SUCCESS ===
echo json_encode([
    'success' => true,
    'message' => 'Profile updated',
    'updated_slots' => $updatedSlots,
    'files' => $coaches[$index]['Files']
]);
exit;
