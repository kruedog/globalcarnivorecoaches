<?php
/**
 * update_coach.php — FINAL VERSION
 * Global Carnivore Coaches — Dec 2025
 */

header('Content-Type: application/json');
ini_set('log_errors', 1);
ini_set('error_log', '/data/uploads/php_errors.log');

// Debug logs (append)
file_put_contents('/data/uploads/debug_post.log', print_r($_POST, true), FILE_APPEND);
file_put_contents('/data/uploads/debug_files.log', print_r($_FILES, true), FILE_APPEND);

// Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

// Username required
$username = trim($_POST['username'] ?? '');
if (!$username) {
    echo json_encode(['success' => false, 'message' => 'Username missing']);
    exit;
}

$coachesFile = '/data/uploads/coaches.json';
if (!file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'coaches.json missing']);
    exit;
}

$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) {
    echo json_encode(['success' => false, 'message' => 'Invalid coaches.json']);
    exit;
}

// Find coach by Username
$index = null;
foreach ($coaches as $i => $coach) {
    if (($coach['Username'] ?? '') === $username) {
        $index = $i;
        break;
    }
}
if ($index === null) {
    echo json_encode(['success' => false, 'message' => 'Coach not found']);
    exit;
}

// === TEXT FIELDS ===
$map = [
    'coachName' => 'CoachName',
    'email'     => 'Email',
    'phone'     => 'Phone',
    'bio'       => 'Bio'
];
foreach ($map as $k => $field) {
    if (isset($_POST[$k])) {
        $coaches[$index][$field] = trim($_POST[$k]);
    }
}

// Specializations
if (isset($_POST['specializations'])) {
    $arr = json_decode($_POST['specializations'], true);
    if (is_array($arr)) {
        $coaches[$index]['Specializations'] = array_values($arr);
    }
}

// Ensure Files array exists
if (!isset($coaches[$index]['Files']) || !is_array($coaches[$index]['Files'])) {
    $coaches[$index]['Files'] = [
        "Profile" => null,
        "Before" => null,
        "After" => null,
        "Certificate" => null
    ];
}

// Helper: extract file from grouped array (files[Profile])
function get_upload(string $type) {
    if (!empty($_FILES['files']['name'][$type])) {
        return [
            'name' => $_FILES['files']['name'][$type],
            'tmp'  => $_FILES['files']['tmp_name'][$type],
            'err'  => $_FILES['files']['error'][$type],
        ];
    }
    return null;
}

// === IMAGE UPLOAD LOOP ===
$slots = ['Profile', 'Before', 'After', 'Certificate'];
$updated = [];

foreach ($slots as $slot) {
    $f = get_upload($slot);
    if (!$f) continue;
    if ($f['err'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp'])) continue;

    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) continue;

    $newName = $username . "_" . $slot . "_" . time() . "." . $ext;
    $dest = '/data/uploads/' . $newName;

    if (move_uploaded_file($f['tmp'], $dest)) {

        // delete old file
        $old = $coaches[$index]['Files'][$slot] ?? null;
        if ($old) {
            $oldPath = '/data/uploads/' . $old;
            if (file_exists($oldPath)) @unlink($oldPath);
        }

        $coaches[$index]['Files'][$slot] = $newName;
        $updated[] = $slot;
    }
}

// === SAVE BACK TO JSON ===
if (file_put_contents($coachesFile, json_encode($coaches, JSON_PRETTY_PRINT)) === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to save coaches.json']);
    exit;
}

// SUCCESS RESPONSE
echo json_encode([
    'success' => true,
    'message' => 'Profile updated',
    'updated_files' => $updated,
    'files_state' => $coaches[$index]['Files']
]);
exit;
