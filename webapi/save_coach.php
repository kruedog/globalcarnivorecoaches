<?php
// save_coach.php — Admin-only CRUD for uploads/coaches.json

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require admin session
if (empty($_SESSION['username']) || strtolower($_SESSION['role'] ?? 'coach') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

$COACHES_FILE = dirname(__DIR__) . '/uploads/coaches.json';

/**
 * Load coaches array
 */
function load_coaches($path) {
    if (!file_exists($path)) return [];
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

/**
 * Save coaches array atomically
 */
function save_coaches($path, $coaches) {
    $json = json_encode(array_values($coaches), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    return file_put_contents($path, $json) !== false;
}

/**
 * Find index of coach by username
 */
function find_index_by_username($coaches, $username) {
    $username = strtolower(trim($username));
    foreach ($coaches as $i => $c) {
        if (isset($c['Username']) && strtolower($c['Username']) === $username) {
            return $i;
        }
    }
    return null;
}

/**
 * Normalize specializations into array
 */
function normalize_specializations($raw) {
    if (!$raw) return [];
    if (is_array($raw)) {
        return array_values(array_filter(array_map('trim', $raw), 'strlen'));
    }
    $parts = preg_split('/[;,|]/', (string)$raw);
    return array_values(array_filter(array_map('trim', $parts), 'strlen'));
}

/**
 * Normalize file path to just "filename.ext" or "folder/filename.ext" under uploads
 */
function normalize_file_path($p) {
    if (!$p) return '';
    $p = trim($p);
    // Strip protocol + host if present
    $p = preg_replace('#^https?://[^/]+/#i', '', $p);
    // Strip leading slashes
    $p = ltrim($p, '/');
    // Remove leading "public/" if any
    $p = preg_replace('#^public/#i', '', $p);
    // Remove leading "uploads/" if any
    $p = preg_replace('#^uploads/#i', '', $p);
    return $p;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$action   = $input['action']   ?? '';
$username = trim($input['Username'] ?? '');

if ($username === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username is required']);
    exit;
}

$coaches = load_coaches($COACHES_FILE);
$index   = find_index_by_username($coaches, $username);

if ($action === 'create') {
    if ($index !== null) {
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        exit;
    }

    $coach = [
        'Username'        => $username,
        'CoachName'       => $input['CoachName'] ?? $username,
        'Email'           => $input['Email'] ?? '',
        'Bio'             => $input['Bio'] ?? '',
        'Specializations' => normalize_specializations($input['Specializations'] ?? ''),
        'Role'            => $input['Role'] ?: 'Coach',
        'Files'           => []
    ];

    // Hash password if provided
    $tempPassword = trim($input['TempPassword'] ?? '');
    if ($tempPassword !== '') {
        $hash = password_hash($tempPassword, PASSWORD_DEFAULT);
        if ($hash) {
            $coach['Password'] = $hash;
        }
    }

    // Files
    $files = [];
    foreach (['Profile','Certificate','Before','After'] as $key) {
        $val = normalize_file_path($input[$key] ?? '');
        if ($val !== '') {
            $files[$key] = $val;
        }
    }
    if ($files) {
        $coach['Files'] = $files;
    }

    $coaches[] = $coach;

    if (!save_coaches($COACHES_FILE, $coaches)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to write coaches.json']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Coach created', 'coach' => $coach]);
    exit;
}

if ($action === 'update') {
    if ($index === null) {
        echo json_encode(['success' => false, 'message' => 'Coach not found']);
        exit;
    }

    $coach = $coaches[$index];

    $coach['CoachName']       = $input['CoachName'] ?? $coach['CoachName'] ?? $username;
    $coach['Email']           = $input['Email'] ?? $coach['Email'] ?? '';
    $coach['Bio']             = $input['Bio'] ?? $coach['Bio'] ?? '';
    $coach['Specializations'] = normalize_specializations($input['Specializations'] ?? ($coach['Specializations'] ?? []));
    $coach['Role']            = $input['Role'] ?: ($coach['Role'] ?? 'Coach');

    // Optional password reset
    $tempPassword = trim($input['TempPassword'] ?? '');
    if ($tempPassword !== '') {
        $hash = password_hash($tempPassword, PASSWORD_DEFAULT);
        if ($hash) {
            $coach['Password'] = $hash;
        }
    }

    // Files
    $files = $coach['Files'] ?? [];
    foreach (['Profile','Certificate','Before','After'] as $key) {
        $val = normalize_file_path($input[$key] ?? ($files[$key] ?? ''));
        if ($val !== '') {
            $files[$key] = $val;
        } else {
            unset($files[$key]);
        }
    }
    $coach['Files'] = $files;

    $coaches[$index] = $coach;

    if (!save_coaches($COACHES_FILE, $coaches)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to write coaches.json']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Coach updated', 'coach' => $coach]);
    exit;
}

if ($action === 'delete') {
    if ($index === null) {
        echo json_encode(['success' => false, 'message' => 'Coach not found']);
        exit;
    }

    array_splice($coaches, $index, 1);

    if (!save_coaches($COACHES_FILE, $coaches)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to write coaches.json']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Coach deleted']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
