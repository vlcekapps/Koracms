<?php

declare(strict_types=1);

/**
 * @param array{cookie:string,csrf:string} $adminSession
 * @return list<string>
 */
function rcFoodCardIntegrityHttpChecks(PDO $pdo, string $baseUrl, array $adminSession): array
{
    $prefix = 'rc-food-card-' . bin2hex(random_bytes(8));
    $trigger = 'rc_food_card_' . bin2hex(random_bytes(8));
    $oldModule = getSetting('module_food', '0');
    $ids = $issues = [];
    $triggerCreated = false;
    $originalCurrent = $pdo->query('SELECT id, updated_at FROM cms_food_cards WHERE is_current = 1')->fetchAll();
    $check = static function (bool $ok, string $label): void {
        if (!$ok) {
            throw new RuntimeException($label);
        }
    };
    $get = static fn (string $path): array => fetchUrl($baseUrl . BASE_URL . $path, $adminSession['cookie'], 0);
    $send = static function (string $endpoint, array $fields) use ($get, $baseUrl, $adminSession, $check): array {
        $review = $get('/admin/food_form.php');
        $token = extractHiddenInputValue($review['body'], 'csrf_token');
        $check(httpIntegrationStatusCode($review) === 200 && $token !== '', 'Cannot obtain Food editor CSRF');
        return postUrl($baseUrl . BASE_URL . '/admin/' . $endpoint, ['csrf_token' => $token] + $fields, $adminSession['cookie'], 0);
    };
    $read = static function (int $id) use ($pdo, &$ids, $check): array {
        $check(in_array($id, $ids, true), 'Unowned Food card read');
        $stmt = $pdo->prepare('SELECT * FROM cms_food_cards WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: [];
    };
    $snapshot = static function () use ($pdo, &$ids, $prefix): array {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $cards = $pdo->prepare('SELECT * FROM cms_food_cards WHERE id IN (' . $placeholders . ') ORDER BY id');
        $cards->execute($ids);
        $revisions = $pdo->prepare("SELECT * FROM cms_revisions WHERE entity_type = 'food' AND entity_id IN ({$placeholders}) ORDER BY id");
        $revisions->execute($ids);
        $redirects = $pdo->prepare('SELECT * FROM cms_redirects WHERE old_path LIKE ? ORDER BY id');
        $redirects->execute([BASE_URL . '/food/card/' . $prefix . '%']);
        // Read all flags to prove failure never deactivates a real current menu.
        return [$cards->fetchAll(), $revisions->fetchAll(), $redirects->fetchAll(),
            $pdo->query('SELECT id, is_current FROM cms_food_cards ORDER BY id')->fetchAll()];
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
                foreach (preg_split('/\s+/', trim($element->getAttribute($attribute)), -1, PREG_SPLIT_NO_EMPTY) as $target) {
                    $check($xpath->query('//*[@id="' . $target . '"]')->length === 1, 'Missing/duplicate Food ARIA target ' . $target);
                }
            }
        }
        return $xpath;
    };
    $expectError = static function (array $response, string $error, ?int $id) use ($baseUrl, $check): void {
        $path = BASE_URL . '/admin/food_form.php' . ($id !== null ? '?id=' . $id . '&' : '?') . 'err=' . $error;
        $check(httpIntegrationStatusCode($response) === 302 && responseHasLocationHeader($response['headers'], $path, $baseUrl), 'Food did not return expected error: ' . $error);
    };
    try {
        saveSetting('module_food', '1');
        $insert = $pdo->prepare("INSERT INTO cms_food_cards (type,title,slug,content,is_current,is_published,orders_enabled,order_requested_at_enabled,status)
            VALUES ('beverage',?,?,?, ?,1,1,1,'published')");
        foreach (['current', 'edited'] as $suffix) {
            $insert->execute([$prefix . '-' . $suffix, $prefix . '-' . $suffix, 'Původní český obsah', (int)($suffix === 'current')]);
            $ids[] = (int)$pdo->lastInsertId();
        }
        $id = $ids[1];
        $review = '/admin/food_form.php?id=' . $id;
        $fields = ['id' => (string)$id, 'type' => 'beverage', 'title' => $prefix . '-changed', 'slug' => $prefix . '-changed',
            'description' => 'Rozepsaná česká poznámka', 'content' => '<p>Zachovaný český obsah lístku</p>',
            'order_instructions' => 'Zachované pokyny', 'order_fulfillment_modes' => ['pickup', 'delivery', 'pickup', 'invalid']];
        $before = $snapshot();
        foreach ([['title' => ''], ['slug' => $prefix . '-current'], ['valid_from' => '2026-02-30'], ['valid_to' => '0'],
            ['valid_from' => '2026-10-20', 'valid_to' => '2026-10-19'], ['order_email' => 'invalid']] as $index => $invalid) {
            $error = ['required', 'slug', 'valid_from', 'valid_to', 'valid_range', 'order_email'][$index];
            $expectError($send('food_save.php', array_replace($fields, $invalid)), $error, $id);
            $check($snapshot() === $before, 'Food validation changed database');
            // Another editor must neither consume nor receive this draft.
            $other = $get('/admin/food_form.php?id=' . $ids[0]);
            $check(!str_contains($other['body'], $fields['description']), 'Food draft leaked into another editor');
            $page = $get($review . '&err=' . $error);
            $xpath = $aria($page['body']);
            $check(str_contains($page['body'], 'role="alert"') && str_contains($page['body'], $fields['description'])
                && str_contains($page['body'], 'Zachovaný český obsah lístku'), 'Food validation lost accessible draft');
            foreach (['is_current', 'is_published', 'orders_enabled', 'order_requested_at_enabled'] as $checkbox) {
                $check($xpath->query('//input[@name="' . $checkbox . '" and @checked]')->length === 0, 'Food restored an unchecked ordinary checkbox');
            }
            $check($xpath->query('//input[@name="order_fulfillment_modes[]" and @checked]')->length === 2, 'Food lost fulfillment choices');
        }

        foreach (['update', 'revision', 'redirect', 'insert'] as $failure) {
            $sql = match ($failure) {
                'update' => "BEFORE UPDATE ON cms_food_cards FOR EACH ROW BEGIN IF NEW.id = {$id} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Owned Food card update failure'; END IF; END",
                'revision' => "BEFORE INSERT ON cms_revisions FOR EACH ROW BEGIN IF NEW.entity_type = 'food' AND NEW.entity_id = {$id} THEN SIGNAL SQLSTATE '23000' SET MYSQL_ERRNO = 1644, MESSAGE_TEXT = 'Owned Food revision failure'; END IF; END",
                'redirect' => "BEFORE INSERT ON cms_redirects FOR EACH ROW BEGIN IF NEW.old_path = " . $pdo->quote(foodCardPublicPath($read($id))) . " THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Owned Food redirect failure'; END IF; END",
                'insert' => "BEFORE INSERT ON cms_food_cards FOR EACH ROW BEGIN IF NEW.title = " . $pdo->quote($prefix . '-new') . " THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Owned Food card insert failure'; END IF; END",
            };
            $pdo->exec('CREATE TRIGGER ' . $trigger . ' ' . $sql);
            $triggerCreated = true;
            $attempt = $fields + ['is_current' => '1'];
            $targetId = $id;
            if ($failure === 'insert') {
                unset($attempt['id']);
                $attempt['title'] = $attempt['slug'] = $prefix . '-new';
                $targetId = null;
            }
            $expectError($send('food_save.php', $attempt), 'save', $targetId);
            $check($snapshot() === $before, 'Food ' . $failure . ' failure changed menu/current flags/history');
            $pdo->exec('DROP TRIGGER ' . $trigger);
            $triggerCreated = false;
            $page = $get($targetId === null ? '/admin/food_form.php?err=save' : $review . '&err=save');
            $aria($page['body']);
            $check(str_contains($page['body'], $fields['description']) && str_contains($page['body'], 'role="alert"'), 'Food storage failure lost form');
        }
        // Successful paths do not change any real menu's current flag.
        $saved = $send('food_save.php', $fields);
        $check(httpIntegrationStatusCode($saved) === 302 && $read($id)['slug'] === $fields['slug'], 'Food successful edit failed');
        $redirect = $pdo->prepare('SELECT new_path FROM cms_redirects WHERE old_path = ?');
        $redirect->execute([foodCardPublicPath($before[0][1])]);
        $check($redirect->fetchColumn() === foodCardPublicPath($read($id)), 'Food canonical redirect was not committed');
        $revision = $pdo->prepare("SELECT COUNT(*) FROM cms_revisions WHERE entity_type = 'food' AND entity_id = ?");
        $revision->execute([$id]);
        $check((int)$revision->fetchColumn() > 0, 'Food edit has no revisions');
        $newFields = $fields;
        unset($newFields['id']);
        $newFields['slug'] = $newFields['title'] = $prefix . '-new';
        $newResponse = $send('food_save.php', $newFields);
        $created = $pdo->prepare('SELECT id FROM cms_food_cards WHERE slug = ?');
        $created->execute([$newFields['slug']]);
        $newId = (int)$created->fetchColumn();
        if ($newId > 0) {
            $ids[] = $newId;
        }
        $check(httpIntegrationStatusCode($newResponse) === 302 && $newId > 0, 'Food create did not insert row');
        $check((int)$read($newId)['is_current'] === 0, 'New Food card unexpectedly changed current offer');

        $before = $snapshot();
        foreach ([[], ['confirm_food_delete_' . $id => 'yes'], ['confirm_food_delete_' . $ids[0] => '1']] as $confirmation) {
            $denied = $send('food_delete.php', ['id' => (string)$id] + $confirmation);
            $check(httpIntegrationStatusCode($denied) === 302 && $snapshot() === $before, 'Food accepted absent/invalid deletion confirmation');
        }
        $list = $get('/admin/food.php?' . http_build_query(['q' => $prefix, 'delete_error' => 'confirm_required', 'delete_error_id' => $id]));
        $xpath = $aria($list['body']);
        $check($xpath->query('//input[@name="confirm_food_delete_' . $id . '" and @aria-invalid="true" and not(@checked)]')->length === 1
            && $xpath->query('//fieldset[legend]')->length > 0, 'Food delete confirmation has inaccessible error or restored consent');
        foreach (['food_save.php', 'food_delete.php'] as $endpoint) {
            $badCsrf = requestRawUrl('POST', $baseUrl . BASE_URL . '/admin/' . $endpoint, http_build_query(['id' => $id, 'csrf_token' => 'invalid']), 'application/x-www-form-urlencoded', $adminSession['cookie'], 0);
            $check(httpIntegrationStatusCode($badCsrf) === 403 && $snapshot() === $before, 'Food accepted invalid CSRF');
            $check(httpIntegrationStatusCode($get('/admin/' . $endpoint)) === 405, 'Food write endpoint accepted GET');
        }
        $deleted = $send('food_delete.php', ['id' => (string)$id, 'confirm_food_delete_' . $id => '1']);
        $check(httpIntegrationStatusCode($deleted) === 302 && $read($id)['deleted_at'] !== null, 'Food confirmed trash failed');
        $check(httpIntegrationStatusCode($get($review)) === 302, 'Food editor opened a trashed card');
        $before = $snapshot();
        $repeated = $send('food_delete.php', ['id' => (string)$id, 'confirm_food_delete_' . $id => '1']);
        $check(httpIntegrationStatusCode($repeated) === 302 && $snapshot() === $before
            && responseHasLocationHeader($repeated['headers'], BASE_URL . '/admin/food.php?delete_error=invalid&delete_error_id=' . $id, $baseUrl), 'Food repeated deletion claimed a new success');
        $expectError($send('food_save.php', $fields), 'missing', $id);
        $check($snapshot() === $before, 'Food stale save modified a trashed card');
        $recovered = $get($review . '&err=missing');
        $xpath = $aria($recovered['body']);
        $check(str_contains($recovered['body'], $fields['description'])
            && $xpath->query('//button[@type="submit" and @disabled and @aria-describedby="form-error"]')->length === 1, 'Food missing card lost rescue data or allowed save');
        saveSetting('module_food', '0');
        foreach (['food_save.php', 'food_delete.php'] as $endpoint) {
            $disabled = postUrl($baseUrl . BASE_URL . '/admin/' . $endpoint, ['csrf_token' => $adminSession['csrf'], 'id' => (string)$id], $adminSession['cookie'], 0);
            $check(httpIntegrationStatusCode($disabled) === 403 && $snapshot() === $before, 'Disabled Food allowed write');
        }
    } catch (Throwable $exception) {
        $issues[] = $exception->getMessage();
    } finally {
        if ($triggerCreated) {
            $pdo->exec('DROP TRIGGER ' . $trigger);
        }
        // A regressed handler could clear nonfixture flags before its failed write.
        // Do not overwrite a menu edited since this test began.
        if ($issues !== []) {
            $restore = $pdo->prepare('UPDATE cms_food_cards SET is_current = 1 WHERE id = ? AND is_current = 0 AND updated_at <=> ?');
            foreach ($originalCurrent as $original) {
                $restore->execute([$original['id'], $original['updated_at']]);
            }
        }
        foreach ($ids as $id) {
            $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'food' AND entity_id = ?")->execute([$id]);
            $pdo->prepare('DELETE FROM cms_food_cards WHERE id = ?')->execute([$id]);
        }
        $pdo->prepare('DELETE FROM cms_redirects WHERE old_path LIKE ?')->execute([BASE_URL . '/food/card/' . $prefix . '%']);
        saveSetting('module_food', $oldModule);
    }
    return $issues;
}
