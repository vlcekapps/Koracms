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
    renderPublicNotFoundPage([
        'title' => 'Autor nenalezen',
        'meta' => [
            'url' => BASE_URL . '/author/' . rawurlencode($slug),
        ],
        'body_class' => 'page-author-not-found',
    ]);
}

$contentType = normalizeAuthorContentType((string)($_GET['typ'] ?? ''));
$contentCounts = fetchPublicAuthorContentCounts($pdo, (int)$author['id']);
$contentFilterOptions = authorContentFilterOptions($contentCounts);
$perPage = max(1, (int)getSetting('blog_per_page', '10'));
$contentTotal = authorContentCountForType($contentCounts, $contentType);
$pagination = paginateArray($contentTotal, $perPage);
['totalPages' => $pages, 'page' => $page, 'offset' => $offset] = $pagination;
$authorContentItems = fetchPublicAuthorContent($pdo, (int)$author['id'], $contentType, $perPage, $offset);

$backBlog = null;
if (isModuleEnabled('blog')) {
    foreach ($authorContentItems as $contentItem) {
        if (($contentItem['content_type'] ?? '') !== 'article') {
            continue;
        }
        $backBlogId = (int)($contentItem['blog_id'] ?? 0);
        if ($backBlogId > 0) {
            $backBlog = getBlogById($backBlogId);
        }
        break;
    }

    if (!$backBlog) {
        $backBlog = getDefaultBlog();
    }
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
        'contentItems' => $authorContentItems,
        'contentCounts' => $contentCounts,
        'contentType' => $contentType,
        'contentFilterOptions' => $contentFilterOptions,
        'pages' => $pages,
        'page' => $page,
        'pagerBaseUrl' => authorPublicPath($author) . '?' . ($contentType !== 'vse' ? http_build_query(['typ' => $contentType]) . '&' : ''),
        'blogEnabled' => isModuleEnabled('blog'),
        'backBlogPath' => $backBlog ? blogIndexPath($backBlog) : '',
        'backBlogLabel' => $backBlog ? 'Zpět na blog' : '',
    ],
    'body_class' => 'page-author',
    'page_kind' => 'detail',
]);
