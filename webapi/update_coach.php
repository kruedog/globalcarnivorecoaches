<?php
// update_coach.php — FINAL JSON-SAFE VERSION (Dec 2025)
// Uses /data/uploads/coaches.json + /data/uploads for files

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Make sure PHP errors don't break JSON
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

// We accept either username OR email (profile.html sends email)
$postedUsername = trim($_POST['username'] ?? '');
$postedEmail    = trim($_POST['email'] ?? '');

if ($postedUsername === '' && $postedEmail === '') {
    echo json_encode(['success' => false, 'message' => 'Missing login details']);
    exit;
}

$coachesFile = '/data/uploads/coaches.json';
$uploadsDir  = '/data/uploads/';

// Ensure dirs/files exist
if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0775, true);
}

if (!file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'Coaches file missing']);
    exit;
}

$json = file_get_contents($coachesFile);
$coaches = json_decode($json, true);
if (!is_array($coaches)) {
    $coaches = [];
}

// Find coach by username OR email (case-insensitive for email)
$index = null;
foreach ($coaches as $i => $c) {
    if (!is_array($c)) continue;

    if ($postedUsername !== '' && isset($c['Username']) && $c['Username'] === $postedUsername) {
        $index = $i;
        break;
    }

    if ($postedEmail !== '' && isset($c['Email']) && strcasecmp($c['Email'], $postedEmail) === 0) {
        $index = $i;
        break;
    }
}

if ($index === null) {
    echo json_encode(['success' => false, 'message' => 'Coach not found']);
    exit;
}

$coach = $coaches[$index];

// ---------- Update text fields ----------
$coach['CoachName'] = trim($_POST['coachName'] ?? ($coach['CoachName'] ?? ''));
$coach['Phone']     = trim($_POST['phone']     ?? ($coach['Phone']     ?? ''));
$coach['Bio']       = trim($_POST['bio']       ?? ($coach['Bio']       ?? ''));

// Specializations (JSON array string from profile.html)
if (isset($_POST['specializations'])) {
    $specs = json_decode($_POST['specializations'], true);
    if (is_array($specs)) {
        $coach['Specializations'] = $specs;
    }
}

// Ensure Files array
if (!isset($coach['Files']) || !is_array($coach['Files'])) {
    $coach['Files'] = [];
}

// ---------- Handle deletions ----------
if (!empty($_POST['delete']) && is_array($_POST['delete'])) {
    foreach ($_POST['delete'] as $type) {
        if (!empty($coach['Files'][$type])) {
            $oldFile = $uploadsDir . $coach['Files'][$type];
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
            unset($coach['Files'][$type]);
        }
    }
}

// ---------- Handle new uploads ----------
if (!empty($_FILES['files']) && !empty($_POST['imageType'])) {
    $files = $_FILES['files'];
    $types = $_POST['imageType'];

    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

        $type = $types[$i] ?? '';
        if ($type === '') continue;

        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'])) continue;

        $baseUsername = $coach['Username'] ?? ('coach_' . $index);
        $safeName = $baseUsername . '_' . time() . '_' . $i . '.' . $ext;
        $dest = $uploadsDir . $safeName;

        if (!move_uploaded_file($files['tmp_name'][$i], $dest)) {
            // If move fails, skip but do NOT break JSON
            continue;
        }

        // If replacing existing file of this type, delete old
        if (!empty($coach['Files'][$type])) {
            $old = $uploadsDir . $coach['Files'][$type];
            if (is_file($old)) {
                @unlink($old);
            }
        }

        // Store filename only
        $coach['Files'][$type] = $safeName;
    }
}

// ---------- Save back to JSON ----------
$coaches[$index] = $coach;
file_put_contents($coachesFile, json_encode($coaches, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo json_encode(['success' => true, 'coach' => $coach]);
exit;
