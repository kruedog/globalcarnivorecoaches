<?php
/**
 * get_devices.php (UPGRADED)
 * Reads from visits_raw.json to extract device analytics.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

$rawFile = __DIR__ . '/../visits_raw.json';

if (!file_exists($rawFile)) {
    echo json_encode([
        'devices'  => [],
        'browsers' => [],
        'os'       => []
    ]);
    exit;
}

$data = json_decode(file_get_contents($rawFile), true);
if (!is_array($data)) {
    echo json_encode([
        'devices'  => [],
        'browsers' => [],
        'os'       => []
    ]);
    exit;
}

$deviceCounts = [];
$browserCounts = [];
$osCounts = [];

foreach ($data as $v) {
    $dev = $v['device']  ?? 'Unknown';
    $brw = $v['browser'] ?? 'Unknown';
    $sys = $v['os']      ?? 'Unknown';

    $deviceCounts[$dev]  = ($deviceCounts[$dev]  ?? 0) + 1;
    $browserCounts[$brw] = ($browserCounts[$brw] ?? 0) + 1;
    $osCounts[$sys]      = ($osCounts[$sys]      ?? 0) + 1;
}

function fmt($arr) {
    $out = [];
    foreach ($arr as $label => $count) {
        $out[] = ['label' => $label, 'count' => $count];
    }
    return $out;
}

echo json_encode([
    'devices'  => fmt($deviceCounts),
    'browsers' => fmt($browserCounts),
    'os'       => fmt($osCounts)
]);
exit;
