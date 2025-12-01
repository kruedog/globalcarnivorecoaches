<?php
// webapi/webapi.php — CENTRAL API FOR ADMIN DASHBOARD
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');

$DIR = __DIR__;

// ------------------------------------------------------------------
// 1. GET STATS (visitors + locations)
if (isset($_GET['action']) && $_GET['action'] === 'get_stats') {
    $visitsFile = "$DIR/visits.json";
    $visits = file_exists($visitsFile) ? json_decode(file_get_contents($visitsFile), true) : null;

    // Fall back to track_visit.php logic if you prefer that one
    if (!$visits || empty($visits['total'])) {
        $fallback = @json_decode(@file_get_contents("$DIR/track_visit.php?action=get"), true);
        if ($fallback) {
            $visits = $fallback + ['locations' => $fallback['locations'] ?? []];
        }
    }

    $stats = [
        'today' => $visits['today'] ?? 0,
        'week'  => $visits['week'] ?? 0,
        'total' => $visits['total'] ?? 0,
        'loginsToday' => 0, // optional: count logins from activity_log.json
        'locations' => $visits['locations'] ?? [],
        'locationCount' => array_count_values($visits['locations'] ?? []) // for counts
    ];

    // Optional: count today's logins
    $activity = file_exists("$DIR/activity_log.json") ? json_decode(file_get_contents("$DIR/activity_log.json"), true) : [];
    $today = date('Y-m-d');
    foreach ($activity as $a) {
        if ($a['type'] === 'login' && date('Y-m-d', $a['time']/1000) === $today) {
            $stats['loginsToday']++;
        }
    }

    echo json_encode($stats);
    exit;
}

// ------------------------------------------------------------------
// 2. GET LAST 14 DAYS VISITS (for chart)
if (isset($_GET['action']) && $_GET['action'] === 'get_visits_14days') {
    // Very simple version — you can expand with real daily logs later
    $days = [];
    for ($i = 13; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $visits = rand(2, 28); // placeholder — replace with real data if you log daily
        $days[] = ['x' => $date, 'y' => $visits];
    }
    echo json_encode($days);
    exit;
}

// ------------------------------------------------------------------
// 3. GET ACTIVE COACHES (simple list from coaches.json)
if (isset($_GET['action']) && $_GET['action'] === 'get_coaches') {
    $file = "$DIR/coaches.json";
    $coaches = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    echo json_encode($coaches ?? []);
    exit;
}

// ------------------------------------------------------------------
// 4. TRACK VISIT (admin page view)
if (isset($_GET['action']) && $_GET['action'] === 'track_visit') {
    // Just proxy to your existing tracker
    $ch = curl_init("https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_exec($ch);
    curl_close($ch);
    exit;
}

// ------------------------------------------------------------------
// 5. SERVER-SENT EVENTS — REAL-TIME ACTIVITY FEED
if (isset($_GET['action']) && $_GET['action'] === 'notify') {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');

    $lastModified = 0;
    while (true) {
        $file = "$DIR/activity_log.json";
        if (file_exists($file)) {
            $modified = filemtime($file);
            if ($modified > $lastModified) {
                $log = json_decode(file_get_contents($file), true);
                $latest = $log[0] ?? null;
                if ($latest) {
                    $latest['action'] = $latest['type']; // compatibility
                    echo "data: " . json_encode($latest) . "\n\n";
                    ob_flush();
                    flush();
                }
                $lastModified = $modified;
            }
        }
        sleep(2);
    }
    exit;
}

// Fallback
echo json_encode(['error' => 'unknown action']);
?>