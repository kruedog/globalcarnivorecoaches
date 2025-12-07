<?php
// webapi/logout.php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://globalcarnivorecoaches.onrender.com');
header('Access-Control-Allow-Credentials: true');

// Destroy session
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}
session_destroy();

echo json_encode([
    'success' => true,
    'message' => 'Logged out',
]);
