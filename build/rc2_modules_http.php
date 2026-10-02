<?php

declare(strict_types=1);

/** @return array<string,string> */
function rc2GalleryZipContents(string $bytes): array
{
    $eocd = strrpos($bytes, "PK\x05\x06");
    if ($eocd === false || strlen($bytes) < $eocd + 22) {
        throw new RuntimeException('Gallery fixture response is not a complete ZIP');
    }
    $count = unpack('v', substr($bytes, $eocd + 10, 2))[1];
    $offset = unpack('V', substr($bytes, $eocd + 16, 4))[1];
    $contents = [];
    for ($index = 0; $index < $count; $index++) {
        if (substr($bytes, $offset, 4) !== "PK\x01\x02" || strlen($bytes) < $offset + 46) {
            throw new RuntimeException('Gallery fixture ZIP central directory is invalid');
        }
        $method = unpack('v', substr($bytes, $offset + 10, 2))[1];
        $compressedSize = unpack('V', substr($bytes, $offset + 20, 4))[1];
        $lengths = unpack('vname/vextra/vcomment', substr($bytes, $offset + 28, 6));
        $localOffset = unpack('V', substr($bytes, $offset + 42, 4))[1];
        $name = substr($bytes, $offset + 46, $lengths['name']);
        if (substr($bytes, $localOffset, 4) !== "PK\x03\x04") {
            throw new RuntimeException('Gallery fixture ZIP local entry is invalid');
        }
        $localLengths = unpack('vname/vextra', substr($bytes, $localOffset + 26, 4));
        $data = substr($bytes, $localOffset + 30 + $localLengths['name'] + $localLengths['extra'], $compressedSize);
        if ($method === 8) {
            $data = gzinflate($data);
        } elseif ($method !== 0) {
            throw new RuntimeException('Gallery fixture ZIP uses an unexpected compression method');
        }
        if (!is_string($data) || isset($contents[$name])) {
            throw new RuntimeException('Gallery fixture ZIP has invalid or duplicate entries');
        }
        $contents[$name] = $data;
        $offset += 46 + $lengths['name'] + $lengths['extra'] + $lengths['comment'];
    }
    return $contents;
}

/**
 * @param array{cookie:string,csrf:string} $adminSession
 * @return list<string>
 */
function rc2GalleryFileHttpChecks(PDO $pdo, string $baseUrl, array $adminSession): array
{
    $prefix = 'rc2-gallery-' . bin2hex(random_bytes(8));
    $issues = [];
    $albumId = 0;
    $childId = 0;
    $photos = [];
    $temporaryFiles = [];
    $oldModule = getSetting('module_gallery', '0');
    $uploads = dirname(__DIR__) . '/uploads/';
    $gallery = $uploads . 'gallery/';
    $filename = $prefix . '.png';
    $outsideName = $prefix . '-outside.png';
    $sentinel = $prefix . '-OUTSIDE-GALLERY-SENTINEL';
    $outsidePath = $uploads . $outsideName;
    $imageBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j1ioAAAAASUVORK5CYII=', true);
    $insertPhoto = static function (string $storedName, string $suffix) use ($pdo, $prefix, &$photos, &$albumId): int {
        $slug = $prefix . '-' . $suffix;
        $pdo->prepare("INSERT INTO cms_gallery_photos (album_id,filename,title,slug,status,is_published) VALUES (?,?,?,?,'published',1)")
            ->execute([$albumId, $storedName, $prefix, $slug]);
        $id = (int)$pdo->lastInsertId();
        $photos[$slug] = $id;
        return $id;
    };
    try {
        saveSetting('module_gallery', '1');
        clearSettingsCache();
        if (!is_string($imageBytes) || !koraEnsureDirectory($gallery)
            || file_exists($outsidePath) || file_exists($gallery . $filename)) {
            throw new RuntimeException('Gallery fixture paths are not available');
        }
        if (file_put_contents($outsidePath, $sentinel) !== strlen($sentinel)
            || file_put_contents($gallery . $filename, $imageBytes) !== strlen($imageBytes)) {
            throw new RuntimeException('Cannot write Gallery fixture bytes');
        }
        $pdo->prepare("INSERT INTO cms_gallery_albums (name,slug,status,is_published) VALUES ('..',?,'published',1)")->execute([$prefix]);
        $albumId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO cms_gallery_albums (name,slug,parent_id,status,is_published) VALUES ('C:\\\\child',?,?,'published',1)")
            ->execute([$prefix . '-child', $albumId]);
        $childId = (int)$pdo->lastInsertId();
        $safeId = $insertPhoto($filename, 'safe');
        $badId = $insertPhoto('../' . $outsideName, 'legacy-unsafe');
        $badAlbumId = $insertPhoto('../' . $outsideName, 'legacy-unsafe-album');
        foreach (['GET', 'HEAD'] as $method) {
            foreach (['full', 'thumb'] as $size) {
                $response = requestRawUrl($method, $baseUrl . BASE_URL . '/gallery/image.php?id=' . $badId . '&size=' . $size, '', 'text/plain', '', 0);
                if (httpIntegrationStatusCode($response) !== 404 || str_contains($response['body'], $sentinel)
                    || !httpIntegrationHeaderContains($response, 'Cache-Control', 'no-store')) {
                    $issues[] = 'Gallery unsafe legacy file reference is readable: ' . $method . '/' . $size;
                }
            }
        }
        $safeResponse = fetchUrl($baseUrl . BASE_URL . '/gallery/image.php?id=' . $safeId, '', 0);
        $thumbResponse = fetchUrl($baseUrl . BASE_URL . '/gallery/image.php?id=' . $safeId . '&size=thumb', '', 0);
        if (httpIntegrationStatusCode($safeResponse) !== 200 || $safeResponse['body'] !== $imageBytes
            || httpIntegrationStatusCode($thumbResponse) !== 200 || $thumbResponse['body'] !== $imageBytes) {
            $issues[] = 'Gallery valid image or safe missing-thumbnail fallback is broken';
        }
        $importRows = [];
        foreach (['valid' => $filename, 'invalid' => '../' . $outsideName, 'windows' => '..\\' . $outsideName] as $suffix => $storedName) {
            $importRows[] = ['id' => 0, 'album_id' => $albumId, 'filename' => $storedName,
                'title' => $prefix, 'slug' => $prefix . '-import-' . $suffix, 'sort_order' => 1];
        }
        $importPath = httpIntegrationCreateTempFile('rc2-gallery-', (string)json_encode(['site' => 'cms', 'gallery_photos' => $importRows], JSON_UNESCAPED_UNICODE), $temporaryFiles);
        $response = postMultipartUrl($baseUrl . BASE_URL . '/admin/import.php', [
            'csrf_token' => $adminSession['csrf'], 'confirm_json_import' => '1',
        ], ['import_file' => ['path' => $importPath, 'filename' => 'cms-gallery.json', 'type' => 'application/json']], $adminSession['cookie'], 0);
        if (httpIntegrationStatusCode($response) !== 200
            || !str_contains($response['body'], 'Přeskočené fotografie s neplatným názvem souboru: 2.')) {
            $issues[] = 'Gallery import does not report rejected filenames';
        }
        $stmt = $pdo->prepare('SELECT id,slug,filename FROM cms_gallery_photos WHERE album_id=? ORDER BY id');
        $stmt->execute([$albumId]);
        $imported = false;
        foreach ($stmt->fetchAll() as $photo) {
            $photos[(string)$photo['slug']] = (int)$photo['id'];
            if ($photo['slug'] === $prefix . '-import-valid') {
                $imported = $photo['filename'] === $filename;
            } elseif (str_starts_with((string)$photo['slug'], $prefix . '-import-')) {
                $issues[] = 'Gallery import stored a rejected path reference';
            }
        }
        if (!$imported) {
            $issues[] = 'Gallery import lost a valid legacy filename';
        }
        // Avoid duplicate archive members from two metadata records sharing one fixture image.
        $importedId = $photos[$prefix . '-import-valid'] ?? 0;
        if ($importedId > 0) {
            $pdo->prepare('DELETE FROM cms_gallery_photos WHERE id=? AND slug=?')->execute([$importedId, $prefix . '-import-valid']);
        }
        $export = postUrl($baseUrl . BASE_URL . '/admin/gallery_export_zip.php', [
            'csrf_token' => $adminSession['csrf'], 'ids' => [$albumId], 'confirm_gallery_albums_bulk_action' => '1',
        ], $adminSession['cookie'], 0);
        if (httpIntegrationStatusCode($export) !== 200
            || !httpIntegrationHeaderContains($export, 'Content-Disposition', 'attachment')
            || !httpIntegrationHeaderContains($export, 'Cache-Control', 'no-store')) {
            $issues[] = 'Gallery safe ZIP export did not return an admin-only attachment';
        } else {
            $entries = rc2GalleryZipContents($export['body']);
            if (($entries['album/' . $filename] ?? null) !== $imageBytes || !array_key_exists('album/C__child/', $entries)
                || count($entries) !== 2) {
                $issues[] = 'Gallery ZIP did not preserve safe image and empty subalbum';
            }
            foreach ($entries as $name => $bytes) {
                if (str_starts_with($name, '/') || str_contains($name, '\\') || str_contains($name, ':')
                    || preg_match('~(?:^|/)\.\.?(/|$)~', $name) === 1 || str_contains($bytes, $sentinel)) {
                    $issues[] = 'Gallery ZIP has an unsafe member or outside bytes';
                }
            }
        }
        saveSetting('module_gallery', '0');
        clearSettingsCache();
        $disabled = postUrl($baseUrl . BASE_URL . '/admin/gallery_export_zip.php', [
            'csrf_token' => $adminSession['csrf'], 'ids' => [$albumId], 'confirm_gallery_albums_bulk_action' => '1',
        ], $adminSession['cookie'], 0);
        if (httpIntegrationStatusCode($disabled) !== 403 || str_starts_with($disabled['body'], 'PK')) {
            $issues[] = 'Disabled Gallery module allowed a ZIP export';
        }
        saveSetting('module_gallery', '1');
        clearSettingsCache();
        foreach ([['gallery_photos', $badId, 'confirm_bulk_delete'], ['gallery_albums', $albumId, 'confirm_gallery_albums_bulk_action']] as [$module, $id, $confirmation]) {
            $response = postUrl($baseUrl . BASE_URL . '/admin/bulk.php', [
                'csrf_token' => $adminSession['csrf'], 'module' => $module, 'action' => 'delete',
                'ids' => [$id], $confirmation => '1', 'redirect' => BASE_URL . '/admin/gallery_albums.php',
            ], $adminSession['cookie'], 0);
            $table = $module === 'gallery_photos' ? 'cms_gallery_photos' : 'cms_gallery_albums';
            $remaining = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE id=?");
            $remaining->execute([$id]);
            if (httpIntegrationStatusCode($response) !== 302 || (int)$remaining->fetchColumn() !== 0
                || file_get_contents($outsidePath) !== $sentinel) {
                $issues[] = $module . ': bulk cleanup damaged an outside sentinel or did not finish';
            }
        }
        if (file_exists($gallery . $filename)) {
            $issues[] = 'Gallery bulk cleanup left its safe original behind';
        }
    } finally {
        saveSetting('module_gallery', $oldModule);
        clearSettingsCache();
        foreach ($photos as $slug => $id) {
            $pdo->prepare('DELETE FROM cms_gallery_photos WHERE id=? AND slug=?')->execute([$id, $slug]);
        }
        foreach ([$childId => $prefix . '-child', $albumId => $prefix] as $id => $slug) {
            if ($id > 0) {
                $pdo->prepare('DELETE FROM cms_gallery_albums WHERE id=? AND slug=?')->execute([$id, $slug]);
            }
        }
        foreach ([$outsidePath, $gallery . $filename] as $path) {
            if (is_file($path)) {
                $expectedBytes = $path === $outsidePath ? $sentinel : $imageBytes;
                if (file_get_contents($path) !== $expectedBytes) {
                    throw new RuntimeException('Gallery fixture bytes changed; refusing filesystem cleanup');
                }
                unlink($path);
            }
        }
        foreach ($temporaryFiles as $path) {
            unlink($path);
        }
    }
    return $issues;
}

/** @return list<string> */
function rc2ModuleRevisionHttpChecks(PDO $pdo, string $baseUrl): array
{
    $issues = [];
    $users = [];
    $articles = [];
    $blogId = 0;
    $newsId = 0;
    $pollId = 0;
    $placeId = 0;
    $prefix = 'rc2-revisions-' . bin2hex(random_bytes(5));
    $settings = [];
    foreach (['blog', 'news', 'polls', 'places'] as $module) {
        $settings['module_' . $module] = getSetting('module_' . $module, '0');
    }
    $check = static function (string $path, string $cookie, int $status, bool $visible) use ($baseUrl, $prefix, &$issues): void {
        $response = fetchUrl($baseUrl . BASE_URL . $path, $cookie, 0);
        if (httpIntegrationStatusCode($response) !== $status
            || str_contains($response['body'], $prefix . '-secret') !== $visible
            || ($status !== 302 && !httpIntegrationHeaderContains($response, 'Cache-Control', 'no-store'))) {
            $issues[] = $path . ': wrong authorization, leaked/missing revision, or missing private headers';
        }
    };
    try {
        foreach ($settings as $key => $value) {
            saveSetting($key, '1');
        }
        clearSettingsCache();
        $sessions = [];
        foreach (['author', 'editor', 'moderator'] as $role) {
            $pdo->prepare("INSERT INTO cms_users (email,password,first_name,last_name,role,is_superadmin,is_confirmed)
                VALUES (?,?,'RC2','Revision',?,0,1)")
                ->execute([$prefix . '-' . $role . '@example.test', password_hash('RC2-test-only-123!', PASSWORD_DEFAULT), $role]);
            $users[$role] = (int)$pdo->lastInsertId();
            $sessions[$role] = koraPrimeTestSession(['cms_logged_in' => true, 'cms_user_id' => $users[$role],
                'cms_user_role' => $role, 'cms_superadmin' => false]);
        }
        $pdo->prepare('INSERT INTO cms_blogs (name,slug) VALUES (?,?)')->execute([$prefix, $prefix]);
        $blogId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO cms_blog_members (blog_id,user_id,member_role) VALUES (?,?,'author')")
            ->execute([$blogId, $users['author']]);
        foreach (['author', 'editor'] as $role) {
            $pdo->prepare("INSERT INTO cms_articles (blog_id,author_id,title,slug,content,status) VALUES (?,?,?,?,?,'draft')")
                ->execute([$blogId, $users[$role], $prefix, $prefix . '-' . $role, 'Draft']);
            $articles[$role] = (int)$pdo->lastInsertId();
        }
        $pdo->prepare("INSERT INTO cms_news (title,slug,content,author_id,status) VALUES (?,?,?,?,'draft')")
            ->execute([$prefix, $prefix, 'Draft', $users['editor']]);
        $newsId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO cms_polls (question,slug) VALUES (?,?)')->execute([$prefix, $prefix]);
        $pollId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO cms_places (name,slug) VALUES (?,?)')->execute([$prefix, $prefix]);
        $placeId = (int)$pdo->lastInsertId();
        $targets = [['article', $articles['author']], ['article', $articles['editor']],
            ['news', $newsId], ['poll', $pollId], ['place', $placeId]];
        foreach ($targets as [$type, $id]) {
            $pdo->prepare('INSERT INTO cms_revisions (entity_type,entity_id,field_name,old_value,new_value) VALUES (?,?,?,?,?)')
                ->execute([$type, $id, 'content', $prefix . '-secret', 'Changed']);
            $path = '/admin/revisions.php?type=' . $type . '&id=' . $id;
            $check($path, '', 302, false);
            $check($path, $sessions['editor']['cookie'], 200, true);
            $check($path, $sessions['moderator']['cookie'], 403, false);
            $owned = $type === 'article' && $id === $articles['author'];
            $check($path, $sessions['author']['cookie'], $owned ? 200 : 403, $owned);
        }
        foreach ($targets as [$type, $id]) {
            $module = ['article' => 'articles', 'news' => 'news', 'poll' => 'polls', 'place' => 'places'][$type];
            $table = ['article' => 'cms_articles', 'news' => 'cms_news', 'poll' => 'cms_polls', 'place' => 'cms_places'][$type];
            $response = postUrl($baseUrl . BASE_URL . '/admin/trash.php', [
                'csrf_token' => $sessions['editor']['csrf'], 'module' => $module, 'id' => $id,
                'action' => 'purge', 'confirm_permanent_delete' => '1',
            ], $sessions['editor']['cookie'], 0);
            $parent = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE id=? AND deleted_at IS NULL");
            $parent->execute([$id]);
            $revision = $pdo->prepare('SELECT COUNT(*) FROM cms_revisions WHERE entity_type=? AND entity_id=?');
            $revision->execute([$type, $id]);
            if (httpIntegrationStatusCode($response) !== 302
                || !responseHasLocationHeader($response['headers'], BASE_URL . '/admin/trash.php?err=invalid_action', $baseUrl)
                || (int)$parent->fetchColumn() !== 1 || (int)$revision->fetchColumn() !== 1) {
                $issues[] = $module . ': confirmed trash purge damaged an active fixture or its revision';
            }
        }
        saveSetting('module_blog', '0');
        clearSettingsCache();
        $check('/admin/revisions.php?type=article&id=' . $articles['author'], $sessions['editor']['cookie'], 403, false);
    } finally {
        foreach ($settings as $key => $value) {
            saveSetting($key, $value);
        }
        clearSettingsCache();
        foreach ($articles as $id) {
            $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type='article' AND entity_id=?")->execute([$id]);
            $pdo->prepare('DELETE FROM cms_articles WHERE id=?')->execute([$id]);
        }
        foreach ([['news', 'cms_news', $newsId], ['poll', 'cms_polls', $pollId], ['place', 'cms_places', $placeId]] as [$type, $table, $id]) {
            if ($id > 0) {
                $pdo->prepare('DELETE FROM cms_revisions WHERE entity_type=? AND entity_id=?')->execute([$type, $id]);
                $pdo->prepare("DELETE FROM {$table} WHERE id=?")->execute([$id]);
            }
        }
        if ($blogId > 0) {
            $pdo->prepare('DELETE FROM cms_blog_members WHERE blog_id=?')->execute([$blogId]);
            $pdo->prepare('DELETE FROM cms_blogs WHERE id=?')->execute([$blogId]);
        }
        foreach ($users as $id) {
            $pdo->prepare('DELETE FROM cms_users WHERE id=?')->execute([$id]);
        }
    }
    return $issues;
}

/** @return list<string> */
function rc2TrashHttpChecks(PDO $pdo, string $baseUrl, array $adminSession): array
{
    $issues = [];
    $fixtures = [];
    $prefix = 'rc2-trash-' . bin2hex(random_bytes(6));
    $trigger = 'rc2_trash_' . bin2hex(random_bytes(6));
    $triggerCreated = false;
    $foodOrderId = 0;
    $insert = static function (string $module, string $table, string $type, array $fields) use ($pdo, $prefix, &$fixtures): int {
        $fields['slug'] = $prefix . '-' . $module;
        $columns = implode(',', array_keys($fields));
        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $pdo->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})")->execute(array_values($fields));
        $id = (int)$pdo->lastInsertId();
        $fixtures[] = ['module' => $module, 'table' => $table, 'type' => $type, 'id' => $id, 'slug' => $fields['slug']];
        $pdo->prepare('INSERT INTO cms_revisions (entity_type,entity_id,field_name,old_value,new_value) VALUES (?,?,?,?,?)')
            ->execute([$type, $id, 'content', $prefix, 'Changed']);
        return $id;
    };
    $snapshot = static function (array $fixture) use ($pdo): array {
        $parent = $pdo->prepare('SELECT * FROM ' . $fixture['table'] . ' WHERE id=?');
        $parent->execute([$fixture['id']]);
        $revisions = $pdo->prepare('SELECT * FROM cms_revisions WHERE entity_type=? AND entity_id=? ORDER BY id');
        $revisions->execute([$fixture['type'], $fixture['id']]);
        $rows = ['parent' => $parent->fetchAll(), 'revisions' => $revisions->fetchAll()];
        $children = match ($fixture['module']) {
            'food_cards' => ['cms_food_sections' => 'card_id', 'cms_food_items' => 'card_id',
                'cms_food_item_variants' => 'card_id', 'cms_food_orders' => 'card_id'],
            'podcasts' => ['cms_podcast_chapters' => 'episode_id'],
            'podcast_shows' => ['cms_podcasts' => 'show_id'],
            'gallery_albums' => ['cms_gallery_photos' => 'album_id'],
            default => [],
        };
        foreach ($children as $table => $column) {
            $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE {$column}=? ORDER BY id");
            $stmt->execute([$fixture['id']]);
            $rows[$table] = $stmt->fetchAll();
        }
        if ($fixture['module'] === 'food_cards') {
            $stmt = $pdo->prepare('SELECT i.* FROM cms_food_order_items i INNER JOIN cms_food_orders o ON o.id=i.order_id WHERE o.card_id=? ORDER BY i.id');
            $stmt->execute([$fixture['id']]);
            $rows['cms_food_order_items'] = $stmt->fetchAll();
        }
        return $rows;
    };
    $purge = static function (array $fixture, bool $confirmed = true) use ($baseUrl, $adminSession): array {
        return postUrl($baseUrl . BASE_URL . '/admin/trash.php', [
            'csrf_token' => $adminSession['csrf'], 'module' => $fixture['module'], 'id' => $fixture['id'],
            'action' => 'purge', 'confirm_permanent_delete' => $confirmed ? '1' : '0',
        ], $adminSession['cookie'], 0);
    };
    try {
        $albumId = $insert('gallery_albums', 'cms_gallery_albums', 'gallery_album', ['name' => $prefix]);
        $insert('gallery_photos', 'cms_gallery_photos', 'gallery_photo', ['album_id' => $albumId, 'filename' => $prefix . '.jpg']);
        $showId = $insert('podcast_shows', 'cms_podcast_shows', 'podcast_show', ['title' => $prefix]);
        $episodeId = $insert('podcasts', 'cms_podcasts', 'podcast_episode', ['show_id' => $showId, 'title' => $prefix]);
        $pdo->prepare('INSERT INTO cms_podcast_chapters (episode_id,title) VALUES (?,?)')->execute([$episodeId, $prefix]);
        $foodId = $insert('food_cards', 'cms_food_cards', 'food', ['title' => $prefix]);
        $foodFixture = $fixtures[count($fixtures) - 1];
        $pdo->prepare('INSERT INTO cms_food_sections (card_id,title) VALUES (?,?)')->execute([$foodId, $prefix]);
        $sectionId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO cms_food_items (card_id,section_id,title) VALUES (?,?,?)')->execute([$foodId, $sectionId, $prefix]);
        $foodItemId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO cms_food_item_variants (card_id,item_id,label) VALUES (?,?,?)')->execute([$foodId, $foodItemId, $prefix]);
        $pdo->prepare('INSERT INTO cms_food_orders (card_id,card_title,reference_code,customer_name,customer_email) VALUES (?,?,?,?,?)')
            ->execute([$foodId, $prefix, $prefix, 'RC2 fixture', 'fixture@example.test']);
        $foodOrderId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO cms_food_order_items (order_id,item_id,item_title) VALUES (?,?,?)')->execute([$foodOrderId, $foodItemId, $prefix]);
        $insert('downloads', 'cms_downloads', 'download', ['title' => $prefix]);
        $insert('pages', 'cms_pages', 'page', ['title' => $prefix]);
        $insert('faq', 'cms_faqs', 'faq', ['question' => $prefix, 'answer' => 'Fixture answer']);
        $insert('events', 'cms_events', 'event', ['title' => $prefix, 'event_date' => date('Y-m-d')]);
        $insert('board', 'cms_board', 'board', ['title' => $prefix, 'posted_date' => date('Y-m-d')]);
        foreach ($fixtures as $fixture) {
            $before = $snapshot($fixture);
            $response = $purge($fixture);
            if (httpIntegrationStatusCode($response) !== 302
                || !responseHasLocationHeader($response['headers'], BASE_URL . '/admin/trash.php?err=invalid_action', $baseUrl)
                || $snapshot($fixture) !== $before) {
                $issues[] = $fixture['module'] . ': confirmed trash purge changed an active fixture or related data';
            }
        }
        $pdo->prepare('UPDATE cms_food_cards SET deleted_at=NOW() WHERE id=?')->execute([$foodId]);
        $before = $snapshot($foodFixture);
        $response = $purge($foodFixture, false);
        $confirmationPath = BASE_URL . '/admin/trash.php?' . http_build_query([
            'err' => 'confirm_purge', 'purge_module' => 'food_cards', 'purge_id' => $foodId,
        ]);
        if (httpIntegrationStatusCode($response) !== 302
            || !responseHasLocationHeader($response['headers'], $confirmationPath, $baseUrl) || $snapshot($foodFixture) !== $before) {
            $issues[] = 'Food trash purge without confirmation changed stored data';
        }
        // Fail after child cleanup to prove that the actual endpoint rolls every deletion back.
        $pdo->exec("CREATE TRIGGER {$trigger} BEFORE DELETE ON cms_food_cards FOR EACH ROW
            BEGIN IF OLD.id = {$foodId} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='RC2 fixture rollback'; END IF; END");
        $triggerCreated = true;
        $response = $purge($foodFixture);
        if (httpIntegrationStatusCode($response) !== 302
            || !responseHasLocationHeader($response['headers'], BASE_URL . '/admin/trash.php?err=invalid_action', $baseUrl)
            || $snapshot($foodFixture) !== $before) {
            $issues[] = 'Food failed trash purge did not roll back sections, items, variants, orders and revisions';
        }
        $pdo->exec("DROP TRIGGER {$trigger}");
        $triggerCreated = false;
        $response = $purge($foodFixture);
        $after = $snapshot($foodFixture);
        $orderItems = $pdo->prepare('SELECT COUNT(*) FROM cms_food_order_items WHERE order_id=?');
        $orderItems->execute([$foodOrderId]);
        if (httpIntegrationStatusCode($response) !== 302
            || !responseHasLocationHeader($response['headers'], BASE_URL . '/admin/trash.php?ok=purged', $baseUrl)
            || array_filter($after, static fn (array $rows): bool => $rows !== []) !== []
            || (int)$orderItems->fetchColumn() !== 0) {
            $issues[] = 'Food confirmed trash purge left fixture data behind';
        }
    } finally {
        if ($triggerCreated) {
            $pdo->exec("DROP TRIGGER {$trigger}");
        }
        foreach (array_reverse($fixtures) as $fixture) {
            $owner = $pdo->prepare('SELECT slug FROM ' . $fixture['table'] . ' WHERE id=?');
            $owner->execute([$fixture['id']]);
            $slug = $owner->fetchColumn();
            if ($slug !== false && $slug !== $fixture['slug']) {
                throw new RuntimeException('RC2 trash fixture owner changed; refusing cleanup');
            }
            if ($fixture['module'] === 'food_cards') {
                if ($foodOrderId > 0) {
                    $pdo->prepare('DELETE FROM cms_food_order_items WHERE order_id=?')->execute([$foodOrderId]);
                }
                foreach (['cms_food_orders', 'cms_food_item_variants', 'cms_food_items', 'cms_food_sections'] as $table) {
                    $pdo->prepare("DELETE FROM {$table} WHERE card_id=?")->execute([$fixture['id']]);
                }
            } elseif ($fixture['module'] === 'podcasts') {
                $pdo->prepare('DELETE FROM cms_podcast_chapters WHERE episode_id=?')->execute([$fixture['id']]);
            }
            $pdo->prepare('DELETE FROM cms_revisions WHERE entity_type=? AND entity_id=?')->execute([$fixture['type'], $fixture['id']]);
            $pdo->prepare('DELETE FROM ' . $fixture['table'] . ' WHERE id=?')->execute([$fixture['id']]);
        }
    }
    return $issues;
}
