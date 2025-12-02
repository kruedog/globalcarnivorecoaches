<?php
// /webapi/update_coach.php — FINAL VERSION THAT WORKS 100% ON RENDER (Dec 2025)
ob_start();
session_start();
header('Content-Type: application/json');

// Safety net – if anything crashes, we still return clean JSON
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error']);
        exit;
    }
});

if (empty($_SESSION['username'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$username = $_SESSION['username'];
$jsonFile = __DIR__ . '/coaches.json';

if (!file_exists($jsonFile)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Data file missing']);
    exit;
}

$coaches = json_decode(file_get_contents($jsonFile), true);
if (!is_array($coaches)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Corrupted data']);
    exit;
}

// Find current coach
$coach = null;
foreach ($coaches as &$c) {
    if (isset($c['Username']) && strcasecmp($c['Username'], $username) === 0) {
        $coach =& $c;
        break;
    }
}
if (!$coach) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Coach not found']);
    exit;
}

// Update simple fields
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
    $clean = is_array($specs) ? array_values(array_filter(array_map('trim', $specs))) : [];
    $coach['Specializations'] = json_encode($clean);
}

$uploadDir = '/opt/render/project/src/webapi/uploads/';
$webPath = 'public/webapi/uploads/';
@mkdir($uploadDir, 0755, true);

if (!isset($coach['Files']) || !is_array($coach['Files'])) {
    $coach['Files'] = [];
}

// New uploads
if (!empty($_FILES['files']['name'][0])) {
    $types = $_POST['imageType'] ?? [];
    foreach ($_FILES['files']['name'] as $i => $name) {
        if (empty($name) || $_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, ['php','phtml','js','sh','exe'])) continue;

        $newName = $username . '_' . time() . "_$i.$ext";
        $target  = $uploadDir . $newName;

        if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $target)) {
            $type = $types[$i] ?? 'Profile';

            // Delete old file of same type
            if (!empty($coach['Files'][$type])) {
                @unlink($uploadDir . basename($coach['Files'][$type]));
            }

            $coach['Files'][$type] = $webPath . $newName;
        }
    }
}

// Delete requested photos
if (!empty($_POST['delete']) && is_array($_POST['delete'])) {
    foreach ($_POST['delete'] as $type) {
        if (!empty($coach['Files'][$type])) {
            @unlink($uploadDir . basename($coach['Files'][$type]));
            unset($coach['Files'][$type]);
        }
    }
}

// Save everything
file_put_contents($jsonFile, json_encode($coaches, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

ob_clean();
echo json_encode(['success' => true, 'message' => 'Profile saved!']);
exit;
?>