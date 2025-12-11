<?php
/**
 * get_leads.php
 * Provides lead-level analytics to the dashboard.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

$logFile = __DIR__ . '/../contact_log.json';

$empty = [
    'contact_today'   => 0,
    'contact_week'    => 0,
    'score_today'     => 0,
    'score_yesterday' => 0,
    'funnel_views'    => 0,
    'funnel_scrolled' => 0,
    'funnel_contact'  => 0,
    'summary'         => 'Not enough data yet.'
];

if (!file_exists($logFile)) {
    echo json_encode($empty);
    exit;
}

$data = json_decode(file_get_contents($logFile), true);
if (!is_array($data)) {
    echo json_encode($empty);
    exit;
}

$today     = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$weekAgo   = date('Y-m-d', strtotime('-7 days'));

$contactToday = 0;
$contactWeek  = 0;

$funnelViews = 0;
$funnelScrolled = 0;
$funnelContact = 0;

$scoreToday     = 0;
$scoreYesterday = 0;

foreach ($data as $e) {

    $date = substr($e['timestamp'] ?? '', 0, 10);
    $type = $e['type'] ?? '';

    // Funnel counts
    if ($type === 'view')    $funnelViews++;
    if ($type === 'scroll')  $funnelScrolled++;
    if ($type === 'contact') $funnelContact++;

    // Contact intent
    if ($type === 'contact') {
        if ($date === $today) $contactToday++;
        if ($date >= $weekAgo) $contactWeek++;
    }

    // Scoring
    $scoreAdd = 0;
    if ($type === 'contact') $scoreAdd = 3;
    if ($type === 'scroll')  $scoreAdd = 2;
    if ($type === 'view')    $scoreAdd = 1;

    if ($date === $today)     $scoreToday += $scoreAdd;
    if ($date === $yesterday) $scoreYesterday += $scoreAdd;
}

$summary = "Contacts today: {$contactToday}. Funnel: {$funnelViews} views, {$funnelScrolled} scrolled, {$funnelContact} contact opens.";

echo json_encode([
    'contact_today'   => $contactToday,
    'contact_week'    => $contactWeek,
    'score_today'     => $scoreToday,
    'score_yesterday' => $scoreYesterday,
    'funnel_views'    => $funnelViews,
    'funnel_scrolled' => $funnelScrolled,
    'funnel_contact'  => $funnelContact,
    'summary'         => $summary
]);
