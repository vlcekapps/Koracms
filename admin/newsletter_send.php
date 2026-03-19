<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$subject = trim($_POST['subject'] ?? '');
$body    = trim($_POST['body']    ?? '');

if ($subject === '' || $body === '') {
    header('Location: ' . BASE_URL . '/admin/newsletter_form.php');
    exit;
}

$pdo      = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';

$subscribers = $pdo->query(
    "SELECT email, token FROM cms_subscribers WHERE confirmed = 1"
)->fetchAll();

$sent = 0;
foreach ($subscribers as $s) {
    $unsubUrl    = BASE_URL . '/unsubscribe.php?token=' . $s['token'];
    $personalBody = $body
        . "\n\n---\n"
        . "Pro odhlášení z odběru klikněte zde: {$unsubUrl}";

    $headers = "From: {$siteName} <noreply@{$host}>\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";

    if (mail($s['email'], $subject, $personalBody, $headers)) {
        $sent++;
    }
}

// Uložit záznam
$pdo->prepare(
    "INSERT INTO cms_newsletters (subject, body, recipient_count, sent_at) VALUES (?,?,?,NOW())"
)->execute([$subject, $body, $sent]);

logAction('newsletter_send', "subject={$subject} recipients={$sent}");

header('Location: ' . BASE_URL . '/admin/newsletter.php');
exit;
