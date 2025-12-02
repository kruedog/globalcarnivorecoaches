<?php
// get_coach.php — Render-ready version (Dec 2025)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$identifier = trim($_GET['email'] ?? $_GET['username'] ?? '');
if ($identifier === '') {
    echo json_encode(['success' => false, 'message' => 'Email or username required']);
    exit;
}

$coachesFile = __DIR__ . '/coaches.json';
if (!file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'coaches.json not found']);
    exit;
}

// Read and clean JSON
$content = @file_get_contents($coachesFile);
if ($content === false) {
    echo json_encode(['success' => false, 'message' => 'Cannot read coaches.json']);
    exit;
}

// Remove BOM, normalize line endings
$content = preg_replace('/^\xEF\xBB\xBF/', '', trim($content));
$content = str_replace(["\r\n", "\r"], "\n", $content);

$coaches = json_decode($content, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($coaches)) $coaches = [];

// Find coach
$found = null;
foreach ($coaches as $coach) {
    if (!is_array($coach)) continue;
    $emailMatch = isset($coach['Email']) && strcasecmp($coach['Email'], $identifier) === 0;
    $userMatch  = isset($coach['Username']) && strcasecmp($coach['Username'], $identifier) === 0;
    if ($emailMatch || $userMatch) { $found = $coach; break; }
}

if (!$found) {
    echo json_encode(['success' => false, 'message' => 'Coach not found']);
    exit;
}

// Fix Windows paths
if (isset($found['Files']) && is_array($found['Files'])) {
    foreach ($found['Files'] as $type => $path) {
        $found['Files'][$type] = str_replace('\\','/',$path);
        $found['Files'][$type] = preg_replace('#^uploads/+#','uploads/',$found['Files'][$type]);
    }
}

echo json_encode([
    'success' => true,
    'coach'   => $found
], JSON_UNESCAPED_SLASHES);
?>
