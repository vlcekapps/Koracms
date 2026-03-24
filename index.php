<?php
require_once __DIR__ . '/db.php';
checkMaintenanceMode();

$siteName = getSetting('site_name', 'Kora CMS');
$siteDesc = getSetting('site_description', '');

$latestNews = [];
$homeNewsCount = (int)getSetting('home_news_count', '5');
if (isModuleEnabled('news') && $homeNewsCount > 0) {
    $stmt = db_connect()->prepare(
        "SELECT id, content, created_at FROM cms_news WHERE status = 'published' ORDER BY created_at DESC LIMIT ?"
    );
    $stmt->execute([$homeNewsCount]);
    $latestNews = $stmt->fetchAll();
}

$latestArticles = [];
$homeBlogCount = (int)getSetting('home_blog_count', '5');
if (isModuleEnabled('blog') && $homeBlogCount > 0) {
    $stmt = db_connect()->prepare(
        "SELECT a.id, a.title, a.slug, a.perex, a.content, a.image_file, a.created_at, c.name AS category,
                COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),'')) AS author_name,
                u.author_public_enabled, u.author_slug, u.role AS author_role
         FROM cms_articles a
         LEFT JOIN cms_categories c ON c.id = a.category_id
         LEFT JOIN cms_users u ON u.id = a.author_id
         WHERE a.status = 'published' AND (a.publish_at IS NULL OR a.publish_at <= NOW())
         ORDER BY a.created_at DESC LIMIT ?"
    );
    $stmt->execute([$homeBlogCount]);
    $latestArticles = $stmt->fetchAll();
    $latestArticles = array_map(
        static fn(array $article): array => hydrateAuthorPresentation($article),
        $latestArticles
    );
}

$latestBoard = [];
$homeBoardCount = (int)getSetting('home_board_count', '5');
if (isModuleEnabled('board') && $homeBoardCount > 0) {
    $stmt = db_connect()->prepare(
        "SELECT b.id, b.title, b.posted_date, b.filename, b.original_name, b.file_size
         FROM cms_board b
         WHERE b.status = 'published' AND b.is_published = 1
           AND (b.removal_date IS NULL OR b.removal_date >= CURDATE())
         ORDER BY b.posted_date DESC, b.sort_order, b.title
         LIMIT ?"
    );
    $stmt->execute([$homeBoardCount]);
    $latestBoard = $stmt->fetchAll();
}

$homePoll = null;
if (isModuleEnabled('polls')) {
    $pollStmt = db_connect()->prepare(
        "SELECT p.*, (SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = p.id) AS vote_count
         FROM cms_polls p
         WHERE p.status = 'active'
           AND (p.start_date IS NULL OR p.start_date <= NOW())
           AND (p.end_date   IS NULL OR p.end_date > NOW())
         ORDER BY p.created_at DESC LIMIT 1"
    );
    $pollStmt->execute();
    $homePoll = $pollStmt->fetch() ?: null;
}

$homeAuthor = resolveHomeAuthor(db_connect());

renderPublicPage([
    'title' => $siteName,
    'meta' => [
        'url' => BASE_URL . '/index.php',
    ],
    'view' => 'home',
    'view_data' => [
        'homeIntro' => getSetting('home_intro', ''),
        'latestNews' => $latestNews,
        'latestArticles' => $latestArticles,
        'latestBoard' => $latestBoard,
        'homePoll' => $homePoll,
        'homeAuthor' => $homeAuthor,
    ],
    'current_nav' => 'home',
    'page_kind' => 'home',
    'body_class' => 'page-home',
    'show_site_description' => $siteDesc !== '',
]);
