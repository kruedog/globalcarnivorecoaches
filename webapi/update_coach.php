<?php
// update_coach.php — FINAL RENDER VERSION
// Email Support • Persistent Disk • Correct Image Indexing • JSON-only output

// Never print HTML errors into JSON
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

function respond($success, $message = '', $extra = []) {
    echo json_encode(array_merge(['success'=>$success,'message'=>$message], $extra));
    exit;
}

// === VALIDATE INPUT ===
if (empty($_POST['username'])) respond(false, 'Missing username');
$username = trim($_POST['username']);

// === LOAD COACHES FILE ===
$coachesFile = __DIR__ . '/coaches.json';
if (!file_exists($coachesFile)) respond(false, 'coaches.json missing');

$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) respond(false, 'coaches.json corrupt');

// === FIND COACH ===
$i = null;
foreach ($coaches as $idx => $c) {
    if (isset($c['Username']) && strtolower($c['Username']) === strtolower($username)) {
        $i = $idx;
        break;
    }
}
if ($i === null) respond(false, 'Coach not found');

// === UPDATE BASIC FIELDS ===
$coaches[$i]['CoachName'] = trim($_POST['coachName'] ?? '');
$coaches[$i]['Email']     = trim($_POST['email'] ?? '');
$coaches[$i]['Phone']     = trim($_POST['phone'] ?? '');
$coaches[$i]['Bio']       = trim($_POST['bio'] ?? '');

if (isset($_POST['specializations'])) {
    $spec = json_decode($_POST['specializations'], true);
    $coaches[$i]['Specializations'] = is_array($spec) ? $spec : [];
}

// === FILE STORAGE SETUP ===
// Persistent disk mount in Render
$diskUploadPath = '/data/uploads/';
$publicPathBase = 'uploads/'; // stored in JSON and used by browser: /uploads/file.jpg

if (!is_dir($diskUploadPath)) {
    @mkdir($diskUploadPath, 0777, true);
}

if (!isset($coaches[$i]['Files']) || !is_array($coaches[$i]['Files'])) {
    $coaches[$i]['Files'] = [];
}

// === HANDLE DELETE REQUESTS ===
$deleteTypes = [];
if (!empty($_POST['delete'])) {
    $deleteTypes = is_array($_POST['delete']) ? $_POST['delete'] : [$_POST['delete']];
}

foreach ($deleteTypes as $type) {
    if (!isset($coaches[$i]['Files'][$type])) continue;
    $storedRel = $coaches[$i]['Files'][$type]; // e.g. "uploads/photo.jpg"
    $storedDisk = $diskUploadPath . basename($storedRel);

    if (is_file($storedDisk)) {
        @unlink($storedDisk);
    }
    unset($coaches[$i]['Files'][$type]);
}

// === HANDLE NEW UPLOADS (correct mapping) ===
if (!empty($_FILES['files']) && isset($_POST['imageType'])) {

    $imageTypes = $_POST['imageType'];    // ["Profile", "Before", ...]
    $count = count($_FILES['files']['name']);

    for ($n = 0; $n < $count; $n++) {

        $type = $imageTypes[$n] ?? null;
        if (!$type) continue;

        if ($_FILES['files']['error'][$n] !== UPLOAD_ERR_OK) continue;

        $origName = $_FILES['files']['name'][$n];
        $tmpName  = $_FILES['files']['tmp_name'][$n];

        // Safe extension & filename
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!$ext) $ext = 'jpg';

        $safeUser = preg_replace('/[^a-z0-9_\-]+/i', '_', $username);
        $safeType = preg_replace('/[^a-z0-9_\-]+/i', '_', $type);

        $newFile = $safeUser . "_" . $safeType . "_" . time() . "." . $ext;
        $diskTarget = $diskUploadPath . $newFile;
        $publicTarget = $publicPathBase . $newFile;

        if (move_uploaded_file($tmpName, $diskTarget)) {
            // Save mapping
            $coaches[$i]['Files'][$type] = $publicTarget;
        }
    }
}

// === SAVE JSON BACK ===
$json = json_encode($coaches, JSON_PRETTY_PRINT);
if ($json === false) respond(false, 'JSON encoding failed');

if (file_put_contents($coachesFile, $json) === false) {
    respond(false, 'Failed writing coaches.json');
}

// === SUCCESS RESPONSE ===
respond(true, 'Profile updated', ['coach'=>$coaches[$i]]);
