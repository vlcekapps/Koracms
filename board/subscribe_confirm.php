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
    rateLimit('board_subscribe_confirm', 5, 300);
    try {
        $stmt = db_connect()->prepare(
            "UPDATE cms_board_subscribers
             SET confirmed = 1, confirmed_at = NOW()
             WHERE token = ? AND confirmed = 0"
        );
        $stmt->execute([$token]);
        $ok = $stmt->rowCount() > 0;
    } catch (\PDOException $e) {
        koraLog('warning', 'board subscription confirmation failed', ['exception' => $e]);
    }
}

renderPublicPage([
    'title' => ($ok ? 'Odběr potvrzen' : 'Neplatný odkaz') . ' – ' . $siteName,
    'meta' => [
        'title' => ($ok ? 'Odběr potvrzen' : 'Neplatný odkaz') . ' – ' . $siteName,
    ],
    'view' => 'utility/status',
    'view_data' => [
        'kicker' => $boardLabel,
        'title' => $ok ? 'Odběr vývěsky potvrzen' : 'Neplatný odkaz',
        'variant' => $ok ? 'success' : 'warning',
        'announceRole' => $ok ? 'status' : '',
        'messages' => $ok
            ? ['Odběr nových položek vývěsky byl úspěšně potvrzen.']
            : ['Odkaz pro potvrzení je neplatný nebo již byl použit.'],
        'actions' => [
            ['href' => BASE_URL . '/board/index.php', 'label' => 'Zpět na vývěsku', 'class' => 'button-secondary'],
        ],
    ],
    'current_nav' => 'board',
    'body_class' => 'page-status page-board-subscribe-confirm',
    'page_kind' => 'utility',
]);
