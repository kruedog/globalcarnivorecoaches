<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['username'])) {
    echo json_encode(['success'=>false,'message'=>'Not logged in']);
    exit;
}

$username = trim($_POST['username'] ?? '');
if (!$username) {
    echo json_encode(['success'=>false,'message'=>'Username missing']);
    exit;
}

$file = __DIR__.'/../uploads/coaches.json';
$coaches = json_decode(file_get_contents($file), true);
if (!is_array($coaches)) $coaches = [];

$index = array_search($username, array_column($coaches,'Username'));
if ($index === false) {
    echo json_encode(['success'=>false,'message'=>'Coach not found']);
    exit;
}

$c = &$coaches[$index];

// updates
$c['CoachName'] = $_POST['coachName'] ?? $c['CoachName'];
$c['Email']     = $_POST['email'] ?? $c['Email'];
$c['Phone']     = $_POST['phone'] ?? $c['Phone'];
$c['Bio']       = $_POST['bio'] ?? $c['Bio'];

if (isset($_POST['specializations'])) {
    $c['Specializations'] = json_decode($_POST['specializations'], true);
}

// password reset
if (!empty($_POST['newPassword'])) {
    $c['Password'] = password_hash($_POST['newPassword'], PASSWORD_DEFAULT);
}

// save
file_put_contents($file, json_encode($coaches, JSON_PRETTY_PRINT));

echo json_encode(['success'=>true,'message'=>'Profile updated']);
?>
