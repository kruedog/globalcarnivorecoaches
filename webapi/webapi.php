<?php
// webapi/webapi.php — DASHBOARD API (Stats + 14-day history)

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Cache-Control: no-cache');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === 'https://globalcarnivorecoaches.onrender.com') {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login_json(): void {
    if (empty($_SESSION['username'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Not logged in'
        ]);
        exit;
    }
}

$DIR    = __DIR__;
$action = $_GET['action'] ?? '';

// =======================
// ACTION: get_stats
// =======================
if ($action === 'get_stats') {

    require_login_json();
    header('Content-Type: application/json; charset=utf-8');

    $visitsFile = $DIR . '/visits.json';

    $today         = 0;
    $week          = 0;
    $total         = 0;
    $locationCount = [];

    if (file_exists($visitsFile)) {
        $v = json_decode(file_get_contents($visitsFile), true) ?: [];

        $today = (int)($v['today'] ?? 0);
        $week  = (int)($v['week'] ?? 0);
        $total = (int)($v['total'] ?? 0);

        $locs = $v['locations'] ?? [];
        if (is_array($locs)) {
            $locationCount = array_count_values($locs);
        }
    }

    echo json_encode([
        'success'       => true,
        'today'         => $today,
        'week'          => $week,
        'total'         => $total,
        'locationCount' => $locationCount
    ]);
    exit;
}

// =======================
// ACTION: get_visits_14days
// =======================
if ($action === 'get_visits_14days') {

    require_login_json();
    header('Content-Type: application/json; charset=utf-8');

    $historyFile = $DIR . '/visits_history.json';
    $history     = [];

    if (file_exists($historyFile)) {
        $history = json_decode(file_get_contents($historyFile), true);
        if (!is_array($history)) {
            $history = [];
        }
    }

    // Normalize, sort oldest → newest
    $clean = [];
    foreach ($history as $rec) {
        if (!isset($rec['date'], $rec['count'])) continue;
        $clean[] = [
            'date'  => (string)$rec['date'],
            'count' => (int)$rec['count']
        ];
    }

    usort($clean, fn($a, $b) => strcmp($a['date'], $b['date']));

    echo json_encode([
        'success' => true,
        'points'  => $clean
    ]);
    exit;
}

// =======================
// DEFAULT: invalid action
// =======================
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => false,
    'message' => 'Invalid or missing action'
]);
exit;
