<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();
sendNoStoreNoIndexHeaders();

requireHttpMethods(['GET']);

if (!isModuleEnabled('board')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$siteName = getSetting('site_name', 'Kora CMS');
$boardLabel = boardModulePublicLabel();
$ok = false;
$token = trim((string)($_GET['token'] ?? ''));

if ($token !== '') {
    rateLimit('board_unsubscribe', 5, 300);
    try {
        $pdo = db_connect();
        $stmt = $pdo->prepare("SELECT id FROM cms_board_subscribers WHERE token = ? LIMIT 1");
        $stmt->execute([$token]);
        $subscriberId = (int)($stmt->fetchColumn() ?: 0);
        if ($subscriberId > 0) {
            $pdo->prepare("DELETE FROM cms_board_subscriber_categories WHERE subscriber_id = ?")->execute([$subscriberId]);
            $pdo->prepare("DELETE FROM cms_board_subscribers WHERE id = ?")->execute([$subscriberId]);
            $ok = true;
        }
    } catch (\PDOException $e) {
        koraLog('warning', 'board unsubscribe failed', ['exception' => $e]);
    }
}

renderPublicPage([
    'title' => 'Odhlášení odběru vývěsky – ' . $siteName,
    'meta' => [
        'title' => 'Odhlášení odběru vývěsky – ' . $siteName,
    ],
    'view' => 'utility/status',
    'view_data' => [
        'kicker' => $boardLabel,
        'title' => 'Odhlášení odběru vývěsky',
        'variant' => $ok ? 'success' : 'warning',
        'announceRole' => $ok ? 'status' : '',
        'messages' => $ok
            ? ['Váš e-mail byl úspěšně odhlášen z odběru vývěsky.']
            : ['Odkaz pro odhlášení je neplatný nebo odběr již neexistuje.'],
        'actions' => [
            ['href' => BASE_URL . '/board/index.php', 'label' => 'Zpět na vývěsku', 'class' => 'button-secondary'],
        ],
    ],
    'current_nav' => 'board',
    'body_class' => 'page-status page-board-unsubscribe',
    'page_kind' => 'utility',
]);
