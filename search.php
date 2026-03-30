<?php
require_once __DIR__ . '/db.php';
checkMaintenanceMode();

$siteName = getSetting('site_name', 'Kora CMS');
$q        = trim($_GET['q'] ?? '');
$results  = [];

if ($q !== '' && mb_strlen($q) >= 2) {
    rateLimit('search', 30, 60);
    $pdo  = db_connect();
    $like = '%' . $q . '%';

    // Pomocná funkce: fulltextový dotaz s fallback na LIKE
    $ftSearch = static function (
        PDO $pdo,
        string $query,
        string $like,
        string $selectSql,
        string $fromWhere,
        string $ftColumns,
        array $extraParams,
        string $orderBy,
        int $limit
    ): array {
        // Zkusíme FULLTEXT (NATURAL LANGUAGE MODE, min 3 znaky v MySQL)
        if (mb_strlen($query) >= 3) {
            try {
                $sql = "{$selectSql}, MATCH({$ftColumns}) AGAINST(? IN NATURAL LANGUAGE MODE) AS _relevance"
                     . " {$fromWhere} AND MATCH({$ftColumns}) AGAINST(? IN NATURAL LANGUAGE MODE)"
                     . " ORDER BY _relevance DESC LIMIT {$limit}";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_merge($extraParams, [$query, $query]));
                $rows = $stmt->fetchAll();
                if (!empty($rows)) {
                    return $rows;
                }
            } catch (\PDOException $e) {
                // FULLTEXT index nemusí existovat – fallback na LIKE
            }
        }

        // Fallback: LIKE
        $likeColumns = array_map('trim', explode(',', $ftColumns));
        $likeConds = implode(' OR ', array_map(fn(string $col) => "{$col} LIKE ?", $likeColumns));
        $sql = $selectSql . " {$fromWhere} AND ({$likeConds}) ORDER BY {$orderBy} LIMIT {$limit}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($extraParams, array_fill(0, count($likeColumns), $like)));
        return $stmt->fetchAll();
    };

    if (isModuleEnabled('blog')) {
        try {
            foreach ($ftSearch(
                $pdo, $q, $like,
                "SELECT a.id, a.title, a.slug, a.perex, a.created_at, 'blog' AS type, b.slug AS blog_slug",
                "FROM cms_articles a LEFT JOIN cms_blogs b ON b.id = a.blog_id WHERE a.status = 'published' AND (a.publish_at IS NULL OR a.publish_at <= NOW())",
                'title, perex, content',
                [],
                'created_at DESC',
                10
            ) as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
            error_log('search blog: ' . $e->getMessage());
        }
    }

    if (isModuleEnabled('news')) {
        try {
            foreach ($ftSearch(
                $pdo, $q, $like,
                "SELECT id, title, slug, content AS perex, created_at, 'news' AS type",
                "FROM cms_news WHERE status = 'published'",
                'title, content',
                [],
                'created_at DESC',
                5
            ) as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
            error_log('search news: ' . $e->getMessage());
        }
    }

    try {
        foreach ($ftSearch(
            $pdo, $q, $like,
            "SELECT id, title, '' AS perex, created_at, 'page' AS type, slug",
            "FROM cms_pages WHERE is_published = 1",
            'title, content',
            [],
            'title',
            5
        ) as $row) {
            $results[] = $row;
        }
    } catch (\PDOException $e) {
        error_log('search pages: ' . $e->getMessage());
    }

    if (isModuleEnabled('events')) {
        try {
            foreach ($ftSearch(
                $pdo, $q, $like,
                "SELECT id, title, slug, COALESCE(NULLIF(excerpt, ''), description) AS perex, event_date AS created_at, 'event' AS type",
                "FROM cms_events WHERE " . eventPublicVisibilitySql(),
                'title, excerpt, description, program_note, location, organizer_name',
                [],
                'event_date DESC',
                5
            ) as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
            error_log('search events: ' . $e->getMessage());
        }
    }

    if (isModuleEnabled('podcast')) {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, title, slug, description AS perex,
                        COALESCE(updated_at, created_at) AS created_at, 'podcast_show' AS type
                 FROM cms_podcast_shows
                 WHERE title LIKE ? OR description LIKE ? OR author LIKE ? OR category LIKE ?
                 ORDER BY updated_at DESC, title ASC LIMIT 5"
            );
            $stmt->execute([$like, $like, $like, $like]);
            foreach ($stmt->fetchAll() as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
            error_log('search podcast_shows: ' . $e->getMessage());
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT p.id, p.title, p.slug, p.description AS perex,
                        COALESCE(p.publish_at, p.updated_at, p.created_at) AS created_at,
                        'podcast_episode' AS type, s.slug AS show_slug, s.title AS show_title
                 FROM cms_podcasts p
                 INNER JOIN cms_podcast_shows s ON s.id = p.show_id
                 WHERE p.status = 'published'
                   AND (p.publish_at IS NULL OR p.publish_at <= NOW())
                   AND (p.title LIKE ? OR p.description LIKE ? OR s.title LIKE ?)
                 ORDER BY COALESCE(p.publish_at, p.created_at) DESC LIMIT 8"
            );
            $stmt->execute([$like, $like, $like]);
            foreach ($stmt->fetchAll() as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
            error_log('search podcast_episodes: ' . $e->getMessage());
        }
    }

    if (isModuleEnabled('faq')) {
        try {
            foreach ($ftSearch(
                $pdo, $q, $like,
                "SELECT id, question AS title, slug, COALESCE(NULLIF(excerpt, ''), answer) AS perex,
                        COALESCE(updated_at, created_at) AS created_at, 'faq' AS type",
                "FROM cms_faqs WHERE " . faqPublicVisibilitySql(),
                'question, excerpt, answer',
                [],
                'created_at DESC, id DESC',
                10
            ) as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
            error_log('search faq: ' . $e->getMessage());
        }
    }

    if (isModuleEnabled('gallery')) {
        try {
            $stmt = $pdo->prepare(
                "SELECT a.id, a.name AS title, a.slug,
                        COALESCE(NULLIF(a.description, ''), a.name) AS perex,
                        COALESCE(a.updated_at, a.created_at) AS created_at,
                        'gallery_album' AS type
                 FROM cms_gallery_albums a
                 WHERE " . galleryAlbumPublicVisibilitySql('a') . "
                   AND (a.name LIKE ? OR a.slug LIKE ? OR a.description LIKE ?)
                 ORDER BY a.updated_at DESC, a.name ASC
                 LIMIT 8"
            );
            $stmt->execute([$like, $like, $like]);
            foreach ($stmt->fetchAll() as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
            error_log('search gallery_albums: ' . $e->getMessage());
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT p.id, p.title AS title, p.slug, a.name AS perex,
                        p.created_at, 'gallery_photo' AS type
                 FROM cms_gallery_photos p
                 INNER JOIN cms_gallery_albums a ON a.id = p.album_id
                 WHERE " . galleryPhotoPublicVisibilitySql('p', 'a') . "
                   AND p.title <> ''
                   AND (p.title LIKE ? OR p.slug LIKE ? OR a.name LIKE ?)
                 ORDER BY p.created_at DESC, p.id DESC
                 LIMIT 8"
            );
            $stmt->execute([$like, $like, $like]);
            foreach ($stmt->fetchAll() as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
            error_log('search gallery_photos: ' . $e->getMessage());
        }
    }

    if (isModuleEnabled('food')) {
        try {
            foreach ($ftSearch(
                $pdo, $q, $like,
                "SELECT id, title, slug, COALESCE(NULLIF(description, ''), title) AS perex,
                        COALESCE(valid_from, updated_at, created_at) AS created_at, 'food_card' AS type",
                "FROM cms_food_cards WHERE status = 'published' AND is_published = 1",
                'title, description, content',
                [],
                'COALESCE(valid_from, created_at) DESC, id DESC',
                10
            ) as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
            error_log('search food: ' . $e->getMessage());
        }
    }

    if (isModuleEnabled('board')) {
        try {
            foreach ($ftSearch(
                $pdo, $q, $like,
                "SELECT id, title, slug, COALESCE(NULLIF(excerpt, ''), description) AS perex,
                        posted_date AS created_at, 'board' AS type",
                "FROM cms_board WHERE " . boardPublicVisibilitySql(),
                'title, excerpt, description',
                [],
                'posted_date DESC',
                10
            ) as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
            error_log('search board: ' . $e->getMessage());
        }
    }

    if (isModuleEnabled('downloads')) {
        try {
            foreach ($ftSearch(
                $pdo, $q, $like,
                "SELECT id, title, slug, COALESCE(NULLIF(excerpt, ''), description) AS perex,
                        COALESCE(release_date, created_at) AS created_at, 'download' AS type",
                "FROM cms_downloads WHERE status = 'published' AND is_published = 1",
                'title, excerpt, description',
                [],
                'is_featured DESC, COALESCE(release_date, created_at) DESC, id DESC',
                10
            ) as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
            error_log('search downloads: ' . $e->getMessage());
        }
    }

    if (isModuleEnabled('places')) {
        try {
            foreach ($ftSearch(
                $pdo, $q, $like,
                "SELECT id, name AS title, slug, COALESCE(NULLIF(excerpt, ''), description) AS perex,
                        created_at, 'place' AS type",
                "FROM cms_places WHERE status = 'published' AND is_published = 1",
                'name, excerpt, description',
                [],
                'name ASC',
                10
            ) as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
            error_log('search places: ' . $e->getMessage());
        }
    }

    if (isModuleEnabled('polls')) {
        try {
            foreach ($ftSearch(
                $pdo, $q, $like,
                "SELECT id, question AS title, slug, COALESCE(NULLIF(description, ''), question) AS perex,
                        COALESCE(updated_at, created_at) AS created_at, 'poll' AS type",
                "FROM cms_polls WHERE (
                        (status = 'active' AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date > NOW()))
                        OR status = 'closed'
                        OR (end_date IS NOT NULL AND end_date <= NOW())
                   )",
                'question, description',
                [],
                'COALESCE(start_date, created_at) DESC, id DESC',
                10
            ) as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
            error_log('search polls: ' . $e->getMessage());
        }
    }

    if (isModuleEnabled('reservations')) {
        try {
            $stmt = $pdo->prepare(
                "SELECT r.id, r.name AS title, r.slug,
                        COALESCE(NULLIF(r.description, ''), r.name) AS perex,
                        'reservation_resource' AS type
                 FROM cms_res_resources r
                 LEFT JOIN cms_res_categories c ON c.id = r.category_id
                 WHERE r.is_active = 1
                   AND (r.name LIKE ? OR r.slug LIKE ? OR r.description LIKE ? OR c.name LIKE ?)
                 ORDER BY r.name
                 LIMIT 10"
            );
            $stmt->execute([$like, $like, $like, $like]);
            foreach ($stmt->fetchAll() as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
            error_log('search reservations: ' . $e->getMessage());
        }
    }
}

function resultUrl(array $result): string
{
    $baseUrl = BASE_URL;
    return match($result['type']) {
        'blog' => articlePublicPath($result),
        'news' => newsPublicPath($result),
        'page' => pagePublicPath($result),
        'event' => eventPublicPath($result),
        'podcast_show' => podcastShowPublicPath($result),
        'podcast_episode' => podcastEpisodePublicPath($result),
        'faq' => faqPublicPath($result),
        'food_card' => foodCardPublicPath($result),
        'gallery_album' => galleryAlbumPublicPath($result),
        'gallery_photo' => galleryPhotoPublicPath($result),
        'download' => downloadPublicPath($result),
        'place' => placePublicPath($result),
        'board' => boardPublicPath($result),
        'poll' => pollPublicPath($result),
        'reservation_resource' => reservationResourcePublicPath($result),
        default => $baseUrl . '/',
    };
}

function typeLabel(string $type): string
{
    return match($type) {
        'blog' => 'Článek',
        'news' => 'Novinka',
        'page' => 'Stránka',
        'event' => 'Akce',
        'podcast_show' => 'Podcast',
        'podcast_episode' => 'Epizoda podcastu',
        'faq' => 'FAQ',
        'food_card' => 'Lístek',
        'gallery_album' => 'Album galerie',
        'gallery_photo' => 'Fotografie',
        'download' => 'Ke stažení',
        'place' => 'Místo',
        'board' => boardModulePublicLabel(),
        'poll' => 'Anketa',
        'reservation_resource' => 'Rezervace',
        default => '',
    };
}

$resultCount = count($results);
$resultCountLabel = match (true) {
    $resultCount === 1 => '1 výsledek',
    $resultCount >= 2 && $resultCount <= 4 => $resultCount . ' výsledky',
    default => $resultCount . ' výsledků',
};

renderPublicPage([
    'title' => 'Vyhledávání – ' . $siteName,
    'meta' => [
        'title' => 'Vyhledávání – ' . $siteName,
    ],
    'view' => 'search',
    'view_data' => [
        'q' => $q,
        'results' => $results,
        'resultCountLabel' => $resultCountLabel,
    ],
    'body_class' => 'page-search',
    'page_kind' => 'utility',
]);
