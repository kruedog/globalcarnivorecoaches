<?php
// webapi/logout.php

declare(strict_types=1);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => 'globalcarnivorecoaches.onrender.com',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None'
]);

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://globalcarnivorecoaches.onrender.com');
header('Access-Control-Allow-Credentials: true');

// Clear session data
$_SESSION = [];
session_unset();
session_destroy();

// Expire cookie
setcookie(
    session_name(),
    '',
    time() - 3600,
    '/',
    'globalcarnivorecoaches.onrender.com',
    true,
    true
);

echo json_encode(['success' => true, 'message' => 'Logged out']);
exit;
