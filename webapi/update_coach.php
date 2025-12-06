<?php
// update_coach.php — stateless update + image upload

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

function fail($msg) {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

function ok($c) {
    echo json_encode(['success' => true, 'coach' => $c]);
    exit;
}

$username = trim($_POST['username'] ?? '');
if ($username === '') fail('Missing username');

$coachesFile = __DIR__ . '/coaches.json';
if (!file_exists($coachesFile)) fail('coaches.json missing');
$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) fail('Invalid coaches.json');

// Find coach
$ci = null;
foreach ($coaches as $i => $c) {
    if (isset($c['Username']) &&
        strtolower($c['Username']) === strtolower($username)) {
        $ci = $i;
        break;
    }
}
if ($ci === null) fail('Coach not found');

// Update text fields
$coaches[$ci]['CoachName'] = trim($_POST['coachName'] ?? '');
$coaches[$ci]['Email']     = trim($_POST['email'] ?? '');
$coaches[$ci]['Phone']     = trim($_POST['phone'] ?? '');
$coaches[$ci]['Bio']       = trim($_POST['bio'] ?? '');

$specs = json_decode($_POST['specializations'] ?? '[]', true);
$coaches[$ci]['Specializations'] = is_array($specs) ? $specs : [];

if (!is_array($coaches[$ci]['Files'])) $coaches[$ci]['Files'] = [];

// Upload paths
$disk = "/data/uploads/";
$public = "uploads/";
@mkdir($disk, 0775, true);

// Handle deletions
if (!empty($_POST['delete'])) {
    $dels = is_array($_POST['delete']) ? $_POST['delete'] : [$_POST['delete']];
    foreach ($dels as $t) {
        if (!empty($coaches[$ci]['Files'][$t])) {
            @unlink($disk . basename($coaches[$ci]['Files'][$t]));
        }
        unset($coaches[$ci]['Files'][$t]);
    }
}

// Handle upload replacements
if (!empty($_FILES['files']) && !empty($_POST['imageType'])) {
    for ($i=0; $i<count($_POST['imageType']); $i++) {
        $type = $_POST['imageType'][$i];
        if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;

        $tmp = $_FILES['files']['tmp_name'][$i];
        $orig = $_FILES['files']['name'][$i];
        if (!is_uploaded_file($tmp)) continue;

        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!$ext) $ext = 'jpg';

        $fname = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $username . '_' . $type)) . "_" . time() . "." . $ext;
        $dest = $disk . $fname;

        if (move_uploaded_file($tmp, $dest)) {
            $coaches[$ci]['Files'][$type] = $public . $fname;
        }
    }
}

// Save JSON back
if (file_put_contents($coachesFile, json_encode($coaches, JSON_PRETTY_PRINT)) === false) {
    fail('Write failed');
}

ok($coaches[$ci]);
