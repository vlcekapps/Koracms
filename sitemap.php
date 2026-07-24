<?php
require_once __DIR__ . '/db.php';

$isHeadRequest = requireReadOnlyHttpMethod();
sendReadOnlyContentHeaders('application/xml; charset=UTF-8', $isHeadRequest);

$pdo = db_connect();

/**
 * @param string|null $value
 */
function sitemapLastmod(?string $value): string
{
    $normalized = trim((string)$value);
    if ($normalized === '') {
        return '';
    }

    $timestamp = strtotime($normalized);
    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d', $timestamp);
}

function sitemapWriteUrl(string $location, string $changefreq, string $priority, string $lastmod = ''): void
{
    echo "  <url>\n";
    echo '    <loc>' . h($location) . "</loc>\n";
    if ($lastmod !== '') {
        echo '    <lastmod>' . h($lastmod) . "</lastmod>\n";
    }
    echo '    <changefreq>' . h($changefreq) . "</changefreq>\n";
    echo '    <priority>' . h($priority) . "</priority>\n";
    echo "  </url>\n";
}

function sitemapLogSectionError(string $section, \Throwable $e): void
{
    koraLog('warning', 'sitemap section query failed', ['section' => $section, 'exception' => $e]);
}

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php
sitemapWriteUrl(siteUrl('/'), 'daily', '1.0');

try {
    $pages = $pdo->query(
        "SELECT slug, COALESCE(updated_at, created_at) AS sitemap_lastmod
         FROM cms_pages
         WHERE status = 'published' AND is_published = 1
         ORDER BY nav_order, title"
    )->fetchAll();
    foreach ($pages as $page) {
        sitemapWriteUrl(pagePublicUrl($page), 'monthly', '0.8', sitemapLastmod((string)($page['sitemap_lastmod'] ?? '')));
    }
} catch (\PDOException $e) {
    sitemapLogSectionError('pages', $e);
}

try {
    $authors = $pdo->query(
        "SELECT author_slug AS slug, role,
                COALESCE(updated_at, created_at) AS sitemap_lastmod
         FROM cms_users
         WHERE author_public_enabled = 1 AND role != 'public'
         ORDER BY is_superadmin DESC, id ASC"
    )->fetchAll();
    if ($authors !== []) {
        sitemapWriteUrl(authorIndexUrl(), 'weekly', '0.6');
        foreach ($authors as $author) {
            sitemapWriteUrl(authorPublicUrl($author), 'monthly', '0.5', sitemapLastmod((string)($author['sitemap_lastmod'] ?? '')));
        }
    }
} catch (\PDOException $e) {
    sitemapLogSectionError('authors', $e);
}

if (isModuleEnabled('blog')) {
    foreach (getAllBlogs() as $sitemapBlog) {
        sitemapWriteUrl(blogIndexUrl($sitemapBlog), 'daily', '0.8');
    }

    try {
        $articles = $pdo->query(
            "SELECT a.id, a.slug, a.updated_at, b.slug AS blog_slug
             FROM cms_articles a
             LEFT JOIN cms_blogs b ON b.id = a.blog_id
             WHERE a.status = 'published' AND (a.publish_at IS NULL OR a.publish_at <= NOW())
             ORDER BY COALESCE(a.publish_at, a.created_at) DESC, a.id DESC"
        )->fetchAll();
        foreach ($articles as $article) {
            sitemapWriteUrl(articlePublicUrl($article), 'weekly', '0.7', sitemapLastmod((string)($article['updated_at'] ?? '')));
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('blog', $e);
    }

    try {
        $categoryList = $pdo->query(
            "SELECT c.id, c.name, c.slug, c.blog_id, b.slug AS blog_slug,
                    GREATEST(
                        COALESCE(c.updated_at, c.created_at),
                        COALESCE(MAX(a.updated_at), MAX(a.created_at), COALESCE(c.updated_at, c.created_at))
                    ) AS sitemap_lastmod
             FROM cms_categories c
             INNER JOIN cms_blogs b ON b.id = c.blog_id
             LEFT JOIN cms_categories child ON child.parent_id = c.id AND child.blog_id = c.blog_id
             INNER JOIN cms_articles a ON a.blog_id = c.blog_id
                AND (a.category_id = c.id OR a.category_id = child.id)
                AND a.deleted_at IS NULL
                AND a.status = 'published'
                AND (a.publish_at IS NULL OR a.publish_at <= NOW())
             WHERE c.slug <> ''
             GROUP BY c.id, c.name, c.slug, c.blog_id, b.slug, b.sort_order, c.created_at, c.updated_at
             ORDER BY b.sort_order ASC, c.name ASC"
        )->fetchAll();
        foreach ($categoryList as $category) {
            sitemapWriteUrl(
                blogCategoryUrl(['slug' => (string)$category['blog_slug']], $category),
                'weekly',
                '0.5',
                sitemapLastmod((string)($category['sitemap_lastmod'] ?? ''))
            );
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('blog_categories', $e);
    }

    try {
        $tagList = $pdo->query(
            "SELECT t.id, t.name, t.slug, t.blog_id, b.slug AS blog_slug,
                    GREATEST(
                        COALESCE(t.updated_at, t.created_at),
                        COALESCE(MAX(a.updated_at), MAX(a.created_at), COALESCE(t.updated_at, t.created_at))
                    ) AS sitemap_lastmod
             FROM cms_tags t
             INNER JOIN cms_blogs b ON b.id = t.blog_id
             INNER JOIN cms_article_tags at2 ON at2.tag_id = t.id
             INNER JOIN cms_articles a ON a.id = at2.article_id
                AND a.blog_id = t.blog_id
                AND a.deleted_at IS NULL
                AND a.status = 'published'
                AND (a.publish_at IS NULL OR a.publish_at <= NOW())
             WHERE t.slug <> ''
             GROUP BY t.id, t.name, t.slug, t.blog_id, b.slug, b.sort_order, t.created_at, t.updated_at
             ORDER BY b.sort_order ASC, t.name ASC"
        )->fetchAll();
        foreach ($tagList as $tag) {
            sitemapWriteUrl(
                blogTagUrl(['slug' => (string)$tag['blog_slug']], $tag),
                'weekly',
                '0.5',
                sitemapLastmod((string)($tag['sitemap_lastmod'] ?? ''))
            );
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('blog_tags', $e);
    }

    try {
        $seriesList = $pdo->query(
            "SELECT s.id, s.slug, s.blog_id, COALESCE(s.updated_at, s.created_at) AS sitemap_lastmod,
                    b.slug AS blog_slug
             FROM cms_blog_series s
             INNER JOIN cms_blogs b ON b.id = s.blog_id
             WHERE s.is_active = 1
               AND EXISTS (
                   SELECT 1
                   FROM cms_blog_series_items si
                   INNER JOIN cms_articles a ON a.id = si.article_id
                   WHERE si.series_id = s.id
                     AND a.deleted_at IS NULL
                     AND a.status = 'published'
                     AND (a.publish_at IS NULL OR a.publish_at <= NOW())
               )
             ORDER BY b.sort_order ASC, s.sort_order ASC, s.title ASC"
        )->fetchAll();
        foreach ($seriesList as $series) {
            sitemapWriteUrl(
                blogSeriesUrl(['slug' => (string)$series['blog_slug']], $series),
                'weekly',
                '0.6',
                sitemapLastmod((string)($series['sitemap_lastmod'] ?? ''))
            );
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('blog_series', $e);
    }

    try {
        $archiveList = $pdo->query(
            "SELECT DATE_FORMAT(COALESCE(a.publish_at, a.created_at), '%Y-%m') AS archive_key,
                    b.slug AS blog_slug,
                    MAX(COALESCE(a.updated_at, a.created_at)) AS sitemap_lastmod
             FROM cms_articles a
             INNER JOIN cms_blogs b ON b.id = a.blog_id
             WHERE a.deleted_at IS NULL
               AND a.status = 'published'
               AND (a.publish_at IS NULL OR a.publish_at <= NOW())
             GROUP BY archive_key, a.blog_id, b.slug, b.sort_order
             ORDER BY b.sort_order ASC, archive_key DESC"
        )->fetchAll();
        foreach ($archiveList as $archive) {
            $archiveKey = normalizeBlogArchiveKey((string)($archive['archive_key'] ?? ''));
            if ($archiveKey === '') {
                continue;
            }
            sitemapWriteUrl(
                blogArchiveUrl(['slug' => (string)$archive['blog_slug']], $archiveKey),
                'monthly',
                '0.4',
                sitemapLastmod((string)($archive['sitemap_lastmod'] ?? ''))
            );
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('blog_archives', $e);
    }
}

if (isModuleEnabled('news')) {
    sitemapWriteUrl(siteUrl('/news/'), 'weekly', '0.6');

    try {
        $newsItems = $pdo->query(
            "SELECT id, slug, COALESCE(updated_at, created_at) AS sitemap_lastmod
             FROM cms_news
             WHERE " . newsPublicVisibilitySql() . "
             ORDER BY created_at DESC"
        )->fetchAll();
        foreach ($newsItems as $newsItem) {
            sitemapWriteUrl(newsPublicUrl($newsItem), 'monthly', '0.5', sitemapLastmod((string)($newsItem['sitemap_lastmod'] ?? '')));
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('news', $e);
    }
}

if (isModuleEnabled('chat')) {
    sitemapWriteUrl(siteUrl('/chat/'), 'weekly', '0.5');

    try {
        $chatTopics = $pdo->query(
            "SELECT t.id, t.slug,
                    GREATEST(
                        COALESCE(t.updated_at, t.created_at),
                        COALESCE(MAX(c.updated_at), MAX(c.created_at), COALESCE(t.updated_at, t.created_at))
                    ) AS sitemap_lastmod
             FROM cms_chat_topics t
             INNER JOIN cms_chat c ON c.topic_id = t.id
                AND c.conversation_type = 'public'
                AND c.public_visibility = 'approved'
             WHERE t.is_active = 1
             GROUP BY t.id, t.name, t.slug, t.sort_order, t.created_at, t.updated_at
             ORDER BY t.sort_order ASC, t.name ASC"
        )->fetchAll();
        foreach ($chatTopics as $topic) {
            sitemapWriteUrl(
                siteUrl('/chat/tema/' . rawurlencode((string)$topic['slug'])),
                'weekly',
                '0.4',
                sitemapLastmod((string)($topic['sitemap_lastmod'] ?? ''))
            );
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('chat_topics', $e);
    }
}

if (isModuleEnabled('board')) {
    sitemapWriteUrl(siteUrl('/board/'), 'weekly', '0.6');

    try {
        $documents = $pdo->query(
            "SELECT id, slug, COALESCE(created_at, CONCAT(posted_date, ' 00:00:00')) AS sitemap_lastmod
             FROM cms_board
             WHERE " . boardPublicVisibilitySql() . "
             ORDER BY posted_date DESC, id DESC"
        )->fetchAll();
        foreach ($documents as $document) {
            sitemapWriteUrl(boardPublicUrl($document), 'monthly', '0.5', sitemapLastmod((string)($document['sitemap_lastmod'] ?? '')));
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('board', $e);
    }

    try {
        $boardCategories = $pdo->query(
            "SELECT c.id, c.name, c.slug,
                    GREATEST(
                        COALESCE(c.updated_at, c.created_at),
                        COALESCE(MAX(b.created_at), COALESCE(c.updated_at, c.created_at))
                    ) AS sitemap_lastmod
             FROM cms_board_categories c
             INNER JOIN cms_board b ON b.category_id = c.id
                AND " . boardPublicVisibilitySql('b') . "
             WHERE c.slug <> ''
             GROUP BY c.id, c.name, c.slug, c.sort_order, c.created_at, c.updated_at
             ORDER BY c.sort_order ASC, c.name ASC"
        )->fetchAll();
        foreach ($boardCategories as $category) {
            sitemapWriteUrl(
                boardCategoryUrl($category),
                'weekly',
                '0.5',
                sitemapLastmod((string)($category['sitemap_lastmod'] ?? ''))
            );
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('board_categories', $e);
    }
}

if (isModuleEnabled('downloads')) {
    sitemapWriteUrl(siteUrl('/downloads/'), 'weekly', '0.6');

    try {
        $downloads = $pdo->query(
            "SELECT id, slug, COALESCE(updated_at, created_at) AS sitemap_lastmod
             FROM cms_downloads
             WHERE status = 'published' AND is_published = 1 AND deleted_at IS NULL
             ORDER BY created_at DESC, id DESC"
        )->fetchAll();
        foreach ($downloads as $download) {
            sitemapWriteUrl(downloadPublicUrl($download), 'monthly', '0.5', sitemapLastmod((string)($download['sitemap_lastmod'] ?? '')));
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('downloads', $e);
    }

    try {
        $downloadCategories = $pdo->query(
            "SELECT c.id, c.slug, COALESCE(c.updated_at, c.created_at) AS sitemap_lastmod
             FROM cms_dl_categories c
             WHERE c.slug <> ''
               AND EXISTS (
                   SELECT 1
                   FROM cms_downloads d
                   WHERE d.dl_category_id = c.id
                     AND d.deleted_at IS NULL
                     AND d.status = 'published'
                     AND d.is_published = 1
               )
             ORDER BY c.name"
        )->fetchAll();
        foreach ($downloadCategories as $category) {
            sitemapWriteUrl(downloadCategoryUrl($category), 'weekly', '0.5', sitemapLastmod((string)($category['sitemap_lastmod'] ?? '')));
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('download_categories', $e);
    }

    try {
        $downloadSeries = $pdo->query(
            "SELECT s.id, s.slug, COALESCE(s.updated_at, s.created_at) AS sitemap_lastmod
             FROM cms_download_series s
             WHERE s.is_active = 1
               AND s.slug <> ''
               AND EXISTS (
                   SELECT 1
                   FROM cms_downloads d
                   WHERE d.download_series_id = s.id
                     AND d.deleted_at IS NULL
                     AND d.status = 'published'
                     AND d.is_published = 1
               )
             ORDER BY s.sort_order, s.title"
        )->fetchAll();
        foreach ($downloadSeries as $series) {
            sitemapWriteUrl(downloadSeriesUrl($series), 'weekly', '0.5', sitemapLastmod((string)($series['sitemap_lastmod'] ?? '')));
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('download_series', $e);
    }
}

if (isModuleEnabled('appmarket')) {
    sitemapWriteUrl(siteUrl('/aplikace'), 'weekly', '0.6');

    try {
        $appmarketApps = $pdo->query(
            "SELECT a.id, a.slug, COALESCE(a.updated_at, a.published_at, a.created_at) AS sitemap_lastmod
             FROM cms_appmarket_apps a
             WHERE " . appmarketAppPublicVisibilitySql('a') . "
               AND EXISTS (
                   SELECT 1
                   FROM cms_appmarket_releases r
                   WHERE r.app_id = a.id
                     AND " . appmarketReleasePublicVisibilitySql('r') . "
               )
             ORDER BY a.is_featured DESC, a.sort_order, a.name"
        )->fetchAll();
        foreach ($appmarketApps as $appmarketApp) {
            sitemapWriteUrl(
                appmarketAppUrl($appmarketApp),
                'weekly',
                '0.6',
                sitemapLastmod((string)($appmarketApp['sitemap_lastmod'] ?? ''))
            );
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('appmarket_apps', $e);
    }

    try {
        $appmarketReleases = $pdo->query(
            "SELECT r.version_code,
                    a.slug,
                    COALESCE(r.updated_at, r.published_at, r.created_at) AS sitemap_lastmod
             FROM cms_appmarket_releases r
             INNER JOIN cms_appmarket_apps a ON a.id = r.app_id
             WHERE " . appmarketAppPublicVisibilitySql('a') . "
               AND " . appmarketReleasePublicVisibilitySql('r') . "
             ORDER BY r.published_at DESC, r.id DESC"
        )->fetchAll();
        foreach ($appmarketReleases as $appmarketRelease) {
            sitemapWriteUrl(
                siteUrl(str_replace(
                    BASE_URL,
                    '',
                    appmarketReleasePath(
                        (string)$appmarketRelease['slug'],
                        (int)$appmarketRelease['version_code']
                    )
                )),
                'monthly',
                '0.5',
                sitemapLastmod((string)($appmarketRelease['sitemap_lastmod'] ?? ''))
            );
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('appmarket_releases', $e);
    }
}

if (isModuleEnabled('faq')) {
    sitemapWriteUrl(siteUrl('/faq/'), 'weekly', '0.6');

    try {
        $faqCategories = $pdo->query(
            "SELECT c.id, c.slug,
                    GREATEST(
                        COALESCE(c.updated_at, c.created_at),
                        COALESCE(MAX(f.updated_at), MAX(f.created_at), COALESCE(c.updated_at, c.created_at))
                    ) AS sitemap_lastmod
             FROM cms_faq_categories c
             INNER JOIN cms_faqs f ON f.category_id = c.id
                AND " . faqPublicVisibilitySql('f') . "
             WHERE c.slug <> ''
             GROUP BY c.id, c.name, c.slug, c.sort_order, c.created_at, c.updated_at
             ORDER BY c.sort_order ASC, c.name ASC"
        )->fetchAll();
        foreach ($faqCategories as $category) {
            sitemapWriteUrl(
                faqCategoryUrl($category),
                'weekly',
                '0.5',
                sitemapLastmod((string)($category['sitemap_lastmod'] ?? ''))
            );
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('faq_categories', $e);
    }

    try {
        $faqs = $pdo->query(
            "SELECT id, slug, COALESCE(updated_at, created_at) AS sitemap_lastmod
             FROM cms_faqs
             WHERE " . faqPublicVisibilitySql() . "
             ORDER BY created_at DESC, id DESC"
        )->fetchAll();
        foreach ($faqs as $faq) {
            sitemapWriteUrl(faqPublicUrl($faq), 'monthly', '0.5', sitemapLastmod((string)($faq['sitemap_lastmod'] ?? '')));
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('faq', $e);
    }
}

if (isModuleEnabled('food')) {
    sitemapWriteUrl(siteUrl('/food/'), 'weekly', '0.6');
    sitemapWriteUrl(siteUrl('/food/archive.php'), 'weekly', '0.5');

    try {
        $foodCards = $pdo->query(
            "SELECT id, slug, COALESCE(updated_at, created_at, valid_from) AS sitemap_lastmod
             FROM cms_food_cards
             WHERE status = 'published' AND is_published = 1
             ORDER BY COALESCE(valid_from, created_at) DESC, id DESC"
        )->fetchAll();
        foreach ($foodCards as $foodCard) {
            sitemapWriteUrl(foodCardPublicUrl($foodCard), 'monthly', '0.5', sitemapLastmod((string)($foodCard['sitemap_lastmod'] ?? '')));
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('food', $e);
    }
}

if (isModuleEnabled('reservations')) {
    sitemapWriteUrl(siteUrl('/reservations/'), 'weekly', '0.6');

    try {
        $reservationResources = $pdo->query(
            "SELECT id, slug
             FROM cms_res_resources
             WHERE is_active = 1
             ORDER BY name"
        )->fetchAll();
        foreach ($reservationResources as $reservationResource) {
            sitemapWriteUrl(reservationResourcePublicUrl($reservationResource), 'weekly', '0.5');
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('reservations', $e);
    }
}

if (isModuleEnabled('events')) {
    sitemapWriteUrl(siteUrl('/events/'), 'weekly', '0.6');

    try {
        $eventTypes = $pdo->query(
            "SELECT t.id, t.slug, COALESCE(t.updated_at, MAX(e.updated_at), MAX(e.created_at)) AS sitemap_lastmod
             FROM cms_event_types t
             INNER JOIN cms_events e ON e.event_type_id = t.id
             WHERE t.is_active = 1
               AND " . eventPublicVisibilitySql('e') . "
             GROUP BY t.id, t.title, t.slug, t.sort_order, t.updated_at
             ORDER BY t.sort_order, t.title"
        )->fetchAll();
        foreach ($eventTypes as $eventType) {
            sitemapWriteUrl(eventTypeUrl($eventType), 'weekly', '0.5', sitemapLastmod((string)($eventType['sitemap_lastmod'] ?? '')));
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('event_types', $e);
    }

    try {
        $events = $pdo->query(
            "SELECT id, slug, COALESCE(updated_at, created_at, event_date) AS sitemap_lastmod
             FROM cms_events
             WHERE " . eventPublicVisibilitySql() . "
             ORDER BY event_date DESC"
        )->fetchAll();
        foreach ($events as $event) {
            sitemapWriteUrl(eventPublicUrl($event), 'monthly', '0.5', sitemapLastmod((string)($event['sitemap_lastmod'] ?? '')));
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('events', $e);
    }
}

if (isModuleEnabled('podcast')) {
    sitemapWriteUrl(siteUrl('/podcast/'), 'weekly', '0.6');

    try {
        $podcastShows = $pdo->query(
            "SELECT id, slug, COALESCE(updated_at, created_at) AS sitemap_lastmod
             FROM cms_podcast_shows
             WHERE " . podcastShowPublicVisibilitySql() . "
             ORDER BY updated_at DESC, title ASC"
        )->fetchAll();
        foreach ($podcastShows as $podcastShow) {
            sitemapWriteUrl(podcastShowPublicUrl($podcastShow), 'weekly', '0.5', sitemapLastmod((string)($podcastShow['sitemap_lastmod'] ?? '')));
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('podcast_shows', $e);
    }

    try {
        $podcastEpisodes = $pdo->query(
            "SELECT p.id, p.slug, s.slug AS show_slug,
                    COALESCE(p.publish_at, p.updated_at, p.created_at) AS sitemap_lastmod
             FROM cms_podcasts p
             INNER JOIN cms_podcast_shows s ON s.id = p.show_id
             WHERE " . podcastEpisodePublicVisibilitySql('p', 's') . "
             ORDER BY COALESCE(p.publish_at, p.created_at) DESC, p.id DESC"
        )->fetchAll();
        foreach ($podcastEpisodes as $podcastEpisode) {
            sitemapWriteUrl(podcastEpisodePublicUrl($podcastEpisode), 'monthly', '0.5', sitemapLastmod((string)($podcastEpisode['sitemap_lastmod'] ?? '')));
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('podcast_episodes', $e);
    }
}

if (isModuleEnabled('gallery')) {
    sitemapWriteUrl(siteUrl('/gallery/'), 'weekly', '0.6');

    try {
        $galleryAlbums = $pdo->query(
            "SELECT id, slug, COALESCE(updated_at, created_at) AS sitemap_lastmod
             FROM cms_gallery_albums
             WHERE " . galleryAlbumPublicVisibilitySql() . "
             ORDER BY updated_at DESC, id DESC"
        )->fetchAll();
        foreach ($galleryAlbums as $galleryAlbum) {
            sitemapWriteUrl(galleryAlbumPublicUrl($galleryAlbum), 'monthly', '0.5', sitemapLastmod((string)($galleryAlbum['sitemap_lastmod'] ?? '')));
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('gallery_albums', $e);
    }

    try {
        $galleryPhotos = $pdo->query(
            "SELECT p.id, p.slug, p.created_at AS sitemap_lastmod
             FROM cms_gallery_photos p
             INNER JOIN cms_gallery_albums a ON a.id = p.album_id
             WHERE " . galleryPhotoPublicVisibilitySql('p', 'a') . "
             ORDER BY p.created_at DESC, p.id DESC"
        )->fetchAll();
        foreach ($galleryPhotos as $galleryPhoto) {
            sitemapWriteUrl(galleryPhotoPublicUrl($galleryPhoto), 'monthly', '0.4', sitemapLastmod((string)($galleryPhoto['sitemap_lastmod'] ?? '')));
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('gallery_photos', $e);
    }
}

if (isModuleEnabled('places')) {
    sitemapWriteUrl(siteUrl('/places/'), 'weekly', '0.6');

    try {
        $places = $pdo->query(
            "SELECT id, slug, COALESCE(updated_at, created_at) AS sitemap_lastmod
             FROM cms_places
             WHERE " . placePublicVisibilitySql() . "
             ORDER BY name ASC"
        )->fetchAll();
        foreach ($places as $place) {
            sitemapWriteUrl(placePublicUrl($place), 'monthly', '0.5', sitemapLastmod((string)($place['sitemap_lastmod'] ?? '')));
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('places', $e);
    }
}

if (isModuleEnabled('polls')) {
    sitemapWriteUrl(siteUrl('/polls/'), 'weekly', '0.6');

    try {
        $polls = $pdo->query(
            "SELECT id, slug, COALESCE(updated_at, created_at) AS sitemap_lastmod
             FROM cms_polls
             WHERE " . pollPublicVisibilitySql() . "
             ORDER BY COALESCE(start_date, created_at) DESC, id DESC"
        )->fetchAll();
        foreach ($polls as $poll) {
            sitemapWriteUrl(pollPublicUrl($poll), 'monthly', '0.5', sitemapLastmod((string)($poll['sitemap_lastmod'] ?? '')));
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('polls', $e);
    }
}

if (isModuleEnabled('forms')) {
    try {
        $forms = $pdo->query(
            "SELECT id, slug, COALESCE(updated_at, created_at) AS sitemap_lastmod
             FROM cms_forms
             WHERE is_active = 1
             ORDER BY updated_at DESC, id DESC"
        )->fetchAll();
        foreach ($forms as $form) {
            sitemapWriteUrl(formPublicUrl($form), 'monthly', '0.5', sitemapLastmod((string)($form['sitemap_lastmod'] ?? '')));
        }
    } catch (\PDOException $e) {
        sitemapLogSectionError('forms', $e);
    }
}
?>
</urlset>
