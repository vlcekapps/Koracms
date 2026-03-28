<?php
require_once __DIR__ . '/db.php';
checkMaintenanceMode();

$slug = authorSlug(trim($_GET['slug'] ?? ''));
if ($slug === '') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$author = fetchPublicAuthorBySlug($pdo, $slug);

if (!$author) {
    http_response_code(404);
    $siteName = getSetting('site_name', 'Kora CMS');
    renderPublicPage([
        'title' => 'Autor nenalezen – ' . $siteName,
        'meta' => [
            'title' => 'Autor nenalezen – ' . $siteName,
            'url' => BASE_URL . '/author/' . rawurlencode($slug),
        ],
        'view' => 'not-found',
        'body_class' => 'page-author-not-found',
    ]);
    exit;
}

$articles = [];
if (isModuleEnabled('blog')) {
    $stmt = $pdo->prepare(
        "SELECT a.id, a.title, a.slug, a.perex, a.content, a.image_file, a.created_at, a.publish_at, a.view_count,
                a.category_id, c.name AS category, b.slug AS blog_slug
         FROM cms_articles a
         LEFT JOIN cms_categories c ON c.id = a.category_id
         LEFT JOIN cms_blogs b ON b.id = a.blog_id
         WHERE a.author_id = ?
           AND a.status = 'published'
           AND (a.publish_at IS NULL OR a.publish_at <= NOW())
         ORDER BY COALESCE(a.publish_at, a.created_at) DESC, a.id DESC"
    );
    $stmt->execute([(int)$author['id']]);
    $articles = $stmt->fetchAll();
}

$siteName = getSetting('site_name', 'Kora CMS');
$metaImage = '';
if (!empty($author['author_avatar'])) {
    $metaImage = siteUrl('/uploads/authors/' . rawurlencode((string)$author['author_avatar']));
}

renderPublicPage([
    'title' => $author['author_display_name'] . ' – ' . $siteName,
    'meta' => [
        'title' => $author['author_display_name'] . ' – ' . $siteName,
        'description' => trim((string)($author['author_bio'] ?? '')),
        'image' => $metaImage,
        'url' => authorPublicUrl($author),
        'type' => 'profile',
    ],
    'view' => 'account/author',
    'view_data' => [
        'author' => $author,
        'articles' => $articles,
        'blogEnabled' => isModuleEnabled('blog'),
    ],
    'body_class' => 'page-author',
    'page_kind' => 'detail',
]);
