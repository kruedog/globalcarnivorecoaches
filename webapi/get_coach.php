<?php
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

// Require username only
$username = trim($_GET['username'] ?? '');
if ($username === '') {
    echo json_encode(['success'=>false,'message'=>'Username required']);
    exit;
}

$file = '/data/uploads/coaches.json';
if (!file_exists($file)) {
    echo json_encode(['success'=>false,'message'=>'No coach data']);
    exit;
}

$coaches = json_decode(file_get_contents($file), true);
if (!is_array($coaches)) {
    echo json_encode(['success'=>false,'message'=>'Invalid coach data']);
    exit;
}

// Case-insensitive username lookup
foreach ($coaches as $c) {
    if (isset($c['Username']) && strcasecmp($c['Username'], $username) === 0) {

        // ✔ normalize file paths (just filename → -> uploads/filename)
        if (isset($c['Files']) && is_array($c['Files'])) {
            foreach ($c['Files'] as $type => $path) {
                if ($path) {
                    $c['Files'][$type] = 'uploads/' . basename($path);
                }
            }
        }

        echo json_encode(['success'=>true,'coach'=>$c], JSON_UNESCAPED_SLASHES);
        exit;
    }
}

// Not found
echo json_encode(['success'=>false,'message'=>'Coach not found']);
exit;
?>
