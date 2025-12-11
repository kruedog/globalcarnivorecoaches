<?php
/**
 * get_engagement.php (UPGRADED)
 * Reads from visits_raw.json for detailed engagement analytics.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

// Raw event log
$rawFile = __DIR__ . '/../visits_raw.json';

$empty = [
    'return_today'   => 0,
    'return_week'    => 0,
    'return_rate'    => 0,
    'duration_short' => 0,
    'duration_med'   => 0,
    'duration_long'  => 0,
    'top_pages'      => [],
    'hourly'         => []
];

if (!file_exists($rawFile)) {
    echo json_encode($empty);
    exit;
}

$data = json_decode(file_get_contents($rawFile), true);
if (!is_array($data)) {
    echo json_encode($empty);
    exit;
}

$today    = date('Y-m-d');
$weekAgo  = date('Y-m-d', strtotime('-7 days'));

$userVisits = []; 
$returnToday = 0;
$returnWeek  = 0;

$durShort = 0;
$durMed   = 0;
$durLong  = 0;

$pageCounts = [];
$hours = array_fill(0, 24, 0);

foreach ($data as $v) {

    $ip = $v['ip'] ?? null;

    // Track visits per IP
    if ($ip) {
        if (!isset($userVisits[$ip])) {
            $userVisits[$ip] = [];
        }
        $userVisits[$ip][] = $v;
    }

    // Duration buckets
    $d = (int)($v['duration'] ?? 0);
    if ($d <= 10) $durShort++;
    elseif ($d <= 30) $durMed++;
    else $durLong++;

    // Path popularity
    $path = $v['path'] ?? '';
    if ($path) {
        if (!isset($pageCounts[$path])) $pageCounts[$path] = 0;
        $pageCounts[$path]++;
    }

    // Hourly activity
    if (!empty($v['timestamp'])) {
        $h = (int)date('G', strtotime($v['timestamp']));
        if ($h >= 0 && $h <= 23) {
            $hours[$h]++;
        }
    }
}

// Calculate return visitors
foreach ($userVisits as $ip => $visits) {
    $countToday = 0;
    $countWeek  = 0;

    foreach ($visits as $ev) {
        $date = substr($ev['timestamp'] ?? '', 0, 10);
        if ($date === $today) $countToday++;
        if ($date >= $weekAgo) $countWeek++;
    }

    if ($countToday > 1) $returnToday++;
    if ($countWeek > 1)  $returnWeek++;
}

$totalUsers = count($userVisits);
$returnRate = $totalUsers > 0 ? ($returnWeek / $totalUsers) * 100 : 0;

// Format top pages
arsort($pageCounts);
$topPages = [];
foreach ($pageCounts as $path => $count) {
    $topPages[] = ['path' => $path, 'count' => $count];
}

// Format hourly data
$hourly = [];
foreach ($hours as $h => $count) {
    $hourly[] = [
        'hour' => $h,
        'label'=> sprintf('%02d:00', $h),
        'count'=> $count
    ];
}

echo json_encode([
    'return_today'   => $returnToday,
    'return_week'    => $returnWeek,
    'return_rate'    => $returnRate,
    'duration_short' => $durShort,
    'duration_med'   => $durMed,
    'duration_long'  => $durLong,
    'top_pages'      => array_slice($topPages, 0, 10),
    'hourly'         => $hourly
]);
exit;
