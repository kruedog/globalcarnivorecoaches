<?php
// webapi/webapi.php — FIXED & STABLE (Visitor Dashboard SAFE)

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Cache-Control: no-cache');

// === Only allow Render origin so session cookies work ===
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === 'https://globalcarnivorecoaches.onrender.com') {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login_json() {
    if (empty($_SESSION['username'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
}

$DIR = __DIR__;

// =======================
// ACTION: get_stats
// =======================
if (($_GET['action'] ?? '') === 'get_stats') {

    require_login_json();
    header('Content-Type: application/json; charset=utf-8');

    $visitsFile = "$DIR/visits.json";
    $locationCounts = [];

    $today = 0;
    $week = 0;
    $total = 0;

    if (file_exists($visitsFile)) {
        $v = json_decode(file_get_contents($visitsFile), true) ?: [];
        $today = $v['today'] ?? 0;
        $week  = $v['week'] ?? 0;
        $total = $v['total'] ?? 0;
        $locs  = $v['locations'] ?? [];
        $locationCounts = array_count_values($locs);
    }

    echo json_encode([
        'success'       => true,
        'today'         => $today,
        'week'          => $week,
        'total'         => $total,
        'locationCount' => $locationCounts
    ]);
    exit;
}

// Anything else →
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => false, 'message' => 'Invalid or missing action']);
exit;
