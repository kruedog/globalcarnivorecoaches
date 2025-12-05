<?php
header('Content-Type: application/json');

$username = trim($_POST['username'] ?? '');
if($username===''){
    echo json_encode(['success'=>false,'message'=>'Username required']);
    exit;
}

$file = '/data/uploads/coaches.json';
if(!file_exists($file)){
    echo json_encode(['success'=>false,'message'=>'No coach data']);
    exit;
}

$coaches = json_decode(file_get_contents($file), true);
if(!is_array($coaches)) $coaches=[];

foreach($coaches as &$c){
    if(isset($c['Username']) && strcasecmp($c['Username'], $username)==0){
        $c['requireAgreement'] = false;
        file_put_contents($file, json_encode($coaches, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo json_encode(['success'=>true]);
        exit;
    }
}
echo json_encode(['success'=>false,'message'=>'Coach not found']);
?>
