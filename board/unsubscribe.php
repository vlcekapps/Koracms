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
$failed = false;
$token = trim((string)($_GET['token'] ?? ''));

if ($token !== '') {
    rateLimit('board_unsubscribe', 5, 300);
    try {
        $ok = removeBoardSubscription(db_connect(), $token);
    } catch (\Throwable $e) {
        $failed = true;
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
        'announceRole' => $failed ? 'alert' : ($ok ? 'status' : ''),
        'messages' => $failed
            ? ['Odhlášení se nepodařilo dokončit. Zkuste stejný odkaz prosím později.']
            : ($ok
            ? ['Váš e-mail byl úspěšně odhlášen z odběru vývěsky.']
            : ['Odkaz pro odhlášení je neplatný nebo odběr již neexistuje.']),
        'actions' => [
            ['href' => BASE_URL . '/board/index.php', 'label' => 'Zpět na vývěsku', 'class' => 'button-secondary'],
        ],
    ],
    'current_nav' => 'board',
    'body_class' => 'page-status page-board-unsubscribe',
    'page_kind' => 'utility',
]);
