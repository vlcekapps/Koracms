<?php
require_once __DIR__ . '/../db.php';
requireCapability('newsletter_manage', 'Přístup odepřen. Pro rozesílání newsletteru nemáte potřebné oprávnění.');
verifyCsrf();

$subject = trim($_POST['subject'] ?? '');
$body = trim($_POST['body'] ?? '');

$_SESSION['newsletter_form_state'] = [
    'subject' => $subject,
    'body' => $body,
];

if ($subject === '' || $body === '') {
    $_SESSION['newsletter_form_error'] = 'Vyplňte prosím předmět i text newsletteru.';
    header('Location: ' . BASE_URL . '/admin/newsletter_form.php');
    exit;
}

$pdo = db_connect();
$subscribers = $pdo->query(
    "SELECT email, token FROM cms_subscribers WHERE confirmed = 1"
)->fetchAll();

if ($subscribers === []) {
    $_SESSION['newsletter_form_error'] = 'Nejsou žádní potvrzení odběratelé. Newsletter nelze odeslat.';
    header('Location: ' . BASE_URL . '/admin/newsletter_form.php');
    exit;
}

$sent = 0;
foreach ($subscribers as $subscriber) {
    $unsubscribeUrl = siteUrl('/unsubscribe.php?token=' . (string)$subscriber['token']);
    $personalBody = $body
        . "\n\n---\n"
        . "Pro odhlášení z odběru klikněte zde: {$unsubscribeUrl}";

    if (sendMail((string)$subscriber['email'], $subject, $personalBody)) {
        $sent++;
    }
}

$pdo->prepare(
    "INSERT INTO cms_newsletters (subject, body, recipient_count, sent_at)
     VALUES (?, ?, ?, NOW())"
)->execute([$subject, $body, $sent]);

$newsletterId = (int)$pdo->lastInsertId();
logAction('newsletter_send', "subject={$subject} recipients={$sent}");
unset($_SESSION['newsletter_form_state'], $_SESSION['newsletter_form_error']);

header('Location: ' . BASE_URL . '/admin/newsletter_history.php?id=' . $newsletterId . '&ok=sent');
exit;
