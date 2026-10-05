<?php

declare(strict_types=1);

/**
 * Loading this file performs no I/O; the shared runner owns all DB/HTTP tests.
 * @param array{cookie:string,csrf:string} $adminSession
 * @return list<string>
 */
function rcChatIntegrityHttpChecks(PDO $pdo, string $baseUrl, array $adminSession): array
{
    $prefix = 'rc-chat-' . bin2hex(random_bytes(8));
    $trigger = 'rc_chat_history_' . bin2hex(random_bytes(8));
    $oldModule = getSetting('module_chat', '0');
    $oldNotify = getSetting('notify_chat_message', '0');
    $issues = [];
    $ownedIds = [];
    $triggerCreated = false;
    $check = static function (bool $condition, string $description): void {
        if (!$condition) {
            throw new RuntimeException($description);
        }
    };
    $checkAria = static function (string $html) use ($check): bool {
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
                    $check(($ids[$id] ?? 0) === 1, 'Missing or duplicate Chat ARIA target: ' . $id);
                }
            }
        }
        return true;
    };
    $count = static function (string $sql, array $parameters) use ($pdo): int {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($parameters);
        return (int)$stmt->fetchColumn();
    };
    $read = static function (int $id) use ($pdo): array {
        $data = [];
        foreach (['cms_chat' => 'id', 'cms_chat_replies' => 'chat_id', 'cms_chat_history' => 'chat_id'] as $table => $column) {
            $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE {$column} = ? ORDER BY id");
            $stmt->execute([$id]);
            $data[$table] = $stmt->fetchAll();
        }
        return $data;
    };
    $post = static function (string $path, array $fields) use ($baseUrl, $check): array {
        $session = koraPrimeTestSession([], 'kora-rc-chat-' . bin2hex(random_bytes(6)));
        $page = fetchUrl($baseUrl . $path, $session['cookie'], 0);
        $check(httpIntegrationStatusCode($page) === 200, 'Chat fixture could not open the public form');
        $token = extractHiddenInputValue($page['body'], 'csrf_token');
        $answer = httpIntegrationExtractCaptchaAnswer($page['body']);
        $check($token !== '' && $answer !== '', 'Chat form lacks CSRF or a usable captcha');
        return postUrl(
            $baseUrl . $path,
            ['csrf_token' => $token, 'captcha' => $answer, 'hp_website' => ''] + $fields,
            responseMergeCookies($page['headers'], $session['cookie']),
            0
        );
    };
    $create = static function (string $suffix, string $type = 'public') use ($pdo, $prefix, &$ownedIds): int {
        $pdo->prepare("INSERT INTO cms_chat (name,email,web,message,conversation_type,status,public_visibility,updated_at)
            VALUES (?,'','','Owned thread body',?,'handled','approved','2000-01-01 00:00:00')")
            ->execute([$prefix . '-' . $suffix, $type]);
        $id = (int)$pdo->lastInsertId();
        $ownedIds[] = $id;
        return $id;
    };
    $dropTrigger = static function () use ($pdo, $trigger, &$triggerCreated): void {
        if ($triggerCreated) {
            $pdo->exec('DROP TRIGGER IF EXISTS ' . $trigger);
            $triggerCreated = false;
        }
    };

    try {
        saveSetting('module_chat', '1');
        saveSetting('notify_chat_message', '0');
        httpIntegrationClearLocalRateLimits($pdo, ['chat', 'chat_reply']);
        $newName = $prefix . '-new';
        $newBody = $prefix . ' retained new message';
        $newEmail = $prefix . '@example.test';
        $pdo->exec("CREATE TRIGGER {$trigger} BEFORE INSERT ON cms_chat_history FOR EACH ROW
            BEGIN
              IF NEW.event_type = 'submitted' AND
                (SELECT name FROM cms_chat WHERE id = NEW.chat_id) = " . $pdo->quote($newName) . " THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Owned chat history failure';
              END IF;
            END");
        $triggerCreated = true;
        $fields = ['name' => $newName, 'email' => $newEmail, 'message' => $newBody, 'conversation_type' => 'public'];
        $failed = $post(BASE_URL . '/chat/index.php', $fields);
        $check(httpIntegrationStatusCode($failed) === 200
            && str_contains($failed['body'], 'Zprávu se nepodařilo uložit.')
            && httpIntegrationInputHasAttributes($failed['body'], 'name', ['value' => $newName])
            && httpIntegrationInputHasAttributes($failed['body'], 'email', ['value' => $newEmail])
            && str_contains($failed['body'], h($newBody))
            && str_contains($failed['body'], 'role="alert"')
            && $checkAria($failed['body']), 'Failed history did not preserve the accessible public message form');
        $check($count('SELECT COUNT(*) FROM cms_chat WHERE name = ?', [$newName]) === 0, 'Failed history left a parent message behind');
        $dropTrigger();
        $successful = $post(BASE_URL . '/chat/index.php', $fields);
        $stmt = $pdo->prepare('SELECT id,public_visibility FROM cms_chat WHERE name = ?');
        $stmt->execute([$newName]);
        $row = $stmt->fetch() ?: null;
        if (is_array($row)) {
            $ownedIds[] = (int)$row['id'];
        }
        $check(
            httpIntegrationStatusCode($successful) === 302 && is_array($row)
            && $row['public_visibility'] === 'pending'
            && $count("SELECT COUNT(*) FROM cms_chat_history WHERE chat_id = ? AND event_type = 'submitted'", [(int)$row['id']]) === 1,
            'Retry after rollback did not create exactly one pending message with history'
        );

        $parentId = $create('reply');
        $otherId = $create('other', 'support');
        $otherBefore = $read($otherId);
        $parentBefore = $read($parentId);
        $replyPath = chatMessagePath(['id' => $parentId]);
        $replyBody = $prefix . ' retained reply';
        $replyFields = ['name' => $prefix . '-respondent', 'email' => $newEmail, 'message' => $replyBody];
        $pdo->exec("CREATE TRIGGER {$trigger} BEFORE INSERT ON cms_chat_history FOR EACH ROW
            BEGIN
              IF NEW.chat_id = {$parentId} AND NEW.event_type = 'reply_submitted' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Owned reply history failure';
              END IF;
            END");
        $triggerCreated = true;
        $failedReply = $post($replyPath, $replyFields);
        $check(httpIntegrationStatusCode($failedReply) === 200 && $read($parentId) === $parentBefore
            && str_contains($failedReply['body'], 'Odpověď se nepodařilo uložit.')
            && httpIntegrationInputHasAttributes($failedReply['body'], 'reply-name', ['value' => $replyFields['name']])
            && str_contains($failedReply['body'], h($replyBody))
            && str_contains($failedReply['body'], 'role="alert"')
            && $checkAria($failedReply['body']), 'Reply history failure caused partial data, HTTP 500 or lost input');
        $dropTrigger();
        $reply = $post($replyPath, $replyFields);
        $afterReply = $read($parentId);
        $check(httpIntegrationStatusCode($reply) === 302
            && responseHasLocationHeader($reply['headers'], $replyPath . '?reply=pending', $baseUrl)
            && count($afterReply['cms_chat_replies']) === 1
            && $afterReply['cms_chat_replies'][0]['status'] === 'pending'
            && count($afterReply['cms_chat_history']) === 1
            && $afterReply['cms_chat'][0]['updated_at'] > '2000-01-01 00:00:00', 'Successful reply did not persist pending moderation/history/activity together');
        $check(!deleteChatMessage($pdo, $parentId, date('Y-m-d H:i:s', time() - 86400)), 'Retention deleted the recently active thread');
        $check($read($parentId) === $afterReply, 'Rejected retention changed the thread or replies');
        $pdo->prepare("UPDATE cms_chat SET public_visibility = 'hidden' WHERE id = ?")->execute([$parentId]);
        $hiddenBefore = $read($parentId);
        $session = koraPrimeTestSession([], 'kora-rc-chat-hidden-' . bin2hex(random_bytes(6)));
        $hiddenPost = postUrl($baseUrl . $replyPath, $replyFields + ['csrf_token' => $session['csrf'], 'captcha' => '1'], $session['cookie'], 0);
        $check(httpIntegrationStatusCode($hiddenPost) === 404 && $read($parentId) === $hiddenBefore, 'Hidden parent accepted a direct public reply');
        $private = fetchUrl($baseUrl . chatMessagePath(['id' => $otherId]), '', 0);
        $check(httpIntegrationStatusCode($private) === 404 && !str_contains($private['body'], 'Owned thread body'), 'Private parent leaked through the public detail');
        $deletePath = BASE_URL . '/admin/chat_message.php?id=' . $parentId;
        $detail = fetchUrl($baseUrl . $deletePath, $adminSession['cookie'], 0);
        $token = extractHiddenInputValue($detail['body'], 'csrf_token');
        $check(httpIntegrationStatusCode($detail) === 200 && $token !== '', 'Cannot obtain current admin deletion review');
        $deleted = postUrl($baseUrl . BASE_URL . '/admin/chat_delete.php', [
            'csrf_token' => $token, 'id' => (string)$parentId,
            'confirm_chat_delete_' . $parentId => '1', 'redirect' => $deletePath,
        ], $adminSession['cookie'], 0);
        $check(httpIntegrationStatusCode($deleted) === 302 && $read($parentId) === ['cms_chat' => [], 'cms_chat_replies' => [], 'cms_chat_history' => []]
            && $read($otherId) === $otherBefore, 'Confirmed deletion failed to clean its own complete thread or touched another message');
    } catch (Throwable $e) {
        $issues[] = $e->getMessage();
    } finally {
        $dropTrigger();
        $stmt = $pdo->prepare('SELECT id,name FROM cms_chat WHERE name LIKE ?');
        $stmt->execute([$prefix . '%']);
        foreach ($stmt->fetchAll() as $row) {
            $id = (int)$row['id'];
            if (!str_starts_with((string)$row['name'], $prefix . '-')) {
                throw new RuntimeException('Refusing cleanup of an unowned Chat fixture');
            }
            $ownedIds[] = $id;
        }
        foreach (array_unique($ownedIds) as $id) {
            $stmt = $pdo->prepare('SELECT name FROM cms_chat WHERE id = ?');
            $stmt->execute([$id]);
            $name = $stmt->fetchColumn();
            if ($name === false) {
                continue;
            }
            if (!str_starts_with((string)$name, $prefix . '-')) {
                throw new RuntimeException('Refusing cleanup of a changed Chat fixture owner');
            }
            deleteChatMessage($pdo, $id);
        }
        httpIntegrationClearLocalRateLimits($pdo, ['chat', 'chat_reply']);
        saveSetting('module_chat', $oldModule);
        saveSetting('notify_chat_message', $oldNotify);
    }
    return $issues;
}
