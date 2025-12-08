<?php
/**
 * get_profile_views.php
 * Returns profile view stats for dashboard charts
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

session_start();
if (!isset($_SESSION['username'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$file = __DIR__ . '/profile_views.json';

if (!file_exists($file)) {
    echo json_encode([
        "success" => true,
        "total_views" => 0,
        "totals_per_coach" => [],
        "history" => []
    ]);
    exit;
}

$data = json_decode(file_get_contents($file), true);
if (!is_array($data)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid profile_views.json",
        "total_views" => 0,
        "totals_per_coach" => [],
        "history" => []
    ]);
    exit;
}

echo json_encode([
    "success" => true,
    "total_views" => array_sum($data['totals'] ?? []),
    "totals_per_coach" => $data['totals'] ?? [],
    "history" => $data['history'] ?? []
]);
