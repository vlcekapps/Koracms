<?php

declare(strict_types=1);

/**
 * @param array{cookie:string,csrf:string} $adminSession
 * @return list<string>
 */
function rcPlaceIntegrityHttpChecks(PDO $pdo, string $baseUrl, array $adminSession): array
{
    $prefix = 'rc-place-' . bin2hex(random_bytes(8));
    $trigger = 'rc_place_write_' . bin2hex(random_bytes(8));
    $oldModule = getSetting('module_places', '0');
    $issues = $ids = $ownedFiles = [];
    $triggerCreated = false;
    $directory = dirname(__DIR__) . '/uploads/places/';
    $check = static function (bool $ok, string $label): void {
        if (!$ok) {
            throw new RuntimeException($label);
        }
    };
    $get = static fn (string $path): array => fetchUrl($baseUrl . BASE_URL . $path, $adminSession['cookie'], 0);
    $post = static function (string $endpoint, string $review, array $fields, array $files = []) use ($get, $check, $baseUrl, $adminSession): array {
        $page = $get($review);
        $token = extractHiddenInputValue($page['body'], 'csrf_token');
        $check(httpIntegrationStatusCode($page) === 200 && $token !== '', 'Cannot obtain Places editor CSRF');
        $url = $baseUrl . BASE_URL . '/admin/' . $endpoint;
        return $files === [] ? postUrl($url, ['csrf_token' => $token] + $fields, $adminSession['cookie'], 0)
            : postMultipartUrl($url, ['csrf_token' => $token] + $fields, $files, $adminSession['cookie'], 0);
    };
    $read = static function (int $id) use ($pdo, $check, $prefix): array {
        $stmt = $pdo->prepare('SELECT * FROM cms_places WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch() ?: [];
        $check($row === [] || (str_starts_with((string)$row['name'], $prefix) && str_starts_with((string)$row['slug'], $prefix)), 'Unowned Places fixture');
        return $row;
    };
    $snapshot = static function (int $id) use ($pdo, $read): array {
        $stmt = $pdo->prepare("SELECT * FROM cms_revisions WHERE entity_type = 'place' AND entity_id = ? ORDER BY id");
        $stmt->execute([$id]);
        return [$read($id), $stmt->fetchAll()];
    };
    $aria = static function (string $html) use ($check): DOMXPath {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        foreach ($xpath->query('//*[@aria-describedby or @aria-labelledby]') as $element) {
            foreach (['aria-describedby', 'aria-labelledby'] as $attribute) {
                foreach (preg_split('/\s+/', trim($element->getAttribute($attribute)), -1, PREG_SPLIT_NO_EMPTY) as $target) {
                    $check($xpath->query('//*[@id="' . $target . '"]')->length === 1, 'Missing/duplicate Places ARIA target ' . $target);
                }
            }
        }
        return $xpath;
    };
    $listFiles = static function () use ($directory): array {
        $files = array_values(array_filter(glob($directory . '*') ?: [], 'is_file'));
        sort($files);
        return $files;
    };
    $saveFailure = static function (array $response, ?int $id) use ($check, $baseUrl): void {
        $query = ['err' => 'save'];
        if ($id !== null) {
            $query['id'] = (string)$id;
        }
        $query['redirect'] = BASE_URL . '/admin/places.php';
        $check(
            responseHasLocationHeader($response['headers'], BASE_URL . '/admin/place_form.php?' . http_build_query($query), $baseUrl),
            'Places storage failure did not return an actual save error'
        );
    };
    try {
        saveSetting('module_places', '1');
        koraEnsureDirectory($directory);
        $image = $prefix . '.png';
        foreach ([$image, $prefix . '.webp'] as $filename) {
            $path = $directory . $filename;
            $stream = fopen($path, 'xb');
            $check(is_resource($stream), 'Cannot create owned Places image');
            $ownedFiles[] = $path;
            fwrite($stream, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a7x8AAAAASUVORK5CYII=', true));
            fclose($stream);
        }
        $stmt = $pdo->prepare("INSERT INTO cms_places (name,slug,image_file,status,is_published) VALUES (?,?,?,'draft',1)");
        foreach (['original', 'shared', 'purge-shared'] as $suffix) {
            $stmt->execute([$prefix . '-' . $suffix, $prefix . '-' . $suffix, $image]);
            $ids[] = (int)$pdo->lastInsertId();
        }
        $id = $ids[0];
        $review = '/admin/place_form.php?id=' . $id;
        $fields = ['id' => (string)$id, 'name' => $prefix . '-edited', 'slug' => $prefix . '-edited',
            'description' => 'Zachovaný český popis místa', 'contact_email' => 'invalid', 'article_status' => 'draft',
            'url' => 'https://example.test/place', 'latitude' => '50.1', 'longitude' => '14.2'];
        $before = $snapshot($id);
        $pdo->prepare('UPDATE cms_places SET deleted_at = NOW() WHERE id = ?')->execute([$ids[2]]);
        $purged = $post('trash.php', '/admin/trash.php', ['action' => 'purge', 'module' => 'places',
            'id' => (string)$ids[2], 'confirm_permanent_delete' => '1']);
        clearstatcache();
        $check(httpIntegrationStatusCode($purged) === 302 && $read($ids[2]) === []
            && is_file($directory . $image) && is_file($directory . $prefix . '.webp'), 'Places purge deleted a still shared image');
        $response = $post('place_save.php', $review, $fields);
        $check(httpIntegrationStatusCode($response) === 302 && $snapshot($id) === $before, 'Invalid Places email changed data');
        $page = $get($review . '&err=email');
        $xpath = $aria($page['body']);
        $check(str_contains($page['body'], 'role="alert"') && str_contains($page['body'], $fields['description'])
            && httpIntegrationInputHasAttributes($page['body'], 'name', ['value' => $fields['name']])
            && httpIntegrationInputHasAttributes($page['body'], 'contact_email', ['value' => 'invalid'])
            && $xpath->query('//input[@name="is_published" and @checked]')->length === 0, 'Places validation lost draft/unchecked publication');

        $fields['contact_email'] = '';
        foreach (['place_image_delete' => '1', 'confirm_place_image_delete' => 'yes'] as $field => $value) {
            $denied = $post('place_save.php', $review, $fields + [$field => $value]);
            $check(httpIntegrationStatusCode($denied) === 302 && $snapshot($id) === $before, 'Places deletion accepted stale/invalid consent');
            $page = $get($review . '&err=image_confirm');
            $xpath = $aria($page['body']);
            $check($xpath->query('//input[@name="confirm_place_image_delete" and @aria-invalid="true" and not(@checked)]')->length === 1, 'Places confirmation recovery is inaccessible or restores consent');
        }

        // Exercise the real multipart upload and fail its following revision INSERT.
        $pdo->exec("CREATE TRIGGER {$trigger} BEFORE INSERT ON cms_revisions FOR EACH ROW BEGIN
            IF NEW.entity_type = 'place' AND NEW.entity_id = {$id} THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Owned Places revision failure';
            END IF; END");
        $triggerCreated = true;
        $filesBefore = $listFiles();
        $failed = $post('place_save.php', $review, $fields, [
            'place_image' => ['path' => $directory . $image, 'filename' => 'fixture.png', 'type' => 'image/png'],
        ]);
        $saveFailure($failed, $id);
        clearstatcache();
        $check(httpIntegrationStatusCode($failed) === 302 && $snapshot($id) === $before
            && $listFiles() === $filesBefore, 'Failed Places revision changed DB/original files or leaked a staged upload');
        $page = $get($review . '&err=save');
        $aria($page['body']);
        $check(str_contains($page['body'], 'role="alert"') && str_contains($page['body'], $fields['description']), 'Places storage failure lost the accessible draft');
        $pdo->exec('DROP TRIGGER ' . $trigger);
        $triggerCreated = false;

        // Redirect failure must roll back both the row and its revisions.
        $oldPath = placePublicPath($before[0]);
        $pdo->exec("CREATE TRIGGER {$trigger} BEFORE INSERT ON cms_redirects FOR EACH ROW BEGIN
            IF NEW.old_path = " . $pdo->quote($oldPath) . " THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Owned Places redirect failure';
            END IF; END");
        $triggerCreated = true;
        $failed = $post('place_save.php', $review, $fields);
        $saveFailure($failed, $id);
        $check(httpIntegrationStatusCode($failed) === 302 && $snapshot($id) === $before, 'Failed Places redirect left partial changes');
        $pdo->exec('DROP TRIGGER ' . $trigger);
        $triggerCreated = false;

        $saved = $post('place_save.php', $review, $fields + ['confirm_place_image_delete' => '1']);
        clearstatcache();
        $check(httpIntegrationStatusCode($saved) === 302 && (string)$read($id)['image_file'] === ''
            && is_file($directory . $image) && is_file($directory . $prefix . '.webp'), 'Places save removed a shared image');
        $redirect = $pdo->prepare('SELECT new_path FROM cms_redirects WHERE old_path = ?');
        $redirect->execute([$oldPath]);
        $check($redirect->fetchColumn() === placePublicPath($read($id)), 'Places save did not commit canonical redirect');

        $pdo->prepare("UPDATE cms_places SET status = 'published' WHERE id = ?")->execute([$ids[1]]);
        $publicImage = fetchUrl($baseUrl . BASE_URL . '/places/image.php?id=' . $ids[1], '', 0);
        $check(httpIntegrationStatusCode($publicImage) === 200 && $publicImage['body'] === file_get_contents($directory . $image), 'Places public image failed');
        foreach (['../' . $image, '..\\' . $image, '.htaccess', $prefix . '.php'] as $invalidName) {
            $pdo->prepare('UPDATE cms_places SET image_file = ? WHERE id = ?')->execute([$invalidName, $ids[1]]);
            $invalidImage = fetchUrl($baseUrl . BASE_URL . '/places/image.php?id=' . $ids[1], '', 0);
            $check(httpIntegrationStatusCode($invalidImage) === 404, 'Places image alias/path was accepted: ' . $invalidName);
        }
        $pdo->prepare('UPDATE cms_places SET image_file = ? WHERE id = ?')->execute([$image, $ids[1]]);

        // An imported second reference in the trash still owns the image.
        $pdo->prepare('UPDATE cms_places SET deleted_at = NOW() WHERE id = ?')->execute([$ids[1]]);
        $purged = $post('trash.php', '/admin/trash.php', ['action' => 'purge', 'module' => 'places',
            'id' => (string)$ids[1], 'confirm_permanent_delete' => '1']);
        clearstatcache();
        $check(httpIntegrationStatusCode($purged) === 302 && $read($ids[1]) === []
            && !is_file($directory . $image) && !is_file($directory . $prefix . '.webp'), 'Places final purge did not clean both image variants');

        $invalidCsrf = requestRawUrl('POST', $baseUrl . BASE_URL . '/admin/place_save.php', http_build_query(['csrf_token' => 'invalid'] + $fields), 'application/x-www-form-urlencoded', $adminSession['cookie'], 0);
        $check(httpIntegrationStatusCode($invalidCsrf) === 403 && $read($id)['name'] === $fields['name'], 'Places save bypassed CSRF');

        $newFields = ['name' => $prefix . '-new', 'slug' => $prefix . '-new', 'description' => 'Nové místo po chybě zápisu', 'article_status' => 'draft'];
        $pdo->exec("CREATE TRIGGER {$trigger} BEFORE INSERT ON cms_places FOR EACH ROW BEGIN
            IF NEW.slug = " . $pdo->quote($newFields['slug']) . " THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Owned Places create failure';
            END IF; END");
        $triggerCreated = true;
        // Use an owned fixture as multipart input, without writing any public item.
        $fixture = $directory . $prefix . '-input.png';
        $stream = fopen($fixture, 'xb');
        $check(is_resource($stream), 'Cannot create Places multipart source');
        $ownedFiles[] = $fixture;
        fwrite($stream, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a7x8AAAAASUVORK5CYII=', true));
        fclose($stream);
        $filesBefore = $listFiles();
        $failed = $post('place_save.php', '/admin/place_form.php', $newFields, [
            'place_image' => ['path' => $fixture, 'filename' => 'fixture.png', 'type' => 'image/png'],
        ]);
        $saveFailure($failed, null);
        clearstatcache();
        $check(httpIntegrationStatusCode($failed) === 302 && $listFiles() === $filesBefore, 'Failed Places create leaked staged files');
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM cms_places WHERE slug = ?');
        $stmt->execute([$newFields['slug']]);
        $check((int)$stmt->fetchColumn() === 0, 'Failed Places create left a row');
        $page = $get('/admin/place_form.php?err=save');
        $aria($page['body']);
        $check(str_contains($page['body'], $newFields['description']) && str_contains($page['body'], 'role="alert"'), 'New Places draft was not preserved after a write error');
        $pdo->exec('DROP TRIGGER ' . $trigger);
        $triggerCreated = false;

        $saved = $post('place_save.php', '/admin/place_form.php', $newFields, [
            'place_image' => ['path' => $fixture, 'filename' => 'fixture.png', 'type' => 'image/png'],
        ]);
        $created = $pdo->prepare('SELECT id FROM cms_places WHERE slug = ?');
        $created->execute([$newFields['slug']]);
        $newId = (int)$created->fetchColumn();
        if ($newId > 0) {
            $ids[] = $newId;
        }
        $newRow = $newId > 0 ? $read($newId) : [];
        $storedImage = (string)($newRow['image_file'] ?? '');
        $check(httpIntegrationStatusCode($saved) === 302 && $newRow !== []
            && str_starts_with($storedImage, 'place_image_') && placeStoredImageFilename($storedImage) === $storedImage, 'Valid Places creation/upload failed');
        foreach (array_unique([$storedImage, pathinfo($storedImage, PATHINFO_FILENAME) . '.webp']) as $filename) {
            $ownedFiles[] = $directory . $filename;
        }
        clearstatcache();
        $check(is_file($directory . $storedImage) && hash_file('sha256', $directory . $storedImage) === hash_file('sha256', $fixture), 'Places upload bytes were not preserved');
        $privateImageUrl = $baseUrl . BASE_URL . '/places/image.php?id=' . $newId;
        $check(httpIntegrationStatusCode(fetchUrl($privateImageUrl, '', 0)) === 404
            && httpIntegrationStatusCode(fetchUrl($privateImageUrl, $adminSession['cookie'], 0)) === 200, 'Places upload bypassed draft visibility');

        $page = $get($review);
        $token = extractHiddenInputValue($page['body'], 'csrf_token');
        $pdo->prepare('UPDATE cms_places SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
        $trashed = $read($id);
        $failed = postUrl($baseUrl . BASE_URL . '/admin/place_save.php', ['csrf_token' => $token] + $fields, $adminSession['cookie'], 0);
        $check(httpIntegrationStatusCode($failed) === 302 && $read($id) === $trashed, 'Stale Places save modified a trashed row');
        $page = $get($review . '&err=missing');
        $xpath = $aria($page['body']);
        $check(
            httpIntegrationStatusCode($page) === 200 && str_contains($page['body'], $fields['description'])
            && str_contains($page['body'], 'role="alert"') && $xpath->query('//button[@type="submit" and @disabled and @aria-describedby="form-error"]')->length === 1,
            'Stale Places save lost recovery data or offered an invalid save'
        );
    } catch (Throwable $exception) {
        $issues[] = $exception->getMessage();
    } finally {
        if ($triggerCreated) {
            $pdo->exec('DROP TRIGGER IF EXISTS ' . $trigger);
        }
        // Clean a failed assertion's unexpected successful create, but only our exact slug.
        $created = $pdo->prepare('SELECT id FROM cms_places WHERE slug = ? AND name = ?');
        $created->execute([$prefix . '-new', $prefix . '-new']);
        $ids = array_unique(array_merge($ids, array_map('intval', $created->fetchAll(PDO::FETCH_COLUMN))));
        foreach ($ids as $id) {
            $row = $read($id);
            if ($row !== []) {
                $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'place' AND entity_id = ?")->execute([$id]);
                $pdo->prepare('DELETE FROM cms_places WHERE id = ?')->execute([$id]);
            }
        }
        $pdo->prepare('DELETE FROM cms_redirects WHERE old_path LIKE ?')->execute(['/places/' . $prefix . '%']);
        foreach ($ownedFiles as $path) {
            clearstatcache(true, $path);
            if (is_file($path)) {
                unlink($path);
            }
        }
        saveSetting('module_places', $oldModule);
    }
    return $issues;
}
