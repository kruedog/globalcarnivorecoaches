<?php
/**
 * get_geo.php (UPGRADED)
 * Uses visits_raw.json to compute regional analytics.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

$rawFile = __DIR__ . '/../visits_raw.json';

if (!file_exists($rawFile)) {
    echo json_encode([
        'top_countries' => [],
        'top_cities'    => [],
        'trend_text'    => 'No geo data available.'
    ]);
    exit;
}

$data = json_decode(file_get_contents($rawFile), true);
if (!is_array($data)) {
    echo json_encode([
        'top_countries' => [],
        'top_cities'    => [],
        'trend_text'    => 'Invalid geo data.'
    ]);
    exit;
}

$countryCounts = [];
$cityCounts    = [];

foreach ($data as $v) {
    $country = $v['country'] ?? 'Unknown';
    $city    = $v['city'] ?? 'Unknown';

    $countryCounts[$country] = ($countryCounts[$country] ?? 0) + 1;
    $cityCounts[$city]       = ($cityCounts[$city]       ?? 0) + 1;
}

arsort($countryCounts);
arsort($cityCounts);

$topCountries = [];
foreach ($countryCounts as $name => $count) {
    $topCountries[] = ['name' => $name, 'count' => $count];
}

$topCities = [];
foreach ($cityCounts as $name => $count) {
    $topCities[] = ['name' => $name, 'count' => $count];
}

$trend = "Top traffic region: " . ($topCountries[0]['name'] ?? 'Unknown');

echo json_encode([
    'top_countries' => array_slice($topCountries, 0, 10),
    'top_cities'    => array_slice($topCities, 0, 10),
    'trend_text'    => $trend
]);
exit;
