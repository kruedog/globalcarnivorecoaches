<?php
// update_coach.php — Render + Persistent Disk + Email-safe JSON output

// Force JSON output only
ini_set('display_errors', '0');              // don't dump HTML errors into output
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

function respond($success, $message = '', $extra = []) {
    $payload = array_merge(['success' => $success, 'message' => $message], $extra);
    echo json_encode($payload);
    exit;
}

// --- BASIC VALIDATION ---
if (empty($_POST['username'])) {
    respond(false, 'Username required');
}

$username = trim($_POST['username']);
$coachesFile = __DIR__ . '/coaches.json';

// --- LOAD COACHES ---
if (!file_exists($coachesFile)) {
    respond(false, 'coaches.json not found');
}

$rawJson = file_get_contents($coachesFile);
$coaches = json_decode($rawJson, true);

if (!is_array($coaches)) {
    respond(false, 'Invalid coaches.json');
}

// --- FIND COACH ---
$index = null;
foreach ($coaches as $i => $coach) {
    if (isset($coach['Username']) && strcasecmp($coach['Username'], $username) === 0) {
        $index = $i;
        break;
    }
}

if ($index === null) {
    respond(false, 'Coach not found');
}

// --- UPDATE SIMPLE FIELDS ---
$coaches[$index]['CoachName'] = trim($_POST['coachName'] ?? '');
$coaches[$index]['Email']     = trim($_POST['email'] ?? '');
$coaches[$index]['Phone']     = trim($_POST['phone'] ?? '');
$coaches[$index]['Bio']       = trim($_POST['bio'] ?? '');

if (isset($_POST['specializations'])) {
    $spec = json_decode($_POST['specializations'], true);
    $coaches[$index]['Specializations'] = is_array($spec) ? $spec : [];
} else {
    $coaches[$index]['Specializations'] = $coaches[$index]['Specializations'] ?? [];
}

// --- FILE PATH SETUP ---
$diskUploadPath = '/data/uploads/';   // persistent disk mount
$publicBase     = 'uploads/';         // what the browser will use: /uploads/filename.jpg

if (!is_dir($diskUploadPath)) {
    @mkdir($diskUploadPath, 0777, true);
}

// Ensure Files array exists
if (!isset($coaches[$index]['Files']) || !is_array($coaches[$index]['Files'])) {
    $coaches[$index]['Files'] = [];
}

// --- HANDLE DELETES ---
$deleteTypes = [];
if (!empty($_POST['delete'])) {
    if (is_array($_POST['delete'])) {
        $deleteTypes = $_POST['delete'];
    } else {
        $deleteTypes = [$_POST['delete']];
    }
}

foreach ($deleteTypes as $type) {
    if (!isset($coaches[$index]['Files'][$type])) continue;

    $stored = $coaches[$index]['Files'][$type]; // e.g. "uploads/somefile.jpg"
    $basename = basename($stored);
    $diskPath = $diskUploadPath . $basename;

    if (is_file($diskPath)) {
        @unlink($diskPath);
    }

    unset($coaches[$index]['Files'][$type]);
}

// --- HANDLE NEW UPLOADS ---
if (!empty($_FILES['files']) && !empty($_FILES['files']['name']) && is_array($_FILES['files']['name'])) {
    $names  = $_FILES['files']['name'];
    $errors = $_FILES['files']['error'];
    $tmp    = $_FILES['files']['tmp_name'];

    $imageTypes = $_POST['imageType'] ?? [];

    foreach ($names as $idx => $originalName) {
        if (!isset($errors[$idx]) || $errors[$idx] !== UPLOAD_ERR_OK) {
            continue;
        }

        $type = $imageTypes[$idx] ?? null; // "Profile", "Before", etc.
        if (!$type) continue;

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!$ext) $ext = 'jpg';

        $safeUser = preg_replace('/[^a-z0-9_\-]+/i', '_', $username);
        $safeType = preg_replace('/[^a-z0-9_\-]+/i', '_', $type);
        $newFile  = $safeUser . '_' . $safeType . '_' . time() . '.' . $ext;

        $targetDiskPath = $diskUploadPath . $newFile;

        if (move_uploaded_file($tmp[$idx], $targetDiskPath)) {
            // Store PUBLIC path in JSON
            $coaches[$index]['Files'][$type] = $publicBase . $newFile;
        }
    }
}

// --- SAVE JSON ---
$encoded = json_encode($coaches, JSON_PRETTY_PRINT);
if ($encoded === false) {
    respond(false, 'JSON encoding failed');
}

if (file_put_contents($coachesFile, $encoded) === false) {
    respond(false, 'Failed to write coaches.json');
}

respond(true, 'Profile updated');
