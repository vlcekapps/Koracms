<?php

declare(strict_types=1);

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
