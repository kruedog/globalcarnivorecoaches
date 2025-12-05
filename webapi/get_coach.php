<?php
header('Content-Type: application/json');

$username = trim($_GET['username'] ?? '');
if ($username==='') {
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

foreach($coaches as $c){
    if(isset($c['Username']) && strcasecmp($c['Username'], $username)==0){
        echo json_encode(['success'=>true,'coach'=>$c],JSON_UNESCAPED_SLASHES);
        exit;
    }
}

echo json_encode(['success'=>false,'message'=>'Coach not found']);
?>
