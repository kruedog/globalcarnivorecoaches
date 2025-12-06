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
session_start();

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if($username==='' || $password===''){
    echo json_encode(['success'=>false,'message'=>'Username and password required']);
    exit;
}

$file = '/data/uploads/coaches.json';
if(!file_exists($file)){
    echo json_encode(['success'=>false,'message'=>'No coach data']);
    exit;
}

$coaches = json_decode(file_get_contents($file), true);
if(!is_array($coaches)) $coaches=[];

$found=null;
foreach ($coaches as $c) {
    if(isset($c['Username']) && strcasecmp($c['Username'], $username)==0) {
        $found=$c;
        break;
    }
}
if(!$found){
    echo json_encode(['success'=>false,'message'=>'Invalid username']);
    exit;
}

if(!password_verify($password, $found['Password'])){
    echo json_encode(['success'=>false,'message'=>'Incorrect password']);
    exit;
}

$_SESSION['username']=$found['Username'];

echo json_encode([
    'success'=>true,
    'username'=>$found['Username'],
    'coachName'=>$found['CoachName']??$found['Username'],
    'requireAgreement'=>$found['requireAgreement']??true
]);
?>
