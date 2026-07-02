<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$schemaParityAuditPath = __DIR__ . DIRECTORY_SEPARATOR . 'schema_parity_audit.php';

function schemaParityAuditSelfTestFail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function schemaParityAuditSelfTestWriteTextFile(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        schemaParityAuditSelfTestFail('Cannot create directory: ' . $directory);
    }

    if (file_put_contents($path, $contents) === false) {
        schemaParityAuditSelfTestFail('Cannot write file: ' . $path);
    }
}

function schemaParityAuditSelfTestRemoveTree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }

    $items = scandir($path);
    if ($items !== false) {
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            schemaParityAuditSelfTestRemoveTree($path . DIRECTORY_SEPARATOR . $item);
        }
    }

    @rmdir($path);
}

/**
 * @param list<string> $command
 * @return array{exitCode:int, output:string}
 */
function runSchemaParityAuditSelfTestCommand(array $command, string $cwd): array
{
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open(
        $command,
        $descriptorSpec,
        $pipes,
        $cwd,
        null,
        ['bypass_shell' => true],
    );

    if (!is_resource($process)) {
        schemaParityAuditSelfTestFail('Cannot start command: ' . implode(' ', $command));
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'exitCode' => (int)$exitCode,
        'output' => trim(
            (is_string($stdout) ? $stdout : '')
            . (is_string($stderr) && $stderr !== '' ? PHP_EOL . $stderr : '')
        ),
    ];
}

/**
 * @return array<string,string>
 */
function validSchemaParityFixture(): array
{
    return [
        'install.php' => <<<'PHP'
<?php
CREATE TABLE IF NOT EXISTS cms_pages (
  id INT,
  blog_id INT NULL,
  blog_nav_order INT NOT NULL DEFAULT 0,
  deleted_at DATETIME NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_nav_links (
  id INT,
  blog_id INT NULL,
  url VARCHAR(255),
  alt_text VARCHAR(255),
  target_blank TINYINT(1),
  is_active TINYINT(1),
  nav_order INT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_media (
  id INT,
  caption VARCHAR(255),
  credit VARCHAR(255),
  visibility VARCHAR(20)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_gallery_photos (
  id INT,
  slug VARCHAR(255),
  status VARCHAR(20),
  is_published TINYINT(1),
  deleted_at DATETIME NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_event_types (
  id INT,
  legacy_key VARCHAR(80),
  slug VARCHAR(150),
  description TEXT,
  meta_title VARCHAR(160),
  meta_description TEXT,
  is_active TINYINT(1),
  sort_order INT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_events (
  id INT,
  event_type_id INT,
  place_id INT,
  recurrence_group_id VARCHAR(64),
  excerpt TEXT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_admin_shortcuts (
  id INT,
  user_id INT,
  item_type VARCHAR(50),
  item_key VARCHAR(120),
  url VARCHAR(500)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_article_related (
  article_id INT,
  related_article_id INT,
  sort_order INT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_blog_series (
  id INT,
  blog_id INT,
  slug VARCHAR(255),
  is_active TINYINT(1)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_blog_series_items (
  series_id INT,
  article_id INT,
  sort_order INT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_categories (
  id INT,
  name VARCHAR(255),
  slug VARCHAR(150),
  description TEXT,
  meta_title VARCHAR(160),
  meta_description TEXT,
  updated_at DATETIME
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_tags (
  id INT,
  name VARCHAR(100),
  slug VARCHAR(100),
  description TEXT,
  meta_title VARCHAR(160),
  meta_description TEXT,
  updated_at DATETIME
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_board_categories (
  id INT,
  name VARCHAR(255),
  slug VARCHAR(150),
  description TEXT,
  meta_title VARCHAR(160),
  meta_description TEXT,
  updated_at DATETIME
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_board_publication_events (
  id INT,
  board_id INT,
  event_type VARCHAR(40)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_board_subscribers (
  id INT,
  email VARCHAR(255),
  confirmed TINYINT(1)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_board_subscriber_categories (
  subscriber_id INT,
  category_id INT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_contact_topics (
  id INT,
  slug VARCHAR(150),
  recipient_email VARCHAR(255),
  is_active TINYINT(1)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_contact (
  id INT,
  sender_name VARCHAR(255),
  topic_id INT,
  topic_label VARCHAR(255),
  reference_code VARCHAR(32),
  replied_at DATETIME,
  replied_by_user_id INT,
  reply_subject VARCHAR(255),
  reply_body TEXT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_dl_categories (
  id INT,
  name VARCHAR(255),
  slug VARCHAR(150),
  description TEXT,
  meta_title VARCHAR(160),
  meta_description TEXT,
  updated_at DATETIME
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_download_series (
  id INT,
  title VARCHAR(255),
  slug VARCHAR(150),
  is_active TINYINT(1),
  sort_order INT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_downloads (
  id INT,
  download_series_id INT,
  is_current_version TINYINT(1)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_faq_categories (
  id INT,
  name VARCHAR(255),
  slug VARCHAR(150),
  description TEXT,
  meta_title VARCHAR(160),
  meta_description TEXT,
  updated_at DATETIME
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_faq_feedback (
  id INT,
  faq_id INT,
  vote VARCHAR(20),
  visitor_hash VARCHAR(64)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_food_sections (
  id INT,
  card_id INT,
  title VARCHAR(255),
  sort_order INT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_food_items (
  id INT,
  card_id INT,
  section_id INT,
  title VARCHAR(255),
  price_amount DECIMAL(10,2),
  media_id INT,
  image_alt_text VARCHAR(255),
  allergens VARCHAR(100),
  dietary_flags VARCHAR(255),
  is_available TINYINT(1)
) ENGINE=InnoDB;
PHP,
        'migrate.php' => <<<'PHP'
<?php
// cms_pages.blog_id
// cms_pages.blog_nav_order
// idx_pages_blog_nav
// cms_nav_links
// idx_nav_links_scope
// idx_nav_links_active
// cms_media.caption
// cms_media.credit
// cms_media.visibility
// idx_media_visibility
// cms_gallery_photos.slug
// cms_gallery_photos.status
// cms_gallery_photos.is_published
// cms_gallery_photos.deleted_at
// cms_event_types
// uq_cms_event_types_slug
// uq_cms_event_types_legacy
// idx_cms_event_types_active_order
// cms_events.event_type_id
// cms_events.place_id
// cms_events.recurrence_group_id
// idx_cms_events_type
// idx_cms_events_place
// idx_cms_events_recurrence
// cms_events.excerpt
// cms_admin_shortcuts
// uq_admin_shortcut_user_item
// idx_admin_shortcut_user_order
// cms_article_related
// idx_article_related_order
// idx_article_related_target
// cms_blog_series
// uq_blog_series_blog_slug
// idx_blog_series_blog_order
// cms_blog_series_items
// idx_blog_series_items_article
// idx_blog_series_items_order
// cms_categories.slug
// uq_categories_blog_slug
// cms_categories.description
// cms_categories.meta_title
// cms_categories.meta_description
// cms_categories.updated_at
// cms_tags.description
// cms_tags.meta_title
// cms_tags.meta_description
// cms_tags.updated_at
// cms_board_categories.slug
// uq_cms_board_categories_slug
// cms_board_categories.description
// cms_board_categories.meta_title
// cms_board_categories.meta_description
// cms_board_categories.updated_at
// cms_board_publication_events
// idx_board_publication_events_board
// cms_board_subscribers
// uq_board_subscribers_email
// cms_board_subscriber_categories
// cms_contact_topics
// uq_cms_contact_topics_slug
// idx_cms_contact_topics_active_order
// cms_contact.sender_name
// cms_contact.topic_id
// cms_contact.topic_label
// cms_contact.reference_code
// cms_contact.replied_at
// cms_contact.replied_by_user_id
// cms_contact.reply_subject
// cms_contact.reply_body
// idx_cms_contact_reference_code
// idx_cms_contact_topic_status
// cms_dl_categories.slug
// uq_cms_dl_categories_slug
// cms_dl_categories.description
// cms_dl_categories.meta_title
// cms_dl_categories.meta_description
// cms_dl_categories.updated_at
// cms_download_series
// uq_cms_download_series_slug
// idx_cms_download_series_active_order
// cms_downloads.download_series_id
// cms_downloads.is_current_version
// idx_cms_downloads_series_current
// cms_faq_categories.slug
// uq_cms_faq_categories_slug
// cms_faq_categories.description
// cms_faq_categories.meta_title
// cms_faq_categories.meta_description
// cms_faq_categories.updated_at
// cms_faq_feedback
// uq_cms_faq_feedback_visitor
// idx_cms_faq_feedback_faq_vote
// cms_food_sections
// idx_food_sections_card_order
// cms_food_items
// cms_food_items.media_id
// cms_food_items.image_alt_text
// idx_food_items_card_order
// idx_food_items_section_order
// idx_food_items_media
// ft_food_items_search
PHP,
        'blog/index.php' => <<<'PHP'
<?php
$sql = 'SELECT id, title, slug, blog_id, blog_nav_order FROM cms_pages';
koraLog('warning', 'blog index pages query failed', []);
PHP,
        'blog/page.php' => <<<'PHP'
<?php
$sql = 'SELECT p.* FROM cms_pages p INNER JOIN cms_blogs b ON b.id = p.blog_id WHERE p.blog_id = ?';
PHP,
        'admin/content_reference_search.php' => <<<'PHP'
<?php
$sql = 'SELECT id, filename, original_name, alt_text, caption, credit, visibility, mime_type, file_size, folder, created_at FROM cms_media WHERE caption LIKE ?';
contentReferenceLogSourceError('media', $e);
PHP,
        'gallery/photo.php' => <<<'PHP'
<?php
$sql = "SELECT p.* FROM cms_gallery_photos p WHERE " . galleryPhotoPublicVisibilitySql('p', 'a');
PHP,
        'sitemap.php' => <<<'PHP'
<?php
$sql = 'SELECT p.slug FROM cms_gallery_photos p INNER JOIN cms_gallery_albums a ON a.id = p.album_id ORDER BY p.created_at DESC, p.id DESC';
$url = blogCategoryUrl($blog, $category) . blogTagUrl($blog, $tag);
$taxonomySql = 'FROM cms_categories c INNER JOIN cms_tags t ON t.blog_id = c.blog_id';
$boardCategory = boardCategoryUrl($category);
$boardTaxonomySql = 'FROM cms_board_categories c';
sitemapLogSectionError('board_categories', $e);
$downloadCategory = downloadCategoryUrl($category);
$downloadSeries = downloadSeriesUrl($series);
$downloadsTaxonomySql = 'FROM cms_dl_categories c INNER JOIN cms_download_series s ON s.id = 1';
$faqCategory = faqCategoryUrl($category);
$faqCategorySql = 'FROM cms_faq_categories c';
sitemapLogSectionError('faq_categories', $e);
PHP,
        'feed.php' => <<<'PHP'
<?php
$excerpt = articleExcerpt($article);
PHP,
        'db.php' => <<<'PHP'
<?php
require_once __DIR__ . '/lib/presentation.php';
PHP,
    ];
}

/**
 * @param array<string,string> $files
 * @return array{exitCode:int, output:string}
 */
function runSchemaParityAuditWithFixture(array $files): array
{
    global $projectRoot, $schemaParityAuditPath;

    $tempRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'koracms_schema_parity_'
        . bin2hex(random_bytes(6));

    try {
        if (!mkdir($tempRoot, 0777, true) && !is_dir($tempRoot)) {
            schemaParityAuditSelfTestFail('Cannot create temp directory: ' . $tempRoot);
        }

        foreach ($files as $relativePath => $contents) {
            schemaParityAuditSelfTestWriteTextFile(
                $tempRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath),
                $contents
            );
        }

        return runSchemaParityAuditSelfTestCommand(
            [PHP_BINARY, $schemaParityAuditPath, $tempRoot],
            $projectRoot
        );
    } finally {
        schemaParityAuditSelfTestRemoveTree($tempRoot);
    }
}

/**
 * @param array<string,string> $files
 */
function assertSchemaParityAuditPasses(string $label, array $files): void
{
    $result = runSchemaParityAuditWithFixture($files);
    if ($result['exitCode'] !== 0) {
        schemaParityAuditSelfTestFail($label . ' should pass schema parity audit.' . PHP_EOL . $result['output']);
    }
}

/**
 * @param array<string,string> $files
 */
function assertSchemaParityAuditFails(string $label, array $files, string $expectedOutput): void
{
    $result = runSchemaParityAuditWithFixture($files);
    if ($result['exitCode'] === 0) {
        schemaParityAuditSelfTestFail($label . ' should fail schema parity audit.');
    }
    if (!str_contains($result['output'], $expectedOutput)) {
        schemaParityAuditSelfTestFail(
            $label . ' failed for an unexpected reason.'
            . PHP_EOL
            . 'Expected output fragment: ' . $expectedOutput
            . PHP_EOL
            . $result['output']
        );
    }
}

if (!is_file($schemaParityAuditPath)) {
    schemaParityAuditSelfTestFail('Schema parity audit self-test cannot find schema_parity_audit.php.');
}

$validFiles = validSchemaParityFixture();

assertSchemaParityAuditPasses('Clean schema parity fixture', $validFiles);

$missingInstallColumnFiles = $validFiles;
$missingInstallColumnFiles['install.php'] = str_replace("  blog_id INT NULL,\n", '', $missingInstallColumnFiles['install.php']);
assertSchemaParityAuditFails(
    'Fresh install column guard',
    $missingInstallColumnFiles,
    'install.php fresh schema is missing critical column cms_pages.blog_id.'
);

$missingMigrationSnippetFiles = $validFiles;
$missingMigrationSnippetFiles['migrate.php'] = str_replace("// cms_media.caption\n", '', $missingMigrationSnippetFiles['migrate.php']);
assertSchemaParityAuditFails(
    'Migration snippet guard',
    $missingMigrationSnippetFiles,
    'migrate.php upgrade schema is missing critical migration guard cms_media.caption.'
);

$unscopedBlogPageFiles = $validFiles;
$unscopedBlogPageFiles['blog/page.php'] = str_replace(' WHERE p.blog_id = ?', '', $unscopedBlogPageFiles['blog/page.php']);
assertSchemaParityAuditFails(
    'Blog page ownership guard',
    $unscopedBlogPageFiles,
    'blog/page.php must keep blog pages scoped to their owning blog.'
);

$ambiguousSitemapFiles = $validFiles;
$ambiguousSitemapFiles['sitemap.php'] = str_replace('ORDER BY p.created_at DESC, p.id DESC', 'ORDER BY created_at DESC', $ambiguousSitemapFiles['sitemap.php']);
assertSchemaParityAuditFails(
    'Gallery sitemap alias guard',
    $ambiguousSitemapFiles,
    'sitemap.php gallery photos query must keep created_at qualified by alias.'
);

$missingFeedHelperFiles = $validFiles;
$missingFeedHelperFiles['db.php'] = "<?php\n";
assertSchemaParityAuditFails(
    'Feed presentation helper guard',
    $missingFeedHelperFiles,
    'feed.php must keep articleExcerpt() available through db.php presentation helpers.'
);

echo "Schema parity audit self-test OK\n";
