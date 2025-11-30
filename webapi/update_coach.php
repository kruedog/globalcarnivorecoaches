<?php
/**
 * update_coach.php — FULLY UPDATED WITH SPECIALIZATIONS SUPPORT
 * Global Carnivore Coaches | November 2025
 */

header('Content-Type: application/json');
session_start();

// AUTH: Must be logged in
if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}
$currentUsername = $_SESSION['username'];

$coachesFile = __DIR__ . '/coaches.json';
if (!file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'coaches.json not found']);
    exit;
}

$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) {
    echo json_encode(['success' => false, 'message' => 'Invalid coaches data']);
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
    echo json_encode(['success' => false, 'message' => 'Your account not found']);
    exit;
}

// Track what changed
$changes = [];

// Text fields
if (!empty($_POST['coachName'])) {
    $changes[] = 'name';
    $coaches[$index]['CoachName'] = trim($_POST['coachName']);
}
if (!empty($_POST['email'])) {
    $changes[] = 'email';
    $coaches[$index]['Email'] = trim($_POST['email']);
}
if (!empty($_POST['password'])) {
    $changes[] = 'password';
    $coaches[$index]['Password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
}
if (isset($_POST['phone'])) {
    $changes[] = 'phone';
    $coaches[$index]['Phone'] = trim($_POST['phone']);
}
if (isset($_POST['bio'])) {
    $changes[] = 'bio';
    $coaches[$index]['Bio'] = $_POST['bio']; // already sanitized by browser
}

// SPECIALIZATIONS — NEW FIELD
if (isset($_POST['specializations'])) {
    $raw = $_POST['specializations'];
    $decoded = json_decode($raw, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        // Sanitize each specialization
        $clean = array_map(function($item) {
            return trim(strip_tags($item));
        }, $decoded);

        // Remove empty entries
        $clean = array_filter($clean, 'strlen');

        $coaches[$index]['Specializations'] = json_encode(array_values($clean));
        $changes[] = 'specializations';
    } else if ($raw === '' || $raw === '[]') {
        // Allow clearing the field
        $coaches[$index]['Specializations'] = json_encode([]);
        $changes[] = 'specializations (cleared)';
    }
}

// File uploads
$uploadDir = __DIR__ . '/uploads/';
$webPath   = 'uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

if (!empty($_FILES['files']['name'][0])) {
    $changes[] = 'photos';
    if (!isset($coaches[$index]['Files']) || !is_array($coaches[$index]['Files'])) {
        $coaches[$index]['Files'] = [];
    }

    $types = $_POST['imageType'] ?? [];

    foreach ($_FILES['files']['name'] as $i => $name) {
        if (empty($name) || $_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, ['php', 'php7', 'phtml', 'exe', 'sh', 'js'])) continue; // block dangerous

        $newName = $currentUsername . '_' . time() . "_$i.$ext";
        $target  = $uploadDir . $newName;

        if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $target)) {
            $type = $types[$i] ?? 'Profile';

            // Delete old file if exists
            if (!empty($coaches[$index]['Files'][$type])) {
                $old = $uploadDir . basename($coaches[$index]['Files'][$type]);
                if (file_exists($old)) @unlink($old);
            }

            $coaches[$index]['Files'][$type] = $webPath . $newName;
        }
    }
}

// File deletes
foreach ((array)($_POST['delete'] ?? []) as $type) {
    if (!empty($coaches[$index]['Files'][$type])) {
        $file = $uploadDir . basename($coaches[$index]['Files'][$type]);
        if (file_exists($file)) @unlink($file);
        unset($coaches[$index]['Files'][$type]);
        $changes[] = "deleted $type photo";
    }
}

// Normalize file paths
if (isset($coaches[$index]['Files']) && is_array($coaches[$index]['Files'])) {
    foreach ($coaches[$index]['Files'] as $k => $v) {
        $coaches[$index]['Files'][$k] = str_replace('\\', '/', $v);
    }
}

// Save updated coaches.json
file_put_contents(
    $coachesFile,
    json_encode($coaches, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

// LOG THE ACTION
require_once __DIR__ . '/log_activity.php';
$detail = $changes
    ? 'Updated: ' . implode(', ', array_unique($changes))
    : 'Viewed profile (no changes)';
log_coach_activity('profile_update', $detail);

echo json_encode([
    'success' => true,
    'message' => 'Profile saved successfully!',
    'coach'   => $coaches[$index]
]);
exit;
?>