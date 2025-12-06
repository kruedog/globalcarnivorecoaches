<?php
// webapi/logout.php — Logs logout + destroys session
<?php
header("Access-Control-Allow-Origin: https://globalcarnivorecoaches.onrender.com");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_set_cookie_params([
    'path' => '/',
    'domain' => 'globalcarnivorecoaches.onrender.com',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None'
]);

session_start();

header('Content-Type: application/json');
require_once __DIR__ . '/log_activity.php';

session_start();
log_coach_activity('logout');

session_destroy();
echo json_encode(['success' => true]);
?>