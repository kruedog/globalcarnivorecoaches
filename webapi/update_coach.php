<?php
// update_coach.php — FINAL RELEASE
// Render Persistent Disk • Email Support • File Keys by Type • JSON-only

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

function respond($success, $message='', $extra=[]) {
    echo json_encode(array_merge(['success'=>$success,'message'=>$message],$extra));
    exit;
}

if (empty($_POST['username'])) respond(false,'Missing username');
$username = trim($_POST['username']);

$coachesFile = __DIR__ . '/coaches.json';
if (!file_exists($coachesFile)) respond(false,'coaches.json missing');

$coaches = json_decode(file_get_contents($coachesFile),true);
if (!is_array($coaches)) respond(false,'Invalid coaches.json');

// Locate coach
$i = null;
foreach ($coaches as $idx=>$c) {
    if (isset($c['Username']) && strtolower($c['Username'])===strtolower($username)) {
        $i = $idx; break;
    }
}
if ($i===null) respond(false,'Coach not found');

// Update fields
$coaches[$i]['CoachName']=trim($_POST['coachName']??'');
$coaches[$i]['Email']    =trim($_POST['email']??'');
$coaches[$i]['Phone']    =trim($_POST['phone']??'');
$coaches[$i]['Bio']      =trim($_POST['bio']??'');

if (isset($_POST['specializations'])) {
    $spec = json_decode($_POST['specializations'],true);
    $coaches[$i]['Specializations'] = is_array($spec)?$spec:[];
}

// File storage locations
$diskUploadPath = '/data/uploads/';
$publicBaseDir  = 'uploads/';

if (!is_dir($diskUploadPath)) @mkdir($diskUploadPath,0777,true);
if (!isset($coaches[$i]['Files']) || !is_array($coaches[$i]['Files']))
    $coaches[$i]['Files'] = [];

// Handle deletes
if (!empty($_POST['delete'])) {
    $delArr = is_array($_POST['delete'])?$_POST['delete']:[$_POST['delete']];
    foreach ($delArr as $type) {
        if (!isset($coaches[$i]['Files'][$type])) continue;
        $fileRel = $coaches[$i]['Files'][$type];
        $fileDisk = $diskUploadPath.basename($fileRel);
        if (is_file($fileDisk)) @unlink($fileDisk);
        unset($coaches[$i]['Files'][$type]);
    }
}

// Handle uploads (type-keyed)
foreach ($_FILES as $field => $fileData) {
    if (!preg_match('/files\[(.+)\]/',$field,$m)) continue;
    $type = $m[1];
    if ($fileData['error']!==UPLOAD_ERR_OK) continue;

    $orig = $fileData['name'];
    $tmp  = $fileData['tmp_name'];
    $ext  = strtolower(pathinfo($orig,PATHINFO_EXTENSION));
    if (!$ext) $ext='jpg';

    $safeUser = preg_replace('/[^a-z0-9_\-]+/i','_',$username);
    $safeType = preg_replace('/[^a-z0-9_\-]+/i','_',$type);

    $newFile = $safeUser.'_'.$safeType.'_'.time().'.'.$ext;
    $diskOut = $diskUploadPath.$newFile;
    $publicRel = $publicBaseDir.$newFile;

    if (move_uploaded_file($tmp,$diskOut)) {
        $coaches[$i]['Files'][$type] = $publicRel;
    }
}

// Save JSON
$json = json_encode($coaches,JSON_PRETTY_PRINT);
if ($json===false) respond(false,'JSON encode error');
if (file_put_contents($coachesFile,$json)===false)
    respond(false,'Write failed');

respond(true,'Profile updated',['coach'=>$coaches[$i]]);
