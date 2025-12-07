<?php
// /webapi/update_coach.php
// Handles profile updates & file uploads for the logged-in coach.

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'POST required'
    ]);
    exit;
}

if (empty($_SESSION['coach_username'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Not logged in'
    ]);
    exit;
}

$COACHES_FILE = __DIR__ . '/../uploads/coaches.json';
$UPLOAD_DIR   = __DIR__ . '/../uploads';

// Ensure upload dir exists
if (!is_dir($UPLOAD_DIR)) {
    @mkdir($UPLOAD_DIR, 0775, true);
}

if (!file_exists($COACHES_FILE)) {
    echo json_encode([
        'success' => false,
        'message' => 'coaches.json not found'
    ]);
    exit;
}

$coaches = json_decode(file_get_contents($COACHES_FILE), true);
if (!is_array($coaches)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid coaches.json'
    ]);
    exit;
}

// Find coach by session username
$username = strtolower($_SESSION['coach_username']);
$index    = null;

foreach ($coaches as $i => $c) {
    if (strtolower($c['Username'] ?? '') === $username) {
        $index = $i;
        break;
    }
}

if ($index === null) {
    echo json_encode([
        'success' => false,
        'message' => 'Coach not found'
    ]);
    exit;
}

$coach = $coaches[$index];

// Basic fields
$coachName = trim($_POST['coachName'] ?? '');
$email     = trim($_POST['email'] ?? '');
$phone     = trim($_POST['phone'] ?? '');
$bio       = (string)($_POST['bio'] ?? '');

if ($coachName !== '') {
    $coach['CoachName'] = $coachName;
}
$coach['Email'] = $email;
$coach['Phone'] = $phone;
$coach['Bio']   = $bio;

// Specializations (JSON array)
$specRaw = $_POST['specializations'] ?? '[]';
$specArr = json_decode($specRaw, true);
if (!is_array($specArr)) {
    $specArr = [];
}
$cleanSpecs = [];
foreach ($specArr as $s) {
    $s = trim((string)$s);
    if ($s !== '') {
        $cleanSpecs[] = $s;
    }
}
$coach['Specializations'] = $cleanSpecs;

// Files handling: Profile / Before / After / Certificate
if (!isset($coach['Files']) || !is_array($coach['Files'])) {
    $coach['Files'] = [];
}
$slots = ['Profile','Before','After','Certificate'];

// Handle deletions
$deleteSlots = $_POST['deleteSlots'] ?? [];
if (!is_array($deleteSlots)) {
    $deleteSlots = [];
}
foreach ($deleteSlots as $slot) {
    if (isset($coach['Files'][$slot])) {
        // optional: delete physical file (skipped to be safe)
        $coach['Files'][$slot] = null;
    }
}

// Handle uploads via $_FILES['files'][slot]
if (!empty($_FILES['files']) && is_array($_FILES['files']['name'] ?? null)) {
    foreach ($slots as $slot) {
        $nameArr   = $_FILES['files']['name'][$slot] ?? null;
        $tmpArr    = $_FILES['files']['tmp_name'][$slot] ?? null;
        $errorArr  = $_FILES['files']['error'][$slot] ?? null;
        $sizeArr   = $_FILES['files']['size'][$slot] ?? null;

        if ($nameArr === null || $tmpArr === null) {
            continue;
        }

        if ($errorArr !== UPLOAD_ERR_OK || $sizeArr <= 0) {
            continue;
        }

        $origName = $nameArr;
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = 'jpg';
        }

        $safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $username . '_' . $slot);
        $newName  = $safeBase . '_' . time() . '.' . $ext;
        $dest     = $UPLOAD_DIR . '/' . $newName;

        if (!move_uploaded_file($tmpArr, $dest)) {
            echo json_encode([
                'success' => false,
                'message' => "Failed to move uploaded file for $slot"
            ]);
            exit;
        }

        // Store only filename (frontend prefixes /uploads/)
        $coach['Files'][$slot] = $newName;
    }
}

// Save back
$coaches[$index] = $coach;
file_put_contents(
    $COACHES_FILE,
    json_encode($coaches, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo json_encode([
    'success' => true,
    'message' => 'Profile updated.',
    'coach'   => $coach
]);
