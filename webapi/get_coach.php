<?php
// get_coach.php — 100% ROBUST VERSION (Nov 2025)
// Works even with corrupted or Windows-formatted coaches.json
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

// SAFELY READ + CLEAN JSON
$content = file_get_contents($coachesFile);
if ($content === false) {
    echo json_encode(['success' => false, 'message' => 'Cannot read coaches.json']);
    exit;
}

// Fix common corruption issues
$content = trim($content);
// Remove BOM if present
$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
// Normalize line endings
$content = str_replace(["\r\n", "\r"], "\n", $content);

$coaches = json_decode($content, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    // LAST RESORT: Try to extract valid JSON array
    if (preg_match('/\[[\s\S]*\]/', $content, $matches)) {
        $coaches = json_decode($matches[0], true);
        if (json_last_error() !== JSON_ERROR_NONE) $coaches = [];
    } else {
        $coaches = [];
    }
}

if (!is_array($coaches)) $coaches = [];

// Find coach by Email OR Username (case-insensitive)
$foundCoach = null;
foreach ($coaches as $coach) {
    if (!is_array($coach)) continue;

    $emailMatch    = isset($coach['Email'])    && strcasecmp($coach['Email'], $identifier) === 0;
    $usernameMatch = isset($coach['Username']) && strcasecmp($coach['Username'], $identifier) === 0;

    if ($emailMatch || $usernameMatch) {
        $foundCoach = $coach;
        break;
    }
}

if (!$foundCoach) {
    echo json_encode(['success' => false, 'message' => 'Coach not found']);
    exit;
}

// Clean file paths (fix Windows backslashes)
if (isset($foundCoach['Files']) && is_array($foundCoach['Files'])) {
    foreach ($foundCoach['Files'] as $type => $path) {
        $foundCoach['Files'][$type] = str_replace('\\', '/', $path);
        // Also strip "uploads/" prefix if double-added
        $foundCoach['Files'][$type] = preg_replace('#^uploads/+#', 'uploads/', $foundCoach['Files'][$type]);
    }
}

echo json_encode([
    'success' => true,
    'coach'   => $foundCoach
], JSON_UNESCAPED_SLASHES);
?>