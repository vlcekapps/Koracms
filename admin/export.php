<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();

$data = [
    'exported_at' => date('c'),
    'site'        => 'cms',
    'version'     => 4,
];

$tables = [
    'settings'    => "SELECT `key`, value FROM cms_settings WHERE `key` NOT IN ('admin_password')",
    'categories'  => "SELECT id, name, blog_id, created_at FROM cms_categories",
    'blogs'       => "SELECT id, name, slug, description, intro_content, logo_file, meta_title, meta_description,
                             rss_subtitle, comments_default, feed_item_limit, sort_order, show_in_nav,
                             created_at, updated_at
                      FROM cms_blogs",
    'blog_members'=> "SELECT blog_id, user_id, member_role, created_at FROM cms_blog_members",
    'blog_slug_redirects' => "SELECT blog_id, old_slug, created_at FROM cms_blog_slug_redirects",
    'articles'    => "SELECT id, title, slug, perex, content, category_id, blog_id, comments_enabled, image_file,
                             meta_title, meta_description, publish_at, unpublish_at, admin_note, is_featured_in_blog,
                             status, created_at FROM cms_articles",
    'article_tags'=> "SELECT article_id, tag_id FROM cms_article_tags",
    'tags'        => "SELECT id, name, slug, blog_id, created_at FROM cms_tags",
    'pages'       => "SELECT id, title, slug, content, show_in_nav, nav_order,
                             is_published, status, created_at FROM cms_pages",
    'news'          => "SELECT id, title, slug, content, author_id, status, created_at, updated_at,
                               unpublish_at, admin_note, meta_title, meta_description, deleted_at
                        FROM cms_news",
    'chat'          => "SELECT id, name, email, web, message, status, public_visibility, approved_at, approved_by_user_id,
                               internal_note, replied_at, replied_by_user_id, replied_subject, replied_to_email,
                               created_at, updated_at
                        FROM cms_chat",
    'chat_history'  => "SELECT id, chat_id, actor_user_id, event_type, message, created_at
                        FROM cms_chat_history",
    'events'        => "SELECT id, title, slug, event_kind, excerpt, description, program_note, location,
                               organizer_name, organizer_email, registration_url, price_note, accessibility_note,
                               image_file, event_date, event_end, is_published, status, unpublish_at, admin_note,
                               created_at, updated_at FROM cms_events",
    'places'        => "SELECT id, name, slug, place_kind, excerpt, description, url, image_file, category,
                               address, locality, latitude, longitude, contact_phone, contact_email,
                               opening_hours, meta_title, meta_description, is_published, sort_order, status, created_at, updated_at
                               FROM cms_places",
    'gallery_albums'=> "SELECT id, parent_id, name, slug, description, cover_photo_id, created_at, updated_at
                               FROM cms_gallery_albums",
    'gallery_photos'=> "SELECT id, album_id, filename, title, slug, sort_order, created_at
                               FROM cms_gallery_photos",
    'dl_categories' => "SELECT id, name, created_at FROM cms_dl_categories",
    'downloads'     => "SELECT id, title, slug, download_type, dl_category_id, excerpt, description,
                               image_file, version_label, platform_label, license_label, project_url,
                               release_date, requirements, checksum_sha256, series_key, external_url,
                               filename, original_name, file_size, download_count, is_featured, sort_order, is_published, status,
                               created_at, updated_at
                               FROM cms_downloads",
    'food_cards'    => "SELECT id, type, title, slug, description, content, valid_from, valid_to,
                               is_current, is_published, status, created_at, updated_at FROM cms_food_cards",
    'podcast_shows' => "SELECT id, title, slug, description, author, subtitle, cover_image,
                               language, category, owner_name, owner_email, explicit_mode, show_type, feed_complete,
                               feed_episode_limit, website_url, created_at, updated_at FROM cms_podcast_shows",
    'podcasts'      => "SELECT id, show_id, title, slug, description, audio_file, image_file, audio_url, subtitle,
                               duration, episode_num, season_num, episode_type, explicit_mode, block_from_feed,
                               publish_at, status, created_at, updated_at FROM cms_podcasts",
    'polls'         => "SELECT id, question, slug, description, start_date, end_date, status, created_at, updated_at FROM cms_polls",
    'poll_options'  => "SELECT id, poll_id, option_text, sort_order FROM cms_poll_options",
    'faq_categories'=> "SELECT id, name, parent_id, sort_order, created_at FROM cms_faq_categories",
    'faqs'          => "SELECT id, question, slug, excerpt, answer, category_id, meta_title, meta_description,
                               is_published, status, created_at, updated_at FROM cms_faqs",
    'board_categories' => "SELECT id, name, sort_order, created_at FROM cms_board_categories",
    'board'         => "SELECT id, title, slug, board_type, excerpt, description, category_id, posted_date, removal_date,
                               image_file, contact_name, contact_phone, contact_email,
                               filename, original_name, file_size, sort_order, is_pinned, is_published,
                               status, created_at FROM cms_board",
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

logAction('export_json');

$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$filename = 'cms-export-' . date('Y-m-d') . '.json';

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($json));
echo $json;
exit;
