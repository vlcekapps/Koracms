<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('downloads')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$id = inputInt('get', 'id');
$slug = downloadSlug(trim((string)($_GET['slug'] ?? '')));
if ($id === null && $slug === '') {
    header('Location: ' . BASE_URL . '/downloads/index.php');
    exit;
}

$pdo = db_connect();

if ($slug !== '') {
    $stmt = $pdo->prepare(
        "SELECT d.*, COALESCE(c.name, '') AS category_name
         FROM cms_downloads d
         LEFT JOIN cms_dl_categories c ON c.id = d.dl_category_id
         WHERE d.slug = ? AND d.deleted_at IS NULL AND d.status = 'published' AND d.is_published = 1
         LIMIT 1"
    );
    $stmt->execute([$slug]);
} else {
    $stmt = $pdo->prepare(
        "SELECT d.*, COALESCE(c.name, '') AS category_name
         FROM cms_downloads d
         LEFT JOIN cms_dl_categories c ON c.id = d.dl_category_id
         WHERE d.id = ? AND d.deleted_at IS NULL AND d.status = 'published' AND d.is_published = 1
         LIMIT 1"
    );
    $stmt->execute([$id]);
}

$download = $stmt->fetch() ?: null;
if (!$download) {
    http_response_code(404);
    $siteName = getSetting('site_name', 'Kora CMS');
    $missingPath = $slug !== ''
        ? BASE_URL . '/downloads/' . rawurlencode($slug)
        : BASE_URL . '/downloads/item.php' . ($id !== null ? '?id=' . urlencode((string)$id) : '');

    renderPublicPage([
        'title' => 'Položka nenalezena - ' . $siteName,
        'meta' => [
            'title' => 'Položka nenalezena - ' . $siteName,
            'url' => $missingPath,
        ],
        'view' => 'not-found',
        'body_class' => 'page-download-not-found',
    ]);
    exit;
}

$download = hydrateDownloadPresentation($download);

if ($slug === '' && (string)$download['slug'] !== '') {
    header('Location: ' . downloadPublicPath($download));
    exit;
}

$otherVersions = [];
if ((string)$download['series_key'] !== '') {
    $versionsStmt = $pdo->prepare(
        "SELECT d.*, COALESCE(c.name, '') AS category_name
         FROM cms_downloads d
         LEFT JOIN cms_dl_categories c ON c.id = d.dl_category_id
         WHERE d.series_key = ?
           AND d.id <> ?
           AND d.status = 'published'
           AND d.is_published = 1
         ORDER BY d.is_featured DESC, COALESCE(d.release_date, DATE(d.created_at)) DESC, d.created_at DESC, d.id DESC
         LIMIT 8"
    );
    $versionsStmt->execute([(string)$download['series_key'], (int)$download['id']]);
    $otherVersions = array_map(
        static fn(array $version): array => hydrateDownloadPresentation($version),
        $versionsStmt->fetchAll()
    );
}

if (!isset($_SESSION['cms_user_id'])) {
    trackPageView('download', (int)$download['id']);
}

$siteName = getSetting('site_name', 'Kora CMS');
$metaDescription = downloadExcerpt($download, 180);
if ($metaDescription === '') {
    $metaDescription = 'Detail položky ke stažení ' . (string)$download['title'];
}

renderPublicPage([
    'title' => (string)$download['title'] . ' - ' . $siteName,
    'meta' => [
        'title' => (string)$download['title'] . ' - ' . $siteName,
        'description' => $metaDescription,
        'url' => downloadPublicUrl($download),
        'type' => 'article',
    ],
    'view' => 'modules/downloads-article',
    'view_data' => [
        'download' => $download,
        'otherVersions' => $otherVersions,
    ],
    'current_nav' => 'downloads',
    'body_class' => 'page-downloads-article',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/download_form.php?id=' . (int)$download['id'],
]);
