<?php

require_once __DIR__ . '/db.php';
checkMaintenanceMode();

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo  = db_connect();
$previewToken = trim($_GET['preview'] ?? '');
if ($previewToken !== '') {
    $stmt = $pdo->prepare(
        "SELECT * FROM cms_pages
         WHERE slug = ?
           AND blog_id IS NULL
           AND deleted_at IS NULL
           AND preview_token = ?"
    );
    $stmt->execute([$slug, $previewToken]);
} else {
    $stmt = $pdo->prepare(
        "SELECT * FROM cms_pages
         WHERE slug = ?
           AND blog_id IS NULL
           AND deleted_at IS NULL
           AND status = 'published'
           AND is_published = 1
           AND (publish_at IS NULL OR publish_at <= NOW())"
    );
    $stmt->execute([$slug]);
}
$page = $stmt->fetch();

if (!$page) {
    renderPublicNotFoundPage([
        'meta' => [
            'url' => pagePublicPath(['slug' => $slug]),
        ],
        'body_class' => 'page-not-found',
    ]);
}

$siteName = getSetting('site_name', 'Kora CMS');

renderPublicPage([
    'title' => $page['title'] . ' – ' . $siteName,
    'meta' => [
        'title' => $page['title'] . ' – ' . $siteName,
        'url' => pagePublicPath($page),
    ],
    'view' => 'page',
    'view_data' => [
        'page' => $page,
    ],
    'current_nav' => 'page:' . $slug,
    'page_kind' => 'page',
    'body_class' => 'page-static',
    'admin_edit_url' => BASE_URL . '/admin/page_form.php?id=' . (int)$page['id'],
]);
