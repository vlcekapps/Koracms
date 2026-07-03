<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('chat')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$errors = [];
$successState = trim((string)($_GET['ok'] ?? ''));
$successReference = trim((string)($_GET['ref'] ?? ''));
$searchQuery = trim((string)($_GET['q'] ?? ''));
$sortOrder = normalizeChatSort(trim((string)($_GET['razeni'] ?? 'newest')));
$topicSlug = chatTopicSlug(trim((string)($_GET['topic_slug'] ?? '')));
$topics = chatTopics($pdo, true);
$activeTopic = null;
if ($topicSlug !== '') {
    $activeTopic = chatTopicBySlug($pdo, $topicSlug, true);
    if ($activeTopic === null) {
        renderPublicNotFoundPage([
            'title' => 'Téma chatu nenalezeno',
            'meta' => [
                'url' => BASE_URL . '/chat/tema/' . rawurlencode($topicSlug),
            ],
            'body_class' => 'page-chat-not-found',
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('chat', 5, 120);

    if (honeypotTriggered()) {
        header('Location: ' . BASE_URL . '/chat/index.php');
        exit;
    }

    verifyCsrf();

    if (!captchaVerify($_POST['captcha'] ?? '')) {
        $errors[] = 'Nesprávná odpověď na ověřovací příklad.';
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));
    $conversationType = normalizeChatConversationType((string)($_POST['conversation_type'] ?? 'public'));
    $topicId = inputInt('post', 'topic_id');
    $selectedTopic = $topicId !== null ? chatTopicById($pdo, $topicId, true) : null;

    if ($name === '') {
        $errors[] = 'Jméno je povinný údaj.';
    }
    if ($message === '') {
        $errors[] = 'Zpráva je povinný údaj.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Neplatná e-mailová adresa.';
    }
    if ($conversationType === 'support' && ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
        $errors[] = 'U soukromého dotazu zadejte platnou e-mailovou adresu pro odpověď.';
    }
    if ($topicId !== null && $selectedTopic === null) {
        $errors[] = 'Vybrané téma chatu není dostupné.';
    }
    if ($message !== '' && chatMessageContainsUrl($message)) {
        $errors[] = 'Do textu zprávy nevkládejte webové adresy ani odkazy.';
    }

    if (empty($errors)) {
        try {
            $referenceCode = $conversationType === 'support' ? uniqueChatReferenceCode($pdo) : '';
            $topicLabel = is_array($selectedTopic) ? (string)($selectedTopic['name'] ?? '') : '';
            $publicVisibility = $conversationType === 'support' ? 'hidden' : 'pending';
            $pdo->prepare(
                "INSERT INTO cms_chat
                 (topic_id, topic_label, conversation_type, reference_code, name, email, web, message, status, public_visibility)
                 VALUES (?, ?, ?, ?, ?, ?, '', ?, 'new', ?)"
            )->execute([
                is_array($selectedTopic) ? (int)$selectedTopic['id'] : null,
                $topicLabel,
                $conversationType,
                $referenceCode,
                $name,
                $email,
                $message,
                $publicVisibility,
            ]);
            $messageId = (int)$pdo->lastInsertId();
            chatHistoryCreate(
                $pdo,
                $messageId,
                null,
                'submitted',
                $conversationType === 'support'
                    ? 'Soukromý dotaz byl přijat do podpůrného inboxu.'
                    : 'Zpráva byla přijata a čeká na schválení.'
            );

            notifyChatMessage($name, $message);

            $targetPath = $activeTopic !== null ? chatTopicPath($activeTopic) : BASE_URL . '/chat/index.php';
            $targetQuery = $conversationType === 'support'
                ? ['ok' => 'support', 'ref' => $referenceCode]
                : ['ok' => 'pending'];
            header('Location: ' . appendUrlQuery($targetPath, $targetQuery));
            exit;
        } catch (\PDOException $e) {
            koraLog('warning', 'chat submission insert failed', ['exception' => $e]);
            $errors[] = 'Zprávu se nepodařilo uložit. Zkuste to prosím později.';
        }
    }
}

$whereSql = "WHERE c.conversation_type = 'public' AND c.public_visibility = 'approved'";
$queryParams = [];
if ($activeTopic !== null) {
    $whereSql .= ' AND c.topic_id = ?';
    $queryParams[] = (int)$activeTopic['id'];
}
if ($searchQuery !== '') {
    $whereSql .= " AND (c.name LIKE ? OR c.message LIKE ?)";
    $searchNeedle = '%' . $searchQuery . '%';
    $queryParams[] = $searchNeedle;
    $queryParams[] = $searchNeedle;
}

$pagination = paginate(
    $pdo,
    "SELECT COUNT(*) FROM cms_chat c {$whereSql}",
    $queryParams,
    chatPublicMessagesPerPage()
);
$orderSql = $sortOrder === 'oldest'
    ? "(c.is_pinned = 1 AND (c.pinned_until IS NULL OR c.pinned_until >= NOW())) DESC, c.created_at ASC, c.id ASC"
    : "(c.is_pinned = 1 AND (c.pinned_until IS NULL OR c.pinned_until >= NOW())) DESC, c.created_at DESC, c.id DESC";
$messagesStmt = $pdo->prepare(
    "SELECT c.id, c.name, c.message, c.created_at, c.topic_id, c.topic_label, c.is_pinned, c.pinned_until,
            t.slug AS topic_slug, t.name AS topic_name,
            (SELECT COUNT(*) FROM cms_chat_replies r WHERE r.chat_id = c.id AND r.status = 'approved') AS reply_count
     FROM cms_chat c
     LEFT JOIN cms_chat_topics t ON t.id = c.topic_id
     {$whereSql}
     ORDER BY {$orderSql}
     LIMIT ? OFFSET ?"
);
$messagesStmt->execute(array_merge(
    $queryParams,
    [$pagination['perPage'], $pagination['offset']]
));
$messages = $messagesStmt->fetchAll();
$captchaExpr = captchaGenerate();

$pagerParams = [];
if ($searchQuery !== '') {
    $pagerParams['q'] = $searchQuery;
}
if ($sortOrder !== 'newest') {
    $pagerParams['razeni'] = $sortOrder;
}
$baseListingPath = $activeTopic !== null ? chatTopicPath($activeTopic) : BASE_URL . '/chat/index.php';
$pagerBaseUrl = $baseListingPath . '?' . ($pagerParams !== [] ? http_build_query($pagerParams) . '&' : '');
$pageHeading = $activeTopic !== null ? 'Chat: ' . (string)$activeTopic['name'] : 'Chat';
$pageDescription = $activeTopic !== null && trim((string)($activeTopic['description'] ?? '')) !== ''
    ? normalizePlainText((string)$activeTopic['description'])
    : 'Moderovaný veřejný chat webu ' . $siteName . '.';

renderPublicPage([
    'title' => $pageHeading . ' – ' . $siteName,
    'meta' => [
        'title' => $pageHeading . ' – ' . $siteName,
        'description' => $pageDescription,
        'url' => $baseListingPath,
    ],
    'view' => 'modules/chat',
    'view_data' => [
        'messages' => $messages,
        'errors' => $errors,
        'successState' => $successState,
        'successReference' => $successReference,
        'captchaExpr' => $captchaExpr,
        'searchQuery' => $searchQuery,
        'sortOrder' => $sortOrder,
        'topics' => $topics,
        'activeTopic' => $activeTopic,
        'pagination' => $pagination,
        'pagerBaseUrl' => $pagerBaseUrl,
        'formData' => [
            'name' => trim((string)($_POST['name'] ?? '')),
            'email' => trim((string)($_POST['email'] ?? '')),
            'message' => trim((string)($_POST['message'] ?? '')),
            'conversation_type' => normalizeChatConversationType((string)($_POST['conversation_type'] ?? 'public')),
            'topic_id' => trim((string)($_POST['topic_id'] ?? ($activeTopic['id'] ?? ''))),
        ],
    ],
    'current_nav' => 'chat',
    'body_class' => 'page-chat',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/chat.php',
]);
