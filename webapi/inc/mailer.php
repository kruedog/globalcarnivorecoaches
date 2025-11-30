<?php
// inc/mailer.php
function send_mail_message($to, $subject, $body, $is_html = false){
    $c = require __DIR__ . '/config.php';
    // If SMTP is enabled you should set up an SMTP relay or use PHPMailer/Swiftmailer.
    if (!empty($c['smtp']['enabled'])) {
        // Basic fallback: try mail() — if you want reliable SMTP, install PHPMailer and update this.
        // For now, attempt mail(); if your NAS supports sendmail/postfix this may work.
    }
    $headers = "From: " . ($c['smtp']['from_email'] ?? 'no-reply@localhost') . "\r\n";
    if ($is_html) $headers .= "Content-type: text/html; charset=utf-8\r\n";
    return mail($to, $subject, $body, $headers);
}
