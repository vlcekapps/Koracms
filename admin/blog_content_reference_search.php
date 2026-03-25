<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

requireLogin(BASE_URL . '/admin/login.php');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (!currentUserHasCapability('blog_manage_own')) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'message' => 'Pro tuto akci nemáte potřebné oprávnění.',
        'results' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function blogContentReferenceAllowedTypes(): array
{
    return [
        'all',
        'blog',
        'page',
        'news',
        'event',
        'faq',
        'gallery',
        'podcast',
        'download',
        'place',
        'board',
        'poll',
    ];
}

function blogContentReferenceTypeLabel(string $type): string
{
    return match ($type) {
        'blog' => 'Článek blogu',
        'page' => 'Statická stránka',
        'news' => 'Novinka',
        'event' => 'Událost',
        'faq' => 'FAQ',
        'gallery_album' => 'Fotogalerie',
        'gallery_photo' => 'Fotografie',
        'podcast_show' => 'Podcast',
        'podcast_episode' => 'Epizoda podcastu',
        'download' => 'Položka ke stažení',
        'place' => 'Zajímavé místo',
        'board' => boardModulePublicLabel(),
        'poll' => 'Anketa',
        default => 'Obsah webu',
    };
}

function blogContentReferenceTitle(array $row): string
{
    $type = (string)($row['type'] ?? '');

    return match ($type) {
        'blog',
        'page',
        'news',
        'event',
        'podcast_show',
        'podcast_episode',
        'download',
        'place',
        'board' => trim((string)($row['title'] ?? '')),
        'faq',
        'poll' => trim((string)($row['title'] ?? '')),
        'gallery_album' => trim((string)($row['title'] ?? '')),
        'gallery_photo' => galleryPhotoLabel($row),
        default => trim((string)($row['title'] ?? '')),
    };
}

function blogContentReferenceExcerpt(array $row, int $limit = 180): string
{
    $type = (string)($row['type'] ?? '');

    return match ($type) {
        'blog' => trim((string)($row['perex'] ?? '')) !== ''
            ? mb_strimwidth(normalizePlainText((string)$row['perex']), 0, $limit, '…', 'UTF-8')
            : mb_strimwidth(normalizePlainText((string)($row['content'] ?? '')), 0, $limit, '…', 'UTF-8'),
        'page' => mb_strimwidth(normalizePlainText((string)($row['content'] ?? '')), 0, $limit, '…', 'UTF-8'),
        'news' => newsExcerpt((string)($row['content'] ?? ''), $limit),
        'event' => mb_strimwidth(normalizePlainText((string)($row['description'] ?? '')), 0, $limit, '…', 'UTF-8'),
        'faq' => faqExcerpt($row, $limit),
        'gallery_album' => galleryAlbumExcerpt($row, $limit),
        'gallery_photo' => trim((string)($row['album_title'] ?? '')) !== ''
            ? 'Album: ' . trim((string)$row['album_title'])
            : '',
        'podcast_show' => mb_strimwidth(normalizePlainText((string)($row['description'] ?? '')), 0, $limit, '…', 'UTF-8'),
        'podcast_episode' => podcastEpisodeExcerpt($row, $limit),
        'download' => downloadExcerpt($row, $limit),
        'place' => placeExcerpt($row, $limit),
        'board' => boardExcerpt($row, $limit),
        'poll' => pollExcerpt($row, $limit),
        default => '',
    };
}

function blogContentReferencePublicPath(array $row): string
{
    return match ((string)($row['type'] ?? '')) {
        'blog' => articlePublicPath($row),
        'page' => pagePublicPath($row),
        'news' => newsPublicPath($row),
        'event' => eventPublicPath($row),
        'faq' => faqPublicPath($row),
        'gallery_album' => galleryAlbumPublicPath($row),
        'gallery_photo' => galleryPhotoPublicPath($row),
        'podcast_show' => podcastShowPublicPath($row),
        'podcast_episode' => podcastEpisodePublicPath($row),
        'download' => downloadPublicPath($row),
        'place' => placePublicPath($row),
        'board' => boardPublicPath($row),
        'poll' => pollPublicPath($row),
        default => BASE_URL . '/',
    };
}

function blogContentReferenceTimestamp(array $row): int
{
    $value = trim((string)($row['created_at'] ?? ''));
    if ($value === '') {
        return 0;
    }

    $timestamp = strtotime($value);
    return $timestamp !== false ? $timestamp : 0;
}

function blogContentReferenceResult(array $row): array
{
    $title = blogContentReferenceTitle($row);
    if ($title === '') {
        $title = blogContentReferenceTypeLabel((string)($row['type'] ?? ''));
    }

    $path = blogContentReferencePublicPath($row);

    return [
        'type' => (string)($row['type'] ?? ''),
        'kind_label' => blogContentReferenceTypeLabel((string)($row['type'] ?? '')),
        'title' => $title,
        'url' => $path,
        'path' => $path,
        'excerpt' => blogContentReferenceExcerpt($row),
    ];
}

$query = trim((string)($_GET['q'] ?? ''));
$requestedType = strtolower(trim((string)($_GET['type'] ?? 'all')));

if (!in_array($requestedType, blogContentReferenceAllowedTypes(), true)) {
    $requestedType = 'all';
}

if (mb_strlen($query) < 2) {
    echo json_encode([
        'ok' => true,
        'count' => 0,
        'message' => 'Zadejte alespoň 2 znaky.',
        'results' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$pdo = db_connect();
$like = '%' . $query . '%';
$results = [];

if (($requestedType === 'all' || $requestedType === 'blog') && isModuleEnabled('blog')) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, title, slug, perex, content, created_at, 'blog' AS type
             FROM cms_articles
             WHERE status = 'published'
               AND (publish_at IS NULL OR publish_at <= NOW())
               AND (title LIKE ? OR perex LIKE ? OR content LIKE ?)
             ORDER BY created_at DESC
             LIMIT 10"
        );
        $stmt->execute([$like, $like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $results[] = blogContentReferenceResult($row);
        }
    } catch (\PDOException $e) {
    }
}

if ($requestedType === 'all' || $requestedType === 'page') {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, title, slug, content, created_at, 'page' AS type
             FROM cms_pages
             WHERE is_published = 1
               AND (title LIKE ? OR content LIKE ? OR slug LIKE ?)
             ORDER BY title
             LIMIT 10"
        );
        $stmt->execute([$like, $like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $results[] = blogContentReferenceResult($row);
        }
    } catch (\PDOException $e) {
    }
}

if (($requestedType === 'all' || $requestedType === 'news') && isModuleEnabled('news')) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, title, slug, content, created_at, 'news' AS type
             FROM cms_news
             WHERE status = 'published'
               AND (title LIKE ? OR content LIKE ? OR slug LIKE ?)
             ORDER BY created_at DESC
             LIMIT 10"
        );
        $stmt->execute([$like, $like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $results[] = blogContentReferenceResult($row);
        }
    } catch (\PDOException $e) {
    }
}

if (($requestedType === 'all' || $requestedType === 'event') && isModuleEnabled('events')) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, title, slug, description, event_date AS created_at, 'event' AS type
             FROM cms_events
             WHERE status = 'published'
               AND is_published = 1
               AND (title LIKE ? OR description LIKE ? OR location LIKE ? OR slug LIKE ?)
             ORDER BY event_date DESC
             LIMIT 10"
        );
        $stmt->execute([$like, $like, $like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $results[] = blogContentReferenceResult($row);
        }
    } catch (\PDOException $e) {
    }
}

if (($requestedType === 'all' || $requestedType === 'faq') && isModuleEnabled('faq')) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, question AS title, slug, excerpt, answer, COALESCE(updated_at, created_at) AS created_at, 'faq' AS type
             FROM cms_faqs
             WHERE COALESCE(status, 'published') = 'published'
               AND is_published = 1
               AND (question LIKE ? OR excerpt LIKE ? OR answer LIKE ? OR slug LIKE ?)
             ORDER BY sort_order, id
             LIMIT 10"
        );
        $stmt->execute([$like, $like, $like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $results[] = blogContentReferenceResult($row);
        }
    } catch (\PDOException $e) {
    }
}

if (($requestedType === 'all' || $requestedType === 'gallery') && isModuleEnabled('gallery')) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, name AS title, slug, description, COALESCE(updated_at, created_at) AS created_at, 'gallery_album' AS type
             FROM cms_gallery_albums
             WHERE name LIKE ? OR slug LIKE ? OR description LIKE ?
             ORDER BY updated_at DESC, name ASC
             LIMIT 8"
        );
        $stmt->execute([$like, $like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $results[] = blogContentReferenceResult($row);
        }
    } catch (\PDOException $e) {
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT p.id, p.title, p.slug, p.filename, a.name AS album_title, p.created_at, 'gallery_photo' AS type
             FROM cms_gallery_photos p
             INNER JOIN cms_gallery_albums a ON a.id = p.album_id
             WHERE p.title LIKE ? OR p.slug LIKE ? OR a.name LIKE ? OR p.filename LIKE ?
             ORDER BY p.created_at DESC, p.id DESC
             LIMIT 8"
        );
        $stmt->execute([$like, $like, $like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $results[] = blogContentReferenceResult($row);
        }
    } catch (\PDOException $e) {
    }
}

if (($requestedType === 'all' || $requestedType === 'podcast') && isModuleEnabled('podcast')) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, title, slug, description, COALESCE(updated_at, created_at) AS created_at, 'podcast_show' AS type
             FROM cms_podcast_shows
             WHERE title LIKE ? OR description LIKE ? OR author LIKE ? OR category LIKE ? OR slug LIKE ?
             ORDER BY updated_at DESC, title ASC
             LIMIT 8"
        );
        $stmt->execute([$like, $like, $like, $like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $results[] = blogContentReferenceResult($row);
        }
    } catch (\PDOException $e) {
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT p.id, p.title, p.slug, p.description, s.slug AS show_slug,
                    COALESCE(p.publish_at, p.updated_at, p.created_at) AS created_at,
                    'podcast_episode' AS type
             FROM cms_podcasts p
             INNER JOIN cms_podcast_shows s ON s.id = p.show_id
             WHERE p.status = 'published'
               AND (p.publish_at IS NULL OR p.publish_at <= NOW())
               AND (p.title LIKE ? OR p.description LIKE ? OR s.title LIKE ? OR p.slug LIKE ?)
             ORDER BY COALESCE(p.publish_at, p.created_at) DESC
             LIMIT 10"
        );
        $stmt->execute([$like, $like, $like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $results[] = blogContentReferenceResult($row);
        }
    } catch (\PDOException $e) {
    }
}

if (($requestedType === 'all' || $requestedType === 'download') && isModuleEnabled('downloads')) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, title, slug, excerpt, description, created_at, 'download' AS type
             FROM cms_downloads
             WHERE status = 'published'
               AND is_published = 1
               AND (title LIKE ? OR excerpt LIKE ? OR description LIKE ? OR slug LIKE ?)
             ORDER BY sort_order, title
             LIMIT 10"
        );
        $stmt->execute([$like, $like, $like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $results[] = blogContentReferenceResult($row);
        }
    } catch (\PDOException $e) {
    }
}

if (($requestedType === 'all' || $requestedType === 'place') && isModuleEnabled('places')) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, name AS title, slug, excerpt, description, created_at, kind, 'place' AS type
             FROM cms_places
             WHERE status = 'published'
               AND is_published = 1
               AND (name LIKE ? OR excerpt LIKE ? OR description LIKE ? OR category LIKE ? OR locality LIKE ? OR address LIKE ? OR slug LIKE ?)
             ORDER BY sort_order, name
             LIMIT 10"
        );
        $stmt->execute([$like, $like, $like, $like, $like, $like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $results[] = blogContentReferenceResult($row);
        }
    } catch (\PDOException $e) {
    }
}

if (($requestedType === 'all' || $requestedType === 'board') && isModuleEnabled('board')) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, title, slug, excerpt, description, posted_date AS created_at, 'board' AS type
             FROM cms_board
             WHERE status = 'published'
               AND is_published = 1
               AND (title LIKE ? OR excerpt LIKE ? OR description LIKE ? OR contact_name LIKE ? OR contact_phone LIKE ? OR contact_email LIKE ? OR slug LIKE ?)
             ORDER BY posted_date DESC
             LIMIT 10"
        );
        $stmt->execute([$like, $like, $like, $like, $like, $like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $results[] = blogContentReferenceResult($row);
        }
    } catch (\PDOException $e) {
    }
}

if (($requestedType === 'all' || $requestedType === 'poll') && isModuleEnabled('polls')) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, question AS title, slug, description, COALESCE(updated_at, created_at) AS created_at, 'poll' AS type
             FROM cms_polls
             WHERE (
                    (status = 'active' AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date > NOW()))
                    OR status = 'closed'
                    OR (end_date IS NOT NULL AND end_date <= NOW())
               )
               AND (question LIKE ? OR description LIKE ? OR slug LIKE ?)
             ORDER BY COALESCE(start_date, created_at) DESC, id DESC
             LIMIT 10"
        );
        $stmt->execute([$like, $like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $results[] = blogContentReferenceResult($row);
        }
    } catch (\PDOException $e) {
    }
}

usort($results, static function (array $left, array $right): int {
    return blogContentReferenceTimestamp($right) <=> blogContentReferenceTimestamp($left);
});

$results = array_slice($results, 0, 24);
$count = count($results);

echo json_encode([
    'ok' => true,
    'count' => $count,
    'message' => $count > 0 ? null : 'Žádný veřejný obsah neodpovídá hledání.',
    'results' => $results,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
