<?php
/**
 * webapi/webapi.php
 * Unified API router for Global Carnivore Coaches dashboard + analytics.
 * 
 * Supports:
 *   - get_stats (legacy)
 *   - get_visits_14days (legacy)
 *   - get_engagement
 *   - get_devices
 *   - get_geo
 *   - get_leads
 * 
 * Loads endpoint files from /webapi/endpoints/
 */

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

// -----------------------------
// CORE HEADERS
// -----------------------------
header('Cache-Control: no-cache');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === 'https://globalcarnivorecoaches.onrender.com') {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -----------------------------
// SECURITY CHECK FOR COACH ACCESS
// -----------------------------
function require_login_json(): void {
    if (empty($_SESSION['username'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Not logged in'
        ]);
        exit;
    }
}

// -----------------------------
// ROUTER SETUP
// -----------------------------
$DIR      = __DIR__;
$ENDPOINT = $DIR . '/endpoints/';
$action   = $_GET['action'] ?? '';

header('Content-Type: application/json; charset=utf-8');

// ============================================================
// LEGACY ENDPOINT: get_stats
// (Uses visits.json)
// ============================================================
if ($action === 'get_stats') {

    require_login_json();

    $visitsFile = $DIR . '/visits.json';
    $today = $week = $total = 0;
    $locationCount = [];

    if (file_exists($visitsFile)) {
        $v = json_decode(file_get_contents($visitsFile), true) ?: [];

        $today = (int)($v['today'] ?? 0);
        $week  = (int)($v['week'] ?? 0);
        $total = (int)($v['total'] ?? 0);

        $locs = $v['locations'] ?? [];
        if (is_array($locs)) {
            $locationCount = array_count_values($locs);
        }
    }

    echo json_encode([
        'success'       => true,
        'today'         => $today,
        'week'          => $week,
        'total'         => $total,
        'locationCount' => $locationCount
    ]);
    exit;
}

// ============================================================
// LEGACY ENDPOINT: get_visits_14days
// (Uses visits_history.json)
// ============================================================
if ($action === 'get_visits_14days') {

    require_login_json();

    $historyFile = $DIR . '/visits_history.json';
    $history = [];

    if (file_exists($historyFile)) {
        $history = json_decode(file_get_contents($historyFile), true);
        if (!is_array($history)) {
            $history = [];
        }
    }

    $clean = [];
    foreach ($history as $rec) {
        if (!isset($rec['date'], $rec['count'])) continue;
        $clean[] = [
            'date'  => (string)$rec['date'],
            'count' => (int)$rec['count']
        ];
    }

    usort($clean, fn($a, $b) => strcmp($a['date'], $b['date']));

    echo json_encode([
        'success' => true,
        'points'  => $clean
    ]);
    exit;
}

// ============================================================
// NEW DASHBOARD ENDPOINTS (modular)
// ============================================================

// Map action → endpoint file
$ROUTES = [
    'get_engagement' => 'get_engagement.php',
    'get_leads'      => 'get_leads.php',
    'get_devices'    => 'get_devices.php',
    'get_geo'        => 'get_geo.php'
];

// If action matches, include the endpoint
if (isset($ROUTES[$action])) {

    require_login_json(); // Only coaches can access analytics

    $endpointFile = $ENDPOINT . $ROUTES[$action];

    if (file_exists($endpointFile)) {
        require $endpointFile;
        exit;
    }

    echo json_encode([
        'success' => false,
        'message' => "Endpoint file not found ($endpointFile)"
    ]);
    exit;
}

// ============================================================
// FALLBACK — INVALID ACTION
// ============================================================
echo json_encode([
    'success' => false,
    'message' => 'Invalid or missing action'
]);
exit;
