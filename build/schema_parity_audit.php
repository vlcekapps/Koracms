<?php

declare(strict_types=1);

$projectRootArgument = $argv[1] ?? null;
$projectRoot = schemaParityProjectRoot(is_string($projectRootArgument) ? $projectRootArgument : null);
$issues = [];

function schemaParityProjectRoot(?string $override): string
{
    $candidates = [];
    if ($override !== null && trim($override) !== '') {
        $candidates[] = $override;
    }

    $environmentOverride = getenv('KORA_SCHEMA_PARITY_AUDIT_ROOT');
    if (is_string($environmentOverride) && trim($environmentOverride) !== '') {
        $candidates[] = $environmentOverride;
    }

    foreach ($candidates as $candidate) {
        $resolved = realpath($candidate);
        if (is_string($resolved) && is_dir($resolved)) {
            return $resolved;
        }
    }

    return dirname(__DIR__);
}

/**
 * @param list<string> $issues
 */
function schemaParityReadFile(string $projectRoot, string $relativePath, array &$issues): string
{
    $path = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($path)) {
        $issues[] = $relativePath . ' is missing.';
        return '';
    }

    $source = file_get_contents($path);
    if (!is_string($source) || $source === '') {
        $issues[] = $relativePath . ' cannot be read.';
        return '';
    }

    return $source;
}

function schemaParityTableContains(string $source, string $tableName, string $needle): bool
{
    $marker = 'CREATE TABLE IF NOT EXISTS ' . $tableName;
    $start = strpos($source, $marker);
    if ($start === false) {
        return false;
    }

    $end = strpos($source, 'ENGINE=InnoDB', $start);
    if ($end === false) {
        return false;
    }

    return str_contains(substr($source, $start, $end - $start), $needle);
}

/**
 * @param list<string> $issues
 */
function schemaParityRequire(bool $condition, string $message, array &$issues): void
{
    if (!$condition) {
        $issues[] = $message;
    }
}

$installSource = schemaParityReadFile($projectRoot, 'install.php', $issues);
$migrateSource = schemaParityReadFile($projectRoot, 'migrate.php', $issues);
$blogIndexSource = schemaParityReadFile($projectRoot, 'blog/index.php', $issues);
$blogPageSource = schemaParityReadFile($projectRoot, 'blog/page.php', $issues);
$contentReferenceSearchSource = schemaParityReadFile($projectRoot, 'admin/content_reference_search.php', $issues);
$galleryPhotoSource = schemaParityReadFile($projectRoot, 'gallery/photo.php', $issues);
$sitemapSource = schemaParityReadFile($projectRoot, 'sitemap.php', $issues);
$feedSource = schemaParityReadFile($projectRoot, 'feed.php', $issues);
$dbSource = schemaParityReadFile($projectRoot, 'db.php', $issues);

$criticalInstallColumns = [
    'cms_pages.blog_id' => ['cms_pages', 'blog_id'],
    'cms_pages.blog_nav_order' => ['cms_pages', 'blog_nav_order'],
    'cms_pages.deleted_at' => ['cms_pages', 'deleted_at'],
    'cms_nav_links.blog_id' => ['cms_nav_links', 'blog_id'],
    'cms_nav_links.url' => ['cms_nav_links', 'url'],
    'cms_nav_links.alt_text' => ['cms_nav_links', 'alt_text'],
    'cms_nav_links.target_blank' => ['cms_nav_links', 'target_blank'],
    'cms_nav_links.is_active' => ['cms_nav_links', 'is_active'],
    'cms_nav_links.nav_order' => ['cms_nav_links', 'nav_order'],
    'cms_media.caption' => ['cms_media', 'caption'],
    'cms_media.credit' => ['cms_media', 'credit'],
    'cms_media.visibility' => ['cms_media', 'visibility'],
    'cms_gallery_photos.slug' => ['cms_gallery_photos', 'slug'],
    'cms_gallery_photos.status' => ['cms_gallery_photos', 'status'],
    'cms_gallery_photos.is_published' => ['cms_gallery_photos', 'is_published'],
    'cms_gallery_photos.deleted_at' => ['cms_gallery_photos', 'deleted_at'],
    'cms_events.excerpt' => ['cms_events', 'excerpt'],
];

foreach ($criticalInstallColumns as $label => [$tableName, $columnName]) {
    schemaParityRequire(
        schemaParityTableContains($installSource, $tableName, $columnName),
        'install.php fresh schema is missing critical column ' . $label . '.',
        $issues
    );
}

$criticalMigrationSnippets = [
    'cms_pages.blog_id',
    'cms_pages.blog_nav_order',
    'idx_pages_blog_nav',
    'cms_nav_links',
    'idx_nav_links_scope',
    'idx_nav_links_active',
    'cms_media.caption',
    'cms_media.credit',
    'cms_media.visibility',
    'idx_media_visibility',
    'cms_gallery_photos.slug',
    'cms_gallery_photos.status',
    'cms_gallery_photos.is_published',
    'cms_gallery_photos.deleted_at',
    'cms_events.excerpt',
];

foreach ($criticalMigrationSnippets as $snippet) {
    schemaParityRequire(
        str_contains($migrateSource, $snippet),
        'migrate.php upgrade schema is missing critical migration guard ' . $snippet . '.',
        $issues
    );
}

schemaParityRequire(
    str_contains($blogIndexSource, 'SELECT id, title, slug, blog_id, blog_nav_order')
    && str_contains($blogIndexSource, 'koraLog(\'warning\', \'blog index pages query failed\''),
    'blog/index.php must keep blog page schema usage guarded and logged.',
    $issues
);
schemaParityRequire(
    str_contains($blogPageSource, 'INNER JOIN cms_blogs b ON b.id = p.blog_id')
    && str_contains($blogPageSource, 'p.blog_id = ?'),
    'blog/page.php must keep blog pages scoped to their owning blog.',
    $issues
);
schemaParityRequire(
    str_contains($contentReferenceSearchSource, 'SELECT id, filename, original_name, alt_text, caption, credit, visibility, mime_type, file_size, folder, created_at')
    && str_contains($contentReferenceSearchSource, 'caption LIKE ?')
    && str_contains($contentReferenceSearchSource, "contentReferenceLogSourceError('media'"),
    'content_reference_search media query must keep media metadata columns and graceful logging.',
    $issues
);
schemaParityRequire(
    str_contains($galleryPhotoSource, 'FROM cms_gallery_photos p')
    && str_contains($galleryPhotoSource, "galleryPhotoPublicVisibilitySql('p', 'a')"),
    'gallery/photo.php must keep gallery photo visibility guarded by table aliases.',
    $issues
);
$gallerySitemapStart = strpos($sitemapSource, 'FROM cms_gallery_photos p');
$gallerySitemapQuery = $gallerySitemapStart === false ? '' : substr($sitemapSource, $gallerySitemapStart, 600);
schemaParityRequire(
    str_contains($gallerySitemapQuery, 'FROM cms_gallery_photos p')
    && str_contains($gallerySitemapQuery, 'ORDER BY p.created_at DESC, p.id DESC')
    && preg_match('/ORDER BY\s+created_at\s+DESC/i', $gallerySitemapQuery) !== 1,
    'sitemap.php gallery photos query must keep created_at qualified by alias.',
    $issues
);
schemaParityRequire(
    str_contains($feedSource, 'articleExcerpt(')
    && str_contains($dbSource, "require_once __DIR__ . '/lib/presentation.php';"),
    'feed.php must keep articleExcerpt() available through db.php presentation helpers.',
    $issues
);

if ($issues !== []) {
    echo "Schema parity audit failed:\n";
    foreach ($issues as $issue) {
        echo '- ' . $issue . "\n";
    }
    exit(1);
}

echo "Schema parity audit OK\n";
