<?php
// inc/config.php
return [
    'base_url' => 'http://kruedog.ddns.net:90', // update if needed
    'coaches_file' => __DIR__ . '/../coaches.json',
    'tokens_file'  => __DIR__ . '/../tokens.json',
    // SMTP: if you can set up SMTP on your NAS, flip enabled => true and fill details.
    'smtp' => [
        'enabled' => false,
        'from_email' => 'no-reply@kruedog.local',
        'host' => 'smtp.example.com',
        'port' => 587,
        'username' => 'smtpuser',
        'password' => 'smtppass',
        'secure' => 'tls', // or 'ssl' or ''
    ],
    'reset_token_ttl' => 60 * 60, // 1 hour
    'session_name' => 'kruedog_sess',
];
