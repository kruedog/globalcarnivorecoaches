<?php
// update_coach.php — FINAL BULLETPROOF VERSION FOR RENDER
header('Content-Type: application/json');
session_start();

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}
$currentUsername = $_SESSION['username'];

$coachesFile = __DIR__ . '/coaches.json';
if (!file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'coaches.json missing']);
    exit;
}

$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) {
    echo json_encode(['success' => false, 'message' => 'Corrupted data']);
    exit;
}

// Find current coach
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

// TEXT FIELDS
if (!empty($_POST['coachName'])) {
    $coaches[$index]['CoachName'] = trim($_POST['coachName']);
    $changes[] = 'name';
}
if (!empty($_POST['email'])) {
    $coaches[$index]['Email'] = trim($_POST['email']);
    $changes[] = 'email';
}
if (!empty($_POST['password'])) {
    $coaches[$index]['Password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $changes[] = 'password';
}
if (isset($_POST['phone'])) {
    $coaches[$index]['Phone'] = trim($_POST['phone'] ?? '');
    $changes[] = 'phone';
}
if (isset($_POST['bio'])) {
    $coaches[$index]['Bio'] = $_POST['bio'];
    $changes[] = 'bio';
}

// SPECIALIZATIONS
if (isset($_POST['specializations'])) {
    $raw = $_POST['specializations'];
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $clean = array_values(array_filter(array_map('trim', $decoded)));
        $coaches[$index]['Specializations'] = json_encode($clean);
        $changes[] = 'specializations';
    }
}

// PERSISTENT UPLOADS ON RENDER DISK
$uploadDir = '/opt/render/project/src/webapi/uploads/';
$webPath   = '/webapi/uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// Ensure Files array exists
if (!isset($coaches[$index]['Files']) || !is_array($coaches[$index]['Files'])) {
    $coaches[$index]['Files'] = [];
}

// NEW UPLOADS
if (!empty($_FILES['files']['name'][0])) {
    $changes[] = 'photos';
    $types = $_POST['imageType'] ?? [];

    foreach ($_FILES['files']['name'] as $i => $name) {
        if (empty($name) || $_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, ['php','phtml','js','sh'])) continue;

        $newName = $currentUsername . '_' . time() . "_$i.$ext";
        $target  = $uploadDir . $newName;

        if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $target)) {
            $type = $types[$i] ?? 'Profile';

            // Remove old photo of same type
            if (!empty($coaches[$index]['Files'][$type])) {
                $old = $uploadDir . basename($coaches[$index]['Files'][$type]);
                if (file_exists($old)) @unlink($old);
            }

            $coaches[$index]['Files'][$type] = $webPath . $newName;
        }
    }
}

// DELETES
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

// SAVE JSON
file_put_contents($coachesFile, json_encode($coaches, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo json_encode([
    'success' => true,
    'message' => 'Profile saved!',
    'changes' => $changes
]);
exit;
?>