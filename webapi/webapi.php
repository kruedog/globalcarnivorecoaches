<?php
// webapi/webapi/webapi.php — FINAL 2025 VERSION
// Works perfectly on Render + NAS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');
date_default_timezone_set('America/New_York');

$DIR = __DIR__;
$ACTIVITY_FILE = "$DIR/activity_log.json";
$VISITS_FILE = "$DIR/visits.json";
$COACHES_FILE = "$DIR/coaches.json";  // if you use one

// Helper: safe JSON read/write
function json_file_get($file, $default = []) {
    if (!file_exists($file)) return $default;
    $content = @file_get_contents($file);
    if (!$content) return $default;
    $data = json_decode($content, true);
    return $data ?: $default;
}
function json_file_put($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Get client IP + location (fallback if no API)
function get_visitor_info() {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $loc = 'Unknown Location';
    if (isset($_SERVER['HTTP_CF_IPCOUNTRY'])) {
        $loc = trim($_SERVER['HTTP_CF_IPCOUNTRY']);
    }
    return ['ip' => $ip, 'location' => $loc];
}

$action = $_GET['action'] ?? '';

// ——— TRACK EVERY VISITNG (called on every page load) ———
if ($action === 'track_visit' || empty($action)) {
    $info = get_visitor_info();
    $today = date('Y-m-d');
    $week = date('Y-W');

    $visits = json_file_get($VISITS_FILE, [
        'total' => 0,
        'today' => 0,
        'week' => 0,
        'last_day' => '',
        'last_week' => '',
        'ips_today' => [],
        'locations' => []
    ]);

    // New visitor today?
    if (!in_array($info['ip'], $visits['ips_today'])) {
        $visits['ips_today'][] = $info['ip'];
        $visits['today']++;
        $visits['total']++;
        $visits['week']++;
        if (!in_array($info['location'], $visits['locations'])) {
            $visits['locations'][] = $info['location'];
        }
    }

    // Reset daily/weekly if needed
    if ($visits['last_day'] !== $today) {
        $visits['today'] = 1;
        $visits['ips_today'] = [$info['ip']];
        $visits['last_day'] = $today;
    }
    if ($visits['last_week'] !== $week) {
        $visits['week'] = 1;
        $visits['last_week'] = $week;
    }

    json_file_put($VISITS_FILE, $visits);

    // Log activity
    $log = json_file_get($ACTIVITY_FILE, []);
    array_unshift($log, [
        'time' => date('Y-m-d H:i:s'),
        'type' => 'visit',
        'coachName' => 'Visitor',
        'details' => $info['location'],
        'location' => $info['location']
    ]);
    $log = array_slice($log, 0, 200); // keep last 200
    json_file_put($ACTIVITY_FILE, $log);

    if (empty($action)) {
        echo json_encode(['status' => 'tracked']);
    }
    exit;
}

// ——— GET STATS ———
if ($action === 'get_stats') {
    $visits = json_file_get($VISITS_FILE, []);
    echo json_encode([
        'total' => $visits['total'] ?? 0,
        'today' => $visits['today'] ?? 0,
        'week' => $visits['week'] ?? 0,
        'loginsToday' => 0, // will be updated by login.php
        'locations' => array_values(array_unique($visits['locations'] ?? []))
    ]);
    exit;
}

// ——— GET ACTIVITY LOG ———
if ($action === 'get_activity') {
    $log = json_file_get($ACTIVITY_FILE, []);
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
    echo json_encode(array_slice($log, 0, $limit));
    exit;
}

// ——— GET VISITORS LAST 14 DAYS (for chart) ———
if ($action === 'get_visits_14days') {
    $visits = json_file_get($VISITS_FILE, []);
    $data = [];
    for ($i = 13; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $data[] = ['x' => $date, 'y' => $visits['daily'][$date] ?? 0];
    }
    echo json_encode($data);
    exit;
}

// ——— SERVER-SENT EVENTS FOR REAL-TIME ———
if ($action === 'notify') {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    $lastId = $_SERVER['HTTP_LAST_EVENT_ID'] ?? 0;
    while (true) {
        $log = json_file_get($ACTIVITY_FILE, []);
        if (count($log) > $lastId) {
            $event = $log[0];
            $event['color'] = match($event['type']) {
                'login' => '#28a745',
                'profile_update' => '#17a2b8',
                'image_upload' => '#9c27b0',
                default => '#666'
            };
            echo "id: " . count($log) . "\n";
            echo "data: " . json_encode($event) . "\n\n";
            ob_flush(); flush();
            $lastId = count($log);
        }
        sleep(3);
    }
    exit;
}

// ——— FALLBACK / DEBUG ———
echo json_encode([
    'status' => 'webapi.php ACTIVE',
    'time' => date('c'),
    'action' => $action,
    'files' => [
        'activity' => file_exists($ACTIVITY_FILE),
        'visits' => file_exists($VISITS_FILE)
    ]
]);