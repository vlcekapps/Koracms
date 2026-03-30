<?php
/**
 * Generický bulk handler pro admin moduly.
 * POST parametry: module, action, ids[], csrf_token, redirect
 */
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$module   = trim($_POST['module'] ?? '');
$action   = trim($_POST['action'] ?? '');
$ids      = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
$redirect = internalRedirectTarget(trim($_POST['redirect'] ?? ''), BASE_URL . '/admin/index.php');

if ($ids === [] || $action === '' || $module === '') {
    header('Location: ' . $redirect);
    exit;
}

$pdo = db_connect();

$moduleConfig = match ($module) {
    'news' => [
        'table'      => 'cms_news',
        'capability' => 'news_manage_own',
        'own_column' => 'author_id',
        'own_check'  => static fn(): bool => canManageOwnNewsOnly(),
        'log_prefix' => 'news',
        'cleanup'    => null,
    ],
    'events' => [
        'table'      => 'cms_events',
        'capability' => 'content_manage_shared',
        'own_column' => null,
        'own_check'  => null,
        'log_prefix' => 'event',
        'cleanup'    => null,
    ],
    'pages' => [
        'table'      => 'cms_pages',
        'capability' => 'content_manage_shared',
        'own_column' => null,
        'own_check'  => null,
        'log_prefix' => 'page',
        'cleanup'    => null,
    ],
    'faq' => [
        'table'      => 'cms_faqs',
        'capability' => 'content_manage_shared',
        'own_column' => null,
        'own_check'  => null,
        'log_prefix' => 'faq',
        'cleanup'    => null,
    ],
    'board' => [
        'table'      => 'cms_board',
        'capability' => 'content_manage_shared',
        'own_column' => null,
        'own_check'  => null,
        'log_prefix' => 'board',
        'cleanup'    => static function (PDO $pdo, array $deleteIds): void {
            $dir = dirname(__DIR__) . '/uploads/board/images/';
            foreach ($deleteIds as $id) {
                $stmt = $pdo->prepare("SELECT image_file FROM cms_board WHERE id = ?");
                $stmt->execute([$id]);
                $file = (string)$stmt->fetchColumn();
                if ($file !== '' && is_file($dir . $file)) {
                    if (!unlink($dir . $file)) {
                        error_log('bulk board: nelze smazat ' . $dir . $file);
                    }
                }
            }
        },
    ],
    'downloads' => [
        'table'      => 'cms_downloads',
        'capability' => 'content_manage_shared',
        'own_column' => null,
        'own_check'  => null,
        'log_prefix' => 'download',
        'cleanup'    => static function (PDO $pdo, array $deleteIds): void {
            $dir = dirname(__DIR__) . '/uploads/downloads/';
            $imgDir = $dir . 'images/';
            foreach ($deleteIds as $id) {
                $stmt = $pdo->prepare("SELECT filename, image_file FROM cms_downloads WHERE id = ?");
                $stmt->execute([$id]);
                $row = $stmt->fetch();
                if ($row) {
                    foreach (['filename' => $dir, 'image_file' => $imgDir] as $col => $path) {
                        $f = trim((string)($row[$col] ?? ''));
                        if ($f !== '' && is_file($path . $f)) {
                            if (!unlink($path . $f)) {
                                error_log('bulk download: nelze smazat ' . $path . $f);
                            }
                        }
                    }
                }
            }
        },
    ],
    'places' => [
        'table'      => 'cms_places',
        'capability' => 'content_manage_shared',
        'own_column' => null,
        'own_check'  => null,
        'log_prefix' => 'place',
        'cleanup'    => static function (PDO $pdo, array $deleteIds): void {
            $dir = dirname(__DIR__) . '/uploads/places/';
            foreach ($deleteIds as $id) {
                $stmt = $pdo->prepare("SELECT image_file FROM cms_places WHERE id = ?");
                $stmt->execute([$id]);
                $file = (string)$stmt->fetchColumn();
                if ($file !== '' && is_file($dir . $file)) {
                    if (!unlink($dir . $file)) {
                        error_log('bulk place: nelze smazat ' . $dir . $file);
                    }
                }
            }
        },
    ],
    'polls' => [
        'table'      => 'cms_polls',
        'capability' => 'content_manage_shared',
        'own_column' => null,
        'own_check'  => null,
        'log_prefix' => 'poll',
        'cleanup'    => static function (PDO $pdo, array $deleteIds): void {
            $ph = implode(',', array_fill(0, count($deleteIds), '?'));
            $pdo->prepare("DELETE FROM cms_poll_votes WHERE poll_id IN ({$ph})")->execute($deleteIds);
            $pdo->prepare("DELETE FROM cms_poll_options WHERE poll_id IN ({$ph})")->execute($deleteIds);
        },
    ],
    'food' => [
        'table'      => 'cms_food_cards',
        'capability' => 'content_manage_shared',
        'own_column' => null,
        'own_check'  => null,
        'log_prefix' => 'food',
        'cleanup'    => null,
    ],
    'gallery_photos' => [
        'table'      => 'cms_gallery_photos',
        'capability' => 'content_manage_shared',
        'own_column' => null,
        'own_check'  => null,
        'log_prefix' => 'gallery_photo',
        'cleanup'    => static function (PDO $pdo, array $deleteIds): void {
            $dir = dirname(__DIR__) . '/uploads/gallery/';
            $thumbDir = $dir . 'thumbs/';
            foreach ($deleteIds as $id) {
                $stmt = $pdo->prepare("SELECT id, filename, slug FROM cms_gallery_photos WHERE id = ?");
                $stmt->execute([$id]);
                $photo = $stmt->fetch() ?: null;
                if ($photo === null) {
                    continue;
                }
                $f = (string)$photo['filename'];
                if ($f !== '' && is_file($dir . $f)) { @unlink($dir . $f); }
                if ($f !== '' && is_file($thumbDir . $f)) { @unlink($thumbDir . $f); }
                $pdo->prepare("DELETE FROM cms_redirects WHERE new_path = ?")->execute([galleryPhotoPublicPath($photo)]);
                $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'gallery_photo' AND entity_id = ?")->execute([(int)$photo['id']]);
            }
        },
    ],
    'gallery_albums' => [
        'table'      => 'cms_gallery_albums',
        'capability' => 'content_manage_shared',
        'own_column' => null,
        'own_check'  => null,
        'log_prefix' => 'gallery_album',
        'cleanup'    => static function (PDO $pdo, array $deleteIds): void {
            $dir = dirname(__DIR__) . '/uploads/gallery/';
            $thumbDir = $dir . 'thumbs/';
            // Rekurzivní sběr všech podalb
            $allIds = $deleteIds;
            $queue = $deleteIds;
            while ($queue !== []) {
                $ph = implode(',', array_fill(0, count($queue), '?'));
                $sub = $pdo->prepare("SELECT id FROM cms_gallery_albums WHERE parent_id IN ({$ph})");
                $sub->execute($queue);
                $queue = [];
                foreach ($sub->fetchAll(PDO::FETCH_COLUMN) as $subId) {
                    if (!in_array((int)$subId, $allIds, true)) {
                        $allIds[] = (int)$subId;
                        $queue[] = (int)$subId;
                    }
                }
            }
            foreach ($allIds as $id) {
                $albumStmt = $pdo->prepare("SELECT id, slug FROM cms_gallery_albums WHERE id = ?");
                $albumStmt->execute([$id]);
                $album = $albumStmt->fetch() ?: null;

                $photos = $pdo->prepare("SELECT id, filename, slug FROM cms_gallery_photos WHERE album_id = ?");
                $photos->execute([$id]);
                foreach ($photos->fetchAll() as $photo) {
                    $f = (string)$photo['filename'];
                    if ($f !== '' && is_file($dir . $f)) { @unlink($dir . $f); }
                    if ($f !== '' && is_file($thumbDir . $f)) { @unlink($thumbDir . $f); }
                    $pdo->prepare("DELETE FROM cms_redirects WHERE new_path = ?")->execute([galleryPhotoPublicPath($photo)]);
                    $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'gallery_photo' AND entity_id = ?")->execute([(int)$photo['id']]);
                }
                $pdo->prepare("DELETE FROM cms_gallery_photos WHERE album_id = ?")->execute([$id]);
                if ($album) {
                    $pdo->prepare("DELETE FROM cms_redirects WHERE new_path = ?")->execute([galleryAlbumPublicPath($album)]);
                }
                $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'gallery_album' AND entity_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM cms_gallery_albums WHERE id = ?")->execute([$id]);
            }
        },
    ],
    'blog_categories' => [
        'table'      => 'cms_categories',
        'capability' => 'blog_manage_all',
        'own_column' => null,
        'own_check'  => null,
        'log_prefix' => 'blog_category',
        'cleanup'    => static function (PDO $pdo, array $deleteIds): void {
            $ph = implode(',', array_fill(0, count($deleteIds), '?'));
            $pdo->prepare("UPDATE cms_articles SET category_id = NULL WHERE category_id IN ({$ph})")->execute($deleteIds);
        },
    ],
    default => null,
};

if ($moduleConfig === null) {
    header('Location: ' . $redirect);
    exit;
}

if (!currentUserHasCapability($moduleConfig['capability'])) {
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'delete') {
    $ownCheck = $moduleConfig['own_check'];
    $table = $moduleConfig['table'];

    // Zjistíme skutečné IDčka (s kontrolou vlastnictví)
    if ($ownCheck !== null && $ownCheck()) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE id IN ({$ph}) AND {$moduleConfig['own_column']} = ?");
        $stmt->execute(array_merge($ids, [currentUserId()]));
        $deleteIds = array_column($stmt->fetchAll(), 'id');
    } else {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE id IN ({$ph})");
        $stmt->execute($ids);
        $deleteIds = array_column($stmt->fetchAll(), 'id');
    }

    $deleteIds = array_map('intval', $deleteIds);

    if ($deleteIds !== []) {
        // Cleanup (soubory, vazební tabulky)
        if ($moduleConfig['cleanup'] !== null) {
            ($moduleConfig['cleanup'])($pdo, $deleteIds);
        }

        // Smazání záznamů
        $ph = implode(',', array_fill(0, count($deleteIds), '?'));
        $pdo->prepare("DELETE FROM {$table} WHERE id IN ({$ph})")->execute($deleteIds);

        logAction($moduleConfig['log_prefix'] . '_bulk_delete', 'ids=' . implode(',', $deleteIds));
    }
} elseif ($action === 'publish') {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $table = $moduleConfig['table'];

    // Ověříme, zda tabulka má sloupec status
    try {
        $pdo->prepare("UPDATE {$table} SET status = 'published' WHERE id IN ({$ph})")->execute($ids);
        logAction($moduleConfig['log_prefix'] . '_bulk_publish', 'ids=' . implode(',', $ids));
    } catch (\PDOException $e) {
        error_log('bulk publish: ' . $e->getMessage());
    }
} elseif ($action === 'hide') {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $table = $moduleConfig['table'];

    try {
        $pdo->prepare("UPDATE {$table} SET status = 'pending' WHERE id IN ({$ph})")->execute($ids);
        logAction($moduleConfig['log_prefix'] . '_bulk_hide', 'ids=' . implode(',', $ids));
    } catch (\PDOException $e) {
        error_log('bulk hide: ' . $e->getMessage());
    }
}

header('Location: ' . $redirect);
exit;
