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
        "SELECT d.*, COALESCE(c.name, '') AS category_name, COALESCE(c.slug, '') AS category_slug,
                s.title AS series_title, s.slug AS series_slug, s.description AS series_description, s.is_active AS series_is_active
         FROM cms_downloads d
         LEFT JOIN cms_dl_categories c ON c.id = d.dl_category_id
         LEFT JOIN cms_download_series s ON s.id = d.download_series_id AND s.is_active = 1
         WHERE d.slug = ? AND d.deleted_at IS NULL AND d.status = 'published' AND d.is_published = 1
         LIMIT 1"
    );
    $stmt->execute([$slug]);
} else {
    $stmt = $pdo->prepare(
        "SELECT d.*, COALESCE(c.name, '') AS category_name, COALESCE(c.slug, '') AS category_slug,
                s.title AS series_title, s.slug AS series_slug, s.description AS series_description, s.is_active AS series_is_active
         FROM cms_downloads d
         LEFT JOIN cms_dl_categories c ON c.id = d.dl_category_id
         LEFT JOIN cms_download_series s ON s.id = d.download_series_id AND s.is_active = 1
         WHERE d.id = ? AND d.deleted_at IS NULL AND d.status = 'published' AND d.is_published = 1
         LIMIT 1"
    );
    $stmt->execute([$id]);
}

$download = $stmt->fetch() ?: null;
if (!$download) {
    $missingPath = $slug !== ''
        ? BASE_URL . '/downloads/' . rawurlencode($slug)
        : BASE_URL . '/downloads/item.php?id=' . urlencode((string)$id);

    renderPublicNotFoundPage([
        'title' => 'Položka nenalezena',
        'meta' => [
            'url' => $missingPath,
        ],
        'body_class' => 'page-download-not-found',
    ]);
}

$download = hydrateDownloadPresentation($download);

if ($slug === '' && (string)$download['slug'] !== '') {
    header('Location: ' . downloadPublicPath($download));
    exit;
}

$otherVersions = [];
$currentVersion = null;
if ((int)($download['download_series_id'] ?? 0) > 0) {
    $versionsStmt = $pdo->prepare(
        "SELECT d.*, COALESCE(c.name, '') AS category_name, COALESCE(c.slug, '') AS category_slug,
                s.title AS series_title, s.slug AS series_slug, s.description AS series_description
         FROM cms_downloads d
         LEFT JOIN cms_dl_categories c ON c.id = d.dl_category_id
         INNER JOIN cms_download_series s ON s.id = d.download_series_id AND s.is_active = 1
         WHERE d.download_series_id = ?
           AND d.id <> ?
           AND d.status = 'published'
           AND d.is_published = 1
           AND d.deleted_at IS NULL
         ORDER BY d.is_current_version DESC, COALESCE(d.release_date, DATE(d.created_at)) DESC, d.created_at DESC, d.id DESC
         LIMIT 8"
    );
    $versionsStmt->execute([(int)$download['download_series_id'], (int)$download['id']]);
    $otherVersions = array_map(
        static fn (array $version): array => hydrateDownloadPresentation($version),
        $versionsStmt->fetchAll()
    );
    if ((int)$download['is_current_version'] !== 1) {
        $currentVersionStmt = $pdo->prepare(
            "SELECT d.*, COALESCE(c.name, '') AS category_name, COALESCE(c.slug, '') AS category_slug,
                    s.title AS series_title, s.slug AS series_slug, s.description AS series_description
             FROM cms_downloads d
             LEFT JOIN cms_dl_categories c ON c.id = d.dl_category_id
             INNER JOIN cms_download_series s ON s.id = d.download_series_id AND s.is_active = 1
             WHERE d.download_series_id = ?
               AND d.id <> ?
               AND d.is_current_version = 1
               AND d.status = 'published'
               AND d.is_published = 1
               AND d.deleted_at IS NULL
             LIMIT 1"
        );
        $currentVersionStmt->execute([(int)$download['download_series_id'], (int)$download['id']]);
        $currentVersionRow = $currentVersionStmt->fetch() ?: null;
        $currentVersion = $currentVersionRow ? hydrateDownloadPresentation($currentVersionRow) : null;
    }
} elseif ((string)$download['series_key'] !== '') {
    $versionsStmt = $pdo->prepare(
        "SELECT d.*, COALESCE(c.name, '') AS category_name, COALESCE(c.slug, '') AS category_slug
         FROM cms_downloads d
         LEFT JOIN cms_dl_categories c ON c.id = d.dl_category_id
         WHERE d.series_key = ?
           AND d.id <> ?
           AND d.status = 'published'
           AND d.is_published = 1
           AND d.deleted_at IS NULL
         ORDER BY d.is_featured DESC, COALESCE(d.release_date, DATE(d.created_at)) DESC, d.created_at DESC, d.id DESC
         LIMIT 8"
    );
    $versionsStmt->execute([(string)$download['series_key'], (int)$download['id']]);
    $otherVersions = array_map(
        static fn (array $version): array => hydrateDownloadPresentation($version),
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
        'currentVersion' => $currentVersion,
    ],
    'current_nav' => 'downloads',
    'body_class' => 'page-downloads-article',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/download_form.php?id=' . (int)$download['id'],
]);
