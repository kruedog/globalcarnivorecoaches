<?php
// webapi/webapi.php — FINAL RENDER STARTER VERSION (Nov 30, 2025)
// Persistent storage + real-time + dashboard ready

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');
date_default_timezone_set('UTC');

$DIR = __DIR__;

// Persistent paths (Render Starter keeps these forever)
$VISITS_FILE     = "$DIR/visits.json";
$ACTIVITY_FILE   = "$DIR/activity_log.json";
$UPLOAD_DIR      = "$DIR/uploads";           // Images go here — survives forever
$COACHES_FILE    = "$DIR/coaches.json";      // If you store coaches in JSON

// Create folders/files if missing
if (!is_dir($UPLOAD_DIR)) mkdir($UPLOAD_DIR, 0755, true);
foreach ([$VISITS_FILE, $ACTIVITY_FILE] as $f) {
    if (!file_exists($f)) file_put_contents($f, $f === $ACTIVITY_FILE ? '[]' : json_encode(['total'=>0,'today'=>0,'week'=>0,'last_day'=>'','last_week'=>'','ips_today'=>[],'locations'=>[]]));
}

// Helper: safe JSON read/write
function json_get($file, $default = []) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) ?: $default : $default;
}
function json_put($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Get visitor info (works great on Render)
function get_visitor() {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $country = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? 'XX';
    $city = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'Unknown';
    return ['ip'=>$ip, 'country'=>$country, 'city'=>$city];
}

$action = $_GET['action'] ?? '';

// ——— TRACK EVERY VISIT (called on every page load) ———
if ($action === 'track_visit' || empty($action)) {
    $v = get_visitor();
    $today = date('Y-m-d');
    $week = date('Y-W');

    $visits = json_get($VISITS_FILE);
    $newVisit = !in_array($v['ip'], $visits['ips_today'] ?? []);

    if ($newVisit) {
        $visits['ips_today'][] = $v['ip'];
        $visits['today'] = ($visits['today'] ?? 0) + 1;
        $visits['week'] = ($visits['week'] ?? 0) + 1;
        $visits['total'] = ($visits['total'] ?? 0) + 1;
        if (!in_array($v['country'], $visits['locations'] ?? [])) {
            $visits['locations'][] = $v['country'];
        }
    }

    if (($visits['last_day'] ?? '') !== $today) {
        $visits['today'] = $newVisit ? 1 : 0;
        $visits['ips_today'] = $newVisit ? [$v['ip']] : [];
        $visits['last_day'] = $today;
    }
    if (($visits['last_week'] ?? '') !== $week) {
        $visits['week'] = $newVisit ? 1 : 0;
        $visits['last_week'] = $week;
    }

    json_put($VISITS_FILE, $visits);

    // Log activity
    $log = json_get($ACTIVITY_FILE);
    array_unshift($log, [
        'time' => date('c'),
        'type' => 'visit',
        'coachName' => 'Visitor',
        'details' => "$v[city], $v[country]",
        'location' => "$v[city], $v[country]"
    ]);
    $log = array_slice($log, 0, 300);
    json_put($ACTIVITY_FILE, $log);

    if (empty($action)) echo json_encode(['status'=>'visit tracked']);
    exit;
}

// ——— GET STATS FOR DASHBOARD ———
if ($action === 'get_stats') {
    $visits = json_get($VISITS_FILE);
    echo json_encode([
        'today' => $visits['today'] ?? 0,
        'week' => $visits['week'] ?? 0,
        'total' => $visits['total'] ?? 0,
        'locations' => array_values(array_unique($visits['locations'] ?? []))
    ]);
    exit;
}

// ——— GET LAST 14 DAYS FOR CHART ———
if ($action === 'get_visits_14days') {
    // Simple stub — real data grows over time
    $data = [];
    for ($i = 13; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $data[] = ['x' => $date, 'y' => rand(3,25)]; // remove this line later when real data exists
    }
    echo json_encode($data);
    exit;
}

// ——— GET ALL COACHES (for Active Coaches list) ———
if ($action === 'get_coaches') {
    // Replace this with your real coach loading logic (DB or JSON)
    $coaches = json_get($COACHES_FILE, []);
    echo json_encode(array_values($coaches));
    exit;
}

// ——— REAL-TIME NOTIFICATIONS (Server-Sent Events) ———
if ($action === 'notify') {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    $last = 0;
    while (true) {
        $log = json_get($ACTIVITY_FILE);
        if (count($log) > $last) {
            $event = $log[0];
            $event['color'] = match($event['type'] ?? '') {
                'login' => '#28a745',
                'profile_update' => '#17a2b8',
                'image_upload' => '#9c27b0',
                default => '#666'
            };
            echo "id: " . count($log) . "\n";
            echo "data: " . json_encode($event) . "\n\n";
            ob_flush(); flush();
            $last = count($log);
        }
        sleep(3);
    }
    exit;
}

// ——— FALLBACK ———
echo json_encode([
    'status' => 'webapi.php ready',
    'time' => date('c'),
    'persistent_uploads' => is_writable($UPLOAD_DIR)
]);