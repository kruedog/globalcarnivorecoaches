<?php
// get_coach.php — robust version Dec 2025
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$identifier = trim($_GET['email'] ?? $_GET['username'] ?? '');
if ($identifier === '') {
    echo json_encode(['success'=>false,'message'=>'Email or username required']);
    exit;
}

$coachesFile = __DIR__ . '/coaches.json';
if (!file_exists($coachesFile)) {
    echo json_encode(['success'=>false,'message'=>'coaches.json not found']);
    exit;
}

$content = file_get_contents($coachesFile);
$content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // remove BOM
$content = str_replace(["\r\n","\r"], "\n", $content);

$coaches = json_decode($content, true);
if (!is_array($coaches)) $coaches = [];

// find coach (case-insensitive)
$foundCoach = null;
foreach ($coaches as $c) {
    if (!is_array($c)) continue;
    if ((isset($c['Email']) && strcasecmp($c['Email'],$identifier)===0) ||
        (isset($c['Username']) && strcasecmp($c['Username'],$identifier)===0)) {
        $foundCoach = $c;
        break;
    }
}

if (!$foundCoach) {
    echo json_encode(['success'=>false,'message'=>'Coach not found']);
    exit;
}

// normalize file paths
if (isset($foundCoach['Files']) && is_array($foundCoach['Files'])) {
    foreach ($foundCoach['Files'] as $type => $path) {
        $foundCoach['Files'][$type] = str_replace('\\','/',$path);
        $foundCoach['Files'][$type] = preg_replace('#^uploads/+#','uploads/',$foundCoach['Files'][$type]);
    }
}

echo json_encode(['success'=>true,'coach'=>$foundCoach], JSON_UNESCAPED_SLASHES);
exit;
?>
