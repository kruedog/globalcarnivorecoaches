<?php
// webapi/logout.php — Logs logout + destroys session
header('Content-Type: application/json');
require_once __DIR__ . '/log_activity.php';

session_start();
log_coach_activity('logout');

session_destroy();
echo json_encode(['success' => true]);
?>