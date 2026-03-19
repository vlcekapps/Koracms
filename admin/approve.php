<?php
require_once __DIR__ . '/../db.php';
requireSuperAdmin();
verifyCsrf();

$module   = $_POST['module']   ?? '';
$id       = inputInt('post', 'id');
$redirect = $_POST['redirect'] ?? BASE_URL . '/admin/index.php';

// Whitelist povolených modulů
$moduleConfig = [
    'articles'  => ['table' => 'cms_articles',  'has_published' => false],
    'news'      => ['table' => 'cms_news',      'has_published' => false],
    'podcasts'  => ['table' => 'cms_podcasts',  'has_published' => false],
    'events'    => ['table' => 'cms_events',    'has_published' => true],
    'places'    => ['table' => 'cms_places',    'has_published' => true],
    'pages'     => ['table' => 'cms_pages',     'has_published' => true],
    'downloads' => ['table' => 'cms_downloads', 'has_published' => true],
    'food'      => ['table' => 'cms_food_cards', 'has_published' => true],
];

if ($id !== null && isset($moduleConfig[$module])) {
    $cfg = $moduleConfig[$module];
    $pdo = db_connect();
    if ($cfg['has_published']) {
        $pdo->prepare(
            "UPDATE {$cfg['table']} SET status='published', is_published=1 WHERE id=?"
        )->execute([$id]);
    } else {
        $pdo->prepare(
            "UPDATE {$cfg['table']} SET status='published' WHERE id=?"
        )->execute([$id]);
    }
    logAction('approve', "module={$module} id={$id}");
}

header('Location: ' . $redirect);
exit;
