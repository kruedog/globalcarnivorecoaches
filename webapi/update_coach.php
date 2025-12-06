<?php
// update_coach.php — Debug Version (Final Fix Candidate)
// Logs full upload behavior so we can inspect what's happening

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

function respond($success, $message = '', $extra = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

/* ───────────────────────────────
   DEBUG LOGGING
   will write files in /data/uploads/
──────────────────────────────── */
$debugDir = '/data/uploads/';
if (!file_exists($debugDir)) {
    @mkdir($debugDir, 0777, true);
}
file_put_contents($debugDir . 'debug_FILES.log', print_r($_FILES, true) . "\n\n", FILE_APPEND);
file_put_contents($debugDir . 'debug_POST.log',  print_r($_POST, true) . "\n\n", FILE_APPEND);
if (!is_writable($debugDir)) {
    file_put_contents($debugDir . 'debug_perm.log', "Uploads directory NOT writable\n", FILE_APPEND);
}

/* ───────────────────────────────
   BASIC INPUT VALIDATION
──────────────────────────────── */
if (empty($_POST['username'])) {
    respond(false, 'Missing username');
}
$username = trim($_POST['username']);

/* ───────────────────────────────
   LOAD COACHES
──────────────────────────────── */
$coachesFile = __DIR__ . '/coaches.json';
if (!file_exists($coachesFile)) {
    respond(false, 'coaches.json missing');
}

$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) {
    respond(false, 'Invalid coaches.json');
}

/* ───────────────────────────────
   FIND COACH ENTRY
──────────────────────────────── */
$ci = null;
foreach ($coaches as $i => $c) {
    if (isset($c['Username']) && strtolower($c['Username']) === strtolower($username)) {
        $ci = $i;
        break;
    }
}
if ($ci === null) {
    respond(false, 'Coach not found');
}

/* ───────────────────────────────
   UPDATE BASIC FIELDS
──────────────────────────────── */
$coaches[$ci]['CoachName'] = trim($_POST['coachName'] ?? '');
$coaches[$ci]['Email']     = trim($_POST['email'] ?? '');
$coaches[$ci]['Phone']     = trim($_POST['phone'] ?? '');
$coaches[$ci]['Bio']       = trim($_POST['bio'] ?? '');

if (isset($_POST['specializations'])) {
    $specs = json_decode($_POST['specializations'], true);
    $coaches[$ci]['Specializations'] = is_array($specs) ? $specs : [];
}

if (!isset($coaches[$ci]['Files']) || !is_array($coaches[$ci]['Files'])) {
    $coaches[$ci]['Files'] = [];
}

/* ───────────────────────────────
   PATHS
──────────────────────────────── */
$diskUploadPath = '/data/uploads/';  // Persistent disk
$publicPrefix   = 'uploads/';        // Web path foundation

/* ───────────────────────────────
   HANDLE DELETE REQUESTS
──────────────────────────────── */
if (!empty($_POST['delete'])) {
    $delTypes = is_array($_POST['delete']) ? $_POST['delete'] : [$_POST['delete']];
    foreach ($delTypes as $type) {
        if (!empty($coaches[$ci]['Files'][$type])) {
            $basename = basename($coaches[$ci]['Files'][$type]);
            $diskFile = $diskUploadPath . $basename;
            if (is_file($diskFile)) {
                @unlink($diskFile);
            }
        }
        unset($coaches[$ci]['Files'][$type]);
    }
}

/* ───────────────────────────────
   HANDLE NEW UPLOADS (nested structure)
──────────────────────────────── */
if (
    isset($_FILES['files']) &&
    isset($_FILES['files']['name']) &&
    is_array($_FILES['files']['name'])
) {
    foreach ($_FILES['files']['name'] as $type => $origName) {
        if (!$origName) continue;

        $error   = $_FILES['files']['error'][$type];
        $tmpPath = $_FILES['files']['tmp_name'][$type];

        if ($error !== UPLOAD_ERR_OK || !$tmpPath || !is_uploaded_file($tmpPath)) {
            continue;
        }

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!$ext) $ext = 'jpg';

        $safeUser = preg_replace('/[^a-z0-9_\-]+/i', '_', $username);
        $safeType = preg_replace('/[^a-z0-9_\-]+/i', '_', $type);
        $newName  = "{$safeUser}_{$safeType}_" . time() . ".$ext";

        $diskFile = $diskUploadPath . $newName;
        $public   = $publicPrefix . $newName;

        if (move_uploaded_file($tmpPath, $diskFile)) {
            $coaches[$ci]['Files'][$type] = $public;
        } else {
            file_put_contents($debugDir . 'debug_move_fail.log',
                "Failed to move file $tmpPath => $diskFile\n", FILE_APPEND);
        }
    }
}

/* ───────────────────────────────
   SAVE CHANGES
──────────────────────────────── */
$jsonOut = json_encode($coaches, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($jsonOut === false) {
    respond(false, 'JSON error: ' . json_last_error_msg());
}

if (file_put_contents($coachesFile, $jsonOut) === false) {
    respond(false, 'Failed to write coaches.json');
}

/* ───────────────────────────────
   SUCCESS RESPONSE
──────────────────────────────── */
respond(true, 'Profile updated', ['coach' => $coaches[$ci]]);
