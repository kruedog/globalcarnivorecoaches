<?php
// webapi/webapi.php — FINAL VERSION (Nov 2025)
// ALL JSON FILES ARE NOW IN THE SAME FOLDER AS THIS SCRIPT
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

date_default_timezone_set('America/Montreal');

// ——— ERROR LOGGING ———
function log_error($msg) {
    $logfile = __DIR__ . '/error.log';
    $time = date('Y-m-d H:i:s');
    file_put_contents($logfile, "[$time] $msg" . PHP_EOL, FILE_APPEND | LOCK_EX);
}

$action = $_GET['action'] ?? '';
log_error("webapi.php called | ACTION: $action");

// ——— ALL FILES IN SAME FOLDER ———
$ACTIVITY_FILE = __DIR__ . '/activity_log.json';
$VISITS_FILE   = __DIR__ . '/visits.json';

// ——— ROBUST JSON LOADER ———
function load_json($file, $default = []) {
    if (!file_exists($file)) {
        log_error("MISSING: $file");
        return $default;
    }
    $content = file_get_contents($file);
    if ($content === false || trim($content) === '') {
        log_error("EMPTY OR UNREADABLE: $file");
        return $default;
    }
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        log_error("JSON ERROR in " . basename($file) . ": " . json_last_error_msg());
        log_error("First 200 chars: " . substr($content, 0, 200));
        return $default;
    }
    log_error("Loaded " . basename($file) . " → " . count($data) . " entries");
    return $data;
}

// ——— GET ACTIVITY LOG ———
if ($action === 'get_activity') {
    $log = load_json($ACTIVITY_FILE, []);
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $recent = array_slice($log, 0, $limit);
    echo json_encode($recent);
    exit;
}

// ——— GET VISITOR STATS ———
if ($action === 'get_stats') {
    $visits = load_json($VISITS_FILE, [
        'total' => 0, 'today' => 0, 'week' => 0,
        'last_day' => '', 'last_week' => '',
        'ips' => [], 'locations' => []
    ]);

    $locations = is_array($visits['locations'] ?? null) ? $visits['locations'] : [];

    echo json_encode([
        'total'     => (int)($visits['total'] ?? 0),
        'today'     => (int)($visits['today'] ?? 0),
        'week'      => (int)($visits['week'] ?? 0),
        'locations' => array_values(array_unique(array_filter($locations)))
    ]);
    exit;
}

// ——— DEBUG ENDPOINT ———
echo json_encode([
    'status' => 'webapi READY',
    'time'   => date('c'),
    'dir'    => __DIR__,
    'activity_exists' => file_exists($ACTIVITY_FILE),
    'activity_size'   => file_exists($ACTIVITY_FILE) ? filesize($ACTIVITY_FILE) : 0,
    'visits_exists'   => file_exists($VISITS_FILE),
    'visits_size'     => file_exists($VISITS_FILE) ? filesize($VISITS_FILE) : 0,
]);
?>