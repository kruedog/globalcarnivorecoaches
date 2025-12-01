<?php
// webapi/update_coach.php — FINAL RENDER STARTER VERSION (2025)
// Persistent Uploads)
// Works on Render Starter + your NAS

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$DIR = __DIR__;

// PERSISTENT UPLOAD FOLDER — survives restarts forever on Render Starter
$UPLOAD_DIR = '/opt/render/project/src/webapi/uploads';

// Fallback for local NAS testing
if (!is_dir($UPLOAD_DIR)) {
    $UPLOAD_DIR = __DIR__ . '/uploads';
}

// Make sure folder exists and is writable
if (!is_dir($UPLOAD_DIR)) {
    mkdir($UPLOAD_DIR, 0755, true);
}
if (!is_writable($UPLOAD_DIR)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Upload directory not writable']);
    exit;
}

// Load current coach data (you probably have this already)
$email = $_POST['email'] ?? '';
if (!$email) {
    echo json_encode(['success' => false, 'message' => 'No email provided']);
    exit;
}

$coachesFile = "$DIR/coaches.json";
$coaches = file_exists($coachesFile) ? json_decode(file_get_contents($coachesFile), true) : [];
$coachIndex = null;
foreach ($coaches as $i => $c) {
    if (strtolower($c['Email'] ?? '') === strtolower($email)) {
        $coachIndex = $i;
        break;
    }
}
if ($coachIndex === null) {
    echo json_encode(['success' => false, 'message' => 'Coach not found']);
    exit;
}
$coach = &$coaches[$coachIndex];

// Update text fields
$fields = ['CoachName','Phone','Bio','Specializations','password'];
foreach ($fields as $f) {
    if (isset($_POST[$f]) && $_POST[$f] !== '') {
        if ($f === 'password') {
            $coach[$f] = password_hash($_POST[$f], PASSWORD_DEFAULT);
        } else {
            $coach[$f] = trim($_POST[$f]);
        }
    }
}

// Handle file uploads
$allowedTypes = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
$maxSize = 10 * 1024 * 1024; // 10 MB
$fileTypes = ['Profile','Before','After','Certificate'];

foreach ($fileTypes as $type) {
    if (!empty($_FILES['files']['name'][0])) {
        foreach ($_FILES['files']['name'] as $k => $name) {
            if ($_FILES['files']['error'][$k] !== 0) continue;

            $tmp = $_FILES['files']['tmp_name'][$k];
            $size = $_FILES['files']['size'][$k];
            $mime = $_FILES['files']['type'][$k];

            if ($size > $maxSize || !in_array($mime, $allowedTypes)) continue;

            $ext = pathinfo($name, PATHINFO_EXTENSION) ?: 'jpg';
            $safeName = $email . '_' . $type . '.' . $ext;
            $dest = "$UPLOAD_DIR/$safeName";

            if (move_uploaded_file($tmp, $dest)) {
                $coach['Files'][$type] = "uploads/$safeName"; // URL path
                // Optional: delete old file if exists
                if (!empty($coach['Files'][$type]) && $coach['Files'][$type] !== "uploads/$safeName") {
                    $old = "$UPLOAD_DIR/" . basename($coach['Files'][$type]);
                    if (file_exists($old)) unlink($old);
                }
            }
        }
    }
}

// Delete requested files
if (!empty($_POST['delete'])) {
    foreach ($_POST['delete'] as $type) {
        if (!empty($coach['Files'][$type])) {
            $file = "$UPLOAD_DIR/" . basename($coach['Files'][$type]);
            if (file_exists($file)) unlink($file);
            unset($coach['Files'][$type]);
        }
    }
}

// Save back to JSON
file_put_contents($coachesFile, json_encode($coaches, JSON_PRETTY_PRINT));

// Log activity
$logEntry = [
    'time' => date('c'),
    'type' => 'profile_update',
    'coachName' => $coach['CoachName'] ?? $coach['Username'],
    'details' => 'Updated profile' . (count($_FILES['files']['name'] ?? []) ? ' + uploaded images' : ''),
    'location' => $_SERVER['HTTP_CF_IPCOUNTRY'] ?? 'Unknown'
];
$logFile = "$DIR/activity_log.json";
$log = json_decode(file_get_contents($logFile), true) ?: [];
array_unshift($log, $logEntry);
file_put_contents($logFile, json_encode(array_slice($log, 0, 300), JSON_PRETTY_PRINT));

echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
?>