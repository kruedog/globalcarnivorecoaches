<?php
// get_coach.php — JSON + Persistent Disk — Dec 2025
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$identifier = trim($_GET['email'] ?? $_GET['username'] ?? '');
if ($identifier === '') {
    echo json_encode(['success' => false, 'message' => 'Email or username required']);
    exit;
}

$coachesFile = __DIR__ . '/coaches.json';
if (!file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'coaches.json missing']);
    exit;
}

$content = file_get_contents($coachesFile);
$content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // remove BOM
$content = str_replace(["\r\n", "\r"], "\n", $content);

$coaches = json_decode($content, true);
if (!is_array($coaches)) $coaches = [];

$foundCoach = null;
foreach ($coaches as $c) {
    if (!is_array($c)) continue;

    if (
        (isset($c['Email']) && strcasecmp($c['Email'], $identifier) === 0) ||
        (isset($c['Username']) && strcasecmp($c['Username'], $identifier) === 0)
    ) {
        $foundCoach = $c;
        break;
    }
}

if (!$foundCoach) {
    echo json_encode(['success' => false, 'message' => 'Coach not found']);
    exit;
}

// Normalize Files values: they should be *filenames only*
// and frontend displays them as `/uploads/<filename>`
if (!empty($foundCoach['Files']) && is_array($foundCoach['Files'])) {
    foreach ($foundCoach['Files'] as $type => $filename) {
        if (!$filename) continue;
        $filename = basename($filename); // ensure no paths leak through
        $foundCoach['Files'][$type] = $filename;
    }
}

echo json_encode([
    'success' => true,
    'coach' => $foundCoach
], JSON_UNESCAPED_SLASHES);
exit;
