<?php
/**
 * log_profile_view.php
 * Logs profile views for ANY visitor (public or coach)
 * Dashboard access remains protected
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

session_start();

// Input validation
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['coach'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing coach username']);
    exit;
}

$coach = preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower(trim($input['coach'])));
if ($coach === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid coach']);
    exit;
}

$today = date('Y-m-d');
$sessionKey = "viewed_{$coach}_{$today}";

// Anti-spam: 1 view per coach per session per day
if (isset($_SESSION[$sessionKey])) {
    echo json_encode([
        'success' => true,
        'message' => 'Already logged today (session protected)'
    ]);
    exit;
}
$_SESSION[$sessionKey] = true;

$file = '/data/uploads/profile_views.json';
$data = [
    'totals' => [],
    'history' => []
];

// Load existing file
if (file_exists($file)) {
    $decoded = json_decode(file_get_contents($file), true);
    if (is_array($decoded)) $data = array_merge($data, $decoded);
}

// Increment total
$data['totals'][$coach] = ($data['totals'][$coach] ?? 0) + 1;

// Increment/day history
$found = false;
foreach ($data['history'] as &$row) {
    if ($row['date'] === $today && $row['coach'] === $coach) {
        $row['views']++;
        $found = true;
        break;
    }
}
unset($row);

if (!$found) {
    $data['history'][] = [
        'date' => $today,
        'coach' => $coach,
        'views' => 1
    ];
}

// Trim to last 180 days
$cutoff = (new DateTime())->modify('-180 days')->format('Y-m-d');
$data['history'] = array_values(array_filter($data['history'],
    fn($r) => isset($r['date']) && $r['date'] >= $cutoff
));

file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));

echo json_encode([
    'success' => true,
    'total_views' => array_sum($data['totals']),
    'totals_per_coach' => $data['totals']
]);

