<?php
// /webapi/update_coach.php — FINAL VERSION THAT WILL NEVER BREAK JSON
session_start();
header('Content-Type: application/json');

// Block any output before this point
ob_start();

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$currentUsername = $_SESSION['username'];
$file = __DIR__ . '/coaches.json';

if (!file_exists($file)) {
    echo json_encode(['success' => false, 'message' => 'coaches.json missing']);
    exit;
}

$coaches = json_decode(file_get_contents($file), true);
if (!is_array($coaches)) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

// Find coach
$coach = null;
foreach ($coaches as &$c) {
    if (isset($c['Username']) && strcasecmp($c['Username'], $currentUsername) === 0) {
        $coach =& $c;
        break;
    }
}
if (!$coach) {
    echo json_encode(['success' => false, 'message' => 'Coach not found']);
    exit;
}

// Update text fields (safe — always exist in FormData)
$coach['CoachName'] = trim($_POST['coachName'] ?? $coach['CoachName'] ?? '');
$coach['Email']     = trim($_POST['email'] ?? $coach['Email'] ?? '');
$coach['Phone']     = trim($_POST['phone'] ?? $coach['Phone'] ?? '');
$coach['Bio']       = $_POST['bio'] ?? $coach['Bio'] ?? '';

if (!empty($_POST['password'])) {
    $coach['Password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
}

// Specializations
if (isset($_POST['specializations'])) {
    $specs = json_decode($_POST['specializations'], true);
    $coach['Specializations'] = json_encode(is_array($specs) ? array_values(array_filter(array_map('trim', $specs))) : []);
}

// Files setup — RENDER PERSISTENT DISK
$uploadDir = '/opt/render/project/src/webapi/uploads/';
$webPath   = '/webapi/uploads/';
@mkdir($uploadDir, 0755, true);

if (!isset($coach['Files']) || !is_array($coach['Files'])) {
    $coach['Files'] = [];
}

// Upload new files
if (!empty($_FILES['files']['name'][0])) {
    $types = $_POST['imageType'] ?? [];
    foreach ($_FILES['files']['name'] as $i => $name) {
        if (empty($name) || $_FILES['files']['error'][$i] !== 0) continue;
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, ['php','phtml','js','sh','exe'])) continue;

        $newName = $currentUsername . '_' . time() . "_$i.$ext";
        $target = $uploadDir . $newName;

        if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $target)) {
            $type = $types[$i] ?? 'Profile';
            // Delete old
            if (!empty($coach['Files'][$type])) {
                @unlink($uploadDir . basename($coach['Files'][$type]));
            }
            $coach['Files'][$type] = $webPath . $newName;
        }
    }
}

// Delete files
if (!empty($_POST['delete']) && is_array($_POST['delete'])) {
    foreach ($_POST['delete'] as $type) {
        if (!empty($coach['Files'][$type])) {
            @unlink($uploadDir . basename($coach['Files'][$type]));
            unset($coach['Files'][$type]);
        }
    }
}

// Save
file_put_contents($file, json_encode($coaches, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo json_encode(['success' => true, 'message' => 'Saved!']);
exit;
?>