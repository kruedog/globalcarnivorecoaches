<?php
// /webapi/save_coach.php
// Admin-only: create/update/delete coaches in uploads/coaches.json

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

if (empty($_SESSION['coach_username']) || strtolower($_SESSION['coach_role'] ?? 'coach') !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Admin access required'
    ]);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON body'
    ]);
    exit;
}

$action   = strtolower((string)($data['action'] ?? ''));
$username = trim((string)($data['Username'] ?? ''));

if ($username === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Username is required.'
    ]);
    exit;
}

$COACHES_FILE = __DIR__ . '/../uploads/coaches.json';

$coaches = [];
if (file_exists($COACHES_FILE)) {
    $coaches = json_decode(file_get_contents($COACHES_FILE), true);
    if (!is_array($coaches)) {
        $coaches = [];
    }
}

// Helper: find index
$idx    = null;
$luname = strtolower($username);
foreach ($coaches as $i => $c) {
    if (strtolower($c['Username'] ?? '') === $luname) {
        $idx = $i;
        break;
    }
}

if ($action === 'delete') {
    if ($idx === null) {
        echo json_encode([
            'success' => false,
            'message' => 'Coach not found'
        ]);
        exit;
    }
    array_splice($coaches, $idx, 1);
    file_put_contents(
        $COACHES_FILE,
        json_encode($coaches, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    echo json_encode([
        'success' => true,
        'message' => 'Coach deleted.'
    ]);
    exit;
}

// CREATE or UPDATE
$coachName      = trim((string)($data['CoachName'] ?? ''));
$email          = trim((string)($data['Email'] ?? ''));
$bio            = (string)($data['Bio'] ?? '');
$specRaw        = (string)($data['Specializations'] ?? '');
$role           = strtolower((string)($data['Role'] ?? 'coach'));
$tempPassword   = (string)($data['TempPassword'] ?? ''); // optional temp password

// parse specs (string -> array)
$specs = [];
if ($specRaw !== '') {
    $parts = preg_split('/[;,|]/', $specRaw);
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '') {
            $specs[] = $p;
        }
    }
}

// Files (paths stored in JSON)
$files = [
    'Profile'     => trim((string)($data['Profile'] ?? '')),
    'Certificate' => trim((string)($data['Certificate'] ?? '')),
    'Before'      => trim((string)($data['Before'] ?? '')),
    'After'       => trim((string)($data['After'] ?? '')),
];

// normalize empty -> null
foreach ($files as $k => $v) {
    $files[$k] = ($v === '') ? null : $v;
}

$newCoach = [
    'Username'        => $username,
    'CoachName'       => $coachName,
    'Email'           => $email,
    'Bio'             => $bio,
    'Role'            => $role ?: 'coach',
    'Specializations' => $specs,
    'Files'           => $files
];

// If existing, merge to keep PasswordHash if not changing
if ($idx !== null) {
    $existing = $coaches[$idx];
    if (!empty($existing['PasswordHash'])) {
        $newCoach['PasswordHash'] = $existing['PasswordHash'];
    } elseif (!empty($existing['Password'])) {
        $newCoach['Password'] = $existing['Password'];
    }
    // allow override Role if admin changed it
}

// Handle temp password (reset)
if ($tempPassword !== '') {
    $newCoach['PasswordHash'] = password_hash($tempPassword, PASSWORD_DEFAULT);
    unset($newCoach['Password']);
}

if ($idx === null) {
    $coaches[] = $newCoach;
} else {
    $coaches[$idx] = $newCoach;
}

file_put_contents(
    $COACHES_FILE,
    json_encode($coaches, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo json_encode([
    'success' => true,
    'message' => ($idx === null ? 'Coach created.' : 'Coach updated.'),
    'coach'   => $newCoach
]);
