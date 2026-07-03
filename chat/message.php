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
$successState = trim((string)($_GET['reply'] ?? ''));
$formData = [
    'name' => '',
    'email' => '',
    'message' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $pdo->prepare(
            "INSERT INTO cms_chat_replies (chat_id, name, email, message, status)
             VALUES (?, ?, ?, ?, 'pending')"
        )->execute([
            (int)$message['id'],
            $formData['name'],
            $formData['email'],
            $formData['message'],
        ]);
        chatHistoryCreate($pdo, (int)$message['id'], null, 'reply_submitted', 'Veřejná odpověď byla přijata a čeká na schválení.');

        header('Location: ' . appendUrlQuery(chatMessagePath($message), ['reply' => 'pending']));
        exit;
    }
}

$replies = chatPublicReplies($pdo, (int)$message['id']);
$captchaExpr = captchaGenerate();
$siteName = getSetting('site_name', 'Kora CMS');
$topicName = trim((string)($message['topic_name'] ?? $message['topic_label'] ?? ''));
$pageTitle = 'Zpráva od ' . (string)$message['name'];
$backUrl = $topicName !== '' && trim((string)($message['topic_slug'] ?? '')) !== ''
    ? chatTopicPath(['slug' => (string)$message['topic_slug']])
    : BASE_URL . '/chat/index.php';

renderPublicPage([
    'title' => $pageTitle . ' – Chat – ' . $siteName,
    'meta' => [
        'title' => $pageTitle . ' – Chat – ' . $siteName,
        'description' => mb_strimwidth(normalizePlainText((string)$message['message']), 0, 180, '…', 'UTF-8'),
        'url' => chatMessagePath($message),
        'type' => 'article',
    ],
    'view' => 'modules/chat-message',
    'view_data' => [
        'message' => $message,
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
