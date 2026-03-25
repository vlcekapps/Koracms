<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('contact')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo       = db_connect();
$siteName  = getSetting('site_name', 'Kora CMS');
$destEmail = getSetting('contact_email', '');

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('contact', 3, 120);

    if (honeypotTriggered()) {
        $success = true;
    } else {
        verifyCsrf();

        $from    = trim($_POST['from'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Zadejte platnou e-mailovou adresu odesílatele.';
        }
        if ($subject === '') {
            $errors[] = 'Předmět je povinný.';
        }
        if ($message === '') {
            $errors[] = 'Zpráva je povinná.';
        }
        if (!captchaVerify($_POST['captcha'] ?? '')) {
            $errors[] = 'Chybná odpověď na ověřovací otázku.';
        }

        if (empty($errors)) {
            $pdo->prepare(
                "INSERT INTO cms_contact (sender_email, subject, message, is_read, status)
                 VALUES (?, ?, ?, 0, 'new')"
            )->execute([$from, $subject, $message]);

            if ($destEmail !== '') {
                $safeSubject = preg_replace('/[\r\n]/', '', $subject);
                $safeFrom = preg_replace('/[\r\n]/', '', $from);
                $mailBody = "Zpráva z kontaktního formuláře.\n\nOd: {$safeFrom}\nPředmět: {$safeSubject}\n\n{$message}";
                sendMail($destEmail, $safeSubject, $mailBody);
            }

            $success = true;
        }
    }
}

$captchaExpr = captchaGenerate();

renderPublicPage([
    'title' => 'Kontakt – ' . $siteName,
    'meta' => [
        'title' => 'Kontakt – ' . $siteName,
        'url' => BASE_URL . '/contact/index.php',
    ],
    'view' => 'modules/contact',
    'view_data' => [
        'success' => $success,
        'errors' => $errors,
        'captchaExpr' => $captchaExpr,
        'formData' => [
            'from' => trim($_POST['from'] ?? ''),
            'subject' => trim($_POST['subject'] ?? ''),
            'message' => trim($_POST['message'] ?? ''),
        ],
    ],
    'current_nav' => 'contact',
    'body_class' => 'page-contact',
    'page_kind' => 'form',
    'admin_edit_url' => BASE_URL . '/admin/contact.php',
]);
