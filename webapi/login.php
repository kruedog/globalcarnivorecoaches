<?php
// Username-only login (Dec 2025)
header('Content-Type: application/json');
session_start();

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    echo json_encode(['success'=>false,'message'=>'Username and password required']);
    exit;
}

$file = __DIR__.'/coaches.json';
if(!file_exists($file)){ echo json_encode(['success'=>false,'message'=>'No coach data']); exit; }

$coaches = json_decode(file_get_contents($file),true);
if(!is_array($coaches)) $coaches=[];

$found=null;
foreach($coaches as $c){
    if(isset($c['Username']) && strcasecmp($c['Username'],$username)==0){
        $found=$c; break;
    }
}
if(!$found){ echo json_encode(['success'=>false,'message'=>'Invalid username']); exit; }

if(!password_verify($password,$found['Password'])){
    echo json_encode(['success'=>false,'message'=>'Incorrect password']);
    exit;
}

// Set session
$_SESSION['username']=$found['Username'];

echo json_encode([
    'success'=>true,
    'username'=>$found['Username'],
    'coachName'=>$found['CoachName'] ?? $found['Username'],
    'requireAgreement'=>$found['requireAgreement'] ?? false
]);
?>
