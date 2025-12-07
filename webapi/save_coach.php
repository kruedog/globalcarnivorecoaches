<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$uploadsDir = dirname(__DIR__) . '/uploads';
$file = $uploadsDir . '/coaches.json';

// Load existing coaches
$coaches = file_exists($file)
    ? json_decode(file_get_contents($file), true)
    : [];

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$action = $input['action'];
$username = trim($input['Username'] ?? '');
if ($username === '') {
    echo json_encode(['success' => false, 'message' => 'Username required']);
    exit;
}

// Helper — find coach index
function findCoachIndex($arr, $user) {
    foreach ($arr as $i => $c) {
        if (strtolower($c['Username']) === strtolower($user)) {
            return $i;
        }
    }
    return -1;
}

$index = findCoachIndex($coaches, $username);

// ----------------------------------------------------------------
// CREATE NEW COACH
if ($action === 'create') {
    if ($index !== -1)
        die(json_encode(['success' => false, 'message' => 'Username already exists']));

    if (empty($input['Password']))
        die(json_encode(['success' => false, 'message' => 'Temp password required for new coach']));

    $coach = [
        'Username'        => $username,
        'CoachName'       => trim($input['CoachName'] ?? ''),
        'Email'           => trim($input['Email'] ?? ''),
        'Bio'             => trim($input['Bio'] ?? ''),
        'Specializations' => preg_split('/[,;|]/', $input['Specializations'] ?? '', -1, PREG_SPLIT_NO_EMPTY),
        'Password'        => password_hash($input['Password'], PASSWORD_DEFAULT),
        'Files' => [
            'Profile'     => trim($input['Profile'] ?? ''),
            'Certificate' => trim($input['Certificate'] ?? ''),
            'Before'      => trim($input['Before'] ?? ''),
            'After'       => trim($input['After'] ?? ''),
        ],
        'created_at' => time()
    ];

    array_push($coaches, $coach);
}

// ----------------------------------------------------------------
// UPDATE EXISTING COACH
elseif ($action === 'update') {
    if ($index === -1)
        die(json_encode(['success' => false, 'message' => 'Coach not found']));

    $coaches[$index]['CoachName']       = trim($input['CoachName'] ?? '');
    $coaches[$index]['Email']           = trim($input['Email'] ?? '');
    $coaches[$index]['Bio']             = trim($input['Bio'] ?? '');
    $coaches[$index]['Specializations'] = preg_split('/[,;|]/', $input['Specializations'] ?? '', -1, PREG_SPLIT_NO_EMPTY);

    $coaches[$index]['Files'] = [
        'Profile'     => trim($input['Profile'] ?? ''),
        'Certificate' => trim($input['Certificate'] ?? ''),
        'Before'      => trim($input['Before'] ?? ''),
        'After'       => trim($input['After'] ?? ''),
    ];

    if (!empty($input['Password'])) {
        $coaches[$index]['Password'] = password_hash($input['Password'], PASSWORD_DEFAULT);
    }
}

// ----------------------------------------------------------------
// DELETE COACH
elseif ($action === 'delete') {
    if ($index === -1)
        die(json_encode(['success' => false, 'message' => 'Coach not found']));
    array_splice($coaches, $index, 1);
}

// ----------------------------------------------------------------
// SAVE TO FILE
if (file_put_contents($file, json_encode($coaches, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true, 'message' => 'Coach saved']);
} else {
    echo json_encode(['success' => false, 'message' => 'Write failed']);
}
?>
