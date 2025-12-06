<?php
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
// We expect parallel arrays: files[] and imageType[]
if (!empty($_FILES['files']) && !empty($_POST['imageType'])) {

    $fileArray = $_FILES['files'];
    $typeArray = $_POST['imageType'];

    foreach ($typeArray as $index => $type) {
        if (!isset($fileArray['name'][$index])) continue;
        if ($fileArray['error'][$index] !== UPLOAD_ERR_OK) continue;

        $origName = $fileArray['name'][$index];
        $tmpPath  = $fileArray['tmp_name'][$index];

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!$ext) $ext = 'jpg';

        $safeUser = preg_replace('/[^a-z0-9_\-]+/i','_', $username);
        $safeType = preg_replace('/[^a-z0-9_\-]+/i','_', $type);

        $newFile = "{$safeUser}_{$safeType}_" . time() . ".{$ext}";

        $targetDisk = $diskUploadPath . $newFile;
        $publicPath = $publicPathBase . $newFile;

        if (move_uploaded_file($tmpPath, $targetDisk)) {
            $coaches[$i]['Files'][$type] = $publicPath;
        }
    }
}


// Not found
echo json_encode(['success'=>false,'message'=>'Coach not found']);
exit;
?>
