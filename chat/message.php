<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('chat')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$messageId = inputInt('get', 'id');
if ($messageId === null) {
    header('Location: ' . BASE_URL . '/chat/index.php');
    exit;
}

$pdo = db_connect();
$stmt = $pdo->prepare(
    "SELECT c.id, c.name, c.message, c.created_at, c.topic_id, c.topic_label, c.is_pinned, c.pinned_until,
            t.name AS topic_name, t.slug AS topic_slug
     FROM cms_chat c
     LEFT JOIN cms_chat_topics t ON t.id = c.topic_id
     WHERE c.id = ?
       AND c.conversation_type = 'public'
       AND c.public_visibility = 'approved'
     LIMIT 1"
);
$stmt->execute([$messageId]);
$message = $stmt->fetch() ?: null;

if (!$message) {
    renderPublicNotFoundPage([
        'title' => 'Zpráva v chatu nenalezena',
        'meta' => [
            'url' => BASE_URL . '/chat/zprava/' . $messageId,
        ],
        'body_class' => 'page-chat-not-found',
    ]);
}

$errors = [];
$threadAvailable = true;
$successState = trim((string)($_GET['reply'] ?? ''));
$contactDefaults = currentUserContactDefaults($pdo);
$isPostRequest = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($isPostRequest) {
    $successState = '';
}
$formData = [
    'name' => $contactDefaults['name'],
    'email' => $contactDefaults['email'],
    'message' => '',
];

if ($isPostRequest) {
    rateLimit('chat_reply', 5, 120);

    if (honeypotTriggered()) {
        header('Location: ' . appendUrlQuery(chatMessagePath($message), ['reply' => 'pending']));
        exit;
    }

    verifyCsrf();
    $formData = [
        'name' => trim((string)($_POST['name'] ?? '')),
        'email' => trim((string)($_POST['email'] ?? '')),
        'message' => trim((string)($_POST['message'] ?? '')),
    ];

    if ($formData['name'] === '') {
        $errors[] = 'Jméno je povinný údaj.';
    }
    if ($formData['email'] !== '' && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Neplatná e-mailová adresa.';
    }
    if ($formData['message'] === '') {
        $errors[] = 'Odpověď je povinný údaj.';
    }
    if ($formData['message'] !== '' && chatMessageContainsUrl($formData['message'])) {
        $errors[] = 'Do textu odpovědi nevkládejte webové adresy ani odkazy.';
    }
    if (!captchaVerify($_POST['captcha'] ?? '')) {
        $errors[] = 'Nesprávná odpověď na ověřovací příklad.';
    }

    if ($errors === []) {
        try {
            $replyStored = chatCreatePublicReply(
                $pdo,
                (int)$message['id'],
                $formData['name'],
                $formData['email'],
                $formData['message']
            );
            if ($replyStored) {
                header('Location: ' . appendUrlQuery(chatMessagePath($message), ['reply' => 'pending']));
                exit;
            }
            $threadAvailable = false;
            http_response_code(409);
            $errors[] = 'Na tuto zprávu již nelze odpovědět. Vlákno bylo skryto nebo odstraněno; rozepsaná odpověď zůstala zachovaná.';
        } catch (\Throwable $e) {
            koraLog('warning', 'chat reply insert failed', ['exception' => $e]);
            $errors[] = 'Odpověď se nepodařilo uložit. Rozepsaný obsah zůstal zachovaný; zkuste to prosím později.';
        }
    }
}

$replies = $threadAvailable ? chatPublicReplies($pdo, (int)$message['id']) : [];
$captchaExpr = captchaGenerate();
$siteName = getSetting('site_name', 'Kora CMS');
$topicName = trim((string)($message['topic_name'] ?? $message['topic_label'] ?? ''));
$pageTitle = $threadAvailable ? 'Zpráva od ' . (string)$message['name'] : 'Vlákno chatu již není dostupné';
$backUrl = $topicName !== '' && trim((string)($message['topic_slug'] ?? '')) !== ''
    ? chatTopicPath(['slug' => (string)$message['topic_slug']])
    : BASE_URL . '/chat/index.php';

renderPublicPage([
    'title' => $pageTitle . ' – Chat – ' . $siteName,
    'meta' => [
        'title' => $pageTitle . ' – Chat – ' . $siteName,
        'description' => $threadAvailable ? mb_strimwidth(normalizePlainText((string)$message['message']), 0, 180, '…', 'UTF-8') : $pageTitle,
        'url' => chatMessagePath($message),
        'type' => 'article',
    ],
    'view' => 'modules/chat-message',
    'view_data' => [
        'message' => $message,
        'threadAvailable' => $threadAvailable,
        'replies' => $replies,
        'errors' => $errors,
        'successState' => $successState,
        'captchaExpr' => $captchaExpr,
        'formData' => $formData,
        'backUrl' => $backUrl,
    ],
    'current_nav' => 'chat',
    'body_class' => 'page-chat-message',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/chat_message.php?id=' . (int)$message['id'],
]);
