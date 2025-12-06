<?php
header('Content-Type: application/json');

$username = trim($_GET['username'] ?? '');
if ($username === '') {
    echo json_encode(['success' => false, 'message' => 'Username required']);
    exit;
}

$coachesFile = __DIR__ . '/../uploads/coaches.json';

if (!file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'System error']);
    exit;
}

$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) {
    echo json_encode(['success' => false, 'message' => 'Invalid coaches.json']);
    exit;
}

foreach ($coaches as $c) {
    if (strcasecmp($c['Username'], $username) === 0) {
        echo json_encode(['success'=>true, 'coach'=>$c]);
        exit;
    }
}

echo json_encode(['success'=>false, 'message'=>'Coach not found']);
