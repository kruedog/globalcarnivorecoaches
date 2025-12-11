<?php
/**
 * track_visit.php
 * Unified tracking for GCC public visitors.
 * 
 * ✔ Increments existing stats (visits.json + visits_history.json)
 * ✔ Adds RAW logging for analytics (visits_raw.json)
 * ✔ Captures:
 *      - IP
 *      - Country, City
 *      - Timestamp
 *      - Path (URL)
 *      - Device type
 *      - Browser
 *      - OS
 *      - Duration (from previous page)
 * ✔ Avoids tracking logged-in coaches
 */

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

// ===========================================================
// SETUP PATHS
// ===========================================================
$rootDir = dirname(__DIR__); // one level above /webapi
$visitsFile       = __DIR__ . '/visits.json';
$historyFile      = __DIR__ . '/visits_history.json';
$rawFile          = __DIR__ . '/visits_raw.json';   // NEW raw analytics log

// ===========================================================
// CAPTURE BASIC VISIT INFO
// ===========================================================
$timestamp = date('Y-m-d H:i:s');

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$path = $_SERVER['REQUEST_URI'] ?? '/';

// ===========================================================
// GEO LOOKUP (only once per session for speed)
// ===========================================================
if (!isset($_SESSION['geo_cache'])) {
    $geo = @file_get_contents("http://ip-api.com/json/{$ip}?fields=city,country");
    $geo = $geo ? json_decode($geo, true) : null;

    $city    = $geo['city'] ?? '';
    $country = $geo['country'] ?? 'Unknown';

    $_SESSION['geo_cache'] = [
        'city'    => $city,
        'country' => $country,
        'label'   => trim($city . ($city && $country ? ', ' : '') . $country)
    ];
}

$geoLabel = $_SESSION['geo_cache']['label'];
$country  = $_SESSION['geo_cache']['country'];
$city     = $_SESSION['geo_cache']['city'];

// ===========================================================
// DEVICE / BROWSER / OS PARSING
// ===========================================================
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

function detectDevice($ua) {
    if (stripos($ua, 'mobile') !== false) return 'Mobile';
    if (stripos($ua, 'tablet') !== false) return 'Tablet';
    return 'Desktop';
}

function detectBrowser($ua) {
    if (stripos($ua, 'Edg') !== false) return 'Edge';
    if (stripos($ua, 'Chrome') !== false) return 'Chrome';
    if (stripos($ua, 'Safari') !== false) return 'Safari';
    if (stripos($ua, 'Firefox') !== false) return 'Firefox';
    return 'Unknown';
}

function detectOS($ua) {
    if (stripos($ua, 'Windows') !== false) return 'Windows';
    if (stripos($ua, 'Mac OS') !== false) return 'macOS';
    if (stripos($ua, 'iPhone') !== false) return 'iOS';
    if (stripos($ua, 'Android') !== false) return 'Android';
    return 'Unknown';
}

$device  = detectDevice($ua);
$browser = detectBrowser($ua);
$os      = detectOS($ua);

// ===========================================================
// SESSION DURATION TRACKING
// ===========================================================
$duration = 0;
if (isset($_SESSION['last_visit_time'])) {
    $duration = time() - $_SESSION['last_visit_time'];
}
$_SESSION['last_visit_time'] = time();

// ===========================================================
// UPDATE visits.json (YOUR ORIGINAL STATS)
// ===========================================================
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
    $loaded = json_decode(file_get_contents($visitsFile), true);
    if (is_array($loaded)) $stats = array_merge($stats, $loaded);
}

$today    = date('Y-m-d');
$weekCode = date('o-W');

// Reset daily
if (($stats['last_day'] ?? '') !== $today) {
    $stats['today'] = 0;
    $stats['last_day'] = $today;
}

// Reset weekly
if (($stats['last_week'] ?? '') !== $weekCode) {
    $stats['week'] = 0;
    $stats['last_week'] = $weekCode;
}

// Increment counters
$stats['total']++;
$stats['today']++;
$stats['week']++;
$stats['locations'][] = $geoLabel;
$stats['last_update'] = time();

// Save stats
file_put_contents($visitsFile, json_encode($stats, JSON_PRETTY_PRINT));

// ===========================================================
// UPDATE visits_history.json (14-day chart)
// ===========================================================
$history = file_exists($historyFile)
    ? json_decode(file_get_contents($historyFile), true)
    : [];

if (!is_array($history)) $history = [];

$found = false;
foreach ($history as &$row) {
    if ($row['date'] === $today) {
        $row['count']++;
        $found = true;
        break;
    }
}
unset($row);

if (!$found) {
    $history[] = ['date' => $today, 'count' => 1];
}

// Keep last 14
usort($history, fn($a,$b) => strcmp($a['date'], $b['date']));
$history = array_slice($history, -14);

// Save
file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT));

// ===========================================================
// NEW: APPEND RAW VISIT EVENT (for analytics endpoints)
// ===========================================================
$raw = [];

if (file_exists($rawFile)) {
    $rawLoaded = json_decode(file_get_contents($rawFile), true);
    if (is_array($rawLoaded)) $raw = $rawLoaded;
}

$raw[] = [
    'timestamp' => $timestamp,
    'ip'        => $ip,
    'path'      => $path,
    'duration'  => $duration,
    'device'    => $device,
    'browser'   => $browser,
    'os'        => $os,
    'city'      => $city,
    'country'   => $country
];

// Keep file from growing infinitely (optional)
if (count($raw) > 50000) {
    $raw = array_slice($raw, -20000);
}

file_put_contents($rawFile, json_encode($raw, JSON_PRETTY_PRINT));

// ===========================================================
// FINAL RESPONSE
// ===========================================================
echo json_encode(['success' => true]);
exit;
