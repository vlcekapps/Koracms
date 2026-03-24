<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('blog')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo      = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$perPage  = max(1, (int)getSetting('blog_per_page', '10'));

$katId   = inputInt('get', 'kat');
$tagSlug = trim($_GET['tag'] ?? '');

$categories = $pdo->query("SELECT id, name FROM cms_categories ORDER BY name")->fetchAll();

$allTags = [];
try {
    $allTags = $pdo->query("SELECT name, slug FROM cms_tags ORDER BY name")->fetchAll();
} catch (\PDOException $e) {
}

$where  = "WHERE a.status = 'published' AND (a.publish_at IS NULL OR a.publish_at <= NOW())";
$params = [];

if ($katId !== null) {
    $where   .= " AND a.category_id = ?";
    $params[] = $katId;
}

if ($tagSlug !== '') {
    $where  .= " AND EXISTS (
        SELECT 1 FROM cms_article_tags at2
        JOIN cms_tags t ON t.id = at2.tag_id
        WHERE at2.article_id = a.id AND t.slug = ?
    )";
    $params[] = $tagSlug;
}

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM cms_articles a {$where}"
);
$countStmt->execute($params);
$total  = (int)$countStmt->fetchColumn();
$pages  = max(1, (int)ceil($total / $perPage));
$page   = max(1, min($pages, (int)($_GET['strana'] ?? 1)));
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT a.id, a.title, a.slug, a.perex, a.content, a.image_file, a.created_at, a.category_id, c.name AS category,
            COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),'')) AS author_name,
            u.author_public_enabled, u.author_slug, u.role AS author_role
     FROM cms_articles a
     LEFT JOIN cms_categories c ON c.id = a.category_id
     LEFT JOIN cms_users u ON u.id = a.author_id
     {$where}
     ORDER BY a.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$articles = $stmt->fetchAll();
$articles = array_map(
    static fn(array $article): array => hydrateAuthorPresentation($article),
    $articles
);

$pageHeading = 'Blog';
if ($katId !== null) {
    $categoryNames = array_column($categories, 'name', 'id');
    $pageHeading = 'Blog – ' . ($categoryNames[$katId] ?? 'Kategorie');
} elseif ($tagSlug !== '') {
    $activeTag = array_filter($allTags, fn($tag) => $tag['slug'] === $tagSlug);
    $activeTag = reset($activeTag);
    if ($activeTag) {
        $pageHeading = 'Blog – #' . $activeTag['name'];
    }
}

$paginBase = BASE_URL . '/blog/index.php?' . ($katId !== null ? 'kat=' . $katId . '&' : '') . ($tagSlug !== '' ? 'tag=' . rawurlencode($tagSlug) . '&' : '');

renderPublicPage([
    'title' => $pageHeading . ' – ' . $siteName,
    'meta' => [
        'title' => $pageHeading . ' – ' . $siteName,
    ],
    'view' => 'modules/blog-index',
    'view_data' => [
        'pageHeading' => $pageHeading,
        'categories' => $categories,
        'allTags' => $allTags,
        'articles' => $articles,
        'pages' => $pages,
        'page' => $page,
        'katId' => $katId,
        'tagSlug' => $tagSlug,
        'paginBase' => $paginBase,
    ],
    'current_nav' => 'blog',
    'body_class' => 'page-blog-index',
    'page_kind' => 'listing',
]);
