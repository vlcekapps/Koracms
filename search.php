<?php
require_once __DIR__ . '/db.php';
checkMaintenanceMode();

$siteName = getSetting('site_name', 'Kora CMS');
$q        = trim($_GET['q'] ?? '');
$results  = [];

if ($q !== '' && mb_strlen($q) >= 2) {
    $pdo  = db_connect();
    $like = '%' . $q . '%';

    if (isModuleEnabled('blog')) {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, title, slug, perex, created_at, 'blog' AS type
                 FROM cms_articles
                 WHERE status = 'published'
                   AND (publish_at IS NULL OR publish_at <= NOW())
                   AND (title LIKE ? OR perex LIKE ? OR content LIKE ?)
                 ORDER BY created_at DESC LIMIT 10"
            );
            $stmt->execute([$like, $like, $like]);
            foreach ($stmt->fetchAll() as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
        }
    }

    if (isModuleEnabled('news')) {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, title, slug, content AS perex, created_at, 'news' AS type
                 FROM cms_news
                 WHERE status = 'published' AND (title LIKE ? OR content LIKE ?)
                 ORDER BY created_at DESC LIMIT 5"
            );
            $stmt->execute([$like, $like]);
            foreach ($stmt->fetchAll() as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
        }
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT id, title, '' AS perex, created_at, 'page' AS type, slug
             FROM cms_pages
             WHERE is_published = 1 AND (title LIKE ? OR content LIKE ?)
             ORDER BY title LIMIT 5"
        );
        $stmt->execute([$like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $results[] = $row;
        }
    } catch (\PDOException $e) {
    }

    if (isModuleEnabled('events')) {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, title, slug, description AS perex, event_date AS created_at, 'event' AS type
                 FROM cms_events
                 WHERE status = 'published' AND is_published = 1
                   AND (title LIKE ? OR description LIKE ? OR location LIKE ?)
                 ORDER BY event_date DESC LIMIT 5"
            );
            $stmt->execute([$like, $like, $like]);
            foreach ($stmt->fetchAll() as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
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
        }
    }

    if (isModuleEnabled('faq')) {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, question AS title, slug, COALESCE(NULLIF(excerpt, ''), answer) AS perex,
                        COALESCE(updated_at, created_at) AS created_at, 'faq' AS type
                 FROM cms_faqs
                 WHERE COALESCE(status,'published') = 'published' AND is_published = 1
                   AND (question LIKE ? OR excerpt LIKE ? OR answer LIKE ?)
                 ORDER BY sort_order, id LIMIT 10"
            );
            $stmt->execute([$like, $like, $like]);
            foreach ($stmt->fetchAll() as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
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
                 WHERE a.name LIKE ? OR a.slug LIKE ? OR a.description LIKE ?
                 ORDER BY a.updated_at DESC, a.name ASC
                 LIMIT 8"
            );
            $stmt->execute([$like, $like, $like]);
            foreach ($stmt->fetchAll() as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT p.id, p.title AS title, p.slug, a.name AS perex,
                        p.created_at, 'gallery_photo' AS type
                 FROM cms_gallery_photos p
                 INNER JOIN cms_gallery_albums a ON a.id = p.album_id
                 WHERE p.title <> '' AND (p.title LIKE ? OR p.slug LIKE ? OR a.name LIKE ?)
                 ORDER BY p.created_at DESC, p.id DESC
                 LIMIT 8"
            );
            $stmt->execute([$like, $like, $like]);
            foreach ($stmt->fetchAll() as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
        }
    }

    if (isModuleEnabled('food')) {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, title, slug, COALESCE(NULLIF(description, ''), title) AS perex,
                        COALESCE(valid_from, updated_at, created_at) AS created_at,
                        'food_card' AS type
                 FROM cms_food_cards
                 WHERE status = 'published' AND is_published = 1
                   AND (title LIKE ? OR slug LIKE ? OR description LIKE ? OR content LIKE ?)
                 ORDER BY COALESCE(valid_from, created_at) DESC, id DESC
                 LIMIT 10"
            );
            $stmt->execute([$like, $like, $like, $like]);
            foreach ($stmt->fetchAll() as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
        }
    }

    if (isModuleEnabled('board')) {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, title, slug, COALESCE(NULLIF(excerpt, ''), description) AS perex,
                        posted_date AS created_at, 'board' AS type
                 FROM cms_board
                 WHERE status = 'published' AND is_published = 1
                   AND (title LIKE ? OR excerpt LIKE ? OR description LIKE ? OR contact_name LIKE ? OR contact_phone LIKE ? OR contact_email LIKE ?)
                 ORDER BY posted_date DESC LIMIT 10"
            );
            $stmt->execute([$like, $like, $like, $like, $like, $like]);
            foreach ($stmt->fetchAll() as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
        }
    }

    if (isModuleEnabled('downloads')) {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, title, slug, COALESCE(NULLIF(excerpt, ''), description) AS perex,
                        created_at, 'download' AS type
                 FROM cms_downloads
                 WHERE status = 'published' AND is_published = 1
                   AND (title LIKE ? OR excerpt LIKE ? OR description LIKE ? OR version_label LIKE ? OR platform_label LIKE ? OR license_label LIKE ?)
                 ORDER BY sort_order, title
                 LIMIT 10"
            );
            $stmt->execute([$like, $like, $like, $like, $like, $like]);
            foreach ($stmt->fetchAll() as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
        }
    }

    if (isModuleEnabled('places')) {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, name AS title, slug, COALESCE(NULLIF(excerpt, ''), description) AS perex,
                        created_at, 'place' AS type
                 FROM cms_places
                 WHERE status = 'published' AND is_published = 1
                   AND (name LIKE ? OR excerpt LIKE ? OR description LIKE ? OR category LIKE ? OR locality LIKE ? OR address LIKE ?)
                 ORDER BY sort_order, name
                 LIMIT 10"
            );
            $stmt->execute([$like, $like, $like, $like, $like, $like]);
            foreach ($stmt->fetchAll() as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
        }
    }

    if (isModuleEnabled('polls')) {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, question AS title, slug, COALESCE(NULLIF(description, ''), question) AS perex,
                        COALESCE(updated_at, created_at) AS created_at, 'poll' AS type
                 FROM cms_polls
                 WHERE (
                        (status = 'active' AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date > NOW()))
                        OR status = 'closed'
                        OR (end_date IS NOT NULL AND end_date <= NOW())
                   )
                   AND (question LIKE ? OR description LIKE ?)
                 ORDER BY COALESCE(start_date, created_at) DESC, id DESC
                 LIMIT 10"
            );
            $stmt->execute([$like, $like]);
            foreach ($stmt->fetchAll() as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
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
