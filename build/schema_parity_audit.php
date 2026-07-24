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
    $marker = 'CREATE TABLE IF NOT EXISTS ' . $tableName . ' (';
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
    'cms_pages.slug_scope_id' => ['cms_pages', 'slug_scope_id'],
    'cms_pages.blog_nav_order' => ['cms_pages', 'blog_nav_order'],
    'cms_pages.deleted_at' => ['cms_pages', 'deleted_at'],
    'cms_nav_links.blog_id' => ['cms_nav_links', 'blog_id'],
    'cms_nav_links.url' => ['cms_nav_links', 'url'],
    'cms_nav_links.alt_text' => ['cms_nav_links', 'alt_text'],
    'cms_nav_links.target_blank' => ['cms_nav_links', 'target_blank'],
    'cms_nav_links.is_active' => ['cms_nav_links', 'is_active'],
    'cms_nav_links.nav_order' => ['cms_nav_links', 'nav_order'],
    'cms_media_collections.name' => ['cms_media_collections', 'name'],
    'cms_media_collections.slug' => ['cms_media_collections', 'slug'],
    'cms_media_collections.default_visibility' => ['cms_media_collections', 'default_visibility'],
    'cms_media.collection_id' => ['cms_media', 'collection_id'],
    'cms_media.caption' => ['cms_media', 'caption'],
    'cms_media.description' => ['cms_media', 'description'],
    'cms_media.credit' => ['cms_media', 'credit'],
    'cms_media.license_label' => ['cms_media', 'license_label'],
    'cms_media.license_url' => ['cms_media', 'license_url'],
    'cms_media.visibility' => ['cms_media', 'visibility'],
    'cms_media.updated_at' => ['cms_media', 'updated_at'],
    'cms_gallery_photos.slug' => ['cms_gallery_photos', 'slug'],
    'cms_gallery_albums.default_credit' => ['cms_gallery_albums', 'default_credit'],
    'cms_gallery_albums.default_license_label' => ['cms_gallery_albums', 'default_license_label'],
    'cms_gallery_albums.default_license_url' => ['cms_gallery_albums', 'default_license_url'],
    'cms_gallery_photos.alt_text' => ['cms_gallery_photos', 'alt_text'],
    'cms_gallery_photos.caption' => ['cms_gallery_photos', 'caption'],
    'cms_gallery_photos.description' => ['cms_gallery_photos', 'description'],
    'cms_gallery_photos.credit' => ['cms_gallery_photos', 'credit'],
    'cms_gallery_photos.license_label' => ['cms_gallery_photos', 'license_label'],
    'cms_gallery_photos.license_url' => ['cms_gallery_photos', 'license_url'],
    'cms_gallery_photos.taken_at' => ['cms_gallery_photos', 'taken_at'],
    'cms_gallery_photos.location_label' => ['cms_gallery_photos', 'location_label'],
    'cms_gallery_photos.status' => ['cms_gallery_photos', 'status'],
    'cms_gallery_photos.is_published' => ['cms_gallery_photos', 'is_published'],
    'cms_gallery_photos.deleted_at' => ['cms_gallery_photos', 'deleted_at'],
    'cms_event_types.legacy_key' => ['cms_event_types', 'legacy_key'],
    'cms_event_types.slug' => ['cms_event_types', 'slug'],
    'cms_event_types.description' => ['cms_event_types', 'description'],
    'cms_event_types.meta_title' => ['cms_event_types', 'meta_title'],
    'cms_event_types.meta_description' => ['cms_event_types', 'meta_description'],
    'cms_event_types.is_active' => ['cms_event_types', 'is_active'],
    'cms_event_types.sort_order' => ['cms_event_types', 'sort_order'],
    'cms_events.event_type_id' => ['cms_events', 'event_type_id'],
    'cms_events.place_id' => ['cms_events', 'place_id'],
    'cms_events.recurrence_group_id' => ['cms_events', 'recurrence_group_id'],
    'cms_events.excerpt' => ['cms_events', 'excerpt'],
    'cms_admin_shortcuts.user_id' => ['cms_admin_shortcuts', 'user_id'],
    'cms_admin_shortcuts.item_type' => ['cms_admin_shortcuts', 'item_type'],
    'cms_admin_shortcuts.item_key' => ['cms_admin_shortcuts', 'item_key'],
    'cms_admin_shortcuts.url' => ['cms_admin_shortcuts', 'url'],
    'cms_article_related.article_id' => ['cms_article_related', 'article_id'],
    'cms_article_related.related_article_id' => ['cms_article_related', 'related_article_id'],
    'cms_article_related.sort_order' => ['cms_article_related', 'sort_order'],
    'cms_blog_series.blog_id' => ['cms_blog_series', 'blog_id'],
    'cms_blog_series.slug' => ['cms_blog_series', 'slug'],
    'cms_blog_series.is_active' => ['cms_blog_series', 'is_active'],
    'cms_blog_series_items.series_id' => ['cms_blog_series_items', 'series_id'],
    'cms_blog_series_items.article_id' => ['cms_blog_series_items', 'article_id'],
    'cms_blog_series_items.sort_order' => ['cms_blog_series_items', 'sort_order'],
    'cms_categories.slug' => ['cms_categories', 'slug'],
    'cms_categories.description' => ['cms_categories', 'description'],
    'cms_categories.meta_title' => ['cms_categories', 'meta_title'],
    'cms_categories.meta_description' => ['cms_categories', 'meta_description'],
    'cms_categories.updated_at' => ['cms_categories', 'updated_at'],
    'cms_tags.description' => ['cms_tags', 'description'],
    'cms_tags.meta_title' => ['cms_tags', 'meta_title'],
    'cms_tags.meta_description' => ['cms_tags', 'meta_description'],
    'cms_tags.updated_at' => ['cms_tags', 'updated_at'],
    'cms_board_categories.slug' => ['cms_board_categories', 'slug'],
    'cms_board_categories.description' => ['cms_board_categories', 'description'],
    'cms_board_categories.meta_title' => ['cms_board_categories', 'meta_title'],
    'cms_board_categories.meta_description' => ['cms_board_categories', 'meta_description'],
    'cms_board_categories.updated_at' => ['cms_board_categories', 'updated_at'],
    'cms_board_publication_events.board_id' => ['cms_board_publication_events', 'board_id'],
    'cms_board_publication_events.event_type' => ['cms_board_publication_events', 'event_type'],
    'cms_board_subscribers.email' => ['cms_board_subscribers', 'email'],
    'cms_board_subscribers.confirmed' => ['cms_board_subscribers', 'confirmed'],
    'cms_board_subscriber_categories.subscriber_id' => ['cms_board_subscriber_categories', 'subscriber_id'],
    'cms_board_subscriber_categories.category_id' => ['cms_board_subscriber_categories', 'category_id'],
    'cms_chat_topics.slug' => ['cms_chat_topics', 'slug'],
    'cms_chat_topics.is_active' => ['cms_chat_topics', 'is_active'],
    'cms_chat.topic_id' => ['cms_chat', 'topic_id'],
    'cms_chat.topic_label' => ['cms_chat', 'topic_label'],
    'cms_chat.conversation_type' => ['cms_chat', 'conversation_type'],
    'cms_chat.reference_code' => ['cms_chat', 'reference_code'],
    'cms_chat.is_pinned' => ['cms_chat', 'is_pinned'],
    'cms_chat.pinned_until' => ['cms_chat', 'pinned_until'],
    'cms_chat.replied_body' => ['cms_chat', 'replied_body'],
    'cms_chat_replies.chat_id' => ['cms_chat_replies', 'chat_id'],
    'cms_chat_replies.status' => ['cms_chat_replies', 'status'],
    'cms_contact_topics.slug' => ['cms_contact_topics', 'slug'],
    'cms_contact_topics.recipient_email' => ['cms_contact_topics', 'recipient_email'],
    'cms_contact_topics.is_active' => ['cms_contact_topics', 'is_active'],
    'cms_contact.sender_name' => ['cms_contact', 'sender_name'],
    'cms_contact.topic_id' => ['cms_contact', 'topic_id'],
    'cms_contact.topic_label' => ['cms_contact', 'topic_label'],
    'cms_contact.reference_code' => ['cms_contact', 'reference_code'],
    'cms_contact.replied_at' => ['cms_contact', 'replied_at'],
    'cms_contact.replied_by_user_id' => ['cms_contact', 'replied_by_user_id'],
    'cms_contact.reply_subject' => ['cms_contact', 'reply_subject'],
    'cms_contact.reply_body' => ['cms_contact', 'reply_body'],
    'cms_dl_categories.slug' => ['cms_dl_categories', 'slug'],
    'cms_dl_categories.description' => ['cms_dl_categories', 'description'],
    'cms_dl_categories.meta_title' => ['cms_dl_categories', 'meta_title'],
    'cms_dl_categories.meta_description' => ['cms_dl_categories', 'meta_description'],
    'cms_dl_categories.updated_at' => ['cms_dl_categories', 'updated_at'],
    'cms_download_series.title' => ['cms_download_series', 'title'],
    'cms_download_series.slug' => ['cms_download_series', 'slug'],
    'cms_download_series.is_active' => ['cms_download_series', 'is_active'],
    'cms_download_series.sort_order' => ['cms_download_series', 'sort_order'],
    'cms_downloads.download_series_id' => ['cms_downloads', 'download_series_id'],
    'cms_downloads.is_current_version' => ['cms_downloads', 'is_current_version'],
    'cms_downloads.external_click_count' => ['cms_downloads', 'external_click_count'],
    'cms_appmarket_apps.slug' => ['cms_appmarket_apps', 'slug'],
    'cms_appmarket_apps.package_id' => ['cms_appmarket_apps', 'package_id'],
    'cms_appmarket_apps.short_description' => ['cms_appmarket_apps', 'short_description'],
    'cms_appmarket_apps.icon_media_id' => ['cms_appmarket_apps', 'icon_media_id'],
    'cms_appmarket_apps.status' => ['cms_appmarket_apps', 'status'],
    'cms_appmarket_certificates.app_id' => ['cms_appmarket_certificates', 'app_id'],
    'cms_appmarket_certificates.fingerprint_sha256' => ['cms_appmarket_certificates', 'fingerprint_sha256'],
    'cms_appmarket_certificates.is_active' => ['cms_appmarket_certificates', 'is_active'],
    'cms_appmarket_releases.app_id' => ['cms_appmarket_releases', 'app_id'],
    'cms_appmarket_releases.version_name' => ['cms_appmarket_releases', 'version_name'],
    'cms_appmarket_releases.version_code' => ['cms_appmarket_releases', 'version_code'],
    'cms_appmarket_releases.package_id_snapshot' => ['cms_appmarket_releases', 'package_id_snapshot'],
    'cms_appmarket_releases.apk_storage_name' => ['cms_appmarket_releases', 'apk_storage_name'],
    'cms_appmarket_releases.apk_size' => ['cms_appmarket_releases', 'apk_size'],
    'cms_appmarket_releases.apk_sha256' => ['cms_appmarket_releases', 'apk_sha256'],
    'cms_appmarket_releases.certificate_id' => ['cms_appmarket_releases', 'certificate_id'],
    'cms_appmarket_releases.certificate_fingerprint_sha256' => ['cms_appmarket_releases', 'certificate_fingerprint_sha256'],
    'cms_appmarket_releases.permissions_json' => ['cms_appmarket_releases', 'permissions_json'],
    'cms_appmarket_releases.analysis_json' => ['cms_appmarket_releases', 'analysis_json'],
    'cms_appmarket_releases.metadata_source' => ['cms_appmarket_releases', 'metadata_source'],
    'cms_appmarket_releases.publisher_token_id' => ['cms_appmarket_releases', 'publisher_token_id'],
    'cms_appmarket_releases.status' => ['cms_appmarket_releases', 'status'],
    'cms_appmarket_releases.download_count' => ['cms_appmarket_releases', 'download_count'],
    'cms_appmarket_screenshots.app_id' => ['cms_appmarket_screenshots', 'app_id'],
    'cms_appmarket_screenshots.media_id' => ['cms_appmarket_screenshots', 'media_id'],
    'cms_appmarket_screenshots.alt_text' => ['cms_appmarket_screenshots', 'alt_text'],
    'cms_appmarket_publish_tokens.app_id' => ['cms_appmarket_publish_tokens', 'app_id'],
    'cms_appmarket_publish_tokens.token_hash' => ['cms_appmarket_publish_tokens', 'token_hash'],
    'cms_appmarket_publish_tokens.scopes' => ['cms_appmarket_publish_tokens', 'scopes'],
    'cms_appmarket_publish_tokens.attestation_algorithm' => ['cms_appmarket_publish_tokens', 'attestation_algorithm'],
    'cms_appmarket_publish_tokens.attestation_public_key' => ['cms_appmarket_publish_tokens', 'attestation_public_key'],
    'cms_appmarket_publish_tokens.attestation_key_fingerprint' => ['cms_appmarket_publish_tokens', 'attestation_key_fingerprint'],
    'cms_appmarket_publish_tokens.expires_at' => ['cms_appmarket_publish_tokens', 'expires_at'],
    'cms_appmarket_publish_tokens.revoked_at' => ['cms_appmarket_publish_tokens', 'revoked_at'],
    'cms_faq_categories.slug' => ['cms_faq_categories', 'slug'],
    'cms_faq_categories.description' => ['cms_faq_categories', 'description'],
    'cms_faq_categories.meta_title' => ['cms_faq_categories', 'meta_title'],
    'cms_faq_categories.meta_description' => ['cms_faq_categories', 'meta_description'],
    'cms_faq_categories.updated_at' => ['cms_faq_categories', 'updated_at'],
    'cms_faq_feedback.faq_id' => ['cms_faq_feedback', 'faq_id'],
    'cms_faq_feedback.vote' => ['cms_faq_feedback', 'vote'],
    'cms_faq_feedback.visitor_hash' => ['cms_faq_feedback', 'visitor_hash'],
    'cms_polls.vote_mode' => ['cms_polls', 'vote_mode'],
    'cms_polls.max_choices' => ['cms_polls', 'max_choices'],
    'cms_polls.results_visibility' => ['cms_polls', 'results_visibility'],
    'cms_poll_vote_sessions.poll_id' => ['cms_poll_vote_sessions', 'poll_id'],
    'cms_poll_vote_sessions.voter_hash' => ['cms_poll_vote_sessions', 'voter_hash'],
    'cms_poll_votes.vote_session_id' => ['cms_poll_votes', 'vote_session_id'],
    'cms_food_cards.orders_enabled' => ['cms_food_cards', 'orders_enabled'],
    'cms_food_cards.order_email' => ['cms_food_cards', 'order_email'],
    'cms_food_cards.order_instructions' => ['cms_food_cards', 'order_instructions'],
    'cms_food_sections.card_id' => ['cms_food_sections', 'card_id'],
    'cms_food_sections.title' => ['cms_food_sections', 'title'],
    'cms_food_sections.serving_date' => ['cms_food_sections', 'serving_date'],
    'cms_food_sections.serving_time_from' => ['cms_food_sections', 'serving_time_from'],
    'cms_food_sections.serving_time_to' => ['cms_food_sections', 'serving_time_to'],
    'cms_food_sections.serving_note' => ['cms_food_sections', 'serving_note'],
    'cms_food_sections.sort_order' => ['cms_food_sections', 'sort_order'],
    'cms_food_items.card_id' => ['cms_food_items', 'card_id'],
    'cms_food_items.section_id' => ['cms_food_items', 'section_id'],
    'cms_food_items.title' => ['cms_food_items', 'title'],
    'cms_food_items.price_amount' => ['cms_food_items', 'price_amount'],
    'cms_food_items.portion_label' => ['cms_food_items', 'portion_label'],
    'cms_food_items.energy_kj' => ['cms_food_items', 'energy_kj'],
    'cms_food_items.energy_kcal' => ['cms_food_items', 'energy_kcal'],
    'cms_food_items.protein_g' => ['cms_food_items', 'protein_g'],
    'cms_food_items.carbs_g' => ['cms_food_items', 'carbs_g'],
    'cms_food_items.fat_g' => ['cms_food_items', 'fat_g'],
    'cms_food_items.salt_g' => ['cms_food_items', 'salt_g'],
    'cms_food_items.media_id' => ['cms_food_items', 'media_id'],
    'cms_food_items.image_alt_text' => ['cms_food_items', 'image_alt_text'],
    'cms_food_items.allergens' => ['cms_food_items', 'allergens'],
    'cms_food_items.dietary_flags' => ['cms_food_items', 'dietary_flags'],
    'cms_food_items.is_available' => ['cms_food_items', 'is_available'],
    'cms_food_orders.reference_code' => ['cms_food_orders', 'reference_code'],
    'cms_food_orders.customer_email' => ['cms_food_orders', 'customer_email'],
    'cms_food_orders.status' => ['cms_food_orders', 'status'],
    'cms_food_order_items.order_id' => ['cms_food_order_items', 'order_id'],
    'cms_food_order_items.item_title' => ['cms_food_order_items', 'item_title'],
    'cms_food_order_items.quantity' => ['cms_food_order_items', 'quantity'],
    'cms_stats_content_daily.stat_date' => ['cms_stats_content_daily', 'stat_date'],
    'cms_stats_content_daily.page_type' => ['cms_stats_content_daily', 'page_type'],
    'cms_stats_content_daily.page_ref_id' => ['cms_stats_content_daily', 'page_ref_id'],
    'cms_stats_content_daily.normalized_path' => ['cms_stats_content_daily', 'normalized_path'],
    'cms_stats_content_daily.path_hash' => ['cms_stats_content_daily', 'path_hash'],
    'cms_stats_content_daily.module_key' => ['cms_stats_content_daily', 'module_key'],
    'cms_stats_content_daily.title_snapshot' => ['cms_stats_content_daily', 'title_snapshot'],
    'cms_stats_content_daily.total_views' => ['cms_stats_content_daily', 'total_views'],
    'cms_stats_content_daily.unique_visitors' => ['cms_stats_content_daily', 'unique_visitors'],
    'cms_res_resources.reminders_enabled' => ['cms_res_resources', 'reminders_enabled'],
    'cms_res_resources.reminder_hours_before' => ['cms_res_resources', 'reminder_hours_before'],
    'cms_res_resources.reminder_message' => ['cms_res_resources', 'reminder_message'],
    'cms_res_resources.calendar_invite_enabled' => ['cms_res_resources', 'calendar_invite_enabled'],
    'cms_res_bookings.calendar_token' => ['cms_res_bookings', 'calendar_token'],
    'cms_res_bookings.reminder_sent_at' => ['cms_res_bookings', 'reminder_sent_at'],
    'cms_res_bookings.reminder_last_error' => ['cms_res_bookings', 'reminder_last_error'],
    'cms_res_booking_events.booking_id' => ['cms_res_booking_events', 'booking_id'],
    'cms_res_booking_events.event_type' => ['cms_res_booking_events', 'event_type'],
    'cms_res_booking_events.description' => ['cms_res_booking_events', 'description'],
    'cms_podcasts.transcript' => ['cms_podcasts', 'transcript'],
    'cms_podcast_shows.feed_guid' => ['cms_podcast_shows', 'feed_guid'],
    'cms_podcasts.feed_guid' => ['cms_podcasts', 'feed_guid'],
    'cms_podcasts.audio_mime_type' => ['cms_podcasts', 'audio_mime_type'],
    'cms_podcasts.audio_file_size' => ['cms_podcasts', 'audio_file_size'],
    'cms_podcast_chapters.episode_id' => ['cms_podcast_chapters', 'episode_id'],
    'cms_podcast_chapters.start_time_seconds' => ['cms_podcast_chapters', 'start_time_seconds'],
    'cms_podcast_chapters.title' => ['cms_podcast_chapters', 'title'],
    'cms_podcast_people.show_id' => ['cms_podcast_people', 'show_id'],
    'cms_podcast_people.episode_id' => ['cms_podcast_people', 'episode_id'],
    'cms_podcast_people.role_key' => ['cms_podcast_people', 'role_key'],
    'cms_podcast_platform_links.show_id' => ['cms_podcast_platform_links', 'show_id'],
    'cms_podcast_platform_links.platform_key' => ['cms_podcast_platform_links', 'platform_key'],
    'cms_podcast_platform_links.url' => ['cms_podcast_platform_links', 'url'],
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
    'cms_pages.slug_scope_id',
    'cms_pages.blog_nav_order',
    'uq_pages_scope_slug',
    'DROP INDEX slug',
    'DROP INDEX uq_cms_pages_slug',
    'idx_pages_blog_nav',
    'cms_nav_links',
    'idx_nav_links_scope',
    'idx_nav_links_active',
    'cms_media_collections',
    'uq_media_collections_slug',
    'idx_media_collections_order',
    'cms_media.collection_id',
    'cms_media.caption',
    'cms_media.description',
    'cms_media.credit',
    'cms_media.license_label',
    'cms_media.license_url',
    'cms_media.visibility',
    'cms_media.updated_at',
    'idx_media_visibility',
    'idx_media_collection',
    'cms_podcasts.transcript',
    'cms_podcast_shows.feed_guid',
    'cms_podcasts.feed_guid',
    'cms_podcasts.audio_mime_type',
    'cms_podcasts.audio_file_size',
    'uq_podcast_shows_feed_guid',
    'uq_podcasts_feed_guid',
    'cms_podcast_chapters',
    'uq_podcast_chapter_start',
    'idx_podcast_chapters_episode',
    'cms_podcast_people',
    'idx_podcast_people_show',
    'idx_podcast_people_episode',
    'cms_podcast_platform_links',
    'uq_podcast_platform_show_key',
    'idx_podcast_platform_show_order',
    'cms_gallery_photos.slug',
    'cms_gallery_albums.default_credit',
    'cms_gallery_albums.default_license_label',
    'cms_gallery_albums.default_license_url',
    'cms_gallery_photos.alt_text',
    'cms_gallery_photos.caption',
    'cms_gallery_photos.description',
    'cms_gallery_photos.credit',
    'cms_gallery_photos.license_label',
    'cms_gallery_photos.license_url',
    'cms_gallery_photos.taken_at',
    'cms_gallery_photos.location_label',
    'cms_gallery_photos.status',
    'cms_gallery_photos.is_published',
    'cms_gallery_photos.deleted_at',
    'cms_event_types',
    'uq_cms_event_types_slug',
    'uq_cms_event_types_legacy',
    'idx_cms_event_types_active_order',
    'cms_events.event_type_id',
    'cms_events.place_id',
    'cms_events.recurrence_group_id',
    'idx_cms_events_type',
    'idx_cms_events_place',
    'idx_cms_events_recurrence',
    'cms_events.excerpt',
    'cms_admin_shortcuts',
    'uq_admin_shortcut_user_item',
    'idx_admin_shortcut_user_order',
    'cms_article_related',
    'idx_article_related_order',
    'idx_article_related_target',
    'cms_blog_series',
    'uq_blog_series_blog_slug',
    'idx_blog_series_blog_order',
    'cms_blog_series_items',
    'idx_blog_series_items_article',
    'idx_blog_series_items_order',
    'cms_categories.slug',
    'uq_categories_blog_slug',
    'cms_categories.description',
    'cms_categories.meta_title',
    'cms_categories.meta_description',
    'cms_categories.updated_at',
    'cms_tags.description',
    'cms_tags.meta_title',
    'cms_tags.meta_description',
    'cms_tags.updated_at',
    'cms_board_categories.slug',
    'uq_cms_board_categories_slug',
    'cms_board_categories.description',
    'cms_board_categories.meta_title',
    'cms_board_categories.meta_description',
    'cms_board_categories.updated_at',
    'cms_board_publication_events',
    'idx_board_publication_events_board',
    'cms_board_subscribers',
    'uq_board_subscribers_email',
    'cms_board_subscriber_categories',
    'cms_chat_topics',
    'uq_cms_chat_topics_slug',
    'idx_cms_chat_topics_active_order',
    'cms_chat.topic_id',
    'cms_chat.conversation_type',
    'cms_chat.reference_code',
    'idx_cms_chat_public',
    'idx_cms_chat_reference',
    'cms_chat_replies',
    'idx_cms_chat_replies_chat_status',
    'cms_contact_topics',
    'uq_cms_contact_topics_slug',
    'idx_cms_contact_topics_active_order',
    'cms_contact.sender_name',
    'cms_contact.topic_id',
    'cms_contact.topic_label',
    'cms_contact.reference_code',
    'cms_contact.replied_at',
    'cms_contact.replied_by_user_id',
    'cms_contact.reply_subject',
    'cms_contact.reply_body',
    'idx_cms_contact_reference_code',
    'idx_cms_contact_topic_status',
    'cms_dl_categories.slug',
    'uq_cms_dl_categories_slug',
    'cms_dl_categories.description',
    'cms_dl_categories.meta_title',
    'cms_dl_categories.meta_description',
    'cms_dl_categories.updated_at',
    'cms_download_series',
    'uq_cms_download_series_slug',
    'idx_cms_download_series_active_order',
    'cms_downloads.download_series_id',
    'cms_downloads.is_current_version',
    'cms_downloads.external_click_count',
    'idx_cms_downloads_series_current',
    'cms_appmarket_apps',
    'uq_appmarket_apps_slug',
    'uq_appmarket_apps_package',
    'idx_appmarket_apps_public',
    'cms_appmarket_certificates',
    'uq_appmarket_certificate_fingerprint',
    'idx_appmarket_certificates_active',
    'cms_appmarket_releases',
    'uq_appmarket_release_version',
    'idx_appmarket_releases_public',
    'idx_appmarket_releases_publisher_token',
    'cms_appmarket_screenshots',
    'uq_appmarket_screenshot_media',
    'idx_appmarket_screenshots_order',
    'cms_appmarket_publish_tokens',
    'uq_appmarket_publish_token_hash',
    'idx_appmarket_publish_tokens_active',
    'idx_appmarket_publish_tokens_attestation',
    'cms_faq_categories.slug',
    'uq_cms_faq_categories_slug',
    'cms_faq_categories.description',
    'cms_faq_categories.meta_title',
    'cms_faq_categories.meta_description',
    'cms_faq_categories.updated_at',
    'cms_faq_feedback',
    'uq_cms_faq_feedback_visitor',
    'idx_cms_faq_feedback_faq_vote',
    'cms_polls.vote_mode',
    'cms_polls.max_choices',
    'cms_polls.results_visibility',
    'cms_poll_vote_sessions',
    'uq_poll_vote_session',
    'idx_poll_vote_sessions_poll',
    'cms_poll_votes.vote_session_id',
    'idx_poll_votes_session',
    'idx_poll_votes_poll_option',
    'uq_poll_vote_option_hash',
    'DROP INDEX uq_poll_ip',
    'cms_food_cards.orders_enabled',
    'cms_food_cards.order_email',
    'cms_food_cards.order_instructions',
    'cms_food_sections',
    'cms_food_sections.serving_date',
    'cms_food_sections.serving_time_from',
    'cms_food_sections.serving_time_to',
    'cms_food_sections.serving_note',
    'idx_food_sections_card_order',
    'cms_food_items',
    'cms_food_items.portion_label',
    'cms_food_items.energy_kj',
    'cms_food_items.energy_kcal',
    'cms_food_items.protein_g',
    'cms_food_items.carbs_g',
    'cms_food_items.fat_g',
    'cms_food_items.salt_g',
    'idx_food_items_card_order',
    'idx_food_items_section_order',
    'idx_food_items_media',
    'ft_food_items_search',
    'cms_food_orders',
    'uq_food_orders_reference',
    'idx_food_orders_card_status',
    'cms_food_order_items',
    'idx_food_order_items_order',
    'idx_food_order_items_item',
    'cms_stats_content_daily',
    'uq_stats_content_daily',
    'idx_stats_content_module_date',
    'idx_stats_content_path_hash',
    'cms_res_resources.reminders_enabled',
    'cms_res_resources.reminder_hours_before',
    'cms_res_resources.reminder_message',
    'cms_res_resources.calendar_invite_enabled',
    'cms_res_bookings.calendar_token',
    'cms_res_bookings.reminder_sent_at',
    'cms_res_bookings.reminder_last_error',
    'cms_res_booking_events',
    'uq_res_calendar_token',
    'idx_res_reminders',
    'idx_res_booking_events_booking',
    'idx_res_booking_events_type',
];

foreach ($criticalMigrationSnippets as $snippet) {
    schemaParityRequire(
        str_contains($migrateSource, $snippet),
        'migrate.php upgrade schema is missing critical migration guard ' . $snippet . '.',
        $issues
    );
}

schemaParityRequire(
    schemaParityTableContains($installSource, 'cms_pages', 'UNIQUE KEY uq_pages_scope_slug (slug_scope_id, slug)')
    && !schemaParityTableContains($installSource, 'cms_pages', 'slug         VARCHAR(255) NOT NULL UNIQUE'),
    'install.php must scope cms_pages slug uniqueness by slug_scope_id instead of a global slug UNIQUE index.',
    $issues
);

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
    str_contains($contentReferenceSearchSource, 'm.description')
    && str_contains($contentReferenceSearchSource, 'm.license_label')
    && str_contains($contentReferenceSearchSource, 'cms_media_collections')
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
schemaParityRequire(
    str_contains($sitemapSource, 'blogCategoryUrl(')
    && str_contains($sitemapSource, 'blogTagUrl(')
    && str_contains($sitemapSource, 'cms_categories c')
    && str_contains($sitemapSource, 'cms_tags t'),
    'sitemap.php must keep public blog category and tag landing URLs.',
    $issues
);
schemaParityRequire(
    str_contains($sitemapSource, 'boardCategoryUrl(')
    && str_contains($sitemapSource, 'cms_board_categories c')
    && str_contains($sitemapSource, "sitemapLogSectionError('board_categories'"),
    'sitemap.php must keep public board category landing URLs.',
    $issues
);
schemaParityRequire(
    str_contains($sitemapSource, 'downloadCategoryUrl(')
    && str_contains($sitemapSource, 'downloadSeriesUrl(')
    && str_contains($sitemapSource, 'cms_dl_categories c')
    && str_contains($sitemapSource, 'cms_download_series s'),
    'sitemap.php must keep public downloads category and series landing URLs.',
    $issues
);
schemaParityRequire(
    str_contains($sitemapSource, 'faqCategoryUrl(')
    && str_contains($sitemapSource, 'cms_faq_categories c')
    && str_contains($sitemapSource, "sitemapLogSectionError('faq_categories'"),
    'sitemap.php must keep public FAQ category landing URLs.',
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
