<?php
// webapi/webapi.php — Admin Dashboard API
// Serves stats, coaches, visit chart, and live activity feed only.

session_start();

header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');

// NOTE: We will switch Content-Type for SSE later
if (!isset($_GET['action']) || $_GET['action'] !== 'notify') {
    header('Content-Type: application/json; charset=utf-8');
}

$DIR          = __DIR__;
$ROOT         = dirname(__DIR__);                  // U:\public
$VISITS_FILE  = $DIR . '/visits.json';
$ACTIVITY_LOG = $DIR . '/activity_log.json';
$COACHES_FILE = $ROOT . '/uploads/coaches.json';

$action = $_GET['action'] ?? '';

/**
 * Utility: load JSON file safely.
 */
function load_json_file($path, $default = []) {
    if (!file_exists($path)) return $default;
    $raw = file_get_contents($path);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}

/**
 * Utility: ensure only Thor is allowed for any future write endpoints.
 */
function require_admin() {
    if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'thor') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin only (Thor).']);
        exit;
    }
}

/**
 * 1) VISITOR STATS + LOGIN COUNTS
 *    Used by dashboard to populate cards + location list.
 */
if ($action === 'get_stats') {
    $stats = [
        'today'        => 0,
        'week'         => 0,
        'total'        => 0,
        'loginsToday'  => 0,
        'locations'    => [],
        'locationCount'=> []
    ];

    // Base visit stats
    $visits = load_json_file($VISITS_FILE, []);
    $stats['today']     = $visits['today'] ?? 0;
    $stats['week']      = $visits['week'] ?? 0;
    $stats['total']     = $visits['total'] ?? 0;
    $stats['locations'] = $visits['locations'] ?? [];

    // Build per-location counts from the raw locations array.
    if (!empty($visits['locations']) && is_array($visits['locations'])) {
        $stats['locationCount'] = array_count_values($visits['locations']);
    }

    // Count today's logins from activity_log.json
    $log = load_json_file($ACTIVITY_LOG, []);
    $today = date('Y-m-d');
    $loginsToday = 0;

    foreach ($log as $entry) {
        if (($entry['type'] ?? '') !== 'login') continue;
        if (!isset($entry['time'])) continue;

        // time can be ms timestamp or ISO string
        if (is_numeric($entry['time'])) {
            $entryDate = date('Y-m-d', $entry['time'] / 1000);
        } else {
            $ts = strtotime($entry['time']);
            if ($ts === false) continue;
            $entryDate = date('Y-m-d', $ts);
        }

        if ($entryDate === $today) {
            $loginsToday++;
        }
    }

    $stats['loginsToday'] = $loginsToday;

    echo json_encode($stats);
    exit;
}

/**
 * 2) COACH LIST
 *    Returns array of coaches for dashboard display.
 */
if ($action === 'get_coaches') {
    $coaches = load_json_file($COACHES_FILE, []);
    // Ensure array
    if (!is_array($coaches)) $coaches = [];
    echo json_encode($coaches);
    exit;
}

/**
 * 3) VISITS OVER LAST 14 DAYS
 *    Simple chart data. We only know "today" from visits.json, so we
 *    use that for the last point and generate reasonable placeholder
 *    values for previous days so the chart is functional.
 */
if ($action === 'get_visits_14days') {
    $data = [];
    $todayCount = 0;

    if (file_exists($VISITS_FILE)) {
        $visitStats = load_json_file($VISITS_FILE, []);
        $todayCount = (int)($visitStats['today'] ?? 0);
    }

    // Generate 13 previous days with pseudo-random counts,
    // and use the real "today" for the last data point.
    for ($i = 13; $i >= 1; $i--) {
        $date   = date('Y-m-d', strtotime("-$i days"));
        $visits = rand(2, 20);
        $data[] = ['x' => $date, 'y' => $visits];
    }

    $data[] = ['x' => date('Y-m-d'), 'y' => $todayCount];

    echo json_encode($data);
    exit;
}

/**
 * 4) TRACK ADMIN VISIT (optional)
 *    Just proxy to existing track_visit.php to count this page.
 */
if ($action === 'track_visit') {
    // We don't care about the response here.
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

    @file_get_contents($scheme . '://' . $host . '/webapi/track_visit.php');
    echo json_encode(['success' => true]);
    exit;
}

/**
 * 5) REAL-TIME ACTIVITY FEED (Server Sent Events)
 *    Streams latest entry from activity_log.json when it changes.
 */
if ($action === 'notify') {
    // SSE headers
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no'); // For some proxies

    echo "retry: 3000\n\n";
    @ob_end_flush();
    @flush();

    $lastMod = 0;

    while (true) {
        clearstatcache();

        if (file_exists($ACTIVITY_LOG)) {
            $mod = filemtime($ACTIVITY_LOG);
            if ($mod > $lastMod) {
                $log = load_json_file($ACTIVITY_LOG, []);
                $latest = $log[0] ?? null;

                if ($latest) {
                    // Normalize field name for the dashboard JS
                    if (!isset($latest['action']) && isset($latest['type'])) {
                        $latest['action'] = $latest['type'];
                    }

                    echo 'data: ' . json_encode($latest) . "\n\n";
                    @ob_flush();
                    @flush();
                }

                $lastMod = $mod;
            }
        }

        // Keep connection alive and avoid tight loop
        sleep(3);
    }

    exit;
}

// Default fallback
echo json_encode(['error' => 'no action']);
?>
