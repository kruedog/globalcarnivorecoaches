<?php
// webapi/save_coach.php — Create / Update / Delete coaches
// Writes to: U:\public\uploads\coaches.json

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');

session_start();

// Only Thor can make structural changes
if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'thor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin only (Thor).']);
    exit;
}

require_once __DIR__ . '/log_activity.php';

$ROOT         = dirname(__DIR__); // U:\public
$COACHES_FILE = $ROOT . '/uploads/coaches.json';

// Accept JSON or standard POST
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data) || empty($data)) {
    $data = $_POST; // fallback
}

$action = strtolower(trim($data['action'] ?? ''));

if ($action === '') {
    echo json_encode(['success' => false, 'message' => 'Missing action (create, update, delete).']);
    exit;
}

// Load coaches
$coaches = [];
if (file_exists($COACHES_FILE)) {
    $decoded = json_decode(file_get_contents($COACHES_FILE), true);
    if (is_array($decoded)) {
        $coaches = $decoded;
    }
}

// Helper: find coach index by Username (case-insensitive)
function find_coach_index(array $coaches, string $username) {
    foreach ($coaches as $i => $coach) {
        if (isset($coach['Username']) && strcasecmp($coach['Username'], $username) === 0) {
            return $i;
        }
    }
    return null;
}

function parse_specializations($value) {
    if (!$value) return [];
    if (is_array($value)) {
        // Clean array
        $out = [];
        foreach ($value as $v) {
            $v = trim((string)$v);
            if ($v !== '') $out[] = $v;
        }
        return $out;
    }
    // String => split on , ; |
    $parts = preg_split('/[;,|]/', (string)$value);
    $out   = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '') $out[] = $p;
    }
    return $out;
}

// Normalize user/coach fields
$username = trim($data['Username'] ?? $data['username'] ?? '');
if ($username === '') {
    echo json_encode(['success' => false, 'message' => 'Username is required.']);
    exit;
}

if ($action === 'delete') {
    $index = find_coach_index($coaches, $username);
    if ($index === null) {
        echo json_encode(['success' => false, 'message' => 'Coach not found.']);
        exit;
    }

    $deleted = $coaches[$index];
    array_splice($coaches, $index, 1);

    file_put_contents($COACHES_FILE, json_encode($coaches, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    if (function_exists('log_coach_activity')) {
        log_coach_activity('coach_delete', ['username' => $username]);
    }

    echo json_encode(['success' => true, 'message' => 'Coach deleted', 'coach' => $deleted]);
    exit;
}

// For create/update
$coachName = trim($data['CoachName'] ?? $data['coachName'] ?? '');
$email     = trim($data['Email']     ?? $data['email']     ?? '');
$bio       = $data['Bio']           ?? $data['bio']       ?? '';

$specRaw = $data['Specializations'] ?? $data['specializations'] ?? '';
$specs   = parse_specializations($specRaw);

// Optional file fields (filenames or relative paths, no upload here)
$fileKeys = ['Profile', 'Before', 'After', 'Certificate'];

$index = find_coach_index($coaches, $username);
$entry = ($index !== null) ? $coaches[$index] : [];

// Preserve other fields (like Password, timestamps, etc.)
$entry['Username'] = $username;
if ($coachName !== '') $entry['CoachName'] = $coachName;
if ($email !== '')     $entry['Email']     = $email;
if ($bio !== '')       $entry['Bio']       = $bio;

// Specializations
$entry['Specializations'] = $specs;

// Files
$files = isset($entry['Files']) && is_array($entry['Files']) ? $entry['Files'] : [];
foreach ($fileKeys as $key) {
    $lowerKey = strtolower($key);
    if (array_key_exists($key, $data)) {
        $value = trim((string)$data[$key]);
        if ($value !== '') $files[$key] = $value;
    } elseif (array_key_exists($lowerKey, $data)) {
        $value = trim((string)$data[$lowerKey]);
        if ($value !== '') $files[$key] = $value;
    }
}
if (!empty($files)) {
    $entry['Files'] = $files;
}

if ($index !== null) {
    // Update existing
    $coaches[$index] = $entry;
    $msg = 'Coach updated';
    $logType = 'coach_update';
} else {
    // Create new
    $coaches[] = $entry;
    $msg = 'Coach created';
    $logType = 'coach_create';
}

file_put_contents($COACHES_FILE, json_encode($coaches, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

if (function_exists('log_coach_activity')) {
    log_coach_activity($logType, ['username' => $username, 'coachName' => $coachName]);
}

echo json_encode(['success' => true, 'message' => $msg, 'coach' => $entry]);
?>
