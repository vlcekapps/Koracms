<?php
require_once __DIR__ . '/db.php';
checkMaintenanceMode();

$siteName = getSetting('site_name', 'Kora CMS');
$token    = trim($_GET['token'] ?? '');
$ok       = false;

if ($token !== '') {
    try {
        $stmt = db_connect()->prepare(
            "DELETE FROM cms_subscribers WHERE token = ?"
        );
        $stmt->execute([$token]);
        $ok = $stmt->rowCount() > 0;
    } catch (\PDOException $e) {
        error_log('unsubscribe: ' . $e->getMessage());
    }
}

renderPublicPage([
    'title' => 'Odhlášení z odběru – ' . $siteName,
    'meta' => [
        'title' => 'Odhlášení z odběru – ' . $siteName,
    ],
    'view' => 'utility/status',
    'view_data' => [
        'kicker' => 'Newsletter',
        'title' => 'Odhlášení z odběru novinek',
        'variant' => $ok ? 'success' : 'warning',
        'announceRole' => $ok ? 'status' : '',
        'messages' => $ok
            ? ['Váš e-mail byl úspěšně odhlášen z odběru novinek.']
            : ['Odkaz pro odhlášení je neplatný nebo odběr již neexistuje.'],
        'actions' => [
            ['href' => BASE_URL . '/index.php', 'label' => 'Zpět na úvod', 'class' => 'button-secondary'],
        ],
    ],
    'body_class' => 'page-status page-unsubscribe',
    'page_kind' => 'utility',
]);
