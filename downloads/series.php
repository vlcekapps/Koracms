<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('downloads')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$seriesSlug = downloadSeriesSlug(trim((string)($_GET['slug'] ?? '')));
if ($seriesSlug === '') {
    header('Location: ' . BASE_URL . '/downloads/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

$seriesStmt = $pdo->prepare(
    "SELECT s.*
     FROM cms_download_series s
     WHERE s.slug = ?
       AND s.is_active = 1
       AND EXISTS (
           SELECT 1
           FROM cms_downloads d
           WHERE d.download_series_id = s.id
             AND d.deleted_at IS NULL
             AND d.status = 'published'
             AND d.is_published = 1
       )
     LIMIT 1"
);
$seriesStmt->execute([$seriesSlug]);
$series = $seriesStmt->fetch() ?: null;
if (!$series) {
    renderPublicNotFoundPage([
        'title' => 'Série nenalezena',
        'meta' => [
            'url' => BASE_URL . '/downloads/serie/' . rawurlencode($seriesSlug),
        ],
        'body_class' => 'page-download-series-not-found',
    ]);
}

$itemsStmt = $pdo->prepare(
    "SELECT d.*, COALESCE(c.name, '') AS category_name, COALESCE(c.slug, '') AS category_slug,
            s.title AS series_title, s.slug AS series_slug, s.description AS series_description
     FROM cms_downloads d
     LEFT JOIN cms_dl_categories c ON c.id = d.dl_category_id
     INNER JOIN cms_download_series s ON s.id = d.download_series_id
     WHERE d.download_series_id = ?
       AND d.deleted_at IS NULL
       AND d.status = 'published'
       AND d.is_published = 1
     ORDER BY d.is_current_version DESC, COALESCE(d.release_date, DATE(d.created_at)) DESC, d.created_at DESC, d.id DESC"
);
$itemsStmt->execute([(int)$series['id']]);
$items = array_map(
    static fn (array $download): array => hydrateDownloadPresentation($download),
    $itemsStmt->fetchAll()
);

$metaDescription = downloadExcerpt(['excerpt' => (string)($series['description'] ?? '')], 180);
if ($metaDescription === '') {
    $metaDescription = 'Verze a soubory ke stažení v sérii ' . (string)$series['title'];
}

renderPublicPage([
    'title' => (string)$series['title'] . ' - Ke stažení - ' . $siteName,
    'meta' => [
        'title' => (string)$series['title'] . ' - Ke stažení - ' . $siteName,
        'description' => $metaDescription,
        'url' => downloadSeriesUrl($series),
        'type' => 'website',
    ],
    'view' => 'modules/downloads-series',
    'view_data' => [
        'series' => $series,
        'items' => $items,
    ],
    'current_nav' => 'downloads',
    'body_class' => 'page-downloads-series',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/download_series.php?edit=' . (int)$series['id'],
]);
