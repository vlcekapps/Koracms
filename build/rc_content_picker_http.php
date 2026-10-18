<?php

declare(strict_types=1);

/**
 * @param array{cookie:string,csrf:string} $adminSession
 * @return list<string>
 */
function rcContentPickerHttpChecks(PDO $pdo, string $baseUrl, array $adminSession): array
{
    $prefix = 'rc-picker-' . bin2hex(random_bytes(8));
    $issues = $owned = $settings = $sources = [];
    $queryCookie = $adminSession['cookie'];
    $limitedSessionId = '';
    $originalSessionId = session_id();
    $oldEditor = getSetting('content_editor', 'html');
    $modules = ['blog', 'news', 'events', 'faq', 'gallery', 'podcast', 'downloads', 'recipes', 'forms', 'places', 'board', 'polls'];
    $tables = ['cms_users', 'cms_blogs', 'cms_articles', 'cms_pages', 'cms_news', 'cms_events', 'cms_faqs', 'cms_gallery_albums', 'cms_gallery_photos',
        'cms_podcast_shows', 'cms_podcasts', 'cms_downloads', 'cms_recipe_categories', 'cms_recipes', 'cms_forms', 'cms_media', 'cms_places', 'cms_board', 'cms_polls'];
    $check = static function (bool $ok, string $label) use (&$issues): void {
        if (!$ok) {
            $issues[] = $label;
        }
    };
    $insert = static function (string $table, array $data) use ($pdo, $tables, &$owned): int {
        if (!in_array($table, $tables, true)) {
            throw new RuntimeException('Unowned picker fixture table');
        }
        $sql = 'INSERT INTO ' . $table . ' (`' . implode('`,`', array_keys($data)) . '`) VALUES (' . implode(',', array_fill(0, count($data), '?')) . ')';
        $pdo->prepare($sql)->execute(array_values($data));
        $id = (int)$pdo->lastInsertId();
        $owned[$table][] = $id;
        return $id;
    };
    $row = static function (string $table, int $id) use ($pdo, $tables): array {
        if (!in_array($table, $tables, true)) {
            throw new RuntimeException('Unowned picker fixture read');
        }
        $stmt = $pdo->prepare('SELECT * FROM ' . $table . ' WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    };
    $update = static function (string $table, int $id, array $values) use ($pdo, $tables): void {
        if (!in_array($table, $tables, true)) {
            throw new RuntimeException('Unowned picker fixture update');
        }
        $fields = array_map(static fn (string $field): string => '`' . $field . '` = ?', array_keys($values));
        $pdo->prepare('UPDATE ' . $table . ' SET ' . implode(',', $fields) . ' WHERE id = ?')->execute([...array_values($values), $id]);
    };
    $query = static function (string $type, string $text) use ($baseUrl, &$queryCookie, $check): array {
        $response = fetchUrl($baseUrl . BASE_URL . '/admin/content_reference_search.php?' . http_build_query(['type' => $type, 'q' => $text]), $queryCookie, 0);
        $data = json_decode($response['body'], true);
        $check(httpIntegrationStatusCode($response) === 200 && is_array($data) && ($data['ok'] ?? false) === true, 'Picker did not return valid successful JSON for ' . $type);
        $check(is_array($data) && ($data['count'] ?? null) === count($data['results'] ?? []), 'Picker count does not match its results');
        $headers = strtolower(implode('\n', $response['headers']));
        $check(str_contains($headers, 'no-store') && str_contains($headers, 'nosniff') && str_contains($headers, 'noindex'), 'Picker JSON lost private response headers');
        return is_array($data) && is_array($data['results'] ?? null) ? $data['results'] : [];
    };
    $contains = static function (array $results, string $title, string $type, ?string $path = null): bool {
        foreach ($results as $result) {
            if (($result['title'] ?? '') === $title && ($result['type'] ?? '') === $type
                && ($path === null || (($result['path'] ?? '') === $path && ($result['url'] ?? '') === $path))) {
                return true;
            }
        }
        return false;
    };
    try {
        foreach ($modules as $module) {
            $settings[$module] = getSetting('module_' . $module, '0');
            saveSetting('module_' . $module, '1');
        }
        clearSettingsCache();
        $authorId = $insert('cms_users', ['email' => $prefix . '@example.invalid', 'password' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
            'role' => 'author', 'first_name' => $prefix, 'is_superadmin' => 0]);
        $blogId = $insert('cms_blogs', ['name' => $prefix, 'slug' => $prefix]);
        $albumId = $insert('cms_gallery_albums', ['name' => $prefix . '-gallery-album', 'slug' => $prefix . '-gallery-album', 'created_at' => '2020-01-01 00:00:00', 'updated_at' => '2020-01-01 00:00:00']);
        $showId = $insert('cms_podcast_shows', ['title' => $prefix . '-podcast-show', 'slug' => $prefix . '-podcast-show', 'created_at' => '2020-01-01 00:00:00']);
        $categoryId = $insert('cms_recipe_categories', ['name' => $prefix, 'slug' => $prefix, 'is_active' => 1]);
        $future = (string)$pdo->query('SELECT DATE_ADD(NOW(), INTERVAL 2 DAY)')->fetchColumn();
        $past = (string)$pdo->query('SELECT DATE_SUB(NOW(), INTERVAL 1 DAY)')->fetchColumn();
        $common = ['status' => 'published', 'created_at' => '2020-01-01 00:00:00'];
        $definitions = [
            'blog' => ['cms_articles', 'blog', 'blog', ['blog_id' => $blogId, 'content' => 'Český veřejný článek'] + $common,
                [['status' => 'draft'], ['status' => 'pending'], ['deleted_at' => $past], ['publish_at' => $future], ['unpublish_at' => $past]]],
            'page' => ['cms_pages', 'page', null, ['content' => 'Česká veřejná stránka', 'blog_id' => null] + $common,
                [['status' => 'draft'], ['status' => 'pending'], ['is_published' => 0], ['deleted_at' => $past], ['publish_at' => $future], ['unpublish_at' => $past]]],
            'blog_page' => ['cms_pages', 'page', 'blog', ['content' => 'Česká blogová stránka', 'blog_id' => $blogId] + $common, [['publish_at' => $future]]],
            'news' => ['cms_news', 'news', 'news', ['content' => 'Veřejná novinka'] + $common,
                [['status' => 'draft'], ['deleted_at' => $past], ['publish_at' => $future], ['unpublish_at' => $past]]],
            'event' => ['cms_events', 'event', 'events', ['event_date' => '2027-01-01 10:00:00'] + $common,
                [['status' => 'draft'], ['is_published' => 0], ['deleted_at' => $past], ['publish_at' => $future], ['unpublish_at' => $past]]],
            'faq' => ['cms_faqs', 'faq', 'faq', ['answer' => 'Veřejná odpověď', 'updated_at' => '2020-01-01 00:00:00'] + $common,
                [['status' => 'pending'], ['is_published' => 0], ['deleted_at' => $past]]],
            'gallery_photo' => ['cms_gallery_photos', 'gallery', 'gallery', ['album_id' => $albumId, 'filename' => $prefix . '.png'] + $common,
                [['status' => 'pending'], ['is_published' => 0], ['deleted_at' => $past]]],
            'podcast_episode' => ['cms_podcasts', 'podcast', 'podcast', ['show_id' => $showId, 'audio_url' => 'https://example.invalid/audio.mp3'] + $common,
                [['status' => 'draft'], ['status' => 'pending'], ['deleted_at' => $past], ['publish_at' => $future]]],
            'download' => ['cms_downloads', 'download', 'downloads', ['external_url' => 'https://example.invalid/software'] + $common,
                [['status' => 'draft'], ['is_published' => 0], ['deleted_at' => $past]]],
            'recipe' => ['cms_recipes', 'recipe', 'recipes', ['category_id' => $categoryId] + $common,
                [['status' => 'draft'], ['deleted_at' => $past], ['publish_at' => $future]]],
            'form' => ['cms_forms', 'forms', 'forms', ['created_at' => '2020-01-01 00:00:00', 'updated_at' => '2020-01-01 00:00:00'], [['is_active' => 0]]],
            'media_image' => ['cms_media', 'media', null, ['filename' => $prefix . '.png', 'mime_type' => 'image/png', 'created_at' => '2020-01-01 00:00:00'], [['visibility' => 'private']]],
            'place' => ['cms_places', 'place', 'places', $common, [['status' => 'draft'], ['is_published' => 0], ['deleted_at' => $past]]],
            'board' => ['cms_board', 'board', 'board', ['posted_date' => '2024-01-01'] + $common,
                [['status' => 'draft'], ['status' => 'pending'], ['is_published' => 0], ['deleted_at' => $past], ['posted_date' => substr($future, 0, 10)], ['publish_at' => $future], ['unpublish_at' => $past]]],
            'poll' => ['cms_polls', 'poll', 'polls', ['status' => 'active', 'created_at' => '2023-01-01 00:00:00'], [['start_date' => $future], ['deleted_at' => $past]]],
        ];
        foreach ($definitions as $type => [$table, $requestType, $module, $data, $states]) {
            $titleColumn = match ($table) {
                'cms_faqs', 'cms_polls' => 'question',
                'cms_places' => 'name',
                'cms_media' => 'original_name',
                default => 'title',
            };
            $title = $prefix . '-' . str_replace('_', '-', $type);
            $slug = $type === 'blog_page' ? $prefix . '-page' : $title;
            $data[$titleColumn] = $title;
            if ($table !== 'cms_media') {
                $data['slug'] = $slug;
            }
            $id = $insert($table, $data);
            $resultType = $type === 'blog_page' ? 'page' : $type;
            $path = match ($type) {
                'blog' => '/' . $prefix . '/' . $slug,
                'page' => '/page.php?slug=' . $slug,
                'blog_page' => '/' . $prefix . '/stranka/' . $slug,
                'gallery_photo' => '/gallery/photo/' . $slug,
                'podcast_episode' => '/podcast/' . $prefix . '-podcast-show/' . $slug,
                default => '',
            };
            $fixture = $row($table, $id);
            $fixture['type'] = $resultType;
            $path = $path !== '' ? BASE_URL . $path : match ($type) {
                'news' => newsPublicPath($fixture), 'event' => eventPublicPath($fixture), 'faq' => faqPublicPath($fixture),
                'download' => downloadPublicPath($fixture), 'recipe' => recipePublicPath($fixture), 'form' => formPublicPath($fixture),
                'media_image' => mediaFileUrl($fixture), 'place' => placePublicPath($fixture), 'board' => boardPublicPath($fixture), 'poll' => pollPublicPath($fixture),
            };
            $sources[$type] = compact('table', 'id', 'title', 'requestType', 'module', 'states', 'resultType', 'path');
        }
        $sources['gallery_album'] = ['table' => 'cms_gallery_albums', 'id' => $albumId, 'title' => $prefix . '-gallery-album',
            'requestType' => 'gallery', 'module' => 'gallery', 'states' => [['status' => 'pending'], ['is_published' => 0], ['deleted_at' => $past]],
            'resultType' => 'gallery_album', 'path' => BASE_URL . '/gallery/album/' . $prefix . '-gallery-album'];
        $sources['podcast_show'] = ['table' => 'cms_podcast_shows', 'id' => $showId, 'title' => $prefix . '-podcast-show',
            'requestType' => 'podcast', 'module' => 'podcast', 'states' => [['status' => 'draft'], ['is_published' => 0], ['deleted_at' => $past]],
            'resultType' => 'podcast_show', 'path' => BASE_URL . '/podcast/' . $prefix . '-podcast-show'];

        $limitedSession = koraPrimeTestSession(['cms_logged_in' => true, 'cms_user_id' => $authorId, 'cms_user_role' => 'author',
            'cms_superadmin' => false, 'cms_user_name' => $prefix], 'kora-picker-' . bin2hex(random_bytes(8)));
        $limitedSessionId = $limitedSession['id'];
        $queryCookie = $limitedSession['cookie'];
        if ($originalSessionId !== '') {
            session_id($originalSessionId);
            session_start();
            session_write_close();
        }

        foreach ($sources as $source) {
            $results = $query($source['requestType'], $source['title']);
            $check($contains($results, $source['title'], $source['resultType'], $source['path']), 'Missing public picker result/canonical path: ' . $source['title']);
            $original = $row($source['table'], $source['id']);
            foreach ($source['states'] as $state) {
                $restore = array_intersect_key($original, $state);
                if (array_key_exists('updated_at', $original)) {
                    $restore['updated_at'] = $original['updated_at'];
                }
                try {
                    $update($source['table'], $source['id'], $state);
                    foreach (array_unique([$source['requestType'], 'all']) as $requestType) {
                        $check(
                            !$contains($query($requestType, $source['title']), $source['title'], $source['resultType']),
                            'Nonpublic picker result: ' . $source['table'] . ' ' . json_encode($state) . ' via ' . $requestType
                        );
                    }
                } finally {
                    $update($source['table'], $source['id'], $restore);
                }
            }
        }
        foreach ([['board', ['removal_date' => substr($past, 0, 10)]], ['poll', ['status' => 'closed']], ['poll', ['end_date' => $past]]] as [$key, $state]) {
            $source = $sources[$key];
            $original = $row($source['table'], $source['id']);
            try {
                $update($source['table'], $source['id'], $state);
                $check($contains($query($source['requestType'], $source['title']), $source['title'], $source['resultType'], $source['path']), 'Picker dropped legitimate public archive: ' . $key);
            } finally {
                $update($source['table'], $source['id'], array_intersect_key($original, $state)
                    + (isset($original['updated_at']) ? ['updated_at' => $original['updated_at']] : []));
            }
        }
        foreach ([['cms_gallery_albums', $albumId, 'gallery_photo', ['is_published' => 0]],
            ['cms_gallery_albums', $albumId, 'gallery_photo', ['deleted_at' => $past]],
            ['cms_gallery_albums', $albumId, 'gallery_photo', ['status' => 'pending']],
            ['cms_podcast_shows', $showId, 'podcast_episode', ['is_published' => 0]],
            ['cms_podcast_shows', $showId, 'podcast_episode', ['deleted_at' => $past]],
            ['cms_podcast_shows', $showId, 'podcast_episode', ['status' => 'draft']],
            ['cms_recipe_categories', $categoryId, 'recipe', ['is_active' => 0]]] as [$table, $id, $child, $state]) {
            $original = $row($table, $id);
            try {
                $update($table, $id, $state);
                $source = $sources[$child];
                foreach ([$source['requestType'], 'all'] as $requestType) {
                    $check(!$contains($query($requestType, $source['title']), $source['title'], $source['resultType']), 'Hidden parent exposed picker child: ' . $child);
                }
            } finally {
                $update($table, $id, array_intersect_key($original, $state) + (isset($original['updated_at']) ? ['updated_at' => $original['updated_at']] : []));
            }
        }
        foreach ($sources as $source) {
            if ($source['module'] === null) {
                continue;
            }
            saveSetting('module_' . $source['module'], '0');
            try {
                if ($source['module'] === 'blog') {
                    $global = $sources['page'];
                    $check($contains($query('page', $global['title']), $global['title'], 'page', $global['path']), 'Disabling blog hid a global page');
                }
                foreach (array_unique([$source['requestType'], 'all']) as $requestType) {
                    $check(!$contains($query($requestType, $source['title']), $source['title'], $source['resultType']), 'Disabled module exposed picker result: ' . $source['title']);
                }
            } finally {
                saveSetting('module_' . $source['module'], '1');
            }
        }
        foreach (range(1, 24) as $number) {
            $insert('cms_articles', ['blog_id' => $blogId, 'title' => $prefix . '-old-' . $number, 'slug' => $prefix . '-old-' . $number,
                'content' => 'Older public fixture', 'status' => 'published', 'created_at' => '2000-01-01 00:00:00']);
        }
        $results = $query('all', $prefix);
        $check(count($results) === 20, 'Picker global result limit changed');
        $check(($results[0]['title'] ?? '') === $sources['event']['title'], 'Picker ranks source order instead of raw dates; first type: ' . (string)($results[0]['type'] ?? 'missing'));
        foreach ($sources as $source) {
            $check($contains($results, $source['title'], $source['resultType'], $source['path']), 'Global picker truncation dropped newer source: ' . $source['title']);
        }
        $keys = ['type', 'kind_label', 'title', 'url', 'path', 'excerpt', 'media_alt', 'thumbnail_url', 'insert_actions'];
        sort($keys);
        foreach ($results as $result) {
            $actualKeys = array_keys($result);
            sort($actualKeys);
            $check($actualKeys === $keys, 'Picker leaked raw/private fields or changed JSON shape');
        }
        $anonymous = fetchUrl($baseUrl . BASE_URL . '/admin/content_reference_search.php?q=' . rawurlencode($prefix), '', 0);
        $check(httpIntegrationStatusCode($anonymous) === 302 && !str_contains($anonymous['body'], $prefix), 'Anonymous picker access was allowed');
        $post = requestRawUrl('POST', $baseUrl . BASE_URL . '/admin/content_reference_search.php', '', 'application/json', $adminSession['cookie'], 0);
        $check(httpIntegrationStatusCode($post) === 405, 'Read-only picker accepted POST');
        $malformed = fetchUrl($baseUrl . BASE_URL . '/admin/content_reference_search.php?q%5B%5D=invalid&type%5B%5D=board', $adminSession['cookie'], 0);
        $data = json_decode($malformed['body'], true);
        $check(httpIntegrationStatusCode($malformed) === 200 && is_array($data) && ($data['results'] ?? null) === [], 'Malformed picker inputs broke JSON');
        $check($query('all', $prefix . "' OR 1=1 --") === [], 'Picker search was not a bound LIKE value');
        saveSetting('content_editor', 'html');
        $page = fetchUrl($baseUrl . BASE_URL . '/admin/news_form.php', $adminSession['cookie'], 0);
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $page['body']);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($dom);
        $check(httpIntegrationStatusCode($page) === 200 && $xpath->query('//section[@role="dialog" and @aria-modal="true" and normalize-space(@aria-labelledby) != ""]')->length > 0, 'Picker lost its named dialog');
        $check($xpath->query('//section[@role="dialog"]//fieldset[legend]')->length > 0
            && $xpath->query('//section[@role="dialog"]//*[@aria-live="polite" and @aria-atomic="true"]')->length > 0, 'Picker lost its fieldset/legend or atomic status');
        foreach ($xpath->query('//section[@role="dialog"]//*[@aria-describedby or @aria-labelledby] | //section[@role="dialog"][@aria-labelledby]') as $element) {
            foreach (['aria-describedby', 'aria-labelledby'] as $attribute) {
                foreach (preg_split('/\s+/', trim($element->getAttribute($attribute)), -1, PREG_SPLIT_NO_EMPTY) as $id) {
                    $check($xpath->query('//*[@id="' . $id . '"]')->length === 1, 'Picker ARIA target is missing/duplicate: ' . $id);
                }
            }
        }
        foreach ($xpath->query('//section[@role="dialog"]//input | //section[@role="dialog"]//select') as $element) {
            $check($element->getAttribute('id') !== '' && $xpath->query('//label[@for="' . $element->getAttribute('id') . '"]')->length === 1, 'Picker field lost its label');
        }
    } catch (Throwable $exception) {
        $issues[] = $exception->getMessage();
    } finally {
        if ($limitedSessionId !== '') {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_id($limitedSessionId);
            session_start();
            session_destroy();
            if ($originalSessionId !== '') {
                session_id($originalSessionId);
                session_start();
                session_write_close();
            }
        }
        // Reverse insertion order removes our children before their own parents.
        foreach (array_reverse(array_keys($owned)) as $table) {
            foreach (array_reverse($owned[$table]) as $id) {
                $pdo->prepare('DELETE FROM ' . $table . ' WHERE id = ?')->execute([$id]);
            }
        }
        foreach ($settings as $module => $value) {
            saveSetting('module_' . $module, $value);
        }
        saveSetting('content_editor', $oldEditor);
        clearSettingsCache();
    }
    return $issues;
}
