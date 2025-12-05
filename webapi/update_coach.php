<?php
require_once 'db.php';
session_start();

header('Content-Type: application/json');

$uploadDir = "/data/uploads/"; // Render Persistent Disk mount

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

$email = $_POST['email'] ?? '';
if (!$email) {
    echo json_encode(["success" => false, "message" => "Missing email"]);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM coaches WHERE Email = ?");
$stmt->execute([$email]);
$coach = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$coach) {
    echo json_encode(["success" => false, "message" => "Coach not found"]);
    exit;
}

// Build update fields
$update = [
    "CoachName" => trim($_POST['coachName'] ?? ''),
    "Phone" => trim($_POST['phone'] ?? ''),
    "Bio" => trim($_POST['bio'] ?? ''),
    "Specializations" => $_POST['specializations'] ?? '[]'
];

if (!empty($_POST['password'])) {
    $update["Password"] = password_hash($_POST['password'], PASSWORD_DEFAULT);
}

// Load existing files
$files = $coach['Files'] ? json_decode($coach['Files'], true) : [];

// Handle deletions
if (!empty($_POST['delete'])) {
    foreach ($_POST['delete'] as $type) {
        if (isset($files[$type])) unset($files[$type]);
    }
}

// Handle newly uploaded files
if (!empty($_FILES['files'])) {
    foreach ($_FILES['files']['name'] as $idx => $name) {
        $imageType = $_POST['imageType'][$idx]; // Profile, Before, After, Certificate

        // Generate unique safe filename
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!$ext) $ext = "jpg"; // default safety

        $username = strtolower(preg_replace('/\W+/', '', $coach['Username']));
        $newFilename = $username . "_" . time() . "_" . $idx . "." . $ext;

        $tmp = $_FILES['files']['tmp_name'][$idx];
        $dest = $uploadDir . $newFilename;

        if (move_uploaded_file($tmp, $dest)) {
            $files[$imageType] = $newFilename; // Store only filename in DB
        }
    }
}

$update["Files"] = json_encode($files, JSON_UNESCAPED_SLASHES);

// Build update SQL
$sql = "UPDATE coaches SET 
    CoachName=:CoachName,
    Phone=:Phone,
    Bio=:Bio,
    Specializations=:Specializations,
    Files=:Files"
    . (!empty($_POST['password']) ? ", Password=:Password" : "")
    . " WHERE Email=:Email";

$stmt = $pdo->prepare($sql);
$update["Email"] = $email;

if ($stmt->execute($update)) {
    echo json_encode(["success" => true, "files" => $files]);
} else {
    echo json_encode(["success" => false, "message" => "DB update failed"]);
}
