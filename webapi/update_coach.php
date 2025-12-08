<?php
/**
 * update_coach.php — FINAL COOKIE-COMPATIBLE VERSION
 * Global Carnivore Coaches — Dec 2025
 *
 * Saves:
 * - Name / Email / Phone / Bio / Specializations
 * - Image uploads (Profile, Before, After, Certificate)
 * - Deletes old replaced images
 */

// Must be BEFORE session_start()
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => 'globalcarnivorecoaches.onrender.com',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None'
]);

session_start();

// CORS for cookie-based auth
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://globalcarnivorecoaches.onrender.com');
header('Access-Control-Allow-Credentials: true');

// ==== AUTH CHECK ====
if (empty($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$username = $_SESSION['username'];

// Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

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

// ==== FIND RECORD ====
$index = null;
foreach ($coaches as $i => $coach) {
    $u = $coach['Username'] ?? '';
    if ($u !== '' && strcasecmp($u, $username) === 0) {
        $index = $i;
        break;
    }
}
if ($index === null) {
    echo json_encode(['success' => false, 'message' => 'Coach not found']);
    exit;
}

// ==== TEXT FIELDS ====
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

// Specializations as JSON array
if (isset($_POST['specializations'])) {
    $spec = json_decode($_POST['specializations'], true);
    if (is_array($spec)) {
        $coaches[$index]['Specializations'] = array_values($spec);
    }
}

// Ensure Files array exists
if (!isset($coaches[$index]['Files']) || !is_array($coaches[$index]['Files'])) {
    $coaches[$index]['Files'] = [
        'Profile'     => null,
        'Before'      => null,
        'After'       => null,
        'Certificate' => null
    ];
}

// Upload destination
$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

// Extract file input
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

// ==== FILE UPLOADS ====
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

// ==== SAVE JSON ====
if (file_put_contents($coachesFile, json_encode($coaches, JSON_PRETTY_PRINT)) === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to write updated data']);
    exit;
}

// ==== SUCCESS ====
echo json_encode([
    'success' => true,
    'message' => 'Profile updated',
    'updated_slots' => $updatedSlots,
    'files' => $coaches[$index]['Files']
]);
exit;
