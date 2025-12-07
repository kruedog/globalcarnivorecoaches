<?php
// webapi/webapi.php  —  DASHBOARD API ONLY (SAFE & WORKING)

header('Cache-Control: no-cache');
header('Access-Control-Allow-Origin: *');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$DIR = __DIR__;

/**
 * Require a logged-in session for JSON responses
 */
function require_login_json() {
    if (empty($_SESSION['username'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'not_logged_in']);
        exit;
    }
}

// ===============================
// 1. GET STATS (visitors + locations)
// ===============================
if (isset($_GET['action']) && $_GET['action'] === 'get_stats') {
    require_login_json();
    header('Content-Type: application/json; charset=utf-8');

    $visitsFile = "$DIR/visits.json";
    $stats = [
        'today'        => 0,
        'week'         => 0,
        'total'        => 0,
        'loginsToday'  => 0,
        'locations'    => [],
        'locationCount'=> []
    ];

    if (file_exists($visitsFile)) {
        $v = json_decode(file_get_contents($visitsFile), true);
        $stats['today']     = $v['today']     ?? 0;
        $stats['week']      = $v['week']      ?? 0;
        $stats['total']     = $v['total']     ?? 0;
        $stats['locations'] = $v['locations'] ?? [];
        $stats['locationCount'] = array_count_values($v['locations'] ?? []);
    }

    // Count today's logins from activity_log.json
    $logFile = "$DIR/activity_log.json";
    if (file_exists($logFile)) {
        $log = json_decode(file_get_contents($logFile), true) ?: [];
        $today = date('Y-m-d');
        foreach ($log as $entry) {
            if (($entry['type'] ?? '') === 'login' && isset($entry['time'])) {
                $ts = $entry['time'];
                $entryDate = is_numeric($ts)
                    ? date('Y-m-d', $ts / 1000)
                    : date('Y-m-d', strtotime($ts));
                if ($entryDate === $today) {
                    $stats['loginsToday']++;
                }
            }
        }
    }

    echo json_encode($stats);
    exit;
}

// ===============================
// 2. GET COACHES LIST
// ===============================
if (isset($_GET['action']) && $_GET['action'] === 'get_coaches') {
    require_login_json();
    header('Content-Type: application/json; charset=utf-8');

    $file = dirname($DIR) . '/uploads/coaches.json';
    $coaches = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    echo json_encode($coaches ?: []);
    exit;
}

// ===============================
// 3. 14-DAY VISITOR CHART (simple placeholder)
// ===============================
if (isset($_GET['action']) && $_GET['action'] === 'get_visits_14days') {
    require_login_json();
    header('Content-Type: application/json; charset=utf-8');

    $data = [];
    for ($i = 13; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        // You can replace this with real history later
        $visits = $i === 0 ? 8 : rand(3, 22);
        $data[] = ['x' => $date, 'y' => $visits];
    }
    echo json_encode($data);
    exit;
}

// ===============================
// 4. TRACK ADMIN VISIT (optional)
// ===============================
// Left public – index.html may still ping track_visit.php directly.
if (isset($_GET['action']) && $_GET['action'] === 'track_visit') {
    @file_get_contents("https://{$_SERVER['HTTP_HOST']}/webapi/track_visit.php");
    exit;
}

// ===============================
// 5. REAL-TIME ACTIVITY FEED (SSE)
// ===============================
if (isset($_GET['action']) && $_GET['action'] === 'notify') {
    if (empty($_SESSION['username'])) {
        http_response_code(401);
        exit;
    }

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    echo "retry: 3000\n\n";

    $lastMod = 0;
    $file = "$DIR/activity_log.json";

    while (true) {
        clearstatcache();
        if (file_exists($file)) {
            $mod = filemtime($file);
            if ($mod > $lastMod) {
                $log = json_decode(file_get_contents($file), true) ?: [];
                $latest = $log[0] ?? null;
                if ($latest) {
                    $latest['action'] = $latest['type'] ?? 'activity';
                    echo "data: " . json_encode($latest) . "\n\n";
                    @ob_flush();
                    @flush();
                }
                $lastMod = $mod;
            }
        }
        sleep(3);
    }
    exit;
}

// Default fallback
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['error' => 'no action']);
