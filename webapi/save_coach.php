<?php
/**
 * save_coach.php — Create / Update / Delete coaches
 * Requires admin session.
 */

session_start();
header('Content-Type: application/json');

// --- Admin/session guard ---
if (!isset($_SESSION['username'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Login required.',
        'code'    => 'NO_SESSION'
    ]);
    exit;
}

if (strtolower($_SESSION['role'] ?? 'coach') !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Admin access required.',
        'code'    => 'NOT_ADMIN'
    ]);
    exit;
}

// --- Method & input ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid method',
        'code'    => 'BAD_METHOD'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON payload',
        'code'    => 'BAD_JSON'
    ]);
    exit;
}

$action   = $input['action']   ?? '';
$username = trim($input['Username'] ?? '');

if ($username === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Username required',
        'code'    => 'MISSING_USERNAME'
    ]);
    exit;
}

// --- Load coaches.json ---
$coachesPath = __DIR__ . '/../uploads/coaches.json';
if (!file_exists($coachesPath)) {
    // Start with empty list if file missing
    $coaches = [];
} else {
    $raw = file_get_contents($coachesPath);
    $coaches = json_decode($raw, true);
    if (!is_array($coaches)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid coaches.json',
            'code'    => 'BAD_COACHES_FILE'
        ]);
        exit;
    }
}

// Helper: save file
function save_coaches_file($path, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (file_put_contents($path, $json) === false) {
        return false;
    }
    return true;
}

// --- Find coach index ---
$foundIndex = null;
foreach ($coaches as $i => $c) {
    if (strcasecmp($c['Username'] ?? '', $username) === 0) {
        $foundIndex = $i;
        break;
    }
}

// --- DELETE ---
if ($action === 'delete') {
    if ($foundIndex === null) {
        echo json_encode([
            'success' => false,
            'message' => 'Coach not found',
            'code'    => 'NOT_FOUND'
        ]);
        exit;
    }

    array_splice($coaches, $foundIndex, 1);

    if (!save_coaches_file($coachesPath, $coaches)) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to write coaches.json',
            'code'    => 'WRITE_ERROR'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Coach deleted'
    ]);
    exit;
}

// --- CREATE / UPDATE ---

$coachName     = trim($input['CoachName'] ?? '');
$email         = trim($input['Email'] ?? '');
$role          = strtolower(trim($input['Role'] ?? 'coach'));
$displayOrder  = isset($input['DisplayOrder']) ? (int)$input['DisplayOrder'] : 999;
$bio           = $input['Bio'] ?? '';
$specRaw       = $input['Specializations'] ?? '';
$profileFile   = trim($input['Profile'] ?? '');
$certFile      = trim($input['Certificate'] ?? '');
$beforeFile    = trim($input['Before'] ?? '');
$afterFile     = trim($input['After'] ?? '');
$tempPassword  = trim($input['TempPassword'] ?? '');

// Normalize specializations → array
$specList = [];
if (is_array($specRaw)) {
    $specList = $specRaw;
} else {
    $specList = array_values(array_filter(array_map('trim', preg_split('/[;,|]+/', (string)$specRaw)), 'strlen'));
}

// Base record
$newData = [
    'Username'      => $username,
    'CoachName'     => $coachName,
    'Email'         => $email,
    'Role'          => $role === 'admin' ? 'admin' : 'coach',
    'DisplayOrder'  => $displayOrder,
    'Bio'           => $bio,
    'Specializations' => $specList,
    'Files' => [
        'Profile'     => $profileFile,
        'Certificate' => $certFile,
        'Before'      => $beforeFile,
        'After'       => $afterFile
    ]
];

// If updating, preserve fields that we don't overwrite (like Password, last_login, etc.)
if ($foundIndex !== null) {
    $existing = $coaches[$foundIndex];

    // Preserve password unless tempPassword is provided
    if ($tempPassword !== '') {
        $newData['Password'] = password_hash($tempPassword, PASSWORD_DEFAULT);
    } else {
        if (!empty($existing['Password'])) {
            $newData['Password'] = $existing['Password'];
        }
    }

    // Preserve any other metadata keys you may already have
    foreach ($existing as $k => $v) {
        if (!array_key_exists($k, $newData) && $k !== 'Files') {
            $newData[$k] = $v;
        }
    }

    $coaches[$foundIndex] = $newData;
    $msg = 'Coach updated';
} else {
    // CREATE
    if ($tempPassword !== '') {
        $newData['Password'] = password_hash($tempPassword, PASSWORD_DEFAULT);
    }
    $coaches[] = $newData;
    $msg = 'Coach created';
}

// Save file
if (!save_coaches_file($coachesPath, $coaches)) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to write coaches.json',
        'code'    => 'WRITE_ERROR'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => $msg
]);
