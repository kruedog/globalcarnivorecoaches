<?php
// webapi/track_visit.php
// Visitor tracking for public pages
// - Counts every page load (except logged-in coaches)
// - Updates visits.json (today/week/total + locations)
// - Updates visits_history.json (last 14 days with counts)

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

session_start();

// Do NOT track logged-in coaches
if (!empty($_SESSION['username'])) {
    echo json_encode(['ignored' => true]);
    exit;
}

$visitsFile  = __DIR__ . '/visits.json';
$historyFile = __DIR__ . '/visits_history.json';

// === LOAD OR INIT visits.json ===
$stats = [
    'total'      => 0,
    'today'      => 0,
    'week'       => 0,
    'last_day'   => null,
    'last_week'  => null,
    'locations'  => [],
    'last_update'=> null,
];

if (file_exists($visitsFile)) {
    $data = json_decode(file_get_contents($visitsFile), true);
    if (is_array($data)) {
        $stats = array_merge($stats, $data);
    }
}

$today    = date('Y-m-d');
$thisWeek = date('o-W'); // ISO week number

// Reset daily counters if new day
if (($stats['last_day'] ?? '') !== $today) {
    $stats['today']    = 0;
    $stats['last_day'] = $today;
}

// Reset weekly counters if new week
if (($stats['last_week'] ?? '') !== $thisWeek) {
    $stats['week']     = 0;
    $stats['last_week']= $thisWeek;
}

// === INCREMENT COUNTS (every page load) ===
$stats['total']++;
$stats['today']++;
$stats['week']++;

// === GEO LOOKUP (once per session) ===
if (!isset($_SESSION['tracked_location'])) {
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '';
    $raw = @file_get_contents("http://ip-api.com/json/{$ip}?fields=city,country");
    $geo = $raw ? json_decode($raw, true) : null;

    $loc = ($geo && empty($geo['message']))
        ? trim(
            ($geo['city'] ?? '') .
            ((($geo['city'] ?? '') && ($geo['country'] ?? '')) ? ', ' : '') .
            ($geo['country'] ?? '')
        )
        : 'Unknown';

    $_SESSION['tracked_location'] = $loc;
}

// Append location for stats
$stats['locations'][]  = $_SESSION['tracked_location'];
$stats['last_update']  = time();

// Save visits.json
file_put_contents(
    $visitsFile,
    json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

// === UPDATE visits_history.json (14-day rolling chart) ===
$history = [];
if (file_exists($historyFile)) {
    $hRaw = file_get_contents($historyFile);
    $history = json_decode($hRaw, true);
    if (!is_array($history)) {
        $history = [];
    }
}

$found = false;
foreach ($history as &$rec) {
    if (($rec['date'] ?? '') === $today) {
        $rec['count'] = ($rec['count'] ?? 0) + 1;
        $found = true;
        break;
    }
}
unset($rec);

if (!$found) {
    $history[] = [
        'date'  => $today,
        'count' => 1
    ];
}

// Keep only last 14 days (most recent)
usort($history, fn($a, $b) => strcmp($a['date'], $b['date']));
if (count($history) > 14) {
    $history = array_slice($history, -14);
}

file_put_contents(
    $historyFile,
    json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

// Final response (not really used by frontend)
echo json_encode(['success' => true]);
exit;
