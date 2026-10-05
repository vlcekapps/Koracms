<?php

declare(strict_types=1);

/**
 * Called by the main HTTP integration runner; loading this file performs no I/O.
 * @param array{cookie:string,csrf:string} $adminSession
 * @return list<string>
 */
function rcFoodMediaUsageHttpChecks(PDO $pdo, string $baseUrl, array $adminSession): array
{
    $prefix = 'rc-food-media-' . bin2hex(random_bytes(8));
    $oldModule = getSetting('module_food', '0');
    $issues = [];
    $fixtures = [];
    $cards = [];
    $tempFiles = [];
    $mediaPath = BASE_URL . '/admin/media.php?q=' . rawurlencode($prefix);
    $mediaUrl = $baseUrl . BASE_URL . '/admin/media.php';
    $readMedia = static function (int $id) use ($pdo): ?array {
        $stmt = $pdo->prepare('SELECT * FROM cms_media WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    };
    $hashes = static function (array $media, ?string $visibility = null): array {
        $result = [];
        foreach (mediaPhysicalPaths($media, $visibility) as $path) {
            clearstatcache(true, $path);
            if (is_file($path)) {
                $result[$path] = (string)hash_file('sha256', $path);
            }
        }
        return $result;
    };
    $csrf = static function () use ($baseUrl, $mediaPath, $adminSession): string {
        $page = fetchUrl($baseUrl . $mediaPath, $adminSession['cookie'], 0);
        $token = extractHiddenInputValue($page['body'], 'csrf_token');
        if (httpIntegrationStatusCode($page) !== 200 || $token === '') {
            throw new RuntimeException('Food media fixture could not obtain a current admin CSRF token');
        }
        return $token;
    };
    $post = static function (array $fields) use ($csrf, $mediaUrl, $mediaPath, $adminSession): array {
        return postUrl($mediaUrl, ['csrf_token' => $csrf(), 'return_to' => $mediaPath] + $fields, $adminSession['cookie'], 0);
    };
    $snapshot = static function () use ($pdo, &$cards): array {
        $result = [];
        foreach ($cards as $cardId => $slug) {
            foreach (['cms_food_cards' => 'id', 'cms_food_sections' => 'card_id', 'cms_food_items' => 'card_id'] as $table => $column) {
                $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE {$column} = ? ORDER BY id");
                $stmt->execute([$cardId]);
                $result[$cardId][$table] = $stmt->fetchAll();
            }
        }
        return $result;
    };

    try {
        saveSetting('module_food', '1');
        foreach (['public' => [1, null], 'hidden' => [0, null], 'deleted' => [1, '2026-10-05 12:00:00']] as $state => [$published, $deletedAt]) {
            $originalName = $prefix . '-' . $state . '.png';
            $png = httpIntegrationCreatePngFixtureFile('kora-food-media-', $tempFiles, 20, 20);
            $response = postMultipartUrl($mediaUrl, [
                'csrf_token' => $csrf(), 'action' => 'upload', 'upload_visibility' => 'public', 'return_to' => $mediaPath,
            ], ['media_files[0]' => ['path' => $png, 'filename' => $originalName, 'type' => 'image/png']], $adminSession['cookie'], 0);
            $stmt = $pdo->prepare('SELECT * FROM cms_media WHERE original_name = ? ORDER BY id DESC LIMIT 1');
            $stmt->execute([$originalName]);
            $media = $stmt->fetch() ?: null;
            if (is_array($media)) {
                $fixtures[] = ['media' => $media, 'state' => $state];
            }
            if (httpIntegrationStatusCode($response) !== 302 || !is_array($media)) {
                throw new RuntimeException('Food media fixture upload failed: ' . $state);
            }
            $files = $hashes($media);
            if (!isset($files[mediaOriginalPath($media)], $files[mediaThumbPath($media)])) {
                throw new RuntimeException('Food media fixture upload did not create its original and thumbnail');
            }
            $slug = $prefix . '-' . $state;
            $pdo->prepare("INSERT INTO cms_food_cards (type,title,slug,description,content,status,is_published,deleted_at)
                VALUES ('food',?,?,?,?,'published',?,?)")
                ->execute([$slug, $slug, '', '', $published, $deletedAt]);
            $cardId = (int)$pdo->lastInsertId();
            $cards[$cardId] = $slug;
            $pdo->prepare('INSERT INTO cms_food_sections (card_id,title) VALUES (?,?)')->execute([$cardId, $slug]);
            $sectionId = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO cms_food_items (card_id,section_id,title,media_id,is_available) VALUES (?,?,?,?,0)')
                ->execute([$cardId, $sectionId, $slug . ' item', (int)$media['id']]);
            $itemId = (int)$pdo->lastInsertId();
            $fixtures[array_key_last($fixtures)] += ['card_id' => $cardId, 'item_id' => $itemId, 'files' => $files];
        }

        // Disabling Food must not release references, including hidden/deleted menus.
        saveSetting('module_food', '0');
        $before = $snapshot();
        foreach ($fixtures as $fixture) {
            $media = $fixture['media'];
            $mediaId = (int)$media['id'];
            $state = $fixture['state'];
            $editor = BASE_URL . '/admin/food_items.php?card=' . $fixture['card_id'] . '&edit_item=' . $fixture['item_id'];
            $page = fetchUrl($baseUrl . BASE_URL . '/admin/media.php?edit=' . $mediaId, $adminSession['cookie'], 0);
            if (httpIntegrationStatusCode($page) !== 200 || !str_contains($page['body'], 'href="' . h($editor) . '"')
                || !str_contains($page['body'], h($cards[$fixture['card_id']] . ' item'))) {
                $issues[] = $state . ' Food item usage is missing its owning editor link in the actual media page';
            }
            foreach ([
                ['action' => 'delete', 'media_id' => $mediaId, 'confirm_media_delete_' . $mediaId => '1'],
                ['action' => 'bulk', 'bulk_action' => 'delete_unused', 'media_ids' => [$mediaId]],
                ['action' => 'update_meta', 'media_id' => $mediaId, 'visibility' => 'private'],
                ['action' => 'bulk', 'bulk_action' => 'make_private', 'media_ids' => [$mediaId]],
            ] as $fields) {
                $response = $post($fields);
                $page = fetchUrl($baseUrl . $mediaPath, $adminSession['cookie'], 0);
                $kind = (string)($fields['bulk_action'] ?? $fields['action']);
                $error = match ($fields['action']) {
                    'delete' => 'Použité médium nelze smazat',
                    'update_meta' => 'Použité médium nelze přepnout do soukromého režimu',
                    default => 'médií byla akce zablokována kvůli použití',
                };
                if (httpIntegrationStatusCode($response) !== 302
                    || !responseHasLocationHeader($response['headers'], $mediaPath, $baseUrl)
                    || httpIntegrationStatusCode($page) !== 200 || !str_contains($page['body'], $error)
                    || !str_contains($page['body'], 'role="alert"')
                    || $readMedia($mediaId) !== $media || $hashes($media) !== $fixture['files'] || $snapshot() !== $before
                    || $hashes($media, 'private') !== []) {
                    $issues[] = $state . ' disabled Food usage did not safely reject ' . $kind . ' with accessible feedback and unchanged metadata/files';
                }
                foreach ($fixtures as $other) {
                    if ($readMedia((int)$other['media']['id']) !== $other['media'] || $hashes($other['media']) !== $other['files']) {
                        $issues[] = $state . ' ' . $kind . ' changed another fixture media file or metadata';
                    }
                }
            }
        }

        foreach ($fixtures as $fixture) {
            $media = $fixture['media'];
            $mediaId = (int)$media['id'];
            $pdo->prepare('UPDATE cms_food_items SET media_id = NULL WHERE id = ? AND card_id = ?')
                ->execute([$fixture['item_id'], $fixture['card_id']]);
            $afterDetach = $snapshot();
            $page = fetchUrl($baseUrl . BASE_URL . '/admin/media.php?edit=' . $mediaId, $adminSession['cookie'], 0);
            if (httpIntegrationStatusCode($page) !== 200 || !str_contains($page['body'], 'Toto médium zatím není nikde nalezené.')) {
                $issues[] = $fixture['state'] . ' Food detachment did not remove the structural usage in a fresh HTTP request';
            }
            $fields = $fixture['state'] === 'hidden'
                ? ['action' => 'bulk', 'bulk_action' => 'delete_unused', 'media_ids' => [$mediaId]]
                : ['action' => 'delete', 'media_id' => $mediaId, 'confirm_media_delete_' . $mediaId => '1'];
            $response = $post($fields);
            $page = fetchUrl($baseUrl . $mediaPath, $adminSession['cookie'], 0);
            $success = $fields['action'] === 'bulk' ? 'Smazáno 1 nepoužitých médií.' : 'Soubor byl smazán.';
            if (httpIntegrationStatusCode($response) !== 302
                || !responseHasLocationHeader($response['headers'], $mediaPath, $baseUrl)
                || httpIntegrationStatusCode($page) !== 200 || !str_contains($page['body'], $success)
                || !str_contains($page['body'], 'role="status"')
                || $readMedia($mediaId) !== null || $hashes($media) !== [] || $hashes($media, 'private') !== [] || $snapshot() !== $afterDetach) {
                $issues[] = $fixture['state'] . ' detached Food media was not fully deleted without changing its menu';
            }
            foreach ($fixtures as $other) {
                if ((int)$other['media']['id'] > $mediaId
                    && ($readMedia((int)$other['media']['id']) !== $other['media'] || $hashes($other['media']) !== $other['files'])) {
                    $issues[] = 'Detached Food media deletion changed an unrelated remaining fixture';
                }
            }
        }
    } catch (Throwable $exception) {
        $issues[] = 'Food media usage HTTP fixture failed: ' . $exception->getMessage();
    } finally {
        try {
            foreach ($cards as $cardId => $slug) {
                $owner = $pdo->prepare('SELECT slug FROM cms_food_cards WHERE id = ?');
                $owner->execute([$cardId]);
                if ($owner->fetchColumn() !== $slug) {
                    throw new RuntimeException('Food media HTTP cleanup refused a foreign menu');
                }
                $pdo->prepare('DELETE FROM cms_food_items WHERE card_id = ?')->execute([$cardId]);
                $pdo->prepare('DELETE FROM cms_food_sections WHERE card_id = ?')->execute([$cardId]);
                $pdo->prepare('DELETE FROM cms_food_cards WHERE id = ? AND slug = ?')->execute([$cardId, $slug]);
            }
            foreach ($fixtures as $fixture) {
                $media = $fixture['media'];
                $current = $readMedia((int)$media['id']);
                if ($current !== null && ($current['filename'] !== $media['filename'] || $current['original_name'] !== $media['original_name'])) {
                    throw new RuntimeException('Food media HTTP cleanup refused foreign media');
                }
                if (!mediaDeletePhysicalFiles($media, 'private')
                    || !mediaDeletePhysicalFiles($media, 'public', null, static fn (): bool => $pdo->prepare('DELETE FROM cms_media WHERE id = ? AND original_name = ?')
                    ->execute([(int)$media['id'], $media['original_name']]))) {
                    throw new RuntimeException('Food media HTTP cleanup could not remove fixture files');
                }
                if ($hashes($media, 'public') !== [] || $hashes($media, 'private') !== []) {
                    throw new RuntimeException('Food media HTTP cleanup left fixture files');
                }
            }
        } catch (Throwable $exception) {
            $issues[] = 'Food media HTTP cleanup failed: ' . $exception->getMessage();
        } finally {
            foreach ($tempFiles as $path) {
                if (is_file($path) && !unlink($path)) {
                    $issues[] = 'Food media HTTP cleanup could not remove its temporary upload';
                }
            }
            saveSetting('module_food', $oldModule);
        }
    }
    return array_values(array_unique($issues));
}
