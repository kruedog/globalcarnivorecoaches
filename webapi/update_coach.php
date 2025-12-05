<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
ini_set('display_errors', 0);

$uploadDir = "/data/uploads/";

$email = trim($_POST['email'] ?? '');
if (!$email) {
    echo json_encode(['success'=>false,'message'=>'Missing email']);
    exit;
}

$coachesFile = __DIR__ . '/coaches.json';
if (!file_exists($coachesFile)) {
    echo json_encode(['success'=>false,'message'=>'coaches.json missing']);
    exit;
}

$data = json_decode(file_get_contents($coachesFile), true);
if (!is_array($data)) $data = [];

$index = null;
foreach ($data as $i=>$c) {
    if (isset($c['Email']) && strcasecmp($c['Email'],$email)===0) {
        $index = $i; break;
    }
}
if ($index === null) {
    echo json_encode(['success'=>false,'message'=>'Coach not found']);
    exit;
}

$coach = $data[$index];

// Safe defaults
$coach['CoachName'] = trim($_POST['coachName'] ?? ($coach['CoachName'] ?? ''));
$coach['Phone']     = trim($_POST['phone'] ?? ($coach['Phone'] ?? ''));
$coach['Bio']       = trim($_POST['bio'] ?? ($coach['Bio'] ?? ''));

// Specializations safe decode
if (isset($_POST['specializations'])) {
    $spec = json_decode($_POST['specializations'], true);
    $coach['Specializations'] = is_array($spec) ? $spec : [];
} elseif (!isset($coach['Specializations'])) {
    $coach['Specializations'] = [];
}

// Ensure Files array exists
if (!isset($coach['Files']) || !is_array($coach['Files'])) {
    $coach['Files'] = [];
}

// Remove old images
if (!empty($_POST['delete'])) {
    foreach ($_POST['delete'] as $t) unset($coach['Files'][$t]);
}

// Username for filenames
$uBase = $coach['Username'] ?? $coach['CoachName'] ?? 'coach';
$uBase = strtolower(preg_replace('/\W+/','',$uBase));
if (!$uBase) $uBase = 'coach';

// Handle file uploads
if (!empty($_FILES['files'])) {
    foreach ($_FILES['files']['name'] as $i=>$name) {
        $type = $_POST['imageType'][$i] ?? null;
        if (!$type) continue;

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION) ?: 'jpg');
        $newFile = "{$uBase}_" . time() . "_{$i}.{$ext}";
        if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $uploadDir . $newFile)) {
            $coach['Files'][$type] = $newFile;
        }
    }
}

$data[$index] = $coach;
file_put_contents($coachesFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo json_encode(['success'=>true,'coach'=>$coach]);
exit;
?>
