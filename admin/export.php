<?php

require_once __DIR__ . '/../db.php';
requireCapability('import_export_manage', 'Přístup odepřen. Pro export dat nemáte potřebné oprávnění.');

$requestMethod = requireHttpMethods(['GET', 'HEAD', 'POST']);

if ($requestMethod === 'HEAD') {
    header('Content-Type: text/html; charset=UTF-8');
    sendAdminNoStoreHeaders();
    exit;
}

function renderJsonExportForm(bool $confirmExportError = false): void
{
    require_once __DIR__ . '/layout.php';

    $exportErrorFields = $confirmExportError ? ['confirm_json_export'] : [];
    $exportConfirmErrorMessage = 'JSON export nejde stáhnout bez potvrzení kontroly citlivosti exportu. U pole Potvrzení stažení je konkrétní nápověda.';

    adminHeader('Export dat');
    ?>
    <p class="admin-description">
      Připraví JSON export obsahu, nastavení a vybraných provozních metadat CMS pro přenos nebo zálohu.
    </p>

    <?php if ($confirmExportError): ?>
      <p id="json-export-form-error" class="error" role="alert" aria-atomic="true"><?= h($exportConfirmErrorMessage) ?></p>
    <?php endif; ?>

    <form method="post" novalidate<?= $confirmExportError ? ' aria-describedby="json-export-form-error"' : '' ?>>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <fieldset class="admin-fieldset-card">
        <legend>Export dat</legend>
        <p id="json-export-review-help" class="field-help field-help--flush">
          JSON export obsahuje články, stránky, média metadata, chatové zprávy, komentáře, odběratele, tokeny odběru a další provozní údaje.
          Heslo administrátora, osobní rezervace a historie rezervací se neexportují, přesto soubor ukládejte jen do oprávněného a bezpečného úložiště.
        </p>
        <label for="confirm_json_export" class="admin-checkbox-label">
          <input type="checkbox" id="confirm_json_export" name="confirm_json_export" value="1" required aria-required="true"<?= adminFieldAttributes('confirm_json_export', $exportErrorFields, [], ['json-export-review-help'], 'confirm-json-export-error') ?>>
          Potvrzuji, že jsem zkontroloval(a) citlivost JSON exportu a mám oprávnění jej stáhnout.
        </label>
        <?php adminRenderFieldError('confirm_json_export', $exportErrorFields, [], 'Před stažením JSON exportu potvrďte, že rozumíte citlivosti exportovaných dat a máte oprávnění soubor stáhnout.', 'confirm-json-export-error'); ?>
        <div class="admin-field-row">
          <button type="submit" class="btn" data-confirm="Stáhnout JSON export CMS? Soubor může obsahovat osobní a provozní údaje.">Stáhnout JSON export</button>
        </div>
      </fieldset>
    </form>

    <p><a href="import.php"><span aria-hidden="true">←</span> Zpět na Import / Export dat</a></p>
    <?php
    adminFooter();
}

if ($requestMethod !== 'POST') {
    renderJsonExportForm();
    exit;
}

verifyCsrf();
$confirmJsonExport = isset($_POST['confirm_json_export'])
    && (string)$_POST['confirm_json_export'] === '1';
if (!$confirmJsonExport) {
    renderJsonExportForm(true);
    exit;
}

$pdo = db_connect();

$data = [
    'exported_at' => date('c'),
    'site'        => 'cms',
    'version'     => 8,
];

$tables = [
    'settings'    => "SELECT `key`, value FROM cms_settings WHERE `key` NOT IN ('admin_password')",
    'categories'  => "SELECT id, name, slug, blog_id, parent_id, description, meta_title, meta_description, created_at, updated_at FROM cms_categories",
    'blogs'       => "SELECT id, name, slug, description, intro_content, logo_file, logo_alt_text, meta_title, meta_description,
                             rss_subtitle, comments_default, feed_item_limit, sort_order, show_in_nav,
                             created_by_user_id,
                             created_at, updated_at
                      FROM cms_blogs",
    'blog_members' => "SELECT blog_id, user_id, member_role, created_at FROM cms_blog_members",
    'blog_slug_redirects' => "SELECT blog_id, old_slug, created_at FROM cms_blog_slug_redirects",
    'articles'    => "SELECT id, title, slug, perex, content, category_id, blog_id, comments_enabled, image_file,
                             meta_title, meta_description, publish_at, unpublish_at, admin_note, is_featured_in_blog,
                             status, created_at FROM cms_articles",
    'article_tags' => "SELECT article_id, tag_id FROM cms_article_tags",
    'article_related' => "SELECT article_id, related_article_id, sort_order, created_at FROM cms_article_related",
    'blog_series' => "SELECT id, blog_id, title, slug, description, is_active, sort_order, created_at, updated_at FROM cms_blog_series",
    'blog_series_items' => "SELECT series_id, article_id, sort_order, created_at FROM cms_blog_series_items",
    'tags'        => "SELECT id, name, slug, blog_id, description, meta_title, meta_description, created_at, updated_at FROM cms_tags",
    'pages'       => "SELECT id, title, slug, content, blog_id, blog_nav_order, show_in_nav, nav_order,
                             is_published, status, created_at FROM cms_pages",
    'nav_links'   => "SELECT id, blog_id, title, url, alt_text, target_blank, is_active, nav_order, created_at, updated_at
                      FROM cms_nav_links",
    'news'          => "SELECT id, title, slug, content, author_id, status, created_at, updated_at,
                               unpublish_at, admin_note, meta_title, meta_description, deleted_at
                        FROM cms_news",
    'chat_topics'   => "SELECT id, name, slug, description, is_active, sort_order, created_at, updated_at
                        FROM cms_chat_topics",
    'chat'          => "SELECT id, topic_id, topic_label, conversation_type, reference_code,
                               name, email, web, message, status, public_visibility,
                               is_pinned, pinned_until, pinned_at, pinned_by_user_id,
                               approved_at, approved_by_user_id,
                               internal_note, replied_at, replied_by_user_id, replied_subject, replied_to_email, replied_body,
                               created_at, updated_at
                        FROM cms_chat",
    'chat_replies'  => "SELECT id, chat_id, name, email, message, status, approved_at, approved_by_user_id, created_at, updated_at
                        FROM cms_chat_replies",
    'chat_history'  => "SELECT id, chat_id, actor_user_id, event_type, message, created_at
                        FROM cms_chat_history",
    'contact_topics' => "SELECT id, name, slug, description, recipient_email, is_active, sort_order, created_at, updated_at
                         FROM cms_contact_topics",
    'event_types'   => "SELECT id, legacy_key, title, slug, description, meta_title, meta_description,
                               is_active, sort_order, created_at, updated_at
                        FROM cms_event_types",
    'events'        => "SELECT id, title, slug, event_kind, event_type_id, place_id, recurrence_group_id,
                               excerpt, description, program_note, location,
                               organizer_name, organizer_email, registration_url, price_note, accessibility_note,
                               image_file, event_date, event_end, is_published, status, publish_at, unpublish_at, admin_note,
                               created_at, updated_at FROM cms_events",
    'places'        => "SELECT id, name, slug, place_kind, excerpt, description, url, image_file, category,
                               address, locality, latitude, longitude, contact_phone, contact_email,
                               opening_hours, meta_title, meta_description, is_published, sort_order, status, deleted_at, created_at, updated_at
                               FROM cms_places",
    'gallery_albums' => "SELECT id, parent_id, name, slug, description, cover_photo_id,
                               default_credit, default_license_label, default_license_url,
                               created_at, updated_at
                               FROM cms_gallery_albums",
    'gallery_photos' => "SELECT id, album_id, filename, title, slug, alt_text, caption, description,
                               credit, license_label, license_url, taken_at, location_label, sort_order, created_at
                               FROM cms_gallery_photos",
    'media_collections' => "SELECT id, name, slug, description, default_visibility, default_credit,
                                   default_license_label, default_license_url, sort_order, created_at, updated_at
                            FROM cms_media_collections",
    'media'         => "SELECT id, filename, original_name, mime_type, file_size, folder, collection_id,
                               alt_text, caption, description, credit, license_label, license_url,
                               visibility, uploaded_by, created_at, updated_at
                        FROM cms_media",
    'dl_categories' => "SELECT id, name, slug, description, meta_title, meta_description, created_at, updated_at FROM cms_dl_categories",
    'download_series' => "SELECT id, title, slug, description, is_active, sort_order, created_at, updated_at FROM cms_download_series",
    'downloads'     => "SELECT id, title, slug, download_type, dl_category_id, excerpt, description,
                               image_file, version_label, platform_label, license_label, project_url,
                               release_date, requirements, checksum_sha256, series_key, download_series_id, is_current_version, external_url,
                               filename, original_name, file_size, download_count, external_click_count, is_featured, sort_order, is_published, status,
                               created_at, updated_at
                               FROM cms_downloads",
    'food_cards'    => "SELECT id, type, title, slug, description, content, valid_from, valid_to,
                               orders_enabled, order_email, order_instructions,
                               is_current, is_published, status, created_at, updated_at FROM cms_food_cards",
    'food_sections' => "SELECT id, card_id, title, description, serving_date, serving_time_from, serving_time_to, serving_note,
                               sort_order, created_at, updated_at FROM cms_food_sections",
    'food_items'    => "SELECT id, card_id, section_id, title, description, price_amount, price_currency,
                               price_note, portion_label, energy_kj, energy_kcal, protein_g, carbs_g, fat_g, salt_g,
                               media_id, image_alt_text, allergens, dietary_flags, is_available, sort_order, created_at, updated_at
                        FROM cms_food_items",
    'res_categories' => "SELECT id, name, sort_order, created_at FROM cms_res_categories",
    'res_resources' => "SELECT id, category_id, name, slug, description, capacity, slot_mode, slot_duration_min,
                               min_advance_hours, max_advance_days, cancellation_hours, requires_approval, allow_guests,
                               reminders_enabled, reminder_hours_before, reminder_message, calendar_invite_enabled,
                               max_concurrent, location, is_active, created_at, updated_at
                        FROM cms_res_resources",
    'res_hours' => "SELECT id, resource_id, day_of_week, open_time, close_time, is_closed FROM cms_res_hours",
    'res_slots' => "SELECT id, resource_id, day_of_week, start_time, end_time, max_bookings FROM cms_res_slots",
    'res_blocked' => "SELECT id, resource_id, blocked_date, reason, created_at FROM cms_res_blocked",
    'res_locations' => "SELECT id, name, address, created_at FROM cms_res_locations",
    'res_resource_locations' => "SELECT resource_id, location_id FROM cms_res_resource_locations",
    'podcast_shows' => "SELECT id, title, slug, description, author, subtitle, cover_image,
                               language, category, owner_name, owner_email, explicit_mode, show_type, feed_complete,
                               feed_episode_limit, feed_guid, website_url, is_published, status, created_at, updated_at FROM cms_podcast_shows",
    'podcasts'      => "SELECT id, show_id, title, slug, description, transcript, audio_file, image_file, audio_url,
                               audio_mime_type, audio_file_size, feed_guid, subtitle,
                               duration, episode_num, season_num, episode_type, explicit_mode, block_from_feed,
                               publish_at, status, created_at, updated_at FROM cms_podcasts",
    'podcast_chapters' => "SELECT id, episode_id, start_time_seconds, title, url, image_url, created_at, updated_at
                           FROM cms_podcast_chapters",
    'podcast_people' => "SELECT id, show_id, episode_id, name, role_key, group_key, profile_url, image_url,
                                sort_order, created_at, updated_at FROM cms_podcast_people",
    'podcast_platform_links' => "SELECT id, show_id, platform_key, label, url, sort_order, created_at, updated_at
                                 FROM cms_podcast_platform_links",
    'polls'         => "SELECT id, question, slug, description, vote_mode, max_choices, results_visibility,
                               meta_title, meta_description, start_date, end_date, status, created_at, updated_at
                        FROM cms_polls",
    'poll_options'  => "SELECT id, poll_id, option_text, sort_order FROM cms_poll_options",
    'faq_categories' => "SELECT id, name, slug, description, meta_title, meta_description, parent_id, sort_order, created_at, updated_at FROM cms_faq_categories",
    'faqs'          => "SELECT id, question, slug, excerpt, answer, category_id, meta_title, meta_description,
                               is_published, status, created_at, updated_at FROM cms_faqs",
    'board_categories' => "SELECT id, name, slug, description, meta_title, meta_description, sort_order, created_at, updated_at
                               FROM cms_board_categories",
    'board'         => "SELECT id, title, slug, board_type, excerpt, description, category_id, posted_date, removal_date,
                               image_file, contact_name, contact_phone, contact_email,
                               filename, original_name, file_size, sort_order, is_pinned, is_published,
                               status, publish_at, unpublish_at, preview_token, deleted_at, created_at FROM cms_board",
    'board_publication_events' => "SELECT id, board_id, event_type, event_date, actor_user_id, public_path,
                                          attachment_name, attachment_size, attachment_checksum, created_at
                                   FROM cms_board_publication_events",
    'board_subscribers' => "SELECT id, email, token, confirmed, all_categories, created_at, confirmed_at
                            FROM cms_board_subscribers",
    'board_subscriber_categories' => "SELECT subscriber_id, category_id FROM cms_board_subscriber_categories",
    'comments'      => "SELECT id, article_id, author_name, author_email, content, status, is_approved, created_at
                               FROM cms_comments",
    'subscribers'   => "SELECT id, email, confirmed, token, created_at FROM cms_subscribers",
    'newsletters'   => "SELECT id, subject, body, recipient_count, sent_at, created_at FROM cms_newsletters",
];

foreach ($tables as $key => $sql) {
    try {
        $data[$key] = $pdo->query($sql)->fetchAll();
    } catch (\PDOException $e) {
        $data[$key] = [];
    }
}

$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($json)) {
    $json = '{}';
}
$filename = 'cms-export-' . date('Y-m-d') . '.json';

sendAdminAttachmentHeaders('application/json; charset=utf-8', $filename, strlen($json));
logAction('export_json');
echo $json;
exit;
