<?php
// update_coach.php — final robust upload handler with debug_state
// Global Carnivore Coaches

// Do NOT echo warnings to the browser (breaks JSON)
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials', 'true');

function respond($success, $message = '', $extra = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

// ---------- BASIC INPUT ----------
if (empty($_POST['username'])) {
    respond(false, 'Missing username');
}
$username = trim($_POST['username']);

// ---------- LOAD COACHES ----------
$coachesFile = __DIR__ . '/coaches.json';
if (!file_exists($coachesFile)) {
    respond(false, 'coaches.json missing');
}

$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) {
    respond(false, 'Invalid coaches.json');
}

// ---------- FIND COACH ----------
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

// ---------- UPDATE BASIC FIELDS ----------
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

// ---------- PATHS ----------
$diskUploadPath = '/data/uploads/';   // Render disk mount
$publicPrefix   = 'uploads/';         // Stored in JSON

// ---------- HANDLE DELETES ----------
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

// ---------- HELPERS ----------
function save_uploaded_for_type($type, $origName, $tmpPath, $error, &$coachFiles, $username, $diskUploadPath, $publicPrefix) {
    if (!$origName) return;
    if ($error !== UPLOAD_ERR_OK) return;
    if (!$tmpPath || !is_uploaded_file($tmpPath)) return;

    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!$ext) $ext = 'jpg';

    $safeUser = preg_replace('/[^a-z0-9_\-]+/i', '_', $username);
    $safeType = preg_replace('/[^a-z0-9_\-]+/i', '_', $type);
    $newName  = $safeUser . '_' . $safeType . '_' . time() . '.' . $ext;

    $diskFile = rtrim($diskUploadPath, '/') . '/' . $newName;
    $public   = $publicPrefix . $newName;

    if (move_uploaded_file($tmpPath, $diskFile)) {
        $coachFiles[$type] = $public;
    }
}

// ---------- HANDLE UPLOADS (BOTH STYLES) ----------
// Style 1: Nested: $_FILES['files']['name']['Profile']
if (isset($_FILES['files']) &&
    isset($_FILES['files']['name']) &&
    is_array($_FILES['files']['name'])
) {
    foreach ($_FILES['files']['name'] as $type => $origName) {
        save_uploaded_for_type(
            $type,
            $origName,
            $_FILES['files']['tmp_name'][$type] ?? null,
            $_FILES['files']['error'][$type]    ?? UPLOAD_ERR_NO_FILE,
            $coaches[$ci]['Files'],
            $username,
            $diskUploadPath,
            $publicPrefix
        );
    }
}

// Style 2: Flat: $_FILES['files[Profile]'], $_FILES['files[Before]'], etc.
foreach ($_FILES as $field => $fd) {
    if ($field === 'files') continue; // already handled above
    if (!preg_match('/^files\[(.+)\]$/', $field, $m)) continue;
    $type = $m[1];

    $origName = $fd['name']     ?? '';
    $tmpPath  = $fd['tmp_name'] ?? '';
    $error    = $fd['error']    ?? UPLOAD_ERR_NO_FILE;

    save_uploaded_for_type(
        $type,
        $origName,
        $tmpPath,
        $error,
        $coaches[$ci]['Files'],
        $username,
        $diskUploadPath,
        $publicPrefix
    );
}

// ---------- DEBUG SNAPSHOT (viewable at /uploads/debug_state.json) ----------
$debugState = [
    'POST'   => $_POST,
    'FILES'  => $_FILES,
    'coach'  => $coaches[$ci],
    'time'   => date('c'),
];
@file_put_contents($diskUploadPath . 'debug_state.json', json_encode($debugState, JSON_PRETTY_PRINT));

// ---------- SAVE COACHES ----------
$jsonOut = json_encode($coaches, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($jsonOut === false) {
    respond(false, 'JSON encode error: ' . json_last_error_msg());
}
if (file_put_contents($coachesFile, $jsonOut) === false) {
    respond(false, 'Failed to write coaches.json');
}

// ---------- SUCCESS ----------
respond(true, 'Profile updated', ['coach' => $coaches[$ci]]);
