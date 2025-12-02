<?php
// /webapi/update_coach.php — WILL NEVER OUTPUT HTML
ob_start();
session_start();
header('Content-Type: application/json');

// If anything went wrong before here, we kill it and send clean JSON
register_shutdown_function(function () {
    if ($error = error_get_last()) {
        if (in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Server error']);
            exit;
        }
    }
});

if (empty($_SESSION['username'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}

$currentUsername = $_SESSION['username'];
$file = __DIR__ . '/coaches.json';

if (!file_exists($file)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'coaches.json missing']);
    exit;
}

$coaches = json_decode(file_get_contents($file), true);
if (!is_array($coaches)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$coach = null;
foreach ($coaches as &$c) {
    if (isset($c['Username']) && strcasecmp($c['Username'], $currentUsername) === 0) {
        $coach =& $c;
        break;
    }
}
if (!$coach) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Not found']);
    exit;
}

// Safe updates
$coach['CoachName'] = trim($_POST['coachName'] ?? $coach['CoachName'] ?? '');
$coach['Email']     = trim($_POST['email'] ?? $coach['Email'] ?? '');
$coach['Phone']     = trim($_POST['phone'] ?? $coach['Phone'] ?? '');
$coach['Bio']       = $_POST['bio'] ?? $coach['Bio'] ?? '';

if (!empty($_POST['password'])) {
    $coach['Password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
}

if (isset($_POST['specializations'])) {
    $s = json_decode($_POST['specializations'], true);
    $coach['Specializations'] = json_encode(is_array($s) ? array_values(array_filter(array_map('trim', $s))) : []);
}

// Persistent disk
$uploadDir = '/opt/render/project/src/webapi/uploads/';
$webPath   = '/webapi/uploads/';
@mkdir($uploadDir, 0755, true);

if (!isset($coach['Files']) || !is_array($coach['Files'])) $coach['Files'] = [];

// Uploads
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
            if (!empty($coach['Files'][$type])) @unlink($uploadDir . basename($coach['Files'][$type]));
            $coach['Files'][$type] = $webPath . $newName;
        }
    }
}

// Deletes
if (!empty($_POST['delete']) && is_array($_POST['delete'])) {
    foreach ($_POST['delete'] as $type) {
        if (!empty($coach['Files'][$type])) {
            @unlink($uploadDir . basename($coach['Files'][$type]));
            unset($coach['Files'][$type]);
        }
    }
}

file_put_contents($file, json_encode($coaches, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

ob_clean();
echo json_encode(['success' => true, 'message' => 'Saved']);
exit;
?>