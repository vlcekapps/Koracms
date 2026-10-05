<?php

declare(strict_types=1);

/** @param array{cookie:string,csrf:string} $adminSession
 * @return list<string>
 */
function rcEditorIntegrityHttpChecks(PDO $pdo, string $baseUrl, array $adminSession): array
{
    $prefix = 'rc-editor-' . bin2hex(random_bytes(8));
    $trigger = 'rc_event_series_' . bin2hex(random_bytes(8));
    $image = $prefix . '.png';
    $imagePath = dirname(__DIR__) . '/uploads/events/images/' . $image;
    $oldDownloads = getSetting('module_downloads', '0');
    $oldEvents = getSetting('module_events', '0');
    $issues = [];
    $triggerCreated = false;
    $check = static function (bool $ok, string $label): void {
        if (!$ok) {
            throw new RuntimeException($label);
        }
    };
    $get = static fn (string $path): array => fetchUrl($baseUrl . BASE_URL . $path, $adminSession['cookie'], 0);
    $post = static function (string $endpoint, string $review, array $fields) use ($get, $baseUrl, $adminSession, $check): array {
        $page = $get($review);
        $token = extractHiddenInputValue($page['body'], 'csrf_token');
        $check(httpIntegrationStatusCode($page) === 200 && $token !== '', 'Cannot obtain editor review CSRF');
        return postUrl($baseUrl . BASE_URL . '/admin/' . $endpoint, ['csrf_token' => $token] + $fields, $adminSession['cookie'], 0);
    };
    $rows = static function (string $table) use ($pdo, $prefix): array {
        if (!in_array($table, ['cms_events', 'cms_downloads'], true)) {
            throw new RuntimeException('Unowned editor fixture table');
        }
        $stmt = $pdo->prepare('SELECT * FROM ' . $table . ' WHERE title LIKE ? ORDER BY id');
        $stmt->execute([$prefix . '%']);
        return $stmt->fetchAll();
    };
    $checkAria = static function (string $html) use ($check): void {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $ids = [];
        foreach ($xpath->query('//*[@id]') as $element) {
            $id = $element->getAttribute('id');
            $ids[$id] = ($ids[$id] ?? 0) + 1;
        }
        foreach ($xpath->query('//*[@aria-describedby or @aria-labelledby]') as $element) {
            foreach (['aria-describedby', 'aria-labelledby'] as $attribute) {
                foreach (preg_split('/\s+/', trim($element->getAttribute($attribute)), -1, PREG_SPLIT_NO_EMPTY) as $id) {
                    $check(($ids[$id] ?? 0) === 1, 'Missing or duplicate editor ARIA target: ' . $id);
                }
            }
        }
    };
    try {
        saveSetting('module_downloads', '1');
        saveSetting('module_events', '1');
        $pdo->prepare("INSERT INTO cms_downloads (title,slug,external_url) VALUES (?,?,'https://example.test/software')")
            ->execute([$prefix . '-download', $prefix . '-download']);
        $downloadId = (int)$pdo->lastInsertId();
        $beforeDownload = $rows('cms_downloads');
        $fields = ['id' => (string)$downloadId, 'title' => $prefix . '-download revised', 'slug' => $prefix . '-download',
            'description' => 'Zachovaný český popis', 'external_url' => 'javascript:invalid', 'article_status' => 'draft', 'is_featured' => '1'];
        $response = $post('download_save.php', '/admin/download_form.php?id=' . $downloadId, $fields);
        $check(httpIntegrationStatusCode($response) === 302 && $rows('cms_downloads') === $beforeDownload, 'Download validation changed stored data');
        $page = $get('/admin/download_form.php?id=' . $downloadId . '&err=url');
        $check(httpIntegrationStatusCode($page) === 200 && str_contains($page['body'], 'role="alert"')
            && httpIntegrationInputHasAttributes($page['body'], 'title', ['value' => $fields['title']])
            && httpIntegrationInputHasAttributes($page['body'], 'external_url', ['value' => $fields['external_url']])
            && str_contains($page['body'], $fields['description']), 'Download editor lost the rejected draft');
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($page['body']);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $check($xpath->query('//input[@name="is_published" and @checked]')->length === 0
            && $xpath->query('//input[@name="is_featured" and @checked]')->length === 1, 'Download recovery changed data checkbox state');
        $checkAria($page['body']);
        $denied = $post('download_delete.php', '/admin/downloads.php', ['id' => (string)$downloadId]);
        $check(httpIntegrationStatusCode($denied) === 302 && $rows('cms_downloads') === $beforeDownload, 'Download delete bypassed fresh confirmation');
        $errorPage = $get('/admin/downloads.php?delete_error=confirm_required&delete_error_id=' . $downloadId);
        $check(str_contains($errorPage['body'], 'role="alert"') && str_contains($errorPage['body'], 'aria-invalid="true"'), 'Download missing-confirmation error is inaccessible');
        $checkAria($errorPage['body']);
        $deleted = $post('download_delete.php', '/admin/downloads.php', ['id' => (string)$downloadId, 'confirm_download_delete_' . $downloadId => '1']);
        $check(httpIntegrationStatusCode($deleted) === 302 && $rows('cms_downloads')[0]['deleted_at'] !== null, 'Confirmed download deletion failed');

        // Fail the third real INSERT, after two terms have already been written.
        $pdo->exec("CREATE TRIGGER {$trigger} BEFORE INSERT ON cms_events FOR EACH ROW BEGIN
            IF NEW.slug = " . $pdo->quote($prefix . '-series-2027-03-31') . " THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Owned third event failure';
            END IF; END");
        $triggerCreated = true;
        $eventFields = ['title' => $prefix . '-series', 'slug' => $prefix . '-series', 'description' => 'Zachovaná opakovaná akce',
            'event_date' => '2027-01-31', 'event_time' => '09:00', 'event_end_date' => '2027-02-01', 'event_end_time' => '09:00',
            'recurrence_frequency' => 'monthly', 'recurrence_interval' => '1', 'recurrence_count' => '3', 'article_status' => 'draft'];
        $failed = $post('event_save.php', '/admin/event_form.php', $eventFields);
        $check(httpIntegrationStatusCode($failed) === 302 && $rows('cms_events') === [], 'Failed recurrence left a partial series');
        $page = $get('/admin/event_form.php?err=save');
        $check(str_contains($page['body'], 'role="alert"') && str_contains($page['body'], $eventFields['description'])
            && httpIntegrationInputHasAttributes($page['body'], 'title', ['value' => $eventFields['title']])
            && httpIntegrationInputHasAttributes($page['body'], 'event_date', ['value' => '2027-01-31']), 'Failed recurrence lost the accessible editor draft');
        $checkAria($page['body']);
        $pdo->exec('DROP TRIGGER ' . $trigger);
        $triggerCreated = false;
        $saved = $post('event_save.php', '/admin/event_form.php', $eventFields);
        $terms = $rows('cms_events');
        $check(httpIntegrationStatusCode($saved) === 302 && count($terms) === 3, 'Recurrence did not create all three terms');
        foreach ($terms as $term) {
            $check(
                strtotime((string)$term['event_end']) - strtotime((string)$term['event_date']) === 86400,
                'Monthly recurrence changed the one-day duration or inverted its end'
            );
        }

        koraEnsureDirectory(dirname($imagePath));
        $check(file_put_contents($imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a7x8AAAAASUVORK5CYII=', true)) !== false, 'Cannot create owned event image');
        $pdo->prepare("INSERT INTO cms_events (title,slug,event_date,image_file,status) VALUES (?,?,'2027-05-01 09:00:00',?,'draft')")
            ->execute([$prefix . '-image', $prefix . '-image', $image]);
        $eventId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO cms_events (title,slug,event_date,image_file,deleted_at,status) VALUES (?,?,'2027-05-02 09:00:00',?,NOW(),'draft')")
            ->execute([$prefix . '-other', $prefix . '-other', $image]);
        $otherId = (int)$pdo->lastInsertId();
        $beforeEvents = $rows('cms_events');
        $denied = $post('event_delete.php', '/admin/events.php', ['id' => (string)$eventId]);
        $check(httpIntegrationStatusCode($denied) === 302 && $rows('cms_events') === $beforeEvents && is_file($imagePath), 'Event deletion bypassed confirmation or removed its image');
        $deleted = $post('event_delete.php', '/admin/events.php', ['id' => (string)$eventId, 'confirm_event_delete_' . $eventId => '1']);
        $check(httpIntegrationStatusCode($deleted) === 302 && is_file($imagePath), 'Event trash must retain its image');
        foreach ([$eventId, $otherId] as $purgeId) {
            $purged = $post('trash.php', '/admin/trash.php', ['action' => 'purge', 'module' => 'events', 'id' => (string)$purgeId, 'confirm_permanent_delete' => '1']);
            $check(httpIntegrationStatusCode($purged) === 302
                && responseHasLocationHeader($purged['headers'], BASE_URL . '/admin/trash.php?ok=purged', $baseUrl), 'Confirmed event purge failed');
            // The HTTP process changed the filesystem; discard this process's cached stat.
            clearstatcache(true, $imagePath);
            $check(is_file($imagePath) === ($purgeId === $eventId), 'Purge must retain a shared/trash image and clean only its last reference');
        }
    } catch (Throwable $exception) {
        $issues[] = $exception->getMessage();
    } finally {
        if ($triggerCreated) {
            $pdo->exec('DROP TRIGGER IF EXISTS ' . $trigger);
        }
        foreach (['cms_events' => 'event', 'cms_downloads' => 'download'] as $table => $entity) {
            foreach ($rows($table) as $row) {
                if (!str_starts_with((string)$row['title'], $prefix . '-') || !str_starts_with((string)$row['slug'], $prefix . '-')) {
                    throw new RuntimeException('Refusing cleanup of an unowned editor fixture');
                }
                $id = (int)$row['id'];
                $pdo->prepare('DELETE FROM cms_content_locks WHERE entity_type = ? AND entity_id = ?')->execute([$entity, $id]);
                $pdo->prepare('DELETE FROM cms_revisions WHERE entity_type = ? AND entity_id = ?')->execute([$entity, $id]);
                $pdo->prepare('DELETE FROM ' . $table . ' WHERE id = ? AND slug = ?')->execute([$id, $row['slug']]);
            }
        }
        clearstatcache(true, $imagePath);
        if (is_file($imagePath)) {
            unlink($imagePath);
        }
        saveSetting('module_downloads', $oldDownloads);
        saveSetting('module_events', $oldEvents);
    }
    return $issues;
}
