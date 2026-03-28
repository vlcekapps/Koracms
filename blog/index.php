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

// Multiblog: blog kontext z routeru nebo default
$blogId = isset($_GET['blog_id']) ? (int)$_GET['blog_id'] : null;
$blog = $blogId !== null ? getBlogById($blogId) : ($GLOBALS['current_blog'] ?? getDefaultBlog());
if (!$blog) {
    $blog = getDefaultBlog();
}
$blogId = (int)$blog['id'];
$blogName = (string)$blog['name'];

$katId   = inputInt('get', 'kat');
$tagSlug = trim($_GET['tag'] ?? '');
$authorSlug = authorSlug(trim($_GET['autor'] ?? ''));

$catStmt = $pdo->prepare("SELECT id, name FROM cms_categories WHERE blog_id = ? ORDER BY name");
$catStmt->execute([$blogId]);
$categories = $catStmt->fetchAll();

$activeAuthor = $authorSlug !== '' ? fetchPublicAuthorBySlug($pdo, $authorSlug) : null;
$showAuthorsIndexLink = false;
$publicBlogs = isMultiBlog() ? getPublicBlogNavigationBlogs($blog) : [];
if (getSetting('blog_authors_index_enabled', '0') === '1') {
    try {
        $showAuthorsIndexLink = (int)$pdo->query(
            "SELECT COUNT(*) FROM cms_users WHERE author_public_enabled = 1 AND role != 'public'"
        )->fetchColumn() > 0;
    } catch (\PDOException $e) {
        $showAuthorsIndexLink = false;
    }
}

$allTags = [];
try {
    $tagStmt = $pdo->prepare("SELECT name, slug FROM cms_tags WHERE blog_id = ? ORDER BY name");
    $tagStmt->execute([$blogId]);
    $allTags = $tagStmt->fetchAll();
} catch (\PDOException $e) {
    error_log('blog/index tags: ' . $e->getMessage());
}

$where  = "WHERE a.status = 'published' AND (a.publish_at IS NULL OR a.publish_at <= NOW()) AND a.blog_id = ?";
$params = [$blogId];

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

if ($authorSlug !== '') {
    if ($activeAuthor) {
        $where .= " AND a.author_id = ?";
        $params[] = (int)$activeAuthor['id'];
    } else {
        $where .= " AND 1 = 0";
    }
}

$pag = paginate($pdo, "SELECT COUNT(*) FROM cms_articles a {$where}", $params, $perPage);
['totalPages' => $pages, 'page' => $page, 'offset' => $offset] = $pag;

$stmt = $pdo->prepare(
    "SELECT a.id, a.title, a.slug, a.perex, a.content, a.image_file, a.created_at, a.category_id, a.blog_id,
            c.name AS category, b.slug AS blog_slug,
            a.view_count,
            COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),'')) AS author_name,
            u.author_public_enabled, u.author_slug, u.role AS author_role
     FROM cms_articles a
     LEFT JOIN cms_categories c ON c.id = a.category_id
     LEFT JOIN cms_blogs b ON b.id = a.blog_id
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

$pageHeading = $blogName;
if ($activeAuthor) {
    $pageHeading = $blogName . ' – ' . $activeAuthor['author_display_name'];
} elseif ($katId !== null) {
    $categoryNames = array_column($categories, 'name', 'id');
    $pageHeading = $blogName . ' – ' . ($categoryNames[$katId] ?? 'Kategorie');
} elseif ($tagSlug !== '') {
    $activeTag = array_filter($allTags, fn($tag) => $tag['slug'] === $tagSlug);
    $activeTag = reset($activeTag);
    if ($activeTag) {
        $pageHeading = $blogName . ' – #' . $activeTag['name'];
    }
} elseif ($authorSlug !== '') {
    $pageHeading = $blogName . ' – autor';
}

$blogIndexBase = blogIndexPath($blog);
$paginBase = $blogIndexBase . (str_contains($blogIndexBase, '?') ? '&' : '?')
    . ($katId !== null ? 'kat=' . $katId . '&' : '')
    . ($tagSlug !== '' ? 'tag=' . rawurlencode($tagSlug) . '&' : '')
    . ($authorSlug !== '' ? 'autor=' . rawurlencode($authorSlug) . '&' : '');

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
        'activeAuthor' => $activeAuthor,
        'showAuthorsIndexLink' => $showAuthorsIndexLink,
        'publicBlogs' => $publicBlogs,
        'paginBase' => $paginBase,
        'blog' => $blog,
    ],
    'current_nav' => 'blog:' . $blog['slug'],
    'body_class' => 'page-blog-index',
    'page_kind' => 'listing',
]);
