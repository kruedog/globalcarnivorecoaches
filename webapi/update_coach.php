<?php
/**
 * update_coach.php — Global Carnivore Coaches
 * Option A: Save Profile, Before, After, Certificate
 * FINAL — December 2025
 */

header('Content-Type: application/json');
ini_set('log_errors', 1);
ini_set('error_log', '/data/uploads/php_errors.log');

// Debug logs
file_put_contents('/data/uploads/debug_post.log', print_r($_POST, true), FILE_APPEND);
file_put_contents('/data/uploads/debug_files.log', print_r($_FILES, true), FILE_APPEND);

// Require POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

// Username required
$username = trim($_POST['username'] ?? '');
if ($username === '') {
    echo json_encode(['success' => false, 'message' => 'No username']);
    exit;
}

// Ensure JSON file exists
$coachesFile = '/data/uploads/coaches.json';
if (!file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'coaches.json missing']);
    exit;
}

$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) {
    echo json_encode(['success' => false, 'message' => 'coaches.json corrupted']);
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

// Update text fields
$fields = [
    'coachName' => 'CoachName',
    'email'     => 'Email',
    'phone'     => 'Phone',
    'bio'       => 'Bio'
];
foreach ($fields as $postField => $jsonField) {
    if (isset($_POST[$postField])) {
        $coaches[$index][$jsonField] = trim($_POST[$postField]);
    }
}

// Specializations → JSON array
if (isset($_POST['specializations'])) {
    $specs = json_decode($_POST['specializations'], true);
    if (is_array($specs)) {
        $coaches[$index]['Specializations'] = array_values($specs);
    }
}

// Ensure Files array exists
if (!isset($coaches[$index]['Files']) || !is_array($coaches[$index]['Files'])) {
    $coaches[$index]['Files'] = [
        "Profile"     => null,
        "Before"      => null,
        "After"       => null,
        "Certificate" => null,
    ];
}

// Handle single file deletion if sent
if (isset($_POST['DeleteFile'])) {
    $delType = $_POST['DeleteFile'];
    if (!empty($coaches[$index]['Files'][$delType])) {
        $oldFile = '/data/uploads/' . $coaches[$index]['Files'][$delType];
        if (file_exists($oldFile)) unlink($oldFile);
        $coaches[$index]['Files'][$delType] = null;
    }
}

// Handle uploads of all 4 image types
foreach (['Profile', 'Before', 'After', 'Certificate'] as $type) {
    if (!empty($_FILES[$type]['name'])) {
        $ext = strtolower(pathinfo($_FILES[$type]['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) continue;

        $newFile = $username . '_' . $type . '_' . time() . '.' . $ext;
        $targetPath = '/data/uploads/' . $newFile;

        if (move_uploaded_file($_FILES[$type]['tmp_name'], $targetPath)) {
            // Delete previous file
            if (!empty($coaches[$index]['Files'][$type])) {
                $old = '/data/uploads/' . $coaches[$index]['Files'][$type];
                if (file_exists($old)) unlink($old);
            }
            $coaches[$index]['Files'][$type] = $newFile;
        }
    }
}

// Save JSON back to persistent disk
if (file_put_contents($coachesFile, json_encode($coaches, JSON_PRETTY_PRINT)) === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to write coaches.json']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Profile updated',
    'received_files' => array_keys($_FILES),
    'updated_files' => $coaches[$index]['Files']
]);
exit;
