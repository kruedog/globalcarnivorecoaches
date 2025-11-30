<?php
// get_coaches.php — Returns all coaches from coaches.json
// @Ibanez_Mat | Global Carnivore Coaches | November 16, 2025
header('Content-Type: application/json');

$coachesFile = __DIR__ . '/coaches.json';

if (!file_exists($coachesFile)) {
    echo json_encode(['success' => false, 'message' => 'coaches.json not found']);
    exit;
}

$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) {
    echo json_encode(['success' => false, 'message' => 'Invalid coaches.json']);
    exit;
}

// Clean slashes in file paths for all coaches (matches get_coach.php)
foreach ($coaches as &$coach) {
    if (isset($coach['Files']) && is_array($coach['Files'])) {
        foreach ($coach['Files'] as $type => $path) {
            $coach['Files'][$type] = str_replace('\\', '/', $path);
        }
    }
}

echo json_encode([
    'success' => true,
    'coaches' => $coaches
]);
?>