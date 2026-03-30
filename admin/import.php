<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$errors  = [];
$success = false;
$summary = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (empty($_FILES['import_file']['name'])) {
        $errors[] = 'Vyberte soubor pro import.';
    } else {
        $tmp  = $_FILES['import_file']['tmp_name'];
        $json = @file_get_contents($tmp);
        $data = $json !== false ? @json_decode($json, true) : null;

        if (!is_array($data) || ($data['site'] ?? '') !== 'cms') {
            $errors[] = 'Neplatný nebo poškozený exportní soubor.';
        } else {
            $pdo = db_connect();
            $pdo->beginTransaction();
            try {
                // Nastavení (přepíše jen existující klíče, heslo neimportujeme)
                if (!empty($data['settings']) && is_array($data['settings'])) {
                    $s = $pdo->prepare(
                        "INSERT INTO cms_settings (`key`, value) VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE value = VALUES(value)"
                    );
                    $skip = ['admin_password'];
                    foreach ($data['settings'] as $row) {
                        if (in_array($row['key'], $skip, true)) continue;
                        $s->execute([$row['key'], $row['value']]);
                    }
                    $summary[] = 'Nastavení importována.';
                }

                // Kategorie (přidá jen chybějící podle jména)
                if (!empty($data['categories']) && is_array($data['categories'])) {
                    $ins = $pdo->prepare("INSERT IGNORE INTO cms_categories (id, name, blog_id) VALUES (?,?,?)");
                    foreach ($data['categories'] as $row) {
                        $ins->execute([(int)$row['id'], $row['name'], max(1, (int)($row['blog_id'] ?? 1))]);
                    }
                    $summary[] = 'Kategorie importovány.';
                }

                // Tagy
                if (!empty($data['tags']) && is_array($data['tags'])) {
                    $ins = $pdo->prepare("INSERT IGNORE INTO cms_tags (id, name, slug, blog_id) VALUES (?,?,?,?)");
                    foreach ($data['tags'] as $row) {
                        $ins->execute([(int)$row['id'], $row['name'], $row['slug'], max(1, (int)($row['blog_id'] ?? 1))]);
                    }
                    $summary[] = 'Tagy importovány.';
                }

                // Blogy
                if (!empty($data['blogs']) && is_array($data['blogs'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_blogs
                         (id, name, slug, description, intro_content, logo_file, meta_title, meta_description,
                          rss_subtitle, comments_default, feed_item_limit, sort_order, show_in_nav, created_at, updated_at)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                    );
                    foreach ($data['blogs'] as $row) {
                        $ins->execute([
                            (int)$row['id'], $row['name'], $row['slug'],
                            $row['description'] ?? '',
                            $row['intro_content'] ?? '',
                            $row['logo_file'] ?? '',
                            $row['meta_title'] ?? '',
                            $row['meta_description'] ?? '',
                            $row['rss_subtitle'] ?? '',
                            (int)($row['comments_default'] ?? 1),
                            max(1, min(100, (int)($row['feed_item_limit'] ?? 20))),
                            (int)($row['sort_order'] ?? 0),
                            (int)($row['show_in_nav'] ?? 1),
                            $row['created_at'] ?? date('Y-m-d H:i:s'),
                            $row['updated_at'] ?? ($row['created_at'] ?? date('Y-m-d H:i:s')),
                        ]);
                    }
                    $summary[] = 'Blogy importovány.';
                }

                if (!empty($data['blog_members']) && is_array($data['blog_members'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_blog_members (blog_id, user_id, member_role, created_at)
                         VALUES (?,?,?,?)"
                    );
                    foreach ($data['blog_members'] as $row) {
                        $userId = (int)($row['user_id'] ?? 0);
                        if ($userId <= 0) {
                            continue;
                        }
                        $memberRole = in_array((string)($row['member_role'] ?? ''), ['author', 'manager'], true)
                            ? (string)$row['member_role']
                            : 'author';
                        $ins->execute([
                            max(1, (int)($row['blog_id'] ?? 1)),
                            $userId,
                            $memberRole,
                            $row['created_at'] ?? date('Y-m-d H:i:s'),
                        ]);
                    }
                    $summary[] = 'Členové týmů blogů importováni.';
                }

                if (!empty($data['blog_slug_redirects']) && is_array($data['blog_slug_redirects'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_blog_slug_redirects (blog_id, old_slug, created_at)
                         VALUES (?,?,?)"
                    );
                    foreach ($data['blog_slug_redirects'] as $row) {
                        $oldSlug = slugify((string)($row['old_slug'] ?? ''));
                        if ($oldSlug === '') {
                            continue;
                        }
                        $ins->execute([
                            max(1, (int)($row['blog_id'] ?? 1)),
                            $oldSlug,
                            $row['created_at'] ?? date('Y-m-d H:i:s'),
                        ]);
                    }
                    $summary[] = 'Redirecty starých slugů blogů importovány.';
                }

                // Články
                if (!empty($data['articles']) && is_array($data['articles'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_articles
                         (id, title, slug, perex, content, category_id, blog_id, comments_enabled, image_file,
                          meta_title, meta_description, publish_at, unpublish_at, admin_note, is_featured_in_blog, status, created_at)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                    );
                    foreach ($data['articles'] as $row) {
                        $importBlogId = (int)($row['blog_id'] ?? 1);
                        $importSlug = articleSlug((string)($row['slug'] ?? ''));
                        if ($importSlug === '') {
                            $importSlug = uniqueArticleSlug($pdo, (string)($row['title'] ?? 'clanek'), null, $importBlogId);
                        } else {
                            $importSlug = uniqueArticleSlug($pdo, $importSlug, (int)$row['id'], $importBlogId);
                        }
                        $ins->execute([
                            (int)$row['id'], $row['title'], $importSlug, $row['perex'] ?? '',
                            $row['content'] ?? '', $row['category_id'] ?: null,
                            $importBlogId,
                            (int)($row['comments_enabled'] ?? 1),
                            $row['image_file'] ?? '',
                            $row['meta_title'] ?? '', $row['meta_description'] ?? '',
                            $row['publish_at'] ?: null,
                            $row['unpublish_at'] ?: null,
                            $row['admin_note'] ?? '',
                            (int)($row['is_featured_in_blog'] ?? 0),
                            $row['status'] ?? 'published', $row['created_at'],
                        ]);
                    }
                    $summary[] = 'Články importovány.';
                }

                // Statické stránky
                if (!empty($data['pages']) && is_array($data['pages'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_pages
                         (id, title, slug, content, show_in_nav, nav_order, is_published, status)
                         VALUES (?,?,?,?,?,?,?,?)"
                    );
                    foreach ($data['pages'] as $row) {
                        $importTitle = trim((string)($row['title'] ?? ''));
                        if ($importTitle === '') {
                            continue;
                        }
                        $importSlug = trim((string)($row['slug'] ?? ''));
                        if ($importSlug === '') {
                            $importSlug = uniquePageSlug($pdo, $importTitle);
                        } else {
                            $importSlug = uniquePageSlug($pdo, $importSlug, (int)$row['id']);
                        }
                        $ins->execute([
                            (int)$row['id'], $importTitle, $importSlug,
                            $row['content'] ?? '', (int)$row['show_in_nav'],
                            (int)$row['nav_order'], (int)$row['is_published'],
                            $row['status'] ?? 'published',
                        ]);
                    }
                    $summary[] = 'Stránky importovány.';
                }

                // Novinky
                if (!empty($data['news']) && is_array($data['news'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_news (id, title, slug, content, status, created_at, updated_at)
                         VALUES (?,?,?,?,?,?,?)"
                    );
                    foreach ($data['news'] as $row) {
                        $importTitle = newsTitleCandidate((string)($row['title'] ?? ''), (string)($row['content'] ?? ''));
                        $importSlug = newsSlug((string)($row['slug'] ?? ''));
                        if ($importSlug === '') {
                            $importSlug = uniqueNewsSlug($pdo, $importTitle);
                        } else {
                            $importSlug = uniqueNewsSlug($pdo, $importSlug, (int)$row['id']);
                        }
                        $ins->execute([
                            (int)$row['id'],
                            $importTitle,
                            $importSlug,
                            $row['content'] ?? '',
                            $row['status'] ?? 'published',
                            $row['created_at'],
                            $row['updated_at'] ?? $row['created_at'],
                        ]);
                    }
                    $summary[] = 'Novinky importovány.';
                }

                // Události
                if (!empty($data['events']) && is_array($data['events'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_events
                         (id, title, slug, event_kind, excerpt, description, program_note, location,
                          organizer_name, organizer_email, registration_url, price_note, accessibility_note,
                          image_file, event_date, event_end, is_published, status, unpublish_at, admin_note,
                          created_at, updated_at)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                    );
                    foreach ($data['events'] as $row) {
                        $importTitle = trim((string)($row['title'] ?? ''));
                        if ($importTitle === '') {
                            $importTitle = 'Událost';
                        }
                        $importSlug = eventSlug((string)($row['slug'] ?? ''));
                        if ($importSlug === '') {
                            $importSlug = uniqueEventSlug($pdo, $importTitle);
                        } else {
                            $importSlug = uniqueEventSlug($pdo, $importSlug, (int)$row['id']);
                        }
                        $ins->execute([
                            (int)$row['id'],
                            $importTitle,
                            $importSlug,
                            normalizeEventKind((string)($row['event_kind'] ?? 'general')),
                            $row['excerpt'] ?? '',
                            $row['description'] ?? '',
                            $row['program_note'] ?? '',
                            $row['location'] ?? '',
                            trim((string)($row['organizer_name'] ?? '')),
                            trim((string)($row['organizer_email'] ?? '')),
                            normalizeDownloadExternalUrl((string)($row['registration_url'] ?? '')),
                            trim((string)($row['price_note'] ?? '')),
                            $row['accessibility_note'] ?? '',
                            trim((string)($row['image_file'] ?? '')),
                            $row['event_date'],
                            $row['event_end'] ?: null,
                            (int)($row['is_published'] ?? 1),
                            in_array(($row['status'] ?? 'published'), ['pending', 'published'], true) ? $row['status'] : 'published',
                            !empty($row['unpublish_at']) ? $row['unpublish_at'] : null,
                            $row['admin_note'] ?? '',
                            $row['created_at'] ?? date('Y-m-d H:i:s'),
                            $row['updated_at'] ?? ($row['created_at'] ?? date('Y-m-d H:i:s')),
                        ]);
                    }
                    $summary[] = 'Události importovány.';
                }

                // Místa
                if (!empty($data['places']) && is_array($data['places'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_places
                         (id, name, slug, place_kind, excerpt, description, url, image_file, category,
                          address, locality, latitude, longitude, contact_phone, contact_email,
                          opening_hours, is_published, sort_order, status, created_at, updated_at)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                    );
                    foreach ($data['places'] as $row) {
                        $importName = trim((string)($row['name'] ?? ''));
                        if ($importName === '') {
                            continue;
                        }
                        $importSlug = uniquePlaceSlug(
                            $pdo,
                            trim((string)($row['slug'] ?? '')) !== '' ? (string)$row['slug'] : $importName,
                            (int)($row['id'] ?? 0)
                        );
                        $ins->execute([
                            (int)$row['id'],
                            $importName,
                            $importSlug,
                            normalizePlaceKind((string)($row['place_kind'] ?? 'sight')),
                            $row['excerpt'] ?? '',
                            $row['description'] ?? '',
                            normalizePlaceUrl((string)($row['url'] ?? '')),
                            $row['image_file'] ?? '',
                            $row['category'] ?? '',
                            $row['address'] ?? '',
                            $row['locality'] ?? '',
                            $row['latitude'] !== '' ? ($row['latitude'] ?? null) : null,
                            $row['longitude'] !== '' ? ($row['longitude'] ?? null) : null,
                            $row['contact_phone'] ?? '',
                            $row['contact_email'] ?? '',
                            $row['opening_hours'] ?? '',
                            (int)($row['is_published'] ?? 1),
                            (int)($row['sort_order'] ?? 0),
                            $row['status'] ?? 'published',
                            $row['created_at'] ?? date('Y-m-d H:i:s'),
                            $row['updated_at'] ?? ($row['created_at'] ?? date('Y-m-d H:i:s')),
                        ]);
                    }
                    $summary[] = 'Zajímavá místa importována.';
                }

                // Galerie – alba (nejdřív, fotky odkazují na album_id)
                if (!empty($data['gallery_albums']) && is_array($data['gallery_albums'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_gallery_albums (id, parent_id, name, slug, description, cover_photo_id, created_at, updated_at)
                         VALUES (?,?,?,?,?,?,?,?)"
                    );
                    foreach ($data['gallery_albums'] as $row) {
                        $albumName = trim((string)($row['name'] ?? ''));
                        if ($albumName === '') {
                            $albumName = 'Album';
                        }
                        $albumSlug = galleryAlbumSlug((string)($row['slug'] ?? ''));
                        if ($albumSlug === '') {
                            $albumSlug = uniqueGalleryAlbumSlug($pdo, $albumName);
                        } else {
                            $albumSlug = uniqueGalleryAlbumSlug($pdo, $albumSlug, (int)$row['id']);
                        }
                        $createdAt = !empty($row['created_at']) ? (string)$row['created_at'] : date('Y-m-d H:i:s');
                        $updatedAt = !empty($row['updated_at']) ? (string)$row['updated_at'] : $createdAt;
                        $ins->execute([
                            (int)$row['id'],
                            $row['parent_id'] ?: null,
                            $albumName,
                            $albumSlug,
                            $row['description'] ?? '',
                            $row['cover_photo_id'] ?: null,
                            $createdAt,
                            $updatedAt,
                        ]);
                    }
                    $summary[] = 'Galerie – alba importována.';
                }

                // Galerie – fotografie
                if (!empty($data['gallery_photos']) && is_array($data['gallery_photos'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_gallery_photos
                         (id, album_id, filename, title, slug, sort_order, created_at)
                         VALUES (?,?,?,?,?,?,?)"
                    );
                    foreach ($data['gallery_photos'] as $row) {
                        $photoTitle = (string)($row['title'] ?? '');
                        $photoSlugCandidate = trim((string)($row['slug'] ?? ''));
                        if ($photoSlugCandidate === '') {
                            $photoSlugCandidate = $photoTitle !== ''
                                ? $photoTitle
                                : pathinfo((string)($row['filename'] ?? ''), PATHINFO_FILENAME);
                        }
                        $photoSlug = uniqueGalleryPhotoSlug($pdo, $photoSlugCandidate, (int)$row['id']);
                        $ins->execute([
                            (int)$row['id'],
                            (int)$row['album_id'],
                            $row['filename'],
                            $photoTitle,
                            $photoSlug,
                            (int)$row['sort_order'],
                            $row['created_at'] ?? date('Y-m-d H:i:s'),
                        ]);
                    }
                    $summary[] = 'Galerie – fotografie importovány.';
                }

                // Kategorie ke stažení
                if (!empty($data['dl_categories']) && is_array($data['dl_categories'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_dl_categories (id, name) VALUES (?,?)"
                    );
                    foreach ($data['dl_categories'] as $row) {
                        $ins->execute([(int)$row['id'], $row['name']]);
                    }
                    $summary[] = 'Kategorie ke stažení importovány.';
                }

                // Soubory ke stažení
                if (!empty($data['downloads']) && is_array($data['downloads'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_downloads
                         (id, title, slug, download_type, dl_category_id, excerpt, description,
                          image_file, version_label, platform_label, license_label, project_url,
                          release_date, requirements, checksum_sha256, series_key, external_url,
                          filename, original_name, file_size, download_count, is_featured, sort_order, is_published, status,
                          created_at, updated_at)
                          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                    );
                    foreach ($data['downloads'] as $row) {
                        $catId = $row['dl_category_id'] ?? null;
                        $title = (string)($row['title'] ?? 'Soubor ke stažení');
                        $createdAt = !empty($row['created_at']) ? (string)$row['created_at'] : date('Y-m-d H:i:s');
                        $updatedAt = !empty($row['updated_at']) ? (string)$row['updated_at'] : $createdAt;
                        $slug = uniqueDownloadSlug(
                            $pdo,
                            (string)($row['slug'] ?? '') !== '' ? (string)$row['slug'] : $title,
                            (int)$row['id']
                        );
                        $ins->execute([
                            (int)$row['id'],
                            $title,
                            $slug,
                            normalizeDownloadType((string)($row['download_type'] ?? 'document')),
                            $catId !== '' ? $catId : null,
                            $row['excerpt'] ?? '',
                            $row['description'] ?? '',
                            $row['image_file'] ?? '',
                            $row['version_label'] ?? '',
                            $row['platform_label'] ?? '',
                            $row['license_label'] ?? '',
                            normalizeDownloadExternalUrl((string)($row['project_url'] ?? '')),
                            !empty($row['release_date']) ? (string)$row['release_date'] : null,
                            $row['requirements'] ?? '',
                            normalizeDownloadChecksum((string)($row['checksum_sha256'] ?? '')),
                            normalizeDownloadSeriesKey((string)($row['series_key'] ?? '')),
                            normalizeDownloadExternalUrl((string)($row['external_url'] ?? '')),
                            $row['filename'] ?? '',
                            $row['original_name'] ?? '',
                            (int)($row['file_size'] ?? 0),
                            (int)($row['download_count'] ?? 0),
                            (int)($row['is_featured'] ?? 0),
                            (int)($row['sort_order'] ?? 0),
                            (int)($row['is_published'] ?? 1),
                            $row['status'] ?? 'published',
                            $createdAt,
                            $updatedAt,
                        ]);
                    }
                    $summary[] = 'Soubory ke stažení importovány.';
                }

                // Jídelní / nápojové lístky
                if (!empty($data['food_cards']) && is_array($data['food_cards'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_food_cards
                         (id, type, title, slug, description, content, valid_from, valid_to,
                          is_current, is_published, status, created_at, updated_at)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
                    );
                    foreach ($data['food_cards'] as $row) {
                        $title = trim((string)($row['title'] ?? ''));
                        if ($title === '') {
                            $title = 'Lístek';
                        }
                        $importSlug = trim((string)($row['slug'] ?? ''));
                        if ($importSlug === '') {
                            $importSlug = uniqueFoodCardSlug($pdo, $title);
                        } else {
                            $importSlug = uniqueFoodCardSlug($pdo, $importSlug, (int)$row['id']);
                        }
                        $createdAt = $row['created_at'] ?? date('Y-m-d H:i:s');
                        $updatedAt = $row['updated_at'] ?? $createdAt;
                        $ins->execute([
                            (int)$row['id'],
                            $row['type'] ?? 'food',
                            $title,
                            $importSlug,
                            $row['description'] ?? '',
                            $row['content'] ?? '',
                            $row['valid_from'] ?: null,
                            $row['valid_to'] ?: null,
                            (int)($row['is_current'] ?? 0),
                            (int)($row['is_published'] ?? 1),
                            $row['status'] ?? 'published',
                            $createdAt,
                            $updatedAt,
                        ]);
                    }
                    $summary[] = 'Jídelní lístky importovány.';
                }

                // Podcast shows (nejdřív – epizody na ně odkazují přes show_id)
                if (!empty($data['podcast_shows']) && is_array($data['podcast_shows'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_podcast_shows
                         (id, title, slug, description, author, subtitle, cover_image, language, category, owner_name, owner_email,
                          explicit_mode, show_type, feed_complete, feed_episode_limit, website_url, created_at, updated_at)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                    );
                    foreach ($data['podcast_shows'] as $row) {
                        $title = trim((string)($row['title'] ?? ''));
                        if ($title === '') {
                            $title = 'Podcast';
                        }
                        $slug = podcastShowSlug((string)($row['slug'] ?? ''));
                        if ($slug === '') {
                            $slug = uniquePodcastShowSlug($pdo, $title);
                        } else {
                            $slug = uniquePodcastShowSlug($pdo, $slug, (int)$row['id']);
                        }
                        $createdAt = !empty($row['created_at']) ? (string)$row['created_at'] : date('Y-m-d H:i:s');
                        $updatedAt = !empty($row['updated_at']) ? (string)$row['updated_at'] : $createdAt;
                        $ins->execute([
                            (int)$row['id'], $title, $slug,
                            $row['description'] ?? '', $row['author'] ?? '', $row['subtitle'] ?? '',
                            $row['cover_image'] ?? '', $row['language'] ?? 'cs',
                            $row['category'] ?? '', $row['owner_name'] ?? '',
                            filter_var((string)($row['owner_email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '',
                            in_array((string)($row['explicit_mode'] ?? ''), ['no', 'clean', 'yes'], true) ? (string)$row['explicit_mode'] : 'no',
                            in_array((string)($row['show_type'] ?? ''), ['episodic', 'serial'], true) ? (string)$row['show_type'] : 'episodic',
                            !empty($row['feed_complete']) ? 1 : 0,
                            max(1, min(1000, (int)($row['feed_episode_limit'] ?? 100))),
                            normalizePodcastWebsiteUrl((string)($row['website_url'] ?? '')),
                            $createdAt, $updatedAt,
                        ]);
                    }
                    $summary[] = 'Podcast shows importovány.';
                }

                // Epizody podcastů
                if (!empty($data['podcasts']) && is_array($data['podcasts'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_podcasts
                          (id, show_id, title, slug, description, audio_file, image_file, audio_url, subtitle,
                           duration, episode_num, season_num, episode_type, explicit_mode, block_from_feed,
                           publish_at, status, created_at, updated_at)
                          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                    );
                    foreach ($data['podcasts'] as $row) {
                        $showId = max(1, (int)($row['show_id'] ?? 1));
                        $title = trim((string)($row['title'] ?? ''));
                        if ($title === '') {
                            $title = 'Epizoda';
                        }
                        $slug = podcastEpisodeSlug((string)($row['slug'] ?? ''));
                        if ($slug === '') {
                            $slug = uniquePodcastEpisodeSlug($pdo, $showId, $title);
                        } else {
                            $slug = uniquePodcastEpisodeSlug($pdo, $showId, $slug, (int)$row['id']);
                        }
                        $createdAt = !empty($row['created_at']) ? (string)$row['created_at'] : date('Y-m-d H:i:s');
                        $updatedAt = !empty($row['updated_at']) ? (string)$row['updated_at'] : $createdAt;
                        $ins->execute([
                            (int)$row['id'], $showId,
                            $title, $slug, $row['description'] ?? '',
                            $row['audio_file'] ?? '', $row['image_file'] ?? '',
                            normalizePodcastEpisodeAudioUrl((string)($row['audio_url'] ?? '')), $row['subtitle'] ?? '',
                            $row['duration'] ?? '', !empty($row['episode_num']) ? (int)$row['episode_num'] : null,
                            !empty($row['season_num']) ? (int)$row['season_num'] : null,
                            in_array((string)($row['episode_type'] ?? ''), ['full', 'trailer', 'bonus'], true) ? (string)$row['episode_type'] : 'full',
                            in_array((string)($row['explicit_mode'] ?? ''), ['inherit', 'no', 'clean', 'yes'], true) ? (string)$row['explicit_mode'] : 'inherit',
                            !empty($row['block_from_feed']) ? 1 : 0,
                            $row['publish_at'] ?: null, $row['status'] ?? 'published', $createdAt, $updatedAt,
                        ]);
                    }
                    $summary[] = 'Epizody podcastů importovány.';
                }

                // Ankety (nejdřív ankety, pak možnosti)
                if (!empty($data['polls']) && is_array($data['polls'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_polls
                         (id, question, slug, description, start_date, end_date, status, created_at, updated_at)
                         VALUES (?,?,?,?,?,?,?,?,?)"
                    );
                    foreach ($data['polls'] as $row) {
                        $question = trim((string)($row['question'] ?? ''));
                        if ($question === '') {
                            $question = 'Anketa';
                        }
                        $slug = pollSlug((string)($row['slug'] ?? ''));
                        if ($slug === '') {
                            $slug = uniquePollSlug($pdo, $question);
                        } else {
                            $slug = uniquePollSlug($pdo, $slug, (int)$row['id']);
                        }
                        $createdAt = !empty($row['created_at']) ? (string)$row['created_at'] : date('Y-m-d H:i:s');
                        $updatedAt = !empty($row['updated_at']) ? (string)$row['updated_at'] : $createdAt;
                        $ins->execute([
                            (int)$row['id'],
                            $question,
                            $slug,
                            $row['description'] ?? '',
                            $row['start_date'] ?: null,
                            $row['end_date'] ?: null,
                            $row['status'] ?? 'active',
                            $createdAt,
                            $updatedAt,
                        ]);
                    }
                    $summary[] = 'Ankety importovány.';
                }

                // Možnosti ankety
                if (!empty($data['poll_options']) && is_array($data['poll_options'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_poll_options
                         (id, poll_id, option_text, sort_order)
                         VALUES (?,?,?,?)"
                    );
                    foreach ($data['poll_options'] as $row) {
                        $ins->execute([
                            (int)$row['id'], (int)$row['poll_id'],
                            $row['option_text'], (int)($row['sort_order'] ?? 0),
                        ]);
                    }
                    $summary[] = 'Možnosti anket importovány.';
                }

                // FAQ kategorie
                if (!empty($data['faq_categories']) && is_array($data['faq_categories'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_faq_categories (id, name, sort_order) VALUES (?,?,?)"
                    );
                    foreach ($data['faq_categories'] as $row) {
                        $ins->execute([(int)$row['id'], $row['name'], (int)($row['sort_order'] ?? 0)]);
                    }
                    $summary[] = 'FAQ kategorie importovány.';
                }

                // FAQ
                if (!empty($data['faqs']) && is_array($data['faqs'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_faqs
                         (id, question, slug, excerpt, answer, category_id, is_published, sort_order, status, created_at, updated_at)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?)"
                    );
                    foreach ($data['faqs'] as $row) {
                        $importQuestion = trim((string)($row['question'] ?? ''));
                        if ($importQuestion === '') {
                            $importQuestion = 'Otázka';
                        }
                        $importSlug = faqSlug((string)($row['slug'] ?? ''));
                        if ($importSlug === '') {
                            $importSlug = uniqueFaqSlug($pdo, $importQuestion);
                        } else {
                            $importSlug = uniqueFaqSlug($pdo, $importSlug, (int)$row['id']);
                        }
                        $ins->execute([
                            (int)$row['id'],
                            $importQuestion,
                            $importSlug,
                            $row['excerpt'] ?? '',
                            $row['answer'] ?? '',
                            $row['category_id'] ?: null,
                            (int)($row['is_published'] ?? 1),
                            (int)($row['sort_order'] ?? 0),
                            $row['status'] ?? 'published',
                            $row['created_at'] ?? date('Y-m-d H:i:s'),
                            $row['updated_at'] ?? ($row['created_at'] ?? date('Y-m-d H:i:s')),
                        ]);
                    }
                    $summary[] = 'FAQ importovány.';
                }

                // Kategorie úřední desky
                if (!empty($data['board_categories']) && is_array($data['board_categories'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_board_categories (id, name, sort_order) VALUES (?,?,?)"
                    );
                    foreach ($data['board_categories'] as $row) {
                        $ins->execute([(int)$row['id'], $row['name'], (int)($row['sort_order'] ?? 0)]);
                    }
                    $summary[] = 'Kategorie úřední desky importovány.';
                }

                // Úřední deska
                if (!empty($data['board']) && is_array($data['board'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_board
                         (id, title, slug, board_type, excerpt, description, category_id, posted_date, removal_date,
                          image_file, contact_name, contact_phone, contact_email,
                          filename, original_name, file_size, sort_order, is_pinned, is_published, status, created_at)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                    );
                    foreach ($data['board'] as $row) {
                        $importTitle = trim((string)($row['title'] ?? ''));
                        if ($importTitle === '') {
                            $importTitle = 'Dokument';
                        }
                        $importSlug = boardSlug((string)($row['slug'] ?? ''));
                        if ($importSlug === '') {
                            $importSlug = uniqueBoardSlug($pdo, $importTitle);
                        } else {
                            $importSlug = uniqueBoardSlug($pdo, $importSlug, (int)$row['id']);
                        }
                        $ins->execute([
                            (int)$row['id'],
                            $importTitle,
                            $importSlug,
                            normalizeBoardType((string)($row['board_type'] ?? 'document')),
                            $row['excerpt'] ?? '',
                            $row['description'] ?? '',
                            $row['category_id'] ?: null,
                            $row['posted_date'],
                            $row['removal_date'] ?: null,
                            $row['image_file'] ?? '',
                            $row['contact_name'] ?? '',
                            $row['contact_phone'] ?? '',
                            $row['contact_email'] ?? '',
                            $row['filename'] ?? '',
                            $row['original_name'] ?? '',
                            (int)($row['file_size'] ?? 0),
                            (int)($row['sort_order'] ?? 0),
                            (int)($row['is_pinned'] ?? 0),
                            (int)($row['is_published'] ?? 1),
                            $row['status'] ?? 'published',
                            $row['created_at'] ?? date('Y-m-d H:i:s'),
                        ]);
                    }
                    $summary[] = 'Úřední deska importována.';
                }

                // Komentáře
                if (!empty($data['comments']) && is_array($data['comments'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_comments
                         (id, article_id, author_name, author_email, content, status, is_approved, created_at)
                         VALUES (?,?,?,?,?,?,?,?)"
                    );
                    foreach ($data['comments'] as $row) {
                        $status = normalizeCommentStatus((string)($row['status'] ?? ((int)($row['is_approved'] ?? 0) === 1 ? 'approved' : 'pending')));
                        $ins->execute([
                            (int)$row['id'], (int)$row['article_id'],
                            $row['author_name'], $row['author_email'] ?? '', $row['content'],
                            $status, commentStatusApprovalValue($status), $row['created_at'],
                        ]);
                    }
                    $summary[] = 'Komentáře importovány.';
                }

                // Odběratelé newsletteru
                if (!empty($data['subscribers']) && is_array($data['subscribers'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_subscribers
                         (id, email, confirmed, token, created_at)
                         VALUES (?,?,?,?,?)"
                    );
                    foreach ($data['subscribers'] as $row) {
                        $ins->execute([
                            (int)$row['id'], $row['email'],
                            (int)($row['confirmed'] ?? 0), $row['token'] ?? '',
                            $row['created_at'],
                        ]);
                    }
                    $summary[] = 'Odběratelé importováni.';
                }

                // Newslettery
                if (!empty($data['newsletters']) && is_array($data['newsletters'])) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO cms_newsletters
                         (id, subject, body, recipient_count, sent_at, created_at)
                         VALUES (?,?,?,?,?,?,?)"
                    );
                    foreach ($data['newsletters'] as $row) {
                        $ins->execute([
                            (int)$row['id'],
                            $row['subject'],
                            $row['body'],
                            (int)($row['recipient_count'] ?? 0),
                            $row['sent_at'] ?? null,
                            $row['created_at'] ?? date('Y-m-d H:i:s'),
                        ]);
                    }
                    $summary[] = 'Newslettery importovány.';
                }

                $pdo->commit();
                clearSettingsCache();
                logAction('import_json');
                $success = true;
            } catch (\PDOException $e) {
                $pdo->rollBack();
                $errors[] = 'Chyba databáze: ' . h($e->getMessage());
            }
        }
    }
}

adminHeader('Import / Export dat');
?>

<h2>Export dat</h2>
<p>Stáhne veškerý obsah (články, novinky, stránky, události, galerie, místa, soubory ke stažení,
   jídelní lístky, podcasty, ankety, FAQ, úřední desku, komentáře, odběratele a newslettery, nastavení) jako JSON soubor.
   Heslo administrátora se neexportuje.
   Nahrané soubory (obrázky, audio, přílohy) je třeba přenést ručně ze složky <code>uploads/</code>.</p>
<p>
  <a href="export.php" class="btn">Stáhnout zálohu (JSON)</a>
</p>

<h2>Import dat</h2>
<p>Importuje obsah z dříve staženého JSON souboru.
   Existující záznamy (stejné ID) jsou přeskočeny – data nebudou přepsána.</p>

<?php if ($success): ?>
  <p class="success" role="status"><strong>Import proběhl úspěšně.</strong>
    <?= implode(' ', array_map('h', $summary)) ?></p>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <ul class="error" role="alert">
    <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
  </ul>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <label for="import_file">JSON soubor (export z tohoto CMS)</label>
  <input type="file" id="import_file" name="import_file" accept=".json,application/json" required aria-required="true">
  <button type="submit" class="btn" style="margin-top:1rem">Importovat</button>
</form>

<?php adminFooter(); ?>
