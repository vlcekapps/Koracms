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
$searchQuery = trim((string)($_GET['q'] ?? ''));
$sortOrder = normalizeChatSort(trim((string)($_GET['razeni'] ?? 'newest')));

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

    if ($name === '') {
        $errors[] = 'Jméno je povinný údaj.';
    }
    if ($message === '') {
        $errors[] = 'Zpráva je povinný údaj.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Neplatná e-mailová adresa.';
    }
    if ($message !== '' && chatMessageContainsUrl($message)) {
        $errors[] = 'Do textu zprávy nevkládejte webové adresy ani odkazy.';
    }

    if (empty($errors)) {
        try {
            $pdo->prepare(
                "INSERT INTO cms_chat (name, email, web, message, status, public_visibility)
                 VALUES (?, ?, '', ?, 'new', 'pending')"
            )->execute([$name, $email, $message]);
            $messageId = (int)$pdo->lastInsertId();
            chatHistoryCreate($pdo, $messageId, null, 'submitted', 'Zpráva byla přijata a čeká na schválení.');

            notifyChatMessage($name, $message);

            header('Location: ' . BASE_URL . '/chat/index.php?ok=pending');
            exit;
        } catch (\PDOException $e) {
            error_log('chat INSERT failed: ' . $e->getMessage());
            $errors[] = 'Zprávu se nepodařilo uložit. Zkuste to prosím později.';
        }
    }
}

$whereSql = "WHERE c.public_visibility = 'approved'";
$queryParams = [];
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
    ? 'c.created_at ASC, c.id ASC'
    : 'c.created_at DESC, c.id DESC';
$messagesStmt = $pdo->prepare(
    "SELECT c.id, c.name, c.message, c.created_at
     FROM cms_chat c
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
$pagerBaseUrl = BASE_URL . '/chat/index.php?' . ($pagerParams !== [] ? http_build_query($pagerParams) . '&' : '');

renderPublicPage([
    'title' => 'Chat – ' . $siteName,
    'meta' => [
        'title' => 'Chat – ' . $siteName,
        'url' => BASE_URL . '/chat/index.php',
    ],
    'view' => 'modules/chat',
    'view_data' => [
        'messages' => $messages,
        'errors' => $errors,
        'successState' => $successState,
        'captchaExpr' => $captchaExpr,
        'searchQuery' => $searchQuery,
        'sortOrder' => $sortOrder,
        'pagination' => $pagination,
        'pagerBaseUrl' => $pagerBaseUrl,
        'formData' => [
            'name' => trim((string)($_POST['name'] ?? '')),
            'email' => trim((string)($_POST['email'] ?? '')),
            'message' => trim((string)($_POST['message'] ?? '')),
        ],
    ],
    'current_nav' => 'chat',
    'body_class' => 'page-chat',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/chat.php',
]);
