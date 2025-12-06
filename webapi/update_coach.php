<?php
/**
 * update_coach.php — Global Carnivore Coaches
 * FINAL PATCH — December 2025
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

// Validate username
$username = trim($_POST['username'] ?? '');
if ($username === '') {
    echo json_encode(['success' => false, 'message' => 'Username missing']);
    exit;
}

$coachesFile = '/data/uploads/coaches.json';
if (!file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'coaches.json not found']);
    exit;
}

$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) {
    echo json_encode(['success' => false, 'message' => 'Invalid coaches.json']);
    exit;
}

// Find coach by Username
$idx = null;
foreach ($coaches as $i => $coach) {
    if (($coach['Username'] ?? '') === $username) {
        $idx = $i;
        break;
    }
}
if ($idx === null) {
    echo json_encode(['success' => false, 'message' => 'Coach not found']);
    exit;
}

// Update text fields (names sent from frontend)
$map = [
    'coachName'      => 'CoachName',
    'email'          => 'Email',
    'phone'          => 'Phone',
    'bio'            => 'Bio'
];

foreach ($map as $posted => $field) {
    if (isset($_POST[$posted])) {
        $coaches[$idx][$field] = trim($_POST[$posted]);
    }
}

// Specializations (array)
if (isset($_POST['specializations'])) {
    $specs = json_decode($_POST['specializations'], true);
    if (is_array($specs)) {
        $coaches[$idx]['Specializations'] = $specs;
    }
}

// Ensure Files array exists
if (!isset($coaches[$idx]['Files']) || !is_array($coaches[$idx]['Files'])) {
    $coaches[$idx]['Files'] = [
        "Profile"     => null,
        "Before"      => null,
        "After"       => null,
        "Certificate" => null,
    ];
}

// Handle deletions
if (isset($_POST['DeleteFile'])) {
    $delType = $_POST['DeleteFile'];
    if (!empty($coaches[$idx]['Files'][$delType])) {
        $path = '/data/uploads/' . $coaches[$idx]['Files'][$delType];
        if (file_exists($path)) unlink($path);
        $coaches[$idx]['Files'][$delType] = null;
    }
}

// Handle new uploads
foreach (['Profile', 'Before', 'After', 'Certificate'] as $type) {
    if (!empty($_FILES[$type]['name'])) {

        $ext = strtolower(pathinfo($_FILES[$type]['name'], PATHINFO_EXTENSION));
        $newFile = $username . '_' . $type . '_' . time() . '.' . $ext;
        $target = '/data/uploads/' . $newFile;

        if (move_uploaded_file($_FILES[$type]['tmp_name'], $target)) {

            // Remove old file
            if (!empty($coaches[$idx]['Files'][$type])) {
                $old = '/data/uploads/' . $coaches[$idx]['Files'][$type];
                if (file_exists($old)) unlink($old);
            }

            $coaches[$idx]['Files'][$type] = $newFile;
        }
    }
}

// Save updated JSON back to disk
if (file_put_contents($coachesFile, json_encode($coaches, JSON_PRETTY_PRINT)) === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to save coaches.json']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Profile updated']);
exit;
