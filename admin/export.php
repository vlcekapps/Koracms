<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();

$data = [
    'exported_at' => date('c'),
    'site'        => 'cms',
    'version'     => 1,
];

$tables = [
    'settings'    => "SELECT `key`, value FROM cms_settings WHERE `key` NOT IN ('admin_password')",
    'categories'  => "SELECT id, name, created_at FROM cms_categories",
    'articles'    => "SELECT id, title, perex, content, category_id, image_file,
                             meta_title, meta_description, publish_at, status, created_at FROM cms_articles",
    'article_tags'=> "SELECT article_id, tag_id FROM cms_article_tags",
    'tags'        => "SELECT id, name, slug, created_at FROM cms_tags",
    'pages'       => "SELECT id, title, slug, content, show_in_nav, nav_order,
                             is_published, status, created_at FROM cms_pages",
    'news'          => "SELECT id, content, status, created_at FROM cms_news",
    'events'        => "SELECT id, title, description, location, event_date, event_end,
                               is_published, status, created_at FROM cms_events",
    'places'        => "SELECT id, name, description, url, category, is_published,
                               sort_order, status, created_at FROM cms_places",
    'gallery_albums'=> "SELECT id, parent_id, name, description, cover_photo_id, created_at
                               FROM cms_gallery_albums",
    'gallery_photos'=> "SELECT id, album_id, filename, title, sort_order, created_at
                               FROM cms_gallery_photos",
    'dl_categories' => "SELECT id, name, created_at FROM cms_dl_categories",
    'downloads'     => "SELECT id, title, dl_category_id, description, filename, original_name,
                               file_size, sort_order, is_published, status, created_at
                               FROM cms_downloads",
    'food_cards'    => "SELECT id, type, title, description, content, valid_from, valid_to,
                               is_current, is_published, status, created_at FROM cms_food_cards",
    'podcast_shows' => "SELECT id, title, slug, description, author, cover_image,
                               language, category, website_url, created_at FROM cms_podcast_shows",
    'podcasts'      => "SELECT id, show_id, title, description, audio_file, audio_url,
                               duration, episode_num, publish_at, status, created_at FROM cms_podcasts",
    'polls'         => "SELECT id, question, start_date, end_date, status, created_at FROM cms_polls",
    'poll_options'  => "SELECT id, poll_id, option_text, sort_order FROM cms_poll_options",
    'faq_categories'=> "SELECT id, name, sort_order, created_at FROM cms_faq_categories",
    'faqs'          => "SELECT id, question, answer, category_id, is_published, sort_order,
                               status, created_at FROM cms_faqs",
    'board_categories' => "SELECT id, name, sort_order, created_at FROM cms_board_categories",
    'board'         => "SELECT id, title, description, category_id, posted_date, removal_date,
                               filename, original_name, file_size, sort_order, is_published,
                               status, created_at FROM cms_board",
    'comments'      => "SELECT id, article_id, author_name, content, is_approved, created_at
                               FROM cms_comments",
    'subscribers'   => "SELECT id, email, confirmed, token, created_at FROM cms_subscribers",
    'newsletters'   => "SELECT id, subject, body, sent_at FROM cms_newsletters",
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
