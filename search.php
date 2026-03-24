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
                "SELECT id, title, description AS perex, created_at, 'podcast' AS type
                 FROM cms_podcasts
                 WHERE (publish_at IS NULL OR publish_at <= NOW()) AND (title LIKE ? OR description LIKE ?)
                 ORDER BY created_at DESC LIMIT 5"
            );
            $stmt->execute([$like, $like]);
            foreach ($stmt->fetchAll() as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
        }
    }

    if (isModuleEnabled('faq')) {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, question AS title, answer AS perex, created_at, 'faq' AS type
                 FROM cms_faqs WHERE is_published = 1 AND (question LIKE ? OR answer LIKE ?)
                 ORDER BY sort_order, id LIMIT 10"
            );
            $stmt->execute([$like, $like]);
            foreach ($stmt->fetchAll() as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
        }
    }

    if (isModuleEnabled('board')) {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, title, description AS perex, posted_date AS created_at, 'board' AS type
                 FROM cms_board
                 WHERE status = 'published' AND is_published = 1
                   AND (title LIKE ? OR description LIKE ?)
                 ORDER BY posted_date DESC LIMIT 10"
            );
            $stmt->execute([$like, $like]);
            foreach ($stmt->fetchAll() as $row) {
                $results[] = $row;
            }
        } catch (\PDOException $e) {
        }
    }

    if (isModuleEnabled('places')) {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, name AS title, description AS perex, created_at, 'place' AS type
                 FROM cms_places WHERE is_published = 1 AND (name LIKE ? OR description LIKE ?)
                 ORDER BY sort_order, name LIMIT 5"
            );
            $stmt->execute([$like, $like]);
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
        'page' => $baseUrl . '/page.php?slug=' . rawurlencode($result['slug'] ?? ''),
        'event' => eventPublicPath($result),
        'podcast' => $baseUrl . '/podcast/index.php#ep-' . (int)$result['id'],
        'faq' => $baseUrl . '/faq/index.php',
        'place' => $baseUrl . '/places/index.php#place-' . (int)$result['id'],
        'board' => $baseUrl . '/board/index.php',
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
        'podcast' => 'Podcast',
        'faq' => 'FAQ',
        'place' => 'Místo',
        'board' => 'Úřední deska',
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
