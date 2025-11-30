<?php
// notify.php — REAL-TIME NOTIFICATIONS FOR DASHBOARD
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Access-Control-Allow-Origin: *');
header('Connection: keep-alive');

$lastId = $_SERVER['HTTP_LAST_EVENT_ID'] ?? 0;
if ($lastId === '') $lastId = 0;

$file = __DIR__ . '/activity_log.json';

while (true) {
    if (file_exists($file)) {
        clearstatcache(true, $file);
        $current = filemtime($file);
        $log = json_decode(file_get_contents($file), true) ?: [];
        
        $newEntries = array_filter($log, fn($e) => ($e['time'] ?? 0) > $lastId);
        if ($newEntries) {
            usort($newEntries, fn($a,$b) => ($b['time'] ?? 0) <=> ($a['time'] ?? 0));
            foreach ($newEntries as $e) {
                $id = $e['time'];
                $icon = match($e['type'] ?? '') {
                    'login'           => 'Login',
                    'logout'          => 'Logout',
                    'profile_update'  => 'Profile',
                    'image_upload'    => 'Image',
                    'password_change' => 'Key',
                    default           => 'Activity'
                };
                $color = match($e['type'] ?? '') {
                    'login'           => '#28a745',
                    'logout'          => '#dc3545',
                    'profile_update'  => '#17a2b8',
                    'image_upload'    => '#9c27b0',
                    'password_change' => '#fd7e14',
                    default           => '#6c757d'
                };

                echo "id: $id\n";
                echo "data: " . json_encode([
                    'coach'    => $e['coachName'] ?? $e['username'],
                    'action'   => $e['type'] ?? 'unknown',
                    'details'  => $e['details'] ?? '',
                    'location' => $e['location'] ?? 'Unknown',
                    'icon'     => $icon,
                    'color'    => $color
                ]) . "\n\n";
                ob_flush();
                flush();
            }
            $lastId = $log[0]['time'] ?? $lastId;
        }
    }
    sleep(2);
}
?>