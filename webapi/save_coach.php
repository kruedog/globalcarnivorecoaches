<?php
/**
 * save_coach.php — Create / Update / Delete coaches
 * Global Carnivore Coaches — Admin Only
 */

session_start();
header('Content-Type: application/json');

// === ADMIN SESSION ENFORCEMENT ===
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

// === HTTP GUARD ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid method',
        'code'    => 'BAD_METHOD'
    ]);
    exit;
}

// === INPUT PARSE ===
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

// === LOAD COACHES FILE ===
$coachesPath = __DIR__ . '/../uploads/coaches.json';
$coaches = file_exists($coachesPath)
    ? json_decode(file_get_contents($coachesPath), true)
    : [];

if (!is_array($coaches)) {
    $coaches = [];
}

// === FIND INDEX IF EXISTS ===
$foundIndex = null;
foreach ($coaches as $i => $c) {
    if (strcasecmp($c['Username'] ?? '', $username) === 0) {
        $foundIndex = $i;
        break;
    }
}

// === DELETE FLOW ===
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
    // Re-index after deletion
    autoRenumber($coaches);

    if (!saveFile($coachesPath, $coaches)) {
        failWrite();
    }

    echo json_encode(['success' => true, 'message' => 'Coach deleted']);
    exit;
}

// === GATHER NEW DATA ===
$coachName     = trim($input['CoachName'] ?? '');
$email         = trim($input['Email'] ?? '');
$role          = strtolower(trim($input['Role'] ?? 'coach'));
$displayOrder  = isset($input['DisplayOrder']) ? intval($input['DisplayOrder']) : 999;
$bio           = $input['Bio'] ?? '';
$specRaw       = $input['Specializations'] ?? '';
$profileFile   = trim($input['Profile'] ?? '');
$certFile      = trim($input['Certificate'] ?? '');
$beforeFile    = trim($input['Before'] ?? '');
$afterFile     = trim($input['After'] ?? '');
$tempPassword  = trim($input['TempPassword'] ?? '');

// Normalize specializations → array
$specList = is_array($specRaw)
    ? $specRaw
    : array_values(array_filter(array_map('trim', preg_split('/[;,|]+/', (string)$specRaw)), 'strlen'));

$newData = [
    'Username'        => $username,
    'CoachName'       => $coachName,
    'Email'           => $email,
    'Role'            => $role === 'admin' ? 'admin' : 'coach',
    'DisplayOrder'    => $displayOrder,
    'Bio'             => $bio,
    'Specializations' => $specList,
    'Files' => [
        'Profile'     => $profileFile,
        'Certificate' => $certFile,
        'Before'      => $beforeFile,
        'After'       => $afterFile
    ]
];

// === PRESERVE PASSWORD & METADATA FOR UPDATE ===
if ($foundIndex !== null) {
    $existing = $coaches[$foundIndex];

    // Keep or replace password
    if ($tempPassword !== '') {
        $newData['Password'] = password_hash($tempPassword, PASSWORD_DEFAULT);
    } elseif (!empty($existing['Password'])) {
        $newData['Password'] = $existing['Password'];
    }

    // Preserve any extra metadata
    foreach ($existing as $k => $v) {
        if (!array_key_exists($k, $newData) && $k !== 'Files') {
            $newData[$k] = $v;
        }
    }

    $coaches[$foundIndex] = $newData;
    $msg = 'Coach updated';
} else {
    // If creating new coach
    if ($tempPassword !== '') {
        $newData['Password'] = password_hash($tempPassword, PASSWORD_DEFAULT);
    }
    $coaches[] = $newData;
    $msg = 'Coach created';
}

// === AUTO-SORT + AUTO-RENUMBER ===
autoRenumber($coaches);

// === SAVE FILE ===
if (!saveFile($coachesPath, $coaches)) {
    failWrite();
}

echo json_encode(['success' => true, 'message' => $msg]);


// ============================================================
// SUPPORT FUNCTIONS
// ============================================================

function saveFile($path, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return file_put_contents($path, $json) !== false;
}

function failWrite() {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to write coaches.json',
        'code'    => 'WRITE_ERROR'
    ]);
    exit;
}

/**
 * Sort by DisplayOrder asc, fallback: Alpha name
 * Then rewrite DisplayOrder as clean 1…N
 */
function autoRenumber(&$arr) {
    usort($arr, function($a, $b) {
        $da = intval($a['DisplayOrder'] ?? 999);
        $db = intval($b['DisplayOrder'] ?? 999);
        if ($da !== $db) return $da - $db;
        return strcasecmp($a['CoachName'] ?? '', $b['CoachName'] ?? '');
    });

    foreach ($arr as $i => &$c) {
        $c['DisplayOrder'] = $i + 1;
    }
    unset($c);
}
