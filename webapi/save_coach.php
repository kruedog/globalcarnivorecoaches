<?php
/**
 * save_coach.php — Create / Update / Delete coaches
 * Global Carnivore Coaches — Admin Only
 */

session_start();
header('Content-Type: application/json');

// ===== ADMIN SESSION CHECK =====
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

// ===== METHOD & PAYLOAD VALIDATION =====
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

// ===== LOAD EXISTING COACHES =====
$coachesPath = __DIR__ . '/../uploads/coaches.json';
$coaches = file_exists($coachesPath)
    ? json_decode(file_get_contents($coachesPath), true)
    : [];

if (!is_array($coaches)) $coaches = [];

// ===== FIND EXISTING COACH INDEX =====
$foundIndex = null;
foreach ($coaches as $i => $c) {
    if (strcasecmp($c['Username'] ?? '', $username) === 0) {
        $foundIndex = $i;
        break;
    }
}

// ===== DELETE ACTION =====
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
    renumberPositions($coaches);

    if (!saveFile($coachesPath, $coaches)) failWrite();

    echo json_encode(['success' => true, 'message' => 'Coach deleted']);
    exit;
}

// ===== EXTRACT UPDATED FIELDS =====
$newData = [
    'Username'        => $username,
    'CoachName'       => trim($input['CoachName'] ?? ''),
    'Email'           => trim($input['Email'] ?? ''),
    'Role'            => strtolower(trim($input['Role'] ?? 'coach')) === 'admin' ? 'admin' : 'coach',
    'DisplayOrder'    => max(1, intval($input['DisplayOrder'] ?? 999)),
    'Bio'             => $input['Bio'] ?? '',
    'Specializations' => normalizeSpecs($input['Specializations'] ?? ''),
    'Files' => [
        'Profile'     => trim($input['Profile'] ?? ''),
        'Certificate' => trim($input['Certificate'] ?? ''),
        'Before'      => trim($input['Before'] ?? ''),
        'After'       => trim($input['After'] ?? '')
    ]
];

$tempPassword = trim($input['TempPassword'] ?? '');

// ===== KEEP PASSWORD & METADATA ON UPDATE =====
if ($foundIndex !== null) {
    $existing = $coaches[$foundIndex];

    // Password handling
    if ($tempPassword !== '') {
        $newData['Password'] = password_hash($tempPassword, PASSWORD_DEFAULT);
    } elseif (!empty($existing['Password'])) {
        $newData['Password'] = $existing['Password'];
    }

    // Preserve untouched keys (e.g., last_login)
    foreach ($existing as $k => $v) {
        if (!array_key_exists($k, $newData) && $k !== 'Files') {
            $newData[$k] = $v;
        }
    }

    // Remove old before reinserting properly
    array_splice($coaches, $foundIndex, 1);
    $msg = 'Coach updated';
} else {
    // Brand new coach
    if ($tempPassword !== '') {
        $newData['Password'] = password_hash($tempPassword, PASSWORD_DEFAULT);
    }
    $msg = 'Coach created';
}

// ===== INSERT INTO CORRECT POSITION =====
insertAtPosition($coaches, $newData);

// ===== SAVE FILE =====
if (!saveFile($coachesPath, $coaches)) failWrite();

echo json_encode(['success' => true, 'message' => $msg]);


// =====================================================
// SUPPORT FUNCTIONS
// =====================================================

// Normalize specializations into array
function normalizeSpecs($raw) {
    if (is_array($raw)) return $raw;
    return array_values(array_filter(
        array_map('trim', preg_split('/[;,|]+/', (string)$raw)),
        'strlen'
    ));
}

function saveFile($path, $data) {
    return file_put_contents($path,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    ) !== false;
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
 * Insert or move coach to requested DisplayOrder exactly
 */
function insertAtPosition(&$list, $coach) {
    $target = intval($coach['DisplayOrder']);
    if ($target < 1) $target = 1;

    // Sort by current order then ensure it is unique keys
    usort($list, fn($a,$b) =>
        intval($a['DisplayOrder'] ?? 999) - intval($b['DisplayOrder'] ?? 999)
    );

    $maxIndex = count($list);
    if ($target > $maxIndex + 1) $target = $maxIndex + 1;

    array_splice($list, $target - 1, 0, [$coach]);

    // Renumber after insert
    renumberPositions($list);
}

/**
 * 1..N numbering with no alphabetical fallback
 */
function renumberPositions(&$list) {
    foreach ($list as $i => &$c) {
        $c['DisplayOrder'] = $i + 1;
    }
    unset($c);
}
