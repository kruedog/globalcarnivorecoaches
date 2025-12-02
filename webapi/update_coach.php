<?php
// update_coach.php — Render-ready version (Dec 2025)
ob_start();
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['username'])) {
    echo json_encode(['success'=>false,'message'=>'Login required']);
    exit;
}

$username = $_SESSION['username'];
$file = __DIR__ . '/coaches.json';

if (!file_exists($file)) {
    echo json_encode(['success'=>false,'message'=>'coaches.json missing']);
    exit;
}

$coaches = json_decode(file_get_contents($file), true);
if (!is_array($coaches)) $coaches = [];

// Find coach
$coach = null;
foreach ($coaches as &$c) {
    if (isset($c['Username']) && strcasecmp($c['Username'],$username)===0) {
        $coach =& $c;
        break;
    }
}

if (!$coach) {
    echo json_encode(['success'=>false,'message'=>'Coach not found']);
    exit;
}

// Update fields
$coach['CoachName'] = trim($_POST['coachName'] ?? $coach['CoachName'] ?? '');
$coach['Email']     = trim($_POST['email'] ?? $coach['Email'] ?? '');
$coach['Phone']     = trim($_POST['phone'] ?? $coach['Phone'] ?? '');
$coach['Bio']       = $_POST['bio'] ?? $coach['Bio'] ?? '';

if (!empty($_POST['password'])) {
    $coach['Password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
}

// Specializations
if (isset($_POST['specializations'])) {
    $s = json_decode($_POST['specializations'], true);
    $coach['Specializations'] = json_encode(is_array($s) ? array_values(array_filter(array_map('trim',$s))) : []);
}

// File uploads
$uploadDir = __DIR__ . '/uploads/';
$webPath   = 'public/webapi/uploads/';
@mkdir($uploadDir, 0755, true);

if (!isset($coach['Files']) || !is_array($coach['Files'])) $coach['Files'] = [];

if (!empty($_FILES['files']['name'][0])) {
    $types = $_POST['imageType'] ?? [];
    foreach ($_FILES['files']['name'] as $i => $name) {
        if (empty($name) || $_FILES['files']['error'][$i]!==UPLOAD_ERR_OK) continue;
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext,['php','phtml','js','sh','exe'])) continue;
        $newName = $username.'_'.time()."_$i.$ext";
        $target  = $uploadDir.$newName;
        if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $target)) {
            $type = $types[$i] ?? 'Profile';
            if (!empty($coach['Files'][$type])) @unlink($uploadDir.basename($coach['Files'][$type]));
            $coach['Files'][$type] = $webPath.$newName;
        }
    }
}

// Deletes
if (!empty($_POST['delete']) && is_array($_POST['delete'])) {
    foreach ($_POST['delete'] as $type) {
        if (!empty($coach['Files'][$type])) {
            @unlink($uploadDir.basename($coach['Files'][$type]));
            unset($coach['Files'][$type]);
        }
    }
}

// Save JSON safely
$json = json_encode($coaches, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($json===false) $json='[]';
file_put_contents($file,$json);

ob_clean();
echo json_encode(['success'=>true,'message'=>'Saved']);
exit;
?>
