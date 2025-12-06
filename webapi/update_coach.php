<?php
// update_coach.php — FINAL
// Correct nested $_FILES structure + Render disk uploads

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

function respond($success, $message = '', $extra = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

// --- BASIC INPUT ---
if (empty($_POST['username'])) {
    respond(false, 'Missing username');
}
$username = trim($_POST['username']);

// --- LOAD COACHES ---
$coachesFile = __DIR__ . '/coaches.json';
if (!file_exists($coachesFile)) {
    respond(false, 'coaches.json missing');
}
$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) {
    respond(false, 'Invalid coaches.json');
}

// --- FIND COACH INDEX ---
$idx = null;
foreach ($coaches as $i => $c) {
    if (isset($c['Username']) && strtolower($c['Username']) === strtolower($username)) {
        $idx = $i;
        break;
    }
}
if ($idx === null) {
    respond(false, 'Coach not found');
}

// --- UPDATE BASIC FIELDS ---
$coaches[$idx]['CoachName'] = trim($_POST['coachName'] ?? '');
$coaches[$idx]['Email']     = trim($_POST['email'] ?? '');
$coaches[$idx]['Phone']     = trim($_POST['phone'] ?? '');
$coaches[$idx]['Bio']       = trim($_POST['bio'] ?? '');

if (isset($_POST['specializations'])) {
    $spec = json_decode($_POST['specializations'], true);
    $coaches[$idx]['Specializations'] = is_array($spec) ? $spec : [];
} else {
    if (!isset($coaches[$idx]['Specializations']) || !is_array($coaches[$idx]['Specializations'])) {
        $coaches[$idx]['Specializations'] = [];
    }
}

// --- FILE STORAGE PATHS ---
$diskUploadPath = '/data/uploads/';   // Render persistent disk
$publicBaseDir  = 'uploads/';         // served via /uploads symlink

if (!is_dir($diskUploadPath)) {
    @mkdir($diskUploadPath, 0777, true);
}

if (!isset($coaches[$idx]['Files']) || !is_array($coaches[$idx]['Files'])) {
    $coaches[$idx]['Files'] = [];
}

// --- HANDLE DELETES ---
if (!empty($_POST['delete'])) {
    $delArr = is_array($_POST['delete']) ? $_POST['delete'] : [$_POST['delete']];
    foreach ($delArr as $type) {
        if (!isset($coaches[$idx]['Files'][$type])) continue;

        $relPath  = $coaches[$idx]['Files'][$type]; // e.g. "uploads/thor_Profile_....jpg"
        $diskPath = $diskUploadPath . basename($relPath);

        if (is_file($diskPath)) {
            @unlink($diskPath);
        }
        unset($coaches[$idx]['Files'][$type]);
    }
}

// --- HANDLE NEW UPLOADS ---
// We expect names like files[Profile], files[Before], files[After], files[Certificate]
// PHP maps this to: $_FILES['files']['name']['Profile'], etc.
if (isset($_FILES['files']) && isset($_FILES['files']['name']) && is_array($_FILES['files']['name'])) {
    foreach ($_FILES['files']['name'] as $type => $origName) {
        if (!$origName) continue;

        $error = $_FILES['files']['error'][$type] ?? UPLOAD_ERR_OK;
        if ($error !== UPLOAD_ERR_OK) continue;

        $tmpPath = $_FILES['files']['tmp_name'][$type] ?? null;
        if (!$tmpPath || !is_uploaded_file($tmpPath)) continue;

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!$ext) $ext = 'jpg';

        $safeUser = preg_replace('/[^a-z0-9_\-]+/i', '_', $username);
        $safeType = preg_replace('/[^a-z0-9_\-]+/i', '_', $type);

        $newFile   = $safeUser . '_' . $safeType . '_' . time() . '.' . $ext;
        $diskOut   = $diskUploadPath . $newFile;
        $publicRel = $publicBaseDir . $newFile;

        if (move_uploaded_file($tmpPath, $diskOut)) {
            // Overwrite just this type
            $coaches[$idx]['Files'][$type] = $publicRel;
        }
    }
}

// --- SAVE BACK TO JSON ---
$json = json_encode($coaches, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    respond(false, 'JSON encode error');
}
if (file_put_contents($coachesFile, $json) === false) {
    respond(false, 'Failed to write coaches.json');
}

// --- SUCCESS ---
respond(true, 'Profile updated', ['coach' => $coaches[$idx]]);
