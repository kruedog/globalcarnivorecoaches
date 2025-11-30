<?php
// webapi/log_activity.php — UNIVERSAL COACH ACTIVITY LOGGER
// Used by login.php, update_coach.php, logout, and real-time dashboard

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function log_coach_activity($type, $details = '') {
    // --- User identification ---
    $username  = $_SESSION['username'] ?? 'unknown';
    $coachName = $_SESSION['coachName'] ?? $_SESSION['username'] ?? 'Unknown Coach';

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    // --- Location (cached per session) ---
    if (!isset($_SESSION['cached_location'])) {
        $geo = @json_decode(@file_get_contents("http://ip-api.com/json/{$ip}?fields=city,country"), true);
        $_SESSION['cached_location'] = ($geo && empty($geo['message']))
            ? trim(($geo['city'] ?? '') . ($geo['city'] && ($geo['country'] ?? '') ? ', ' : '') . ($geo['country'] ?? ''))
            : 'Unknown';
    }
    $location = $_SESSION['cached_location'];

    // --- Build entry ---
    $entry = [
        'type'      => $type,
        'username'  => $username,
        'coachName' => $coachName,
        'time'      => round(microtime(true) * 1000), // milliseconds for JS
        'ip'        => $ip,
        'userAgent' => $ua,
        'location'  => $location,
        'details'   => is_string($details) ? $details : json_encode($details, JSON_UNESCAPED_UNICODE)
    ];

    // --- Write to log ---
    $file = __DIR__ . '/activity_log.json';
    $log  = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    if (!is_array($log)) $log = [];

    array_unshift($log, $entry);           // newest first
    $log = array_slice($log, 0, 1500);     // keep last 1500 entries

    // Atomic write (prevents corruption on shared hosting)
    file_put_contents($file, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
?>