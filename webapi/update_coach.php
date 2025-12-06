<?php
// update_coach.php — Render Persistent Disk Compatible
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

// SECURITY — required field
if (!isset($_POST['username']) || empty(trim($_POST['username']))) {
    echo json_encode(['success' => false, 'message' => 'Username required']);
    exit;
}

$username = trim($_POST['username']);
$coachesFile = __DIR__ . '/coaches.json';

// Load coaches data
if (!file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'coaches.json not found']);
    exit;
}
$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) {
    echo json_encode(['success' => false, 'message' => 'Invalid coaches.json']);
    exit;
}

// Find coach entry
$coachIndex = null;
foreach ($coaches as $i => $coach) {
    if (isset($coach['Username']) && strtolower($coach['Username']) === strtolower($username)) {
        $coachIndex = $i;
        break;
    }
}
if ($coachIndex === null) {
    echo json_encode(['success' => false, 'message' => 'Coach not found']);
    exit;
}

// Update core fields
$coaches[$coachIndex]['CoachName'] = trim($_POST['coachName'] ?? '');
$coaches[$coachIndex]['Phone']     = trim($_POST['phone'] ?? '');
$coaches[$coachIndex]['Bio']       = trim($_POST['bio'] ?? '');

if (isset($_POST['specializations'])) {
    $decoded = json_decode($_POST['specializations'], true);
    $coaches[$coachIndex]['Specializations'] = is_array($decoded) ? $decoded : [];
}

// Prepare file paths
$diskUploadPath = '/data/uploads/';   // persistent storage
$publicPathBase = 'uploads/';         // served by browser

// Ensure disk directory exists
if (!is_dir($diskUploadPath)) {
    mkdir($diskUploadPath, 0777, true);
}

// Initialize Files object
if (!isset($coaches[$coachIndex]['Files']) || !is_array($coaches[$coachIndex]['Files'])) {
    $coaches[$coachIndex]['Files'] = [];
}

// Handle file deletions
if (!empty($_POST['delete'])) {
    foreach ($_POST['delete'] as $type) {
        if (!empty($coaches[$coachIndex]['Files'][$type])) {
            $fileOnDisk = $diskUploadPath . basename($coaches[$coachIndex]['Files'][$type]);
            if (file_exists($fileOnDisk)) unlink($fileOnDisk);
            unset($coaches[$coachIndex]['Files'][$type]); // remove from JSON
        }
    }
}

// Handle new uploads
if (!empty($_FILES['files']['name'])) {
    foreach ($_FILES['files']['name'] as $idx => $fileName) {
        if ($_FILES['files']['error'][$idx] !== UPLOAD_ERR_OK) continue;

        $type = $_POST['imageType'][$idx] ?? null;
        if (!$type) continue;

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $safeName = strtolower($username) . '_' . $type . '_' . time() . '.' . $ext;

        $targetDiskPath = $diskUploadPath . $safeName;

        if (move_uploaded_file($_FILES['files']['tmp_name'][$idx], $targetDiskPath)) {
            // Save the PUBLIC path to JSON
            $coaches[$coachIndex]['Files'][$type] = $publicPathBase . $safeName;
        }
    }
}

// Save updated JSON
file_put_contents($coachesFile, json_encode($coaches, JSON_PRETTY_PRINT));

echo json_encode(['success' => true]);
exit;
?>
