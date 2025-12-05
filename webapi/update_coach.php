<?php
// update_coach.php — JSON + Persistent Disk — Final Dec 2025
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
ini_set('display_errors', 0); // Never break JSON output

$uploadDir = "/data/uploads/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

// Identify coach by email
$email = $_POST['email'] ?? '';
if (!$email) {
    echo json_encode(["success" => false, "message" => "Missing email"]);
    exit;
}

// Load coaches.json
$coachesFile = __DIR__ . '/coaches.json';
if (!file_exists($coachesFile)) {
    echo json_encode(["success" => false, "message" => "coaches.json missing"]);
    exit;
}

$data = json_decode(file_get_contents($coachesFile), true);
if (!is_array($data)) $data = [];

// Find coach
$index = null;
foreach ($data as $i => $c) {
    if (isset($c['Email']) && strcasecmp($c['Email'], $email) === 0) {
        $index = $i;
        break;
    }
}

if ($index === null) {
    echo json_encode(["success" => false, "message" => "Coach not found"]);
    exit;
}

$coach = $data[$index];

// Update fields
$coach['CoachName'] = trim($_POST['coachName'] ?? ($coach['CoachName'] ?? ''));
$coach['Phone']     = trim($_POST['phone'] ?? ($coach['Phone'] ?? ''));
$coach['Bio']       = trim($_POST['bio'] ?? ($coach['Bio'] ?? ''));

// Specializations
if (!empty($_POST['specializations'])) {
    $coach['Specializations'] = json_decode($_POST['specializations'], true);
}

// Existing files array
if (!isset($coach['Files']) || !is_array($coach['Files'])) {
    $coach['Files'] = [];
}

// Remove deleted
if (!empty($_POST['delete'])) {
    foreach ($_POST['delete'] as $type) {
        unset($coach['Files'][$type]);
    }
}

// Handle new uploads
if (!empty($_FILES['files'])) {
    $username = strtolower(preg_replace('/\W+/', '', $coach['Username'] ?? 'coach'));

    foreach ($_FILES['files']['name'] as $idx => $name) {
        $type = $_POST['imageType'][$idx] ?? null;
        if (!$type) continue;

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION) ?: 'jpg');
        $newFilename = "{$username}_" . time() . "_{$idx}.{$ext}";
        $tmp = $_FILES['files']['tmp_name'][$idx];

        if (move_uploaded_file($tmp, $uploadDir . $newFilename)) {
            // Save ONLY filename, not path
            $coach['Files'][$type] = $newFilename;
        }
    }
}

// Write back JSON
$data[$index] = $coach;
file_put_contents($coachesFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo json_encode(["success" => true, "coach" => $coach]);
exit;
