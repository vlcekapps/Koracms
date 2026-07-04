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
$topics = contactTopics($pdo, true);
$topicRequired = $topics !== [];

$errors        = [];
$fieldErrors   = [];
$success       = false;
$referenceCode = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('contact', 3, 120);

    if (honeypotTriggered()) {
        $success = true;
    } else {
        verifyCsrf();

        $senderName = trim((string)($_POST['sender_name'] ?? ''));
        $from       = trim((string)($_POST['from'] ?? ''));
        $subject    = trim((string)($_POST['subject'] ?? ''));
        $message    = trim((string)($_POST['message'] ?? ''));
        $topicId    = inputInt('post', 'topic_id');
        $topic      = $topicId !== null ? contactTopicById($pdo, $topicId, true) : null;

        if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Zadejte platnou e-mailovou adresu odesílatele.';
            $fieldErrors['from'] = 'Zadejte platnou e-mailovou adresu odesílatele.';
        }
        if ($topicRequired && $topic === null) {
            $errors[] = 'Vyberte téma dotazu.';
            $fieldErrors['topic_id'] = 'Vyberte téma dotazu.';
        }
        if ($subject === '') {
            $errors[] = 'Předmět je povinný.';
            $fieldErrors['subject'] = 'Předmět je povinný.';
        }
        if ($message === '') {
            $errors[] = 'Zpráva je povinná.';
            $fieldErrors['message'] = 'Zpráva je povinná.';
        }
        if (!captchaVerify($_POST['captcha'] ?? '')) {
            $captchaError = publicCaptchaErrorMessage();
            $errors[] = $captchaError;
            $fieldErrors['captcha'] = $captchaError;
        }

        if (empty($errors)) {
            $referenceCode = uniqueContactReferenceCode($pdo);
            $topicLabel = is_array($topic) ? (string)($topic['name'] ?? '') : '';
            try {
                $pdo->prepare(
                    "INSERT INTO cms_contact
                     (sender_name, sender_email, topic_id, topic_label, reference_code, subject, message, is_read, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'new')"
                )->execute([
                    $senderName,
                    $from,
                    is_array($topic) ? (int)$topic['id'] : null,
                    $topicLabel,
                    $referenceCode,
                    $subject,
                    $message,
                ]);
            } catch (\PDOException $e) {
                koraLog('warning', 'contact submission insert failed', ['exception' => $e]);
                $errors[] = 'Zprávu se nepodařilo uložit. Zkuste to prosím později.';
            }

            if (empty($errors)) {
                $mailSent = true;
                $recipientEmail = contactNotificationRecipient($destEmail, $topic);
                if ($recipientEmail !== '') {
                    $senderLine = $senderName !== '' ? "{$senderName} <{$from}>" : $from;
                    $topicLine = $topicLabel !== '' ? $topicLabel : 'Bez tématu';
                    $mailBody = "Zpráva z kontaktního formuláře.\n\n"
                        . "Referenční kód: {$referenceCode}\n"
                        . "Od: {$senderLine}\n"
                        . "Téma: {$topicLine}\n"
                        . "Předmět: {$subject}\n\n"
                        . $message;
                    $mailSubject = 'Kontakt: ' . $subject . ' – ' . $siteName;
                    $mailSent = sendMail($recipientEmail, $mailSubject, $mailBody, [
                        'reply_to' => $from,
                    ]);
                }

                $success = true;
                if (!$mailSent) {
                    $errors[] = 'Zpráva byla uložena, ale e-mailové oznámení se nepodařilo odeslat.';
                }
            }
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
        'fieldErrors' => $fieldErrors,
        'referenceCode' => $referenceCode,
        'topics' => $topics,
        'topicRequired' => $topicRequired,
        'captchaExpr' => $captchaExpr,
        'formData' => [
            'sender_name' => trim((string)($_POST['sender_name'] ?? '')),
            'from' => trim($_POST['from'] ?? ''),
            'topic_id' => trim((string)($_POST['topic_id'] ?? '')),
            'subject' => trim($_POST['subject'] ?? ''),
            'message' => trim($_POST['message'] ?? ''),
        ],
    ],
    'current_nav' => 'contact',
    'body_class' => 'page-contact',
    'page_kind' => 'form',
    'admin_edit_url' => BASE_URL . '/admin/contact.php',
]);
