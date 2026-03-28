<?php
require_once __DIR__ . '/db.php';
checkMaintenanceMode();

$siteName = getSetting('site_name', 'Kora CMS');
$ok       = false;
$token    = trim($_GET['token'] ?? '');

if ($token !== '') {
    try {
        $stmt = db_connect()->prepare(
            "UPDATE cms_subscribers SET confirmed = 1 WHERE token = ? AND confirmed = 0"
        );
        $stmt->execute([$token]);
        $ok = $stmt->rowCount() > 0;
    } catch (\PDOException $e) {
        error_log('subscribe_confirm: ' . $e->getMessage());
    }
}

renderPublicPage([
    'title' => ($ok ? 'Potvrzení odběru' : 'Neplatný odkaz') . ' – ' . $siteName,
    'meta' => [
        'title' => ($ok ? 'Potvrzení odběru' : 'Neplatný odkaz') . ' – ' . $siteName,
    ],
    'view' => 'utility/status',
    'view_data' => [
        'kicker' => 'Newsletter',
        'title' => $ok ? 'Odběr potvrzen' : 'Neplatný odkaz',
        'variant' => $ok ? 'success' : 'warning',
        'announceRole' => $ok ? 'status' : '',
        'messages' => $ok
            ? ['Váš odběr novinek byl úspěšně potvrzen. Děkujeme!']
            : ['Odkaz pro potvrzení je neplatný nebo již byl použit.'],
        'actions' => [
            ['href' => BASE_URL . '/index.php', 'label' => 'Zpět na úvod', 'class' => 'button-secondary'],
        ],
    ],
    'body_class' => 'page-status page-subscribe-confirm',
    'page_kind' => 'utility',
]);
