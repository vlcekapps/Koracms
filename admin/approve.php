<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$module = trim($_POST['module'] ?? '');
$id = inputInt('post', 'id');
$redirect = internalRedirectTarget($_POST['redirect'] ?? '', BASE_URL . '/admin/index.php');

$moduleConfig = [
    'articles' => ['table' => 'cms_articles', 'has_published' => false, 'capability' => 'blog_approve'],
    'news' => ['table' => 'cms_news', 'has_published' => false, 'capability' => 'news_approve'],
    'podcasts' => ['table' => 'cms_podcasts', 'has_published' => false, 'capability' => 'content_approve_shared'],
    'events' => ['table' => 'cms_events', 'has_published' => true, 'capability' => 'content_approve_shared'],
    'faq' => ['table' => 'cms_faqs', 'has_published' => true, 'capability' => 'content_approve_shared'],
    'places' => ['table' => 'cms_places', 'has_published' => true, 'capability' => 'content_approve_shared'],
    'pages' => ['table' => 'cms_pages', 'has_published' => true, 'capability' => 'content_approve_shared'],
    'downloads' => ['table' => 'cms_downloads', 'has_published' => true, 'capability' => 'content_approve_shared'],
    'food' => ['table' => 'cms_food_cards', 'has_published' => true, 'capability' => 'content_approve_shared'],
    'board' => ['table' => 'cms_board', 'has_published' => true, 'capability' => 'content_approve_shared'],
];

if ($id !== null && isset($moduleConfig[$module])) {
    $config = $moduleConfig[$module];
    if (!currentUserHasCapability($config['capability'])) {
        adminForbidden('Přístup odepřen. Pro schvalování tohoto obsahu nemáte potřebné oprávnění.');
    }

    $pdo = db_connect();
    if ($config['has_published']) {
        $pdo->prepare(
            "UPDATE {$config['table']} SET status = 'published', is_published = 1 WHERE id = ?"
        )->execute([$id]);
    } else {
        $pdo->prepare(
            "UPDATE {$config['table']} SET status = 'published' WHERE id = ?"
        )->execute([$id]);
    }
    logAction('approve', "module={$module} id={$id}");
}

header('Location: ' . $redirect);
exit;
