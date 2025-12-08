<?php
// webapi/track_visit.php
// Visitor tracking for public pages
// Counts: every page load, unique per session

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

session_start();

// Do NOT track logged-in coaches
if (!empty($_SESSION['username'])) {
    echo json_encode(['ignored' => true]);
    exit;
}

$path = __DIR__ . '/visits.json';

// Load existing stats
$stats = [
    'total'     => 0,
    'today'     => 0,
    'week'      => 0,
    'locations' => []
];

if (file_exists($path)) {
    $data = json_decode(file_get_contents($path), true);
    if (is_array($data)) {
        $stats = array_merge($stats, $data);
    }
}

$today = date('Y-m-d');
$thisWeek = date('o-W'); // ISO week

// Reset day/week if stale
if (($stats['last_day'] ?? '') !== $today) {
    $stats['today'] = 0;
    $stats['last_day'] = $today;
}

if (($stats['last_week'] ?? '') !== $thisWeek) {
    $stats['week'] = 0;
    $stats['last_week'] = $thisWeek;
}

// Count every page load ✔
$stats['total']++;
$stats['today']++;
$stats['week']++;

// Geo lookup (only once per visitor session)
if (!isset($_SESSION['tracked_location'])) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $look = @file_get_contents("http://ip-api.com/json/{$ip}?fields=city,country");
    $geo = json_decode($look, true);

    $loc = ($geo && empty($geo['message']))
        ? trim(($geo['city'] ?? '') . (($geo['city'] ?? '') && ($geo['country'] ?? '') ? ', ' : '') . ($geo['country'] ?? ''))
        : 'Unknown';

    $_SESSION['tracked_location'] = $loc;
}

// Add location
$stats['locations'][] = $_SESSION['tracked_location'];

// Update timestamp
$stats['last_update'] = time();

// Save changes
file_put_contents($path, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo json_encode(['success' => true]);
