<?php
// /webapi/update_coach.php — FINAL WORKING VERSION (Aug 2025)
header('Content-Type: application/json');
session_start();

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}

$currentUsername = $_SESSION['username'];
$coachesFile = __DIR__ . '/coaches.json';

if (!file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'coaches.json not found']);
    exit;
}

$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) {
    echo json_encode(['success' => false, 'message' => 'Invalid coaches.json']);
    exit;
}

// Find coach
$index = null;
foreach ($coaches as $i => $c) {
    if (isset($c['Username']) && strcasecmp($c['Username'], $currentUsername) === 0) {
        $index = $i;
        break;
    }
}
if ($index === null) {
    echo json_encode(['success' => false, 'message' => 'Coach not found']);
    exit;
}

$changes = [];

// Text fields – safe access
$coaches[$index]['CoachName'] = trim($_POST['coachName'] ?? $coaches[$index]['CoachName'] ?? '');
$coaches[$index]['Email']     = trim($_POST['email'] ?? $coaches[$index]['Email'] ?? '');
$coaches[$index]['Phone']     = trim($_POST['phone'] ?? $coaches[$index]['Phone'] ?? '');
$coaches[$index]['Bio']       = $_POST['bio'] ?? $coaches[$index]['Bio'] ?? '';

if (!empty($_POST['password'])) {
    $coaches[$index]['Password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $changes[] = 'password';
}

// Specializations
if (isset($_POST['specializations'])) {
    $specs = json_decode($_POST['specializations'], true);
    if (is_array($specs)) {
        $clean = array_values(array_filter(array_map('trim', $specs)));
        $coaches[$index]['Specializations'] = json_encode($clean);
        $changes[] = 'specializations';
    }
}

// Persistent uploads on Render disk
$uploadDir = '/opt/render/project/src/webapi/uploads/';
$webPath   = '/webapi/uploads/';
@mkdir($uploadDir, 0755, true);

if (!isset($coaches[$index]['Files']) || !is_array($coaches[$index]['Files'])) {
    $coaches[$index]['Files'] = [];
}

// Handle new uploads
if (!empty($_FILES['files']['name'][0])) {
    $changes[] = 'photos';
    $types = $_POST['imageType'] ?? [];

    foreach ($_FILES['files']['name'] as $i => $name) {
        if (empty($name) || $_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, ['php','phtml','js','sh','exe'])) continue;

        $newName = $currentUsername . '_' . time() . "_$i.$ext";
        $target  = $uploadDir . $newName;

        if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $target)) {
            $type = $types[$i] ?? 'Profile';

            // Remove old photo of same type
            if (!empty($coaches[$index]['Files'][$type])) {
                $oldFile = $uploadDir . basename($coaches[$index]['Files'][$type]);
                if (file_exists($oldFile)) @unlink($oldFile);
            }

            $coaches[$index]['Files'][$type] = $webPath . $newName;
        }
    }
}

// Handle deletes
if (!empty($_POST['delete']) && is_array($_POST['delete'])) {
    foreach ($_POST['delete'] as $type) {
        if (!empty($coaches[$index]['Files'][$type])) {
            $file = $uploadDir . basename($coaches[$index]['Files'][$type]);
            if (file_exists($file)) @unlink($file);
            unset($coaches[$index]['Files'][$type]);
            $changes[] = "deleted $type";
        }
    }
}

// Save file
file_put_contents($coachesFile, json_encode($coaches, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo json_encode([
    'success' => true,
    'message' => 'Profile saved successfully!'
]);
exit;
?>