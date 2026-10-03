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
    $files = [
        'install.php' => <<<'PHP'
<?php
CREATE TABLE IF NOT EXISTS cms_rate_limit (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  attempts INT NOT NULL DEFAULT 1,
  window_start DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NULL DEFAULT NULL,
  INDEX idx_rate_limit_expires_at (expires_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_pages (
  id INT,
  slug VARCHAR(255) NOT NULL,
  blog_id INT NULL,
  slug_scope_id INT GENERATED ALWAYS AS (IFNULL(blog_id, 0)) STORED,
  blog_nav_order INT NOT NULL DEFAULT 0,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_pages_scope_slug (slug_scope_id, slug)
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
CREATE TABLE IF NOT EXISTS cms_media_collections (
  id INT,
  name VARCHAR(160),
  slug VARCHAR(180),
  default_visibility VARCHAR(20),
  default_license_url VARCHAR(255)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_media (
  id INT,
  collection_id INT,
  caption VARCHAR(255),
  description TEXT,
  credit VARCHAR(255),
  license_label VARCHAR(120),
  license_url VARCHAR(255),
  visibility VARCHAR(20),
  updated_at DATETIME
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_podcast_shows (
  id INT,
  feed_guid VARCHAR(255),
  UNIQUE KEY uq_podcast_shows_feed_guid (feed_guid)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_podcasts (
  id INT,
  transcript TEXT,
  feed_guid VARCHAR(255),
  audio_mime_type VARCHAR(100),
  audio_file_size BIGINT,
  UNIQUE KEY uq_podcasts_feed_guid (feed_guid)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_podcast_chapters (
  id INT,
  episode_id INT,
  start_time_seconds DECIMAL(12,3),
  title VARCHAR(255),
  UNIQUE KEY uq_podcast_chapter_start (episode_id, start_time_seconds),
  KEY idx_podcast_chapters_episode (episode_id, start_time_seconds, id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_podcast_people (
  id INT,
  show_id INT,
  episode_id INT,
  role_key VARCHAR(50),
  KEY idx_podcast_people_show (show_id, episode_id, sort_order, id),
  KEY idx_podcast_people_episode (episode_id, sort_order, id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_podcast_platform_links (
  id INT,
  show_id INT,
  platform_key VARCHAR(50),
  url VARCHAR(500),
  sort_order INT,
  UNIQUE KEY uq_podcast_platform_show_key (show_id, platform_key),
  KEY idx_podcast_platform_show_order (show_id, sort_order, id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_gallery_albums (
  id INT,
  default_credit VARCHAR(255),
  default_license_label VARCHAR(100),
  default_license_url VARCHAR(255)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_gallery_photos (
  id INT,
  slug VARCHAR(255),
  alt_text VARCHAR(255),
  caption TEXT,
  description TEXT,
  credit VARCHAR(255),
  license_label VARCHAR(100),
  license_url VARCHAR(255),
  taken_at DATE NULL,
  location_label VARCHAR(255),
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
CREATE TABLE IF NOT EXISTS cms_chat_topics (
  id INT,
  slug VARCHAR(150),
  is_active TINYINT(1)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_chat (
  id INT,
  topic_id INT,
  topic_label VARCHAR(255),
  conversation_type VARCHAR(20),
  reference_code VARCHAR(32),
  is_pinned TINYINT(1),
  pinned_until DATETIME,
  replied_body TEXT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_chat_replies (
  id BIGINT,
  chat_id INT,
  status VARCHAR(20)
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
  is_current_version TINYINT(1),
  external_click_count INT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_appmarket_apps (
  id INT,
  slug VARCHAR(150),
  package_id VARCHAR(255) NULL DEFAULT NULL,
  short_description VARCHAR(500),
  icon_media_id INT,
  status VARCHAR(20),
  UNIQUE KEY uq_appmarket_apps_slug (slug),
  UNIQUE KEY uq_appmarket_apps_package (package_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_appmarket_certificates (
  id INT,
  app_id INT,
  fingerprint_sha256 CHAR(64),
  is_active TINYINT(1)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_appmarket_releases (
  id INT,
  app_id INT,
  version_name VARCHAR(100),
  version_code BIGINT,
  platform VARCHAR(32) NOT NULL DEFAULT 'android',
  system_requirements TEXT NULL,
  file_storage_name VARCHAR(255) NOT NULL DEFAULT '',
  file_original_name VARCHAR(255) NOT NULL DEFAULT '',
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  file_sha256 CHAR(64) NOT NULL DEFAULT '',
  file_extension VARCHAR(32) NOT NULL DEFAULT '',
  package_id_snapshot VARCHAR(255),
  apk_storage_name VARCHAR(255),
  apk_original_name VARCHAR(255),
  apk_size BIGINT,
  apk_sha256 CHAR(64),
  certificate_id INT,
  certificate_fingerprint_sha256 CHAR(64),
  permissions_json LONGTEXT,
  supported_abis_json LONGTEXT,
  analysis_json LONGTEXT,
  metadata_source ENUM('apk','publisher_attestation','manual') NOT NULL DEFAULT 'apk',
  publisher_token_id INT,
  update_priority VARCHAR(20),
  required_below_version_code BIGINT,
  release_channel VARCHAR(20),
  rollout_percentage INT,
  download_count BIGINT,
  status VARCHAR(20)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_appmarket_screenshots (
  id INT,
  app_id INT,
  media_id INT,
  alt_text VARCHAR(255)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_appmarket_publish_tokens (
  id INT,
  app_id INT,
  token_hash CHAR(64),
  scopes VARCHAR(255),
  attestation_algorithm VARCHAR(32),
  attestation_public_key TEXT,
  attestation_key_fingerprint CHAR(64),
  expires_at DATETIME,
  revoked_at DATETIME
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
CREATE TABLE IF NOT EXISTS cms_polls (
  id INT,
  question VARCHAR(500),
  vote_mode VARCHAR(20),
  max_choices INT,
  results_visibility VARCHAR(20)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_poll_vote_sessions (
  id INT,
  poll_id INT,
  voter_hash VARCHAR(64)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_poll_votes (
  id INT,
  poll_id INT,
  option_id INT,
  vote_session_id INT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_food_cards (
  id INT,
  orders_enabled TINYINT(1),
  order_email VARCHAR(255),
  order_instructions TEXT,
  order_fulfillment_modes VARCHAR(100),
  order_requested_at_enabled TINYINT(1)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_food_sections (
  id INT,
  card_id INT,
  title VARCHAR(255),
  serving_date DATE,
  serving_time_from TIME,
  serving_time_to TIME,
  serving_note VARCHAR(255),
  sort_order INT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_food_items (
  id INT,
  card_id INT,
  section_id INT,
  title VARCHAR(255),
  price_amount DECIMAL(10,2),
  portion_label VARCHAR(80),
  energy_kj INT,
  energy_kcal INT,
  protein_g DECIMAL(8,2),
  carbs_g DECIMAL(8,2),
  fat_g DECIMAL(8,2),
  salt_g DECIMAL(8,2),
  media_id INT,
  image_alt_text VARCHAR(255),
  allergens VARCHAR(100),
  dietary_flags VARCHAR(255),
  is_available TINYINT(1)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_food_item_variants (
  id INT,
  card_id INT,
  item_id INT,
  label VARCHAR(120),
  portion_label VARCHAR(80),
  price_amount DECIMAL(10,2),
  is_available TINYINT(1),
  sort_order INT,
  UNIQUE KEY uq_food_item_variants_label (item_id, label),
  KEY idx_food_item_variants_order (item_id, sort_order, id),
  KEY idx_food_item_variants_card (card_id, item_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_food_orders (
  id INT,
  card_id INT,
  reference_code VARCHAR(32),
  customer_email VARCHAR(255),
  fulfillment_type VARCHAR(20),
  requested_at DATETIME,
  customer_address TEXT,
  status VARCHAR(20)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_food_order_items (
  id INT,
  order_id INT,
  variant_id INT,
  item_title VARCHAR(255),
  variant_label VARCHAR(120),
  portion_label VARCHAR(80),
  quantity INT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_recipe_categories (
  id INT,
  slug VARCHAR(180),
  meta_title VARCHAR(160),
  is_active TINYINT(1)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_recipes (
  id INT,
  category_id INT,
  slug VARCHAR(180),
  dietary_flags VARCHAR(255),
  allergens VARCHAR(255),
  media_id INT,
  status VARCHAR(20)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_recipe_ingredient_groups (
  id INT,
  recipe_id INT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_recipe_ingredients (
  id INT,
  recipe_id INT,
  group_id INT,
  quantity_min DECIMAL(12,4),
  quantity_max DECIMAL(12,4),
  name VARCHAR(255)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_recipe_steps (
  id INT,
  recipe_id INT,
  instruction TEXT,
  media_id INT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_recipe_structure_snapshots (
  id BIGINT,
  recipe_id INT,
  snapshot_json LONGTEXT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_res_resources (
  id INT,
  reminders_enabled TINYINT(1),
  reminder_hours_before INT,
  reminder_message TEXT,
  calendar_invite_enabled TINYINT(1)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_res_bookings (
  id INT,
  calendar_token CHAR(32),
  reminder_sent_at DATETIME,
  reminder_last_error TEXT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_res_booking_events (
  id INT,
  booking_id INT,
  event_type VARCHAR(40),
  description TEXT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_stats_content_daily (
  id INT,
  stat_date DATE,
  page_type VARCHAR(50),
  page_ref_id INT,
  normalized_path VARCHAR(500),
  path_hash CHAR(64),
  module_key VARCHAR(50),
  title_snapshot VARCHAR(255),
  total_views INT,
  unique_visitors INT
) ENGINE=InnoDB;
PHP,
        'migrate.php' => <<<'PHP'
<?php
CREATE TABLE IF NOT EXISTS cms_rate_limit (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  attempts INT NOT NULL DEFAULT 1,
  window_start DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NULL DEFAULT NULL,
  INDEX idx_rate_limit_expires_at (expires_at)
) ENGINE=InnoDB;
$addColumns = [
    'cms_rate_limit.expires_at' => "ALTER TABLE cms_rate_limit ADD COLUMN expires_at DATETIME NULL DEFAULT NULL",
];
if ($columnExists('cms_rate_limit', 'expires_at')) {
    $pdo->exec(
        "UPDATE cms_rate_limit SET expires_at = DATE_ADD(window_start, INTERVAL 7 DAY) WHERE expires_at IS NULL"
    );
}
if (!$indexExists('cms_rate_limit', 'idx_rate_limit_expires_at')) {
    $pdo->exec("ALTER TABLE cms_rate_limit ADD INDEX idx_rate_limit_expires_at (expires_at)");
}
// cms_pages.blog_id
// cms_pages.slug_scope_id
// cms_pages.blog_nav_order
// uq_pages_scope_slug
// DROP INDEX slug
// DROP INDEX uq_cms_pages_slug
// idx_pages_blog_nav
// cms_nav_links
// idx_nav_links_scope
// idx_nav_links_active
// cms_media_collections
// uq_media_collections_slug
// idx_media_collections_order
// cms_media.collection_id
// cms_media.caption
// cms_media.description
// cms_media.credit
// cms_media.license_label
// cms_media.license_url
// cms_media.visibility
// cms_media.updated_at
// idx_media_visibility
// idx_media_collection
// cms_podcasts.transcript
// cms_podcast_shows.feed_guid
// cms_podcasts.feed_guid
// cms_podcasts.audio_mime_type
// cms_podcasts.audio_file_size
// uq_podcast_shows_feed_guid
// uq_podcasts_feed_guid
// cms_podcast_chapters
// uq_podcast_chapter_start
// idx_podcast_chapters_episode
// cms_podcast_people
// idx_podcast_people_show
// idx_podcast_people_episode
// cms_podcast_platform_links
// uq_podcast_platform_show_key
// idx_podcast_platform_show_order
// cms_gallery_photos.slug
// cms_gallery_albums.default_credit
// cms_gallery_albums.default_license_label
// cms_gallery_albums.default_license_url
// cms_gallery_photos.alt_text
// cms_gallery_photos.caption
// cms_gallery_photos.description
// cms_gallery_photos.credit
// cms_gallery_photos.license_label
// cms_gallery_photos.license_url
// cms_gallery_photos.taken_at
// cms_gallery_photos.location_label
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
// cms_chat_topics
// uq_cms_chat_topics_slug
// idx_cms_chat_topics_active_order
// cms_chat.topic_id
// cms_chat.conversation_type
// cms_chat.reference_code
// idx_cms_chat_public
// idx_cms_chat_reference
// cms_chat_replies
// idx_cms_chat_replies_chat_status
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
// cms_downloads.external_click_count
// idx_cms_downloads_series_current
// cms_appmarket_apps
// uq_appmarket_apps_slug
// uq_appmarket_apps_package
// idx_appmarket_apps_public
// cms_appmarket_certificates
// uq_appmarket_certificate_fingerprint
// idx_appmarket_certificates_active
// cms_appmarket_releases
// cms_appmarket_releases.platform
// cms_appmarket_releases.system_requirements
// cms_appmarket_releases.file_storage_name
// cms_appmarket_releases.file_original_name
// cms_appmarket_releases.file_size
// cms_appmarket_releases.file_sha256
// cms_appmarket_releases.file_extension
CREATE TABLE IF NOT EXISTS cms_appmarket_apps (
  id INT,
  slug VARCHAR(150),
  package_id VARCHAR(255) NULL DEFAULT NULL,
  UNIQUE KEY uq_appmarket_apps_slug (slug),
  UNIQUE KEY uq_appmarket_apps_package (package_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cms_appmarket_releases (
  id INT,
  platform VARCHAR(32) NOT NULL DEFAULT 'android',
  system_requirements TEXT NULL,
  file_storage_name VARCHAR(255) NOT NULL DEFAULT '',
  file_original_name VARCHAR(255) NOT NULL DEFAULT '',
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  file_sha256 CHAR(64) NOT NULL DEFAULT '',
  file_extension VARCHAR(32) NOT NULL DEFAULT '',
  package_id_snapshot VARCHAR(255),
  apk_storage_name VARCHAR(255),
  apk_original_name VARCHAR(255),
  apk_size BIGINT,
  apk_sha256 CHAR(64),
  metadata_source ENUM('apk','publisher_attestation','manual') NOT NULL DEFAULT 'apk',
  status VARCHAR(20)
) ENGINE=InnoDB;
ALTER TABLE cms_appmarket_apps MODIFY COLUMN package_id VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE cms_appmarket_releases ADD COLUMN platform VARCHAR(32) NOT NULL DEFAULT 'android';
ALTER TABLE cms_appmarket_releases ADD COLUMN system_requirements TEXT NULL;
ALTER TABLE cms_appmarket_releases ADD COLUMN file_storage_name VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE cms_appmarket_releases ADD COLUMN file_original_name VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE cms_appmarket_releases ADD COLUMN file_size BIGINT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE cms_appmarket_releases ADD COLUMN file_sha256 CHAR(64) NOT NULL DEFAULT '';
ALTER TABLE cms_appmarket_releases ADD COLUMN file_extension VARCHAR(32) NOT NULL DEFAULT '';
ALTER TABLE cms_appmarket_releases MODIFY COLUMN metadata_source ENUM('apk','publisher_attestation','manual') NOT NULL DEFAULT 'apk';
// uq_appmarket_release_version
// idx_appmarket_releases_public
// idx_appmarket_releases_compatible
// idx_appmarket_releases_distribution
// idx_appmarket_releases_publisher_token
// cms_appmarket_screenshots
// uq_appmarket_screenshot_media
// idx_appmarket_screenshots_order
// cms_appmarket_publish_tokens
// uq_appmarket_publish_token_hash
// idx_appmarket_publish_tokens_active
// idx_appmarket_publish_tokens_attestation
// cms_faq_categories.slug
// uq_cms_faq_categories_slug
// cms_faq_categories.description
// cms_faq_categories.meta_title
// cms_faq_categories.meta_description
// cms_faq_categories.updated_at
// cms_faq_feedback
// uq_cms_faq_feedback_visitor
// idx_cms_faq_feedback_faq_vote
// cms_polls.vote_mode
// cms_polls.max_choices
// cms_polls.results_visibility
// cms_poll_vote_sessions
// uq_poll_vote_session
// idx_poll_vote_sessions_poll
// cms_poll_votes.vote_session_id
// idx_poll_votes_session
// idx_poll_votes_poll_option
// uq_poll_vote_option_hash
// DROP INDEX uq_poll_ip
// cms_food_cards.orders_enabled
// cms_food_cards.order_email
// cms_food_cards.order_instructions
// cms_food_cards.order_fulfillment_modes
// cms_food_cards.order_requested_at_enabled
// cms_food_sections
// cms_food_sections.serving_date
// cms_food_sections.serving_time_from
// cms_food_sections.serving_time_to
// cms_food_sections.serving_note
// idx_food_sections_card_order
// cms_food_items
// cms_food_items.portion_label
// cms_food_items.energy_kj
// cms_food_items.energy_kcal
// cms_food_items.protein_g
// cms_food_items.carbs_g
// cms_food_items.fat_g
// cms_food_items.salt_g
// cms_food_items.media_id
// cms_food_items.image_alt_text
// idx_food_items_card_order
// idx_food_items_section_order
// idx_food_items_media
// ft_food_items_search
// cms_food_item_variants
// uq_food_item_variants_label
// idx_food_item_variants_order
// idx_food_item_variants_card
// cms_food_orders
// cms_food_orders.fulfillment_type
// cms_food_orders.requested_at
// cms_food_orders.customer_address
// uq_food_orders_reference
// idx_food_orders_card_status
// cms_food_order_items
// idx_food_order_items_order
// idx_food_order_items_item
// cms_food_order_items.variant_id
// cms_food_order_items.variant_label
// cms_food_order_items.portion_label
// idx_food_order_items_variant
// cms_recipe_categories
// uq_recipe_categories_slug
// idx_recipe_categories_public
// cms_recipes
// uq_recipes_slug
// idx_recipes_public
// idx_recipes_category
// idx_recipes_media
// ft_recipes_search
// cms_recipe_ingredient_groups
// idx_recipe_ingredient_groups_order
// cms_recipe_ingredients
// idx_recipe_ingredients_order
// cms_recipe_steps
// idx_recipe_steps_order
// idx_recipe_steps_media
// cms_recipe_structure_snapshots
// idx_recipe_structure_snapshots_recipe
// cms_res_resources.reminders_enabled
// cms_res_resources.reminder_hours_before
// cms_res_resources.reminder_message
// cms_res_resources.calendar_invite_enabled
// cms_res_bookings.calendar_token
// cms_res_bookings.reminder_sent_at
// cms_res_bookings.reminder_last_error
// cms_res_booking_events
// uq_res_calendar_token
// idx_res_reminders
// idx_res_booking_events_booking
// idx_res_booking_events_type
// cms_stats_content_daily
// uq_stats_content_daily
// idx_stats_content_module_date
// idx_stats_content_path_hash
PHP,
        'cron.php' => <<<'PHP'
<?php
$statement = $pdo->prepare("DELETE FROM cms_rate_limit WHERE expires_at <= NOW()");
$statement->execute();
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
$sql = 'SELECT m.id, m.filename, m.original_name, m.alt_text, m.caption, m.description, m.credit, m.license_label, m.license_url, m.visibility, m.mime_type, m.file_size, m.folder, m.created_at, c.name AS collection_name FROM cms_media m LEFT JOIN cms_media_collections c ON c.id = m.collection_id WHERE m.visibility = \'public\' AND m.caption LIKE ?';
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
    $shopFiles = validShopSchemaParityFixture();
    $files['install.php'] .= "\n" . $shopFiles['install.php'];
    $files['migrate.php'] .= "\n" . $shopFiles['migrate.php'];
    $files['lib/shop.php'] = $shopFiles['lib/shop.php'];
    return $files;
}

/**
 * Independent contract data: never include production helpers or connect to DB.
 * @return array<string,string>
 */
function validShopSchemaParityTables(): array
{
    return [
        'cms_shop_categories' => "CREATE TABLE IF NOT EXISTS cms_shop_categories (
            id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, slug VARCHAR(150) NOT NULL,
            description TEXT, is_active TINYINT NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0,
            UNIQUE KEY uq_shop_category_slug (slug)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'cms_shop_products' => "CREATE TABLE IF NOT EXISTS cms_shop_products (
            id INT AUTO_INCREMENT PRIMARY KEY, category_id INT NOT NULL, title VARCHAR(255) NOT NULL,
            slug VARCHAR(150) NOT NULL, description TEXT, requirements TEXT, license_text TEXT, update_policy TEXT,
            price_cents BIGINT NOT NULL, tax_class VARCHAR(20) NOT NULL DEFAULT 'general',
            file_storage_name VARCHAR(80) NOT NULL DEFAULT '', file_original_name VARCHAR(255) NOT NULL DEFAULT '',
            file_size BIGINT NOT NULL DEFAULT 0, file_sha256 VARCHAR(64) NOT NULL DEFAULT '',
            is_active TINYINT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_shop_product_slug (slug), INDEX idx_shop_product_category (category_id,is_active)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'cms_shop_payment_methods' => "CREATE TABLE IF NOT EXISTS cms_shop_payment_methods (
            id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, account_number VARCHAR(100) NOT NULL,
            iban VARCHAR(34) NOT NULL, is_active TINYINT NOT NULL DEFAULT 1,
            fio_token_encrypted TEXT, fio_last_polled_at DATETIME NULL, fio_last_error VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'cms_shop_tax_rules' => "CREATE TABLE IF NOT EXISTS cms_shop_tax_rules (
            id INT AUTO_INCREMENT PRIMARY KEY, country_code CHAR(2) NOT NULL, country_name VARCHAR(100) NOT NULL,
            general_rate_bp INT NOT NULL DEFAULT 0, publication_rate_bp INT NOT NULL DEFAULT 0,
            tax_note TEXT, is_active TINYINT NOT NULL DEFAULT 0,
            UNIQUE KEY uq_shop_tax_country (country_code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'cms_shop_sequences' => "CREATE TABLE IF NOT EXISTS cms_shop_sequences (
            sequence_key VARCHAR(40) PRIMARY KEY, sequence_value INT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'cms_shop_orders' => "CREATE TABLE IF NOT EXISTS cms_shop_orders (
            id INT AUTO_INCREMENT PRIMARY KEY, order_number VARCHAR(10) NOT NULL, user_id INT NULL,
            status ENUM('accepted','awaiting_payment','paid','fulfilled','cancelled','refunded') NOT NULL,
            customer_name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, address VARCHAR(255) NOT NULL,
            city VARCHAR(150) NOT NULL, postal_code VARCHAR(30) NOT NULL, country_code CHAR(2) NOT NULL,
            payment_method_id INT NOT NULL, total_cents BIGINT NOT NULL, tax_cents BIGINT NOT NULL,
            currency CHAR(3) NOT NULL DEFAULT 'CZK', seller_snapshot MEDIUMTEXT NOT NULL,
            legal_snapshot MEDIUMTEXT NOT NULL, payment_snapshot TEXT NOT NULL,
            token_hash CHAR(64) NOT NULL, token_encrypted TEXT NOT NULL, token_expires_at DATETIME NOT NULL,
            consent_at DATETIME NOT NULL, tax_verified_at DATETIME NULL, tax_evidence TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, paid_at DATETIME NULL, fulfilled_at DATETIME NULL,
            cancelled_at DATETIME NULL, confirmation_sent_at DATETIME NULL, delivery_sent_at DATETIME NULL,
            mail_claim_until DATETIME NULL, mail_attempts INT NOT NULL DEFAULT 0, mail_retry_at DATETIME NULL,
            mail_last_error VARCHAR(255) NOT NULL DEFAULT '',
            mail_sent_status VARCHAR(24) NOT NULL DEFAULT '', mail_claim_token CHAR(64) NULL,
            UNIQUE KEY uq_shop_order_number (order_number), UNIQUE KEY uq_shop_order_token (token_hash),
            INDEX idx_shop_orders_user (user_id,created_at), INDEX idx_shop_orders_status (status,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'cms_shop_order_items' => "CREATE TABLE IF NOT EXISTS cms_shop_order_items (
            id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, product_id INT NOT NULL, title VARCHAR(255) NOT NULL,
            quantity INT NOT NULL, unit_price_cents BIGINT NOT NULL, total_cents BIGINT NOT NULL,
            tax_rate_bp INT NOT NULL, tax_cents BIGINT NOT NULL, file_storage_name VARCHAR(80) NOT NULL,
            file_original_name VARCHAR(255) NOT NULL, file_size BIGINT NOT NULL, file_sha256 CHAR(64) NOT NULL,
            product_snapshot MEDIUMTEXT NOT NULL,
            INDEX idx_shop_items_order (order_id,id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'cms_shop_payments' => "CREATE TABLE IF NOT EXISTS cms_shop_payments (
            id INT AUTO_INCREMENT PRIMARY KEY, payment_method_id INT NOT NULL, bank_transaction_id VARCHAR(100) NOT NULL,
            order_id INT NOT NULL, amount_cents BIGINT NOT NULL, currency CHAR(3) NOT NULL, received_at DATETIME NOT NULL,
            UNIQUE KEY uq_shop_payment_movement (payment_method_id,bank_transaction_id),
            UNIQUE KEY uq_shop_payment_order (order_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'cms_shop_invoices' => "CREATE TABLE IF NOT EXISTS cms_shop_invoices (
            id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, kind ENUM('proforma','final','credit') NOT NULL,
            invoice_number VARCHAR(40) NOT NULL, snapshot_json MEDIUMTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_shop_invoice_kind (order_id,kind), UNIQUE KEY uq_shop_invoice_number (invoice_number)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'cms_shop_order_events' => "CREATE TABLE IF NOT EXISTS cms_shop_order_events (
            id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, event_type VARCHAR(40) NOT NULL,
            note TEXT, user_id INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_shop_events_order (order_id,id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
}

/** @return array<string,string> */
function validShopSchemaParityFixture(): array
{
    $suffix = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    $tableSource = '$shopTables = [' . "\n";
    $helperSource = "<?php\nthrow new RuntimeException('Shop fixture must never execute.');\n"
        . "function shopSchema(): array\n{\n"
        . "    \$suffix = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';\n    return [\n";
    foreach (validShopSchemaParityTables() as $tableName => $sql) {
        $tableSource .= "    '" . $tableName . "' => \"" . $sql . "\",\n";
        $helperSource .= "        '" . $tableName . "' => \"" . substr($sql, 0, -strlen($suffix)) . '" . $suffix,' . "\n";
    }
    $tableSource .= "];\n";
    $helperSource .= "    ];\n}\n";
    $upgradeSource = <<<'PHP'
$addColumns = [
    'cms_shop_order_items.product_snapshot' => "ALTER TABLE cms_shop_order_items ADD COLUMN product_snapshot MEDIUMTEXT NOT NULL",
    'cms_shop_orders.mail_sent_status' => "ALTER TABLE cms_shop_orders ADD COLUMN mail_sent_status VARCHAR(24) NOT NULL DEFAULT ''",
    'cms_shop_orders.mail_claim_token' => "ALTER TABLE cms_shop_orders ADD COLUMN mail_claim_token CHAR(64) NULL",
];
$shopSnapshotTypeStmt = $pdo->prepare(
    "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_shop_order_items' AND COLUMN_NAME = 'product_snapshot'"
);
$shopSnapshotTypeStmt->execute();
if ($shopSnapshotTypeStmt->fetchColumn() === 'text') {
    $pdo->exec("ALTER TABLE cms_shop_order_items MODIFY COLUMN product_snapshot MEDIUMTEXT NOT NULL");
}
PHP;
    return [
        'install.php' => $tableSource,
        'migrate.php' => $tableSource . $upgradeSource,
        'lib/shop.php' => $helperSource,
    ];
}

/** @param callable(string):string $mutation */
function mutateShopSchemaParityTable(string $source, string $tableName, callable $mutation): string
{
    $mutatedSource = preg_replace_callback(
        '/"CREATE TABLE IF NOT EXISTS ' . preg_quote($tableName, '/') . ' \([^"]*"/',
        static fn (array $matches): string => $mutation($matches[0]),
        $source,
        1,
        $count
    );
    if (!is_string($mutatedSource) || $count !== 1 || $mutatedSource === $source) {
        schemaParityAuditSelfTestFail('Shop mutation did not change exactly one DDL literal: ' . $tableName);
    }
    return $mutatedSource;
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

$shopTables = validShopSchemaParityTables();
if (count($shopTables) !== 10) {
    schemaParityAuditSelfTestFail('Shop contract fixture must contain exactly ten tables.');
}
$shopMutationCount = 0;
foreach (['install.php', 'migrate.php', 'lib/shop.php'] as $sourceName) {
    foreach ($shopTables as $tableName => $sql) {
        $mutatedFiles = $validFiles;
        $mutatedFiles[$sourceName] = mutateShopSchemaParityTable(
            $mutatedFiles[$sourceName],
            $tableName,
            static fn (string $literal): string => ''
        );
        assertSchemaParityAuditFails(
            'Shop missing table ' . $sourceName . ' ' . $tableName,
            $mutatedFiles,
            $sourceName . ' is missing the shop table ' . $tableName . '.'
        );
        $shopMutationCount++;

        preg_match_all('/(?:\(\s*|,\s*)([a-z_]+)\s+[A-Z]+\b/', $sql, $columnMatches);
        if ($columnMatches[1] === []) {
            schemaParityAuditSelfTestFail('Shop fixture has no column definitions: ' . $tableName);
        }
        foreach ($columnMatches[1] as $columnName) {
            $mutatedFiles = $validFiles;
            $mutatedFiles[$sourceName] = mutateShopSchemaParityTable(
                $mutatedFiles[$sourceName],
                $tableName,
                static fn (string $literal): string => preg_replace(
                    '/\b' . preg_quote($columnName, '/') . '(?=\s+[A-Z]+\b)/',
                    $columnName . '_missing',
                    $literal,
                    1
                ) ?? ''
            );
            assertSchemaParityAuditFails(
                'Shop required column ' . $sourceName . ' ' . $tableName . '.' . $columnName,
                $mutatedFiles,
                $sourceName . ' shop schema is missing required column ' . $tableName . '.' . $columnName . '.'
            );
            $shopMutationCount++;
        }

        preg_match_all('/(?:UNIQUE KEY|INDEX) [a-z_]+ \([a-z_,]+\)/', $sql, $indexMatches);
        foreach ($indexMatches[0] as $index) {
            foreach (['', preg_replace('/\([^)]*\)$/', '(missing_column)', $index) ?? ''] as $replacement) {
                $mutatedFiles = $validFiles;
                $mutatedFiles[$sourceName] = mutateShopSchemaParityTable(
                    $mutatedFiles[$sourceName],
                    $tableName,
                    static fn (string $literal): string => str_replace($index, $replacement, $literal)
                );
                assertSchemaParityAuditFails(
                    'Shop required index ' . $sourceName . ' ' . $tableName . ' ' . $index,
                    $mutatedFiles,
                    $sourceName . ' shop schema is missing required index ' . $tableName . ': ' . $index . '.'
                );
                $shopMutationCount++;
            }
        }
    }
    foreach ([
        ['cms_shop_sequences', 'sequence_key VARCHAR(40) PRIMARY KEY', 'sequence_key VARCHAR(40)'],
        ['cms_shop_orders', "status ENUM('accepted','awaiting_payment','paid','fulfilled','cancelled','refunded') NOT NULL", "status ENUM('accepted','paid') NOT NULL"],
        ['cms_shop_orders', 'total_cents BIGINT NOT NULL', 'total_cents DECIMAL(12,2) NOT NULL'],
        ['cms_shop_orders', 'tax_cents BIGINT NOT NULL', 'tax_cents FLOAT NOT NULL'],
        ['cms_shop_orders', "currency CHAR(3) NOT NULL DEFAULT 'CZK'", "currency CHAR(3) NOT NULL DEFAULT 'EUR'"],
        ['cms_shop_orders', 'token_hash CHAR(64) NOT NULL', 'token_hash CHAR(64) NULL'],
        ['cms_shop_orders', "mail_sent_status VARCHAR(24) NOT NULL DEFAULT ''", "mail_sent_status VARCHAR(24) NOT NULL DEFAULT 'paid'"],
        ['cms_shop_orders', 'mail_claim_token CHAR(64) NULL', 'mail_claim_token CHAR(64) NOT NULL'],
        ['cms_shop_order_items', 'product_snapshot MEDIUMTEXT NOT NULL', 'product_snapshot MEDIUMTEXT NULL'],
        ['cms_shop_order_items', 'product_snapshot MEDIUMTEXT NOT NULL', 'product_snapshot TEXT NOT NULL'],
        ['cms_shop_invoices', "kind ENUM('proforma','final','credit') NOT NULL", "kind ENUM('proforma','final') NOT NULL"],
        ['cms_shop_invoices', 'snapshot_json MEDIUMTEXT NOT NULL', 'snapshot_json TEXT NOT NULL'],
    ] as [$tableName, $definition, $replacement]) {
        $mutatedFiles = $validFiles;
        $mutatedFiles[$sourceName] = mutateShopSchemaParityTable(
            $mutatedFiles[$sourceName],
            $tableName,
            static fn (string $literal): string => str_replace($definition, $replacement, $literal)
        );
        assertSchemaParityAuditFails(
            'Shop critical definition ' . $sourceName . ' ' . $definition,
            $mutatedFiles,
            $sourceName . ' shop schema has an incompatible definition for ' . $tableName . ': ' . $definition . '.'
        );
        $shopMutationCount++;
    }
    foreach ([
        ['id INT AUTO_INCREMENT PRIMARY KEY', 'id INT PRIMARY KEY', ' must retain its auto-increment primary key.'],
        ['user_id INT NULL,', 'user_id INT NULL, FOREIGN KEY (user_id) REFERENCES cms_users(id),', ' must use InnoDB/utf8mb4 without foreign keys.'],
        ['user_id INT NULL,', 'user_id INT NULL REFERENCES cms_users(id),', ' must use InnoDB/utf8mb4 without foreign keys.'],
    ] as [$original, $replacement, $expectedOutput]) {
        $mutatedFiles = $validFiles;
        $mutatedFiles[$sourceName] = mutateShopSchemaParityTable(
            $mutatedFiles[$sourceName],
            'cms_shop_orders',
            static fn (string $literal): string => str_replace($original, $replacement, $literal)
        );
        assertSchemaParityAuditFails(
            'Shop primary key/foreign key contract ' . $sourceName,
            $mutatedFiles,
            $sourceName . ' shop table cms_shop_orders' . $expectedOutput
        );
        $shopMutationCount++;
    }

    if ($sourceName !== 'lib/shop.php') {
        foreach (['ENGINE=MyISAM DEFAULT CHARSET=utf8mb4', 'ENGINE=InnoDB DEFAULT CHARSET=latin1'] as $replacement) {
            $mutatedFiles = $validFiles;
            $mutatedFiles[$sourceName] = mutateShopSchemaParityTable(
                $mutatedFiles[$sourceName],
                'cms_shop_orders',
                static fn (string $literal): string => str_replace('ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', $replacement, $literal)
            );
            assertSchemaParityAuditFails(
                'Shop engine/charset contract ' . $sourceName,
                $mutatedFiles,
                $sourceName . ' shop table cms_shop_orders must use InnoDB/utf8mb4 without foreign keys.'
            );
            $shopMutationCount++;
        }
    }

    $mutatedFiles = $validFiles;
    $mutatedFiles[$sourceName] = mutateShopSchemaParityTable(
        $mutatedFiles[$sourceName],
        'cms_shop_products',
        static fn (string $literal): string => str_replace('title VARCHAR(255)', 'title VARCHAR(100)', $literal)
    );
    $paritySourceName = $sourceName === 'lib/shop.php' ? 'install.php' : $sourceName;
    assertSchemaParityAuditFails(
        'Shop complete DDL parity ' . $sourceName,
        $mutatedFiles,
        $paritySourceName . ' shop DDL must match lib/shop.php shopSchema() for cms_shop_products.'
    );
    $shopMutationCount++;

    $mutatedFiles = $validFiles;
    $mutatedFiles[$sourceName] .= "\n" . '$unexpected = "CREATE TABLE IF NOT EXISTS cms_shop_unexpected (id INT)'
        . ($sourceName === 'lib/shop.php' ? '" . $suffix;' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";');
    assertSchemaParityAuditFails(
        'Shop complete table set ' . $sourceName,
        $mutatedFiles,
        $paritySourceName . ' shop table set must match lib/shop.php shopSchema().'
    );
    $shopMutationCount++;
}

foreach ([
    'cms_shop_order_items.product_snapshot' => 'MEDIUMTEXT NOT NULL',
    'cms_shop_orders.mail_sent_status' => "VARCHAR(24) NOT NULL DEFAULT ''",
    'cms_shop_orders.mail_claim_token' => 'CHAR(64) NULL',
] as $columnLabel => $definition) {
    [$tableName, $columnName] = explode('.', $columnLabel, 2);
    foreach ([
        ["'" . $columnLabel . "' =>", "'" . $columnLabel . "_missing' =>"],
        ['ALTER TABLE ' . $tableName . ' ADD COLUMN ' . $columnName . ' ' . $definition, 'SELECT 1'],
    ] as [$original, $replacement]) {
        $mutatedFiles = $validFiles;
        $mutatedFiles['migrate.php'] = str_replace($original, $replacement, $mutatedFiles['migrate.php']);
        assertSchemaParityAuditFails(
            'Shop existing installation upgrade ' . $columnLabel,
            $mutatedFiles,
            'migrate.php must register the shop upgrade for ' . $columnLabel . '.'
        );
        $shopMutationCount++;
    }
}

foreach ([
    ['SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS', 'SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS'],
    ["TABLE_NAME = 'cms_shop_order_items' AND COLUMN_NAME = 'product_snapshot'", "TABLE_NAME = 'cms_shop_orders' AND COLUMN_NAME = 'product_snapshot'"],
    ["COLUMN_NAME = 'product_snapshot'", "COLUMN_NAME = 'title'"],
    ['$shopSnapshotTypeStmt->execute();', ''],
    ["\$shopSnapshotTypeStmt->fetchColumn() === 'text'", 'true'],
    ['ALTER TABLE cms_shop_order_items MODIFY COLUMN product_snapshot MEDIUMTEXT NOT NULL', ''],
    ['ALTER TABLE cms_shop_order_items MODIFY COLUMN product_snapshot MEDIUMTEXT NOT NULL', 'ALTER TABLE cms_shop_order_items MODIFY COLUMN product_snapshot TEXT NOT NULL'],
] as [$original, $replacement]) {
    $mutatedFiles = $validFiles;
    $mutatedFiles['migrate.php'] = str_replace($original, $replacement, $mutatedFiles['migrate.php'], $count);
    if ($count !== 1) {
        schemaParityAuditSelfTestFail('Shop snapshot widening mutation must change exactly one upgrade guard.');
    }
    assertSchemaParityAuditFails(
        'Shop idempotent legacy snapshot widening',
        $mutatedFiles,
        'migrate.php must idempotently widen legacy TEXT product snapshots to MEDIUMTEXT NOT NULL.'
    );
    $shopMutationCount++;
}

$missingShopHelperFiles = $validFiles;
unset($missingShopHelperFiles['lib/shop.php']);
assertSchemaParityAuditFails('Shop helper source required', $missingShopHelperFiles, 'lib/shop.php is missing.');
$shopMutationCount++;

$wrongShopSuffixFiles = $validFiles;
$wrongShopSuffixFiles['lib/shop.php'] = str_replace('ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', 'ENGINE=MyISAM DEFAULT CHARSET=latin1', $wrongShopSuffixFiles['lib/shop.php']);
assertSchemaParityAuditFails('Shop helper suffix required', $wrongShopSuffixFiles, 'lib/shop.php shopSchema() must use the InnoDB/utf8mb4 suffix.');
$shopMutationCount++;

$shopCommentsAndWhitespaceFiles = $validFiles;
foreach (['install.php', 'migrate.php', 'lib/shop.php'] as $sourceName) {
    $shopCommentsAndWhitespaceFiles[$sourceName] = mutateShopSchemaParityTable(
        $shopCommentsAndWhitespaceFiles[$sourceName],
        'cms_shop_orders',
        static fn (string $literal): string => str_replace('id INT', 'id     INT', $literal)
    );
    $shopCommentsAndWhitespaceFiles[$sourceName] .= "\n" . '// "CREATE TABLE IF NOT EXISTS cms_shop_commented (id INT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";';
}
assertSchemaParityAuditPasses('Shop DDL whitespace and commented-out tables ignored', $shopCommentsAndWhitespaceFiles);

$rateLimitMutationCount = 0;
foreach (['install.php', 'migrate.php'] as $sourceName) {
    foreach (['', '  expires_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'] as $replacement) {
        $mutatedFiles = $validFiles;
        $mutatedFiles[$sourceName] = str_replace(
            '  expires_at DATETIME NULL DEFAULT NULL,',
            $replacement,
            $mutatedFiles[$sourceName]
        );
        assertSchemaParityAuditFails(
            'Rate-limit nullable expiry definition in ' . $sourceName,
            $mutatedFiles,
            $sourceName . ' must define cms_rate_limit.expires_at as DATETIME NULL DEFAULT NULL.'
        );
        $rateLimitMutationCount++;
    }
    foreach (['', 'INDEX idx_rate_limit_expires_at (window_start)'] as $replacement) {
        $mutatedFiles = $validFiles;
        $mutatedFiles[$sourceName] = str_replace(
            'INDEX idx_rate_limit_expires_at (expires_at)',
            $replacement,
            $mutatedFiles[$sourceName]
        );
        assertSchemaParityAuditFails(
            'Rate-limit expiry index in ' . $sourceName,
            $mutatedFiles,
            $sourceName . ' must index cms_rate_limit.expires_at with idx_rate_limit_expires_at.'
        );
        $rateLimitMutationCount++;
    }
}
$rateLimitUpgradeMutations = [
    [
        'ALTER TABLE cms_rate_limit ADD COLUMN expires_at DATETIME NULL DEFAULT NULL',
        'ALTER TABLE cms_rate_limit ADD COLUMN expires_at DATETIME NOT NULL',
        'migrate.php must register the nullable cms_rate_limit.expires_at upgrade.',
    ],
    [
        "'cms_rate_limit.expires_at' =>",
        "'cms_rate_limit.expiry' =>",
        'migrate.php must register the nullable cms_rate_limit.expires_at upgrade.',
    ],
    [
        'ALTER TABLE cms_rate_limit ADD INDEX idx_rate_limit_expires_at (expires_at)',
        '',
        'migrate.php must idempotently add idx_rate_limit_expires_at to existing installations.',
    ],
    [
        '!$indexExists(\'cms_rate_limit\', \'idx_rate_limit_expires_at\')',
        'true',
        'migrate.php must idempotently add idx_rate_limit_expires_at to existing installations.',
    ],
];
foreach ($rateLimitUpgradeMutations as [$original, $replacement, $expectedOutput]) {
    $mutatedFiles = $validFiles;
    $mutatedFiles['migrate.php'] = str_replace($original, $replacement, $mutatedFiles['migrate.php']);
    assertSchemaParityAuditFails('Rate-limit upgrade guard', $mutatedFiles, $expectedOutput);
    $rateLimitMutationCount++;
}
foreach ([
    ['DATE_ADD(window_start, INTERVAL 7 DAY)', 'DATE_ADD(NOW(), INTERVAL 7 DAY)'],
    ['INTERVAL 7 DAY', 'INTERVAL 1 HOUR'],
    ['WHERE expires_at IS NULL', ''],
    ['WHERE expires_at IS NULL', 'WHERE expires_at IS NOT NULL'],
    ['UPDATE cms_rate_limit', 'UPDATE cms_other_table'],
    ['$columnExists(\'cms_rate_limit\', \'expires_at\')', 'true'],
] as [$original, $replacement]) {
    $mutatedFiles = $validFiles;
    $mutatedFiles['migrate.php'] = str_replace($original, $replacement, $mutatedFiles['migrate.php']);
    assertSchemaParityAuditFails(
        'Rate-limit conservative legacy backfill',
        $mutatedFiles,
        'migrate.php must preserve rate-limit rows and backfill only unknown expiries from window_start plus 7 days.'
    );
    $rateLimitMutationCount++;
}
foreach (['DELETE FROM cms_rate_limit', 'TRUNCATE TABLE cms_rate_limit'] as $destructiveSql) {
    $mutatedFiles = $validFiles;
    $mutatedFiles['migrate.php'] .= "\n" . '$pdo->exec("' . $destructiveSql . '");';
    assertSchemaParityAuditFails(
        'Rate-limit migration must not reset counters',
        $mutatedFiles,
        'migrate.php must preserve rate-limit rows and backfill only unknown expiries from window_start plus 7 days.'
    );
    $rateLimitMutationCount++;
}
foreach ([
    'DELETE FROM cms_rate_limit',
    'DELETE FROM cms_rate_limit WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR)',
    'DELETE FROM cms_rate_limit WHERE expires_at < NOW()',
    'DELETE FROM cms_rate_limit WHERE expires_at <= NOW() OR expires_at IS NULL',
    '',
] as $replacement) {
    $mutatedFiles = $validFiles;
    $mutatedFiles['cron.php'] = str_replace(
        'DELETE FROM cms_rate_limit WHERE expires_at <= NOW()',
        $replacement,
        $mutatedFiles['cron.php']
    );
    assertSchemaParityAuditFails(
        'Rate-limit deadline-only cron cleanup',
        $mutatedFiles,
        'cron.php must delete rate-limit rows only at their own expires_at deadline, preserving unknown expiries.'
    );
    $rateLimitMutationCount++;
}

$additionalCleanupFiles = $validFiles;
$additionalCleanupFiles['cron.php'] .= "\n" . '$pdo->exec("DELETE FROM cms_rate_limit");';
assertSchemaParityAuditFails(
    'Rate-limit cron must not add a second unscoped cleanup',
    $additionalCleanupFiles,
    'cron.php must delete rate-limit rows only at their own expires_at deadline, preserving unknown expiries.'
);
$rateLimitMutationCount++;

$appmarketColumnMutations = [
    'cms_appmarket_apps.package_id' => [
        'MODIFY', 'VARCHAR(255) NULL DEFAULT NULL', "VARCHAR(255) NOT NULL DEFAULT ''",
    ],
    'cms_appmarket_releases.platform' => [
        'ADD', "VARCHAR(32) NOT NULL DEFAULT 'android'", "VARCHAR(32) NOT NULL DEFAULT 'other'",
    ],
    'cms_appmarket_releases.system_requirements' => [
        'ADD', 'TEXT NULL', 'TEXT NOT NULL',
    ],
    'cms_appmarket_releases.file_storage_name' => [
        'ADD', "VARCHAR(255) NOT NULL DEFAULT ''", "VARCHAR(500) NOT NULL DEFAULT ''",
    ],
    'cms_appmarket_releases.file_original_name' => [
        'ADD', "VARCHAR(255) NOT NULL DEFAULT ''", "VARCHAR(100) NOT NULL DEFAULT ''",
    ],
    'cms_appmarket_releases.file_size' => [
        'ADD', 'BIGINT UNSIGNED NOT NULL DEFAULT 0', 'BIGINT NOT NULL DEFAULT 0',
    ],
    'cms_appmarket_releases.file_sha256' => [
        'ADD', "CHAR(64) NOT NULL DEFAULT ''", "VARCHAR(64) NOT NULL DEFAULT ''",
    ],
    'cms_appmarket_releases.file_extension' => [
        'ADD', "VARCHAR(32) NOT NULL DEFAULT ''", "VARCHAR(16) NOT NULL DEFAULT ''",
    ],
    'cms_appmarket_releases.metadata_source' => [
        'MODIFY',
        "ENUM('apk','publisher_attestation','manual') NOT NULL DEFAULT 'apk'",
        "ENUM('apk','publisher_attestation') NOT NULL DEFAULT 'apk'",
    ],
];
$appmarketMutationCount = 0;
foreach ($appmarketColumnMutations as $columnLabel => [$operation, $definition, $incompatibleDefinition]) {
    [$tableName, $columnName] = explode('.', $columnLabel, 2);
    foreach (['install.php', 'migrate.php'] as $sourceName) {
        foreach (['missing' => '', 'incompatible' => '  ' . $columnName . ' ' . $incompatibleDefinition . ','] as $mutation => $replacement) {
            $mutatedFiles = $validFiles;
            $mutatedFiles[$sourceName] = str_replace(
                '  ' . $columnName . ' ' . $definition . ',',
                $replacement,
                $mutatedFiles[$sourceName]
            );
            assertSchemaParityAuditFails(
                'Appmarket ' . $sourceName . ' ' . $mutation . ' column ' . $columnLabel,
                $mutatedFiles,
                $sourceName . ' Appmarket software schema has an incompatible definition for ' . $columnLabel . '.'
            );
            $appmarketMutationCount++;
        }
    }

    $missingUpgradeFiles = $validFiles;
    $missingUpgradeFiles['migrate.php'] = str_replace(
        'ALTER TABLE ' . $tableName . ' ' . $operation . ' COLUMN ' . $columnName . ' ' . $definition . ';',
        '',
        $missingUpgradeFiles['migrate.php']
    );
    assertSchemaParityAuditFails(
        'Appmarket existing installation upgrade ' . $columnLabel,
        $missingUpgradeFiles,
        'migrate.php must upgrade the Appmarket software column ' . $columnLabel . '.'
    );
    $appmarketMutationCount++;
}

foreach (['install.php', 'migrate.php'] as $sourceName) {
    foreach (['uq_appmarket_apps_slug (slug)', 'uq_appmarket_apps_package (package_id)'] as $uniqueKey) {
        $missingUniqueKeyFiles = $validFiles;
        $missingUniqueKeyFiles[$sourceName] = str_replace(
            'UNIQUE KEY ' . $uniqueKey,
            '',
            $missingUniqueKeyFiles[$sourceName]
        );
        assertSchemaParityAuditFails(
            'Appmarket ' . $sourceName . ' uniqueness ' . $uniqueKey,
            $missingUniqueKeyFiles,
            $sourceName . ' must preserve Appmarket uniqueness: ' . $uniqueKey . '.'
        );
        $appmarketMutationCount++;
    }
    foreach (['package_id_snapshot', 'apk_storage_name', 'apk_original_name', 'apk_size', 'apk_sha256'] as $legacyColumn) {
        $missingLegacyColumnFiles = $validFiles;
        $missingLegacyColumnFiles[$sourceName] = preg_replace(
            '/^\s+' . $legacyColumn . '\s+[^\r\n]+/m',
            '',
            $missingLegacyColumnFiles[$sourceName]
        ) ?? '';
        assertSchemaParityAuditFails(
            'Appmarket ' . $sourceName . ' legacy compatibility ' . $legacyColumn,
            $missingLegacyColumnFiles,
            $sourceName . ' must preserve the legacy Appmarket column ' . $legacyColumn . '.'
        );
        $appmarketMutationCount++;
    }
}

$missingInstallColumnFiles = $validFiles;
$missingInstallColumnFiles['install.php'] = str_replace([
    "  slug_scope_id INT GENERATED ALWAYS AS (IFNULL(blog_id, 0)) STORED,\n",
    "  UNIQUE KEY uq_pages_scope_slug (slug_scope_id, slug)\n",
], '', $missingInstallColumnFiles['install.php']);
assertSchemaParityAuditFails(
    'Fresh install column guard',
    $missingInstallColumnFiles,
    'install.php fresh schema is missing critical column cms_pages.slug_scope_id.'
);

$missingRecipeInstallColumnFiles = $validFiles;
$missingRecipeInstallColumnFiles['install.php'] = str_replace(
    "  dietary_flags VARCHAR(255),\n",
    '',
    $missingRecipeInstallColumnFiles['install.php']
);
assertSchemaParityAuditFails(
    'Recipe fresh install column guard',
    $missingRecipeInstallColumnFiles,
    'install.php fresh schema is missing critical column cms_recipes.dietary_flags.'
);

$missingFoodVariantInstallColumnFiles = $validFiles;
$missingFoodVariantInstallColumnFiles['install.php'] = str_replace(
    "  variant_label VARCHAR(120),\n",
    '',
    $missingFoodVariantInstallColumnFiles['install.php']
);
assertSchemaParityAuditFails(
    'Food variant snapshot fresh install column guard',
    $missingFoodVariantInstallColumnFiles,
    'install.php fresh schema is missing critical column cms_food_order_items.variant_label.'
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

echo 'Rate-limit schema/cleanup mutations rejected: ' . $rateLimitMutationCount . "\n";
echo 'Appmarket schema mutations rejected: ' . $appmarketMutationCount . "\n";
echo 'Shop schema mutations rejected: ' . $shopMutationCount . "\n";
echo "Schema parity audit self-test OK\n";
