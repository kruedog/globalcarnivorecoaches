<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$identifier = trim($_GET['email'] ?? $_GET['username'] ?? '');
if ($identifier === '') {
    echo json_encode(['success'=>false,'message'=>'Email or username required']);
    exit;
}

$coachesFile = __DIR__ . '/coaches.json';
if (!file_exists($coachesFile)) {
    echo json_encode(['success'=>false,'message'=>'coaches.json missing']);
    exit;
}

$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) $coaches = [];

$found = null;
foreach ($coaches as $c) {
    if ((isset($c['Email']) && strcasecmp($c['Email'],$identifier)===0) ||
        (isset($c['Username']) && strcasecmp($c['Username'],$identifier)===0)) {
        $found = $c;
        break;
    }
}

if (!$found) {
    echo json_encode(['success'=>false,'message'=>'Coach not found']);
    exit;
}

if (isset($found['Files'])) {
    foreach ($found['Files'] as $k=>$v) {
        $found['Files'][$k] = basename($v);
    }
}

echo json_encode(['success'=>true,'coach'=>$found], JSON_UNESCAPED_SLASHES);
exit;
?>
