<?php
// track_visit.php — FINAL WORKING VERSION (Nov 2025)
// Location: U:\public\webapi\track_visit.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

date_default_timezone_set('America/Montreal');

$file = __DIR__ . '/visits.json';  // ← Correct path

// Initialize if missing
if (!file_exists($file)) {
    $init = [
        'total' => 0, 'today' => 0, 'week' => 0,
        'last_day' => '', 'last_week' => '',
        'ips' => [], 'locations' => [], 'last_update' => time()
    ];
    file_put_contents($file, json_encode($init, JSON_PRETTY_PRINT));
}

$stats = json_decode(file_get_contents($file), true) ?: [];
$stats['ips'] = $stats['ips'] ?? [];
$stats['locations'] = $stats['locations'] ?? [];

$today = date('Y-m-d');
$week  = date('o-W');
$userIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

// Reset daily/weekly counters
if (($stats['last_day'] ?? '') !== $today) {
    $stats['today'] = 0;
    $stats['last_day'] = $today;
    $stats['ips'] = [];
}
if (($stats['last_week'] ?? '') !== $week) {
    $stats['week'] = 0;
    $stats['last_week'] = $week;
}

// Block bots
$bots = ['bot','crawler','spider','headless','lighthouse','chrome-lighthouse'];
foreach ($bots as $bot) {
    if (strpos($userAgent, $bot) !== false) {
        goto send_response;
    }
}

// Get location
$location = 'Unknown';
if ($userIp !== 'unknown' && !preg_match('/^(127\.|192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $userIp)) {
    $geo = @json_decode(file_get_contents("http://ip-api.com/json/{$userIp}?fields=city,country"), true);
    if ($geo && empty($geo['message'])) {
        $location = trim(($geo['city']??'') . ($geo['city'] && $geo['country'] ? ', ' : '') . ($geo['country']??''));
    }
}

// Count visitor (once per IP per day)
session_start();
if (empty($_SESSION['counted_today_' . $today])) {
    if (!in_array($userIp, $stats['ips'])) {
        $stats['ips'][] = $userIp;
        $stats['total'] = ($stats['total'] ?? 0) + 1;
        $stats['today']++;
        $stats['week']++;

        if ($location !== 'Unknown' && !in_array($location, $stats['locations'])) {
            $stats['locations'][] = $location;
        }

        $stats['last_update'] = time();
        file_put_contents($file, json_encode($stats, JSON_PRETTY_PRINT));
    }
    $_SESSION['counted_today_' . $today] = true;
}

send_response:
if (isset($_GET['action']) && $_GET['action'] === 'get') {
    echo json_encode([
        'total' => $stats['total'] ?? 0,
        'today' => $stats['today'] ?? 0,
        'week'  => $stats['week'] ?? 0,
        'locations' => array_values(array_unique($stats['locations']))
    ]);
    exit;
}

echo json_encode(['success' => true]);
?>