<?php

declare(strict_types=1);

/**
 * @param array{id:string,cookie:string,csrf:string} $adminSession
 * @return list<string>
 */
function rcBoardIntegrityHttpChecks(PDO $pdo, string $baseUrl, array $adminSession): array
{
    $prefix = 'rc-board-write-' . bin2hex(random_bytes(8));
    $trigger = 'rc_board_write_' . bin2hex(random_bytes(8));
    $oldModule = getSetting('module_board', '0');
    $oldNewsletter = getSetting('module_newsletter', '0');
    $tempFiles = $storedFiles = $createdIds = [];
    $triggerCreated = false;
    $issues = [];
    $subscriberId = 0;
    $check = static function (bool $ok, string $label): void {
        if (!$ok) {
            throw new RuntimeException($label);
        }
    };
    $get = static fn (string $path): array => fetchUrl($baseUrl . BASE_URL . $path, $adminSession['cookie'], 0);
    $post = static function (string $endpoint, string $review, array $fields, array $files = []) use ($get, $check, $baseUrl, $adminSession): array {
        $page = $get($review);
        $token = extractHiddenInputValue($page['body'], 'csrf_token');
        $check(httpIntegrationStatusCode($page) === 200 && $token !== '', 'Cannot obtain Board review CSRF');
        $url = $baseUrl . BASE_URL . '/admin/' . $endpoint;
        return $files !== [] ? postMultipartUrl($url, ['csrf_token' => $token] + $fields, $files, $adminSession['cookie'], 0)
            : postUrl($url, ['csrf_token' => $token] + $fields, $adminSession['cookie'], 0);
    };
    $read = static function (int $id) use ($pdo, $prefix): array {
        $statement = $pdo->prepare('SELECT * FROM cms_board WHERE id = ?');
        $statement->execute([$id]);
        $row = $statement->fetch() ?: [];
        if ($row !== [] && (!str_starts_with((string)$row['slug'], $prefix) || !str_starts_with((string)$row['title'], $prefix))) {
            throw new RuntimeException('Refusing unowned Board fixture');
        }
        return $row;
    };
    $snapshot = static function (int $id) use ($pdo, $read): array {
        $result = [$read($id)];
        foreach (['cms_board_publication_events' => 'board_id', 'cms_revisions' => 'entity_id'] as $table => $key) {
            $sql = 'SELECT * FROM ' . $table . ' WHERE ' . $key . ' = ?';
            if ($table === 'cms_revisions') {
                $sql .= " AND entity_type = 'board'";
            }
            $statement = $pdo->prepare($sql . ' ORDER BY id');
            $statement->execute([$id]);
            $result[] = $statement->fetchAll();
        }
        return $result;
    };
    $aria = static function (string $html) use ($check): DOMXPath {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//*[@aria-describedby or @aria-labelledby]') as $element) {
            foreach (['aria-describedby', 'aria-labelledby'] as $attribute) {
                foreach (preg_split('/\s+/', trim($element->getAttribute($attribute)), -1, PREG_SPLIT_NO_EMPTY) as $id) {
                    $check($xpath->query('//*[@id="' . $id . '"]')->length === 1, 'Missing/duplicate Board ARIA target ' . $id);
                }
            }
        }
        return $xpath;
    };
    $listFiles = static function (): array {
        $paths = array_merge(
            glob(dirname(__DIR__) . '/uploads/board/board_*') ?: [],
            glob(dirname(__DIR__) . '/uploads/board/images/board_image_*') ?: []
        );
        $paths = array_values(array_filter($paths, 'is_file'));
        sort($paths);
        return $paths;
    };
    try {
        saveSetting('module_board', '1');
        saveSetting('module_newsletter', '1');
        $uploadText = httpIntegrationCreateTempFile('kora-board-', 'New owned attachment bytes', $tempFiles);
        $uploadImage = httpIntegrationCreatePngFixtureFile('kora-board-', $tempFiles);
        $attachment = $prefix . '.txt';
        $image = $prefix . '.png';
        foreach (['uploads/board/' . $attachment => 'Original owned attachment', 'uploads/board/images/' . $image => (string)file_get_contents($uploadImage)] as $relative => $contents) {
            $path = dirname(__DIR__) . '/' . $relative;
            koraEnsureDirectory(dirname($path));
            $stream = fopen($path, 'xb');
            $check(is_resource($stream), 'Cannot create owned Board file');
            $storedFiles[] = $path;
            fwrite($stream, $contents);
            fclose($stream);
        }
        $pdo->prepare("INSERT INTO cms_board (title,slug,posted_date,filename,original_name,file_size,image_file,status,is_published)
            VALUES (?,?,CURDATE(),?,?,?,?, 'published',1)")->execute([
                $prefix . '-document', $prefix . '-document', $attachment, $prefix . '.txt', 25, $image,
            ]);
        $id = (int)$pdo->lastInsertId();
        $createdIds[] = $id;
        $review = '/admin/board_form.php?id=' . $id;
        $fields = ['id' => (string)$id, 'title' => $prefix . '-revised', 'slug' => $prefix . '-document',
            'description' => 'Rozepsaný český popis vývěsky', 'posted_date' => date('Y-m-d'),
            'contact_email' => 'invalid', 'article_status' => 'published'];
        $before = $snapshot($id);
        foreach (['board_save.php', 'board_clone.php', 'board_delete.php'] as $endpoint) {
            $csrfDenied = postRawUrl(
                $baseUrl . BASE_URL . '/admin/' . $endpoint,
                http_build_query(['csrf_token' => 'invalid-owned-token', 'confirm_board_delete_' . $id => '1'] + $fields),
                'application/x-www-form-urlencoded',
                $adminSession['cookie'],
                0
            );
            $check(httpIntegrationStatusCode($csrfDenied) === 403 && $snapshot($id) === $before, 'Invalid CSRF changed Board data through ' . $endpoint);
        }
        $failed = $post('board_save.php', $review, $fields);
        $check(httpIntegrationStatusCode($failed) === 302 && $snapshot($id) === $before, 'Board validation changed data');
        $page = $get($review . '&err=contact_email');
        $xpath = $aria($page['body']);
        $check(str_contains($page['body'], 'role="alert"')
            && httpIntegrationInputHasAttributes($page['body'], 'title', ['value' => $fields['title']])
            && str_contains($page['body'], $fields['description'])
            && $xpath->query('//input[@name="is_published" and @checked]')->length === 0
            && $xpath->query('//input[@name="confirm_board_image_delete" and @checked]')->length === 0, 'Rejected Board editor lost data or restored consent');
        $fields['contact_email'] = '';
        $fields['is_published'] = '1';
        $pdo->exec("CREATE TRIGGER {$trigger} BEFORE INSERT ON cms_board_publication_events FOR EACH ROW BEGIN
            IF NEW.board_id = {$id} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Owned Board evidence failure'; END IF; END");
        $triggerCreated = true;
        $fileList = $listFiles();
        $uploads = ['file' => ['path' => $uploadText, 'filename' => $prefix . '-new.txt', 'type' => 'text/plain'],
            'board_image' => ['path' => $uploadImage, 'filename' => $prefix . '-new.png', 'type' => 'image/png']];
        $failed = $post('board_save.php', $review, $fields, $uploads);
        $check(httpIntegrationStatusCode($failed) === 302, 'Failed Board evidence did not return PRG');
        $check(responseHasLocationHeader($failed['headers'], BASE_URL . '/admin/board_form.php?err=save&id=' . $id, $baseUrl), 'Failed Board evidence did not return the correct editor error');
        $check($snapshot($id) === $before, 'Failed Board evidence did not roll back the whole write');
        $check($listFiles() === $fileList, 'Failed Board upload left a new file or removed an original');
        $check(file_get_contents(dirname(__DIR__) . '/uploads/board/' . $attachment) === 'Original owned attachment', 'Original attachment bytes changed');
        $check(hash_file('sha256', dirname(__DIR__) . '/uploads/board/images/' . $image) === hash_file('sha256', $uploadImage), 'Original image bytes changed');
        $aria($get($review . '&err=save')['body']);
        $denied = $post('board_delete.php', '/admin/board.php', ['id' => (string)$id]);
        $check(httpIntegrationStatusCode($denied) === 302 && $snapshot($id) === $before, 'Board deletion bypassed consent');
        $page = $get('/admin/board.php?delete_error=confirm_required&delete_error_id=' . $id);
        $xpath = $aria($page['body']);
        $check($xpath->query('//input[@name="confirm_board_delete_' . $id . '" and @aria-invalid="true"]')->length === 1, 'Board deletion error has no field-level explanation');
        $failed = $post('board_delete.php', '/admin/board.php', ['id' => (string)$id, 'confirm_board_delete_' . $id => '1']);
        $check(httpIntegrationStatusCode($failed) === 302 && $snapshot($id) === $before, 'Delete evidence failure left partial deletion');
        $pdo->exec('DROP TRIGGER ' . $trigger);
        $triggerCreated = false;

        $saved = $post('board_save.php', $review, $fields, $uploads);
        $updated = $read($id);
        foreach (['filename' => '/uploads/board/', 'image_file' => '/uploads/board/images/'] as $column => $directory) {
            $name = (string)($updated[$column] ?? '');
            $check($name !== '' && basename($name) === $name, 'Successful Board upload has no valid stored name');
            $storedFiles[] = dirname(__DIR__) . $directory . $name;
        }
        $events = $snapshot($id)[1];
        $check(httpIntegrationStatusCode($saved) === 302 && count($events) === 1
            && $events[0]['event_type'] === 'attachment_changed'
            && $events[0]['attachment_checksum'] === hash_file('sha256', $uploadText), 'Successful replacement lacks matching evidence/checksum');
        $clone = $post('board_clone.php', $review, ['id' => (string)$id]);
        $cloneStmt = $pdo->prepare('SELECT id FROM cms_board WHERE slug = ?');
        $cloneStmt->execute([$prefix . '-document-kopie']);
        $cloneId = (int)$cloneStmt->fetchColumn();
        $check(httpIntegrationStatusCode($clone) === 302 && $cloneId > 0, 'Cannot create owned Board clone');
        $createdIds[] = $cloneId;
        $sharedImage = (string)$updated['image_file'];
        $fields['confirm_board_image_delete'] = '1';
        $post('board_save.php', $review, $fields);
        clearstatcache();
        $check($read($id)['image_file'] === '' && is_file(dirname(__DIR__) . '/uploads/board/images/' . $sharedImage), 'Image detachment broke a clone');

        // A hidden owned fixture tests restoration without mailing real subscribers.
        $pdo->prepare('UPDATE cms_board SET is_published = 0 WHERE id = ?')->execute([$id]);
        $deleted = $post('board_delete.php', '/admin/board.php', ['id' => (string)$id, 'confirm_board_delete_' . $id => '1']);
        $check(httpIntegrationStatusCode($deleted) === 302 && $read($id)['deleted_at'] !== null, 'Confirmed Board trash failed');
        $deletedSnapshot = $snapshot($id);
        $pdo->exec("CREATE TRIGGER {$trigger} BEFORE INSERT ON cms_board_publication_events FOR EACH ROW BEGIN
            IF NEW.board_id = {$id} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Owned Board restore evidence failure'; END IF; END");
        $triggerCreated = true;
        $restore = $post('trash.php', '/admin/trash.php', ['action' => 'restore', 'module' => 'board', 'id' => (string)$id]);
        $check(httpIntegrationStatusCode($restore) === 302 && $snapshot($id) === $deletedSnapshot, 'Failed restore changed data/evidence');
        $pdo->exec('DROP TRIGGER ' . $trigger);
        $triggerCreated = false;
        $restore = $post('trash.php', '/admin/trash.php', ['action' => 'restore', 'module' => 'board', 'id' => (string)$id]);
        $check(httpIntegrationStatusCode($restore) === 302 && $read($id)['deleted_at'] === null, 'Confirmed Board restore failed');
        $post('board_delete.php', '/admin/board.php', ['id' => (string)$id, 'confirm_board_delete_' . $id => '1']);
        foreach ([$id, $cloneId] as $purgeId) {
            if ($purgeId === $cloneId) {
                $post('board_delete.php', '/admin/board.php', ['id' => (string)$cloneId, 'confirm_board_delete_' . $cloneId => '1']);
            }
            $purge = $post('trash.php', '/admin/trash.php', ['action' => 'purge', 'module' => 'board', 'id' => (string)$purgeId, 'confirm_permanent_delete' => '1']);
            clearstatcache();
            $check(httpIntegrationStatusCode($purge) === 302 && $read($purgeId) === [], 'Confirmed Board purge failed');
            $check(is_file(dirname(__DIR__) . '/uploads/board/images/' . $sharedImage) === ($purgeId === $id), 'Purge did not retain/clean the last shared image reference');
            $check(!is_file(dirname(__DIR__) . '/uploads/board/' . (string)$updated['filename']), 'Purge left an unreferenced attachment');
        }

        $pdo->prepare('INSERT INTO cms_subscribers (email,token,confirmed) VALUES (?,?,1)')->execute([$prefix . '@example.invalid', bin2hex(random_bytes(32))]);
        $subscriberId = (int)$pdo->lastInsertId();
        $invalidMail = $post('newsletter_send.php', '/admin/newsletter_form.php', ['subject' => '', 'body' => 'Zachované tělo rozesílky', 'confirm_newsletter_send' => '1']);
        $check(httpIntegrationStatusCode($invalidMail) === 302, 'Invalid mailing did not return its editor');
        $page = $get('/admin/newsletter_form.php');
        $xpath = $aria($page['body']);
        $check(str_contains($page['body'], 'Zachované tělo rozesílky')
            && $xpath->query('//input[@name="confirm_newsletter_send" and @checked]')->length === 0, 'Newsletter restored stale consent or lost text');
    } catch (Throwable $exception) {
        $issues[] = $exception->getMessage();
    } finally {
        if ($triggerCreated) {
            $pdo->exec('DROP TRIGGER IF EXISTS ' . $trigger);
        }
        foreach ($createdIds as $id) {
            $row = $read($id);
            if ($row === []) {
                continue;
            }
            $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'board' AND entity_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM cms_content_locks WHERE entity_type = 'board' AND entity_id = ?")->execute([$id]);
            $pdo->prepare('DELETE FROM cms_board_publication_events WHERE board_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM cms_board WHERE id = ? AND slug = ?')->execute([$id, $row['slug']]);
            deleteUnusedBoardFiles($pdo, $row);
        }
        if ($subscriberId > 0) {
            $pdo->prepare('DELETE FROM cms_subscribers WHERE id = ? AND email = ?')->execute([$subscriberId, $prefix . '@example.invalid']);
        }
        foreach ($storedFiles as $path) {
            // Exact names only; the cleanup helper also preserves a referenced WebP variant.
            deleteUnusedBoardFiles($pdo, [str_ends_with(dirname($path), '/images') ? 'image_file' : 'filename' => basename($path)]);
        }
        foreach ($tempFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        saveSetting('module_board', $oldModule);
        saveSetting('module_newsletter', $oldNewsletter);
    }
    return $issues;
}
