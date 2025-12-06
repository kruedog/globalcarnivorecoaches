<?php
// ----------------------------------------------
// update_coach.php — FINAL, PRODUCTION VERSION
// Global Carnivore Coaches
// ----------------------------------------------
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


ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

function fail($msg) {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

function ok($coach) {
    echo json_encode(['success' => true, 'coach' => $coach]);
    exit;
}

// ----------------------------------------------
// Validate POST
// ----------------------------------------------
if (empty($_POST['username'])) {
    fail("Missing username");
}

$username = trim($_POST['username']);

// ----------------------------------------------
// Load coaches file
// ----------------------------------------------
$coachesFile = __DIR__ . '/coaches.json';
if (!file_exists($coachesFile)) {
    fail("coaches.json missing");
}

$coaches = json_decode(file_get_contents($coachesFile), true);
if (!is_array($coaches)) {
    fail("coaches.json corrupted");
}

// ----------------------------------------------
// Find coach by username
// ----------------------------------------------
$ci = null;
foreach ($coaches as $i => $coach) {
    if (!empty($coach['Username']) &&
        strtolower($coach['Username']) === strtolower($username)) {
        $ci = $i;
        break;
    }
}
if ($ci === null) {
    fail("Coach not found");
}

// ----------------------------------------------
// Update fields
// ----------------------------------------------
$coaches[$ci]['CoachName'] = trim($_POST['coachName'] ?? '');
$coaches[$ci]['Email']     = trim($_POST['email'] ?? '');
$coaches[$ci]['Phone']     = trim($_POST['phone'] ?? '');
$coaches[$ci]['Bio']       = trim($_POST['bio'] ?? '');

if (!isset($coaches[$ci]['Files']) || !is_array($coaches[$ci]['Files'])) {
    $coaches[$ci]['Files'] = [];
}

if (isset($_POST['specializations'])) {
    $specs = json_decode($_POST['specializations'], true);
    $coaches[$ci]['Specializations'] = is_array($specs) ? $specs : [];
}

// ----------------------------------------------
// Paths
// ----------------------------------------------
$diskUploadPath = '/data/uploads/';  // Render persistent disk
$publicPrefix   = 'uploads/';       // Stored in JSON

// ----------------------------------------------
// Handle deletion of existing images
// ----------------------------------------------
if (!empty($_POST['delete'])) {
    $deletes = is_array($_POST['delete']) ? $_POST['delete'] : [$_POST['delete']];
    foreach ($deletes as $type) {
        if (!empty($coaches[$ci]['Files'][$type])) {
            $basename = basename($coaches[$ci]['Files'][$type]);
            $filePath = $diskUploadPath . $basename;
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }
        unset($coaches[$ci]['Files'][$type]);
    }
}

// ----------------------------------------------
// Handle new uploads
// from FormData: files[] + imageType[]
// ----------------------------------------------
if (!empty($_FILES['files']) && !empty($_POST['imageType'])) {

    $fileData  = $_FILES['files'];
    $typeArray = $_POST['imageType']; // parallel array

    foreach ($typeArray as $index => $type) {

        if (!isset($fileData['name'][$index])) continue;
        if ($fileData['error'][$index] !== UPLOAD_ERR_OK) continue;

        $origName = $fileData['name'][$index];
        $tmpPath  = $fileData['tmp_name'][$index];

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!$ext) $ext = 'jpg';

        $safeUser = preg_replace('/[^a-z0-9_\-]+/i', '_', $username);
        $safeType = preg_replace('/[^a-z0-9_\-]+/i', '_', $type);
        $fileName = "{$safeUser}_{$safeType}_" . time() . ".$ext";

        $diskFile  = $diskUploadPath . $fileName;
        $publicURL = $publicPrefix . $fileName;

        if (move_uploaded_file($tmpPath, $diskFile)) {
            $coaches[$ci]['Files'][$type] = $publicURL;
        }
    }
}

// ----------------------------------------------
// Save updated coaches.json
// ----------------------------------------------
$jsonOut = json_encode($coaches, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($jsonOut === false) {
    fail("JSON error: " . json_last_error_msg());
}

if (file_put_contents($coachesFile, $jsonOut) === false) {
    fail("Failed writing coaches.json");
}

// ----------------------------------------------
// Success
// ----------------------------------------------
ok($coaches[$ci]);
