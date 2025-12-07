<?php
// track_visit.php — Counts visitors & locations for dashboard
// Location: U:\public\webapi\track_visit.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

date_default_timezone_set('America/Toronto');

session_start();

$file = __DIR__ . '/visits.json';

// Initialize if missing
if (!file_exists($file)) {
    $init = [
        'total'      => 0,
        'today'      => 0,
        'week'       => 0,
        'last_day'   => '',
        'last_week'  => '',
        'ips'        => [],
        'locations'  => [],
        'last_update'=> 0
    ];
    file_put_contents($file, json_encode($init, JSON_PRETTY_PRINT));
}

$stats = json_decode(file_get_contents($file), true);
if (!is_array($stats)) {
    $stats = [
        'total'      => 0,
        'today'      => 0,
        'week'       => 0,
        'last_day'   => '',
        'last_week'  => '',
        'ips'        => [],
        'locations'  => [],
        'last_update'=> 0
    ];
}

$today = date('Y-m-d');
$week  = date('o-\WW'); // ISO week

// Reset daily / weekly counters if needed
if (($stats['last_day'] ?? '') !== $today) {
    $stats['today'] = 0;
    $stats['last_day'] = $today;
    $stats['ips'] = [];
}
if (($stats['last_week'] ?? '') !== $week) {
    $stats['week'] = 0;
    $stats['last_week'] = $week;
}

$userIp    = $_SERVER['REMOTE_ADDR']       ?? 'unknown';
$userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

// Basic bot filter
$bots = ['bot', 'crawler', 'spider', 'headless', 'lighthouse', 'chrome-lighthouse'];
foreach ($bots as $bot) {
    if (strpos($userAgent, $bot) !== false) {
        goto send_response;
    }
}

// Determine location (city, country)
$location = 'Unknown';
if ($userIp !== 'unknown' &&
    !preg_match('/^(127\.|192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $userIp)) {

    $geo = @json_decode(@file_get_contents("http://ip-api.com/json/{$userIp}?fields=city,country,status,message"), true);
    if ($geo && ($geo['status'] ?? '') === 'success') {
        $city    = trim($geo['city']    ?? '');
        $country = trim($geo['country'] ?? '');
        if ($city !== '' && $country !== '') {
            $location = $city . ', ' . $country;
        } elseif ($country !== '') {
            $location = $country;
        }
    }
}

// Count only first visit per IP per day
if (!in_array($userIp, $stats['ips'], true)) {
    $stats['ips'][]  = $userIp;
    $stats['total']  = (int)($stats['total'] ?? 0) + 1;
    $stats['today']  = (int)($stats['today'] ?? 0) + 1;
    $stats['week']   = (int)($stats['week']  ?? 0) + 1;

    // IMPORTANT CHANGE:
    // Always append the location when a new visit is counted,
    // even if it's the same region as previous visitors.
    if ($location !== 'Unknown') {
        $stats['locations'][] = $location;
    }

    $stats['last_update'] = time();
    file_put_contents($file, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

send_response:

// Optional small JSON for debug/use
if (isset($_GET['action']) && $_GET['action'] === 'get') {
    echo json_encode([
        'total'     => $stats['total'] ?? 0,
        'today'     => $stats['today'] ?? 0,
        'week'      => $stats['week']  ?? 0,
        // NOTE: we deliberately keep duplicate locations here now
        'locations' => $stats['locations'] ?? []
    ]);
    exit;
}

echo json_encode(['success' => true]);
?>
