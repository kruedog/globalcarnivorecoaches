<?php
header('Content-Type: application/json');

$id = trim($_GET['username'] ?? '');
if($id===''){ echo json_encode(['success'=>false,'message'=>'Username required']); exit; }

$file = __DIR__.'/coaches.json';
$data = file_get_contents($file);
$coaches = json_decode($data,true);

$found=null;
foreach($coaches as $c){
    if(isset($c['Username']) && strcasecmp($c['Username'],$id)==0){
        $found=$c; break;
    }
}

if(!$found){ echo json_encode(['success'=>false,'message'=>'Coach not found']); exit; }

echo json_encode(['success'=>true,'coach'=>$found],JSON_UNESCAPED_SLASHES);
?>
