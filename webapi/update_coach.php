<?php
// update_coach.php — FINAL (Dec 2025)
// Saves profile changes + uploads to persistent disk

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Ensure POST with username
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Invalid method']);
    exit;
}

$username = trim($_POST['username'] ?? '');
if ($username === '') {
    echo json_encode(['success'=>false,'message'=>'Missing username']);
    exit;
}

$coachesFile = '/data/uploads/coaches.json';
$uploadsDir  = '/data/uploads/';

if (!file_exists($coachesFile)) {
    echo json_encode(['success'=>false,'message'=>'Coaches file missing']);
    exit;
}

// Load and decode JSON
$json = file_get_contents($coachesFile);
$coaches = json_decode($json, true);
if (!is_array($coaches)) $coaches = [];

// Find coach record
$index = array_search($username, array_column($coaches, 'Username'));
if ($index === false) {
    echo json_encode(['success'=>false,'message'=>'Coach not found']);
    exit;
}

$coach = $coaches[$index];

// Update basic fields
$coach['CoachName'] = trim($_POST['coachName'] ?? $coach['CoachName']);
$coach['Phone']     = trim($_POST['phone']     ?? $coach['Phone']);
$coach['Bio']       = trim($_POST['bio']       ?? $coach['Bio']);

// Update specializations
if (isset($_POST['specializations'])) {
    $specs = json_decode($_POST['specializations'], true);
    if (is_array($specs)) $coach['Specializations'] = $specs;
}

// Ensure Files array exists
if (!isset($coach['Files']) || !is_array($coach['Files'])) {
    $coach['Files'] = [];
}

// Handle deletions from UI
if (!empty($_POST['delete']) && is_array($_POST['delete'])) {
    foreach ($_POST['delete'] as $type) {
        if (!empty($coach['Files'][$type])) {
            $oldFile = $uploadsDir . $coach['Files'][$type];
            if (file_exists($oldFile)) unlink($oldFile);
            unset($coach['Files'][$type]);
        }
    }
}

// Handle new uploads
if (!empty($_FILES['files']) && !empty($_POST['imageType'])) {
    $files = $_FILES['files'];
    $types = $_POST['imageType'];

    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

        $type = $types[$i];
        $ext  = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'])) continue;

        $safeName = $username . '_' . time() . '_' . $i . '.' . $ext;
        $dest = $uploadsDir . $safeName;

        if (!move_uploaded_file($files['tmp_name'][$i], $dest)) continue;

        // If replacing existing file, delete old one
        if (!empty($coach['Files'][$type])) {
            $old = $uploadsDir . $coach['Files'][$type];
            if (file_exists($old)) unlink($old);
        }

        $coach['Files'][$type] = $safeName;
    }
}

// Save JSON
$coaches[$index] = $coach;
file_put_contents($coachesFile, json_encode($coaches, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

echo json_encode(['success'=>true, 'coach'=>$coach]);
exit;
?>
