<?php
// Správa zpráv, inboxu a přehled ke schválení – extrahováno z db.php

function messageStatusDefinitions(): array
{
    return [
        'new' => ['label' => 'Nové'],
        'read' => ['label' => 'Přečtené'],
        'handled' => ['label' => 'Vyřízené'],
    ];
}

function normalizeMessageStatus(string $status): string
{
    $normalized = trim($status);
    return array_key_exists($normalized, messageStatusDefinitions()) ? $normalized : 'new';
}

function messageStatusLabel(string $status): string
{
    $normalized = normalizeMessageStatus($status);
    return messageStatusDefinitions()[$normalized]['label'] ?? 'Nové';
}

function messageStatusReadValue(string $status): int
{
    return normalizeMessageStatus($status) === 'new' ? 0 : 1;
}

function inboxStatusCounts(PDO $pdo, string $tableName, bool $hasLegacyReadColumn = false): array
{
    $counts = array_fill_keys(array_keys(messageStatusDefinitions()), 0);

    try {
        $rows = $pdo->query("SELECT status, COUNT(*) AS cnt FROM {$tableName} GROUP BY status")->fetchAll();
        foreach ($rows as $row) {
            $statusKey = normalizeMessageStatus((string)($row['status'] ?? 'new'));
            $counts[$statusKey] = (int)($row['cnt'] ?? 0);
        }
    } catch (\PDOException $e) {
        if ($hasLegacyReadColumn) {
            try {
                $counts['new'] = (int)$pdo->query("SELECT COUNT(*) FROM {$tableName} WHERE is_read = 0")->fetchColumn();
                $counts['read'] = (int)$pdo->query("SELECT COUNT(*) FROM {$tableName} WHERE is_read = 1")->fetchColumn();
            } catch (\PDOException $fallbackError) {
                return $counts;
            }
        }
    }

    return $counts;
}

function newInboxMessageCount(string $tableName, bool $hasLegacyReadColumn = false): int
{
    try {
        return (int)db_connect()->query(
            "SELECT COUNT(*) FROM {$tableName} WHERE status = 'new'"
        )->fetchColumn();
    } catch (\PDOException $e) {
        if ($hasLegacyReadColumn) {
            try {
                return (int)db_connect()->query(
                    "SELECT COUNT(*) FROM {$tableName} WHERE is_read = 0"
                )->fetchColumn();
            } catch (\PDOException $fallbackError) {
                return 0;
            }
        }

        return 0;
    }
}

function unreadContactCount(): int
{
    if (!isModuleEnabled('contact')) {
        return 0;
    }

    return newInboxMessageCount('cms_contact', true);
}

function unreadChatCount(): int
{
    if (!isModuleEnabled('chat')) {
        return 0;
    }

    return newInboxMessageCount('cms_chat');
}

function totalUnreadMessageCount(): int
{
    return unreadContactCount() + unreadChatCount();
}

function newsletterSubscriberStatusLabel(bool $confirmed): string
{
    return $confirmed ? 'Potvrzeno' : 'Čeká na potvrzení';
}

function newsletterSubscriberCounts(PDO $pdo): array
{
    $counts = [
        'confirmed' => 0,
        'pending' => 0,
    ];

    try {
        $rows = $pdo->query(
            "SELECT confirmed, COUNT(*) AS cnt
             FROM cms_subscribers
             GROUP BY confirmed"
        )->fetchAll();
        foreach ($rows as $row) {
            $bucket = ((int)($row['confirmed'] ?? 0) === 1) ? 'confirmed' : 'pending';
            $counts[$bucket] = (int)($row['cnt'] ?? 0);
        }
    } catch (\PDOException $e) {
        return $counts;
    }

    return $counts;
}

function setContactMessageStatus(PDO $pdo, int $messageId, string $status): bool
{
    $normalizedStatus = normalizeMessageStatus($status);

    try {
        $stmt = $pdo->prepare(
            "UPDATE cms_contact
             SET status = ?, is_read = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        $stmt->execute([$normalizedStatus, messageStatusReadValue($normalizedStatus), $messageId]);
        if ($stmt->rowCount() > 0) {
            return true;
        }
    } catch (\PDOException $e) {
        try {
            $stmt = $pdo->prepare("UPDATE cms_contact SET is_read = ? WHERE id = ?");
            $stmt->execute([messageStatusReadValue($normalizedStatus), $messageId]);
            if ($stmt->rowCount() > 0) {
                return true;
            }
        } catch (\PDOException $fallbackError) {
            return false;
        }
    }

    $exists = $pdo->prepare("SELECT COUNT(*) FROM cms_contact WHERE id = ?");
    $exists->execute([$messageId]);
    return (int)$exists->fetchColumn() > 0;
}

function setChatMessageStatus(PDO $pdo, int $messageId, string $status): bool
{
    $normalizedStatus = normalizeMessageStatus($status);

    try {
        $stmt = $pdo->prepare(
            "UPDATE cms_chat
             SET status = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        $stmt->execute([$normalizedStatus, $messageId]);
        if ($stmt->rowCount() > 0) {
            return true;
        }
    } catch (\PDOException $e) {
        return false;
    }

    $exists = $pdo->prepare("SELECT COUNT(*) FROM cms_chat WHERE id = ?");
    $exists->execute([$messageId]);
    return (int)$exists->fetchColumn() > 0;
}

function chatPublicVisibilityDefinitions(): array
{
    return [
        'pending' => ['label' => 'Ke schválení'],
        'approved' => ['label' => 'Zveřejněno'],
        'hidden' => ['label' => 'Skryto'],
    ];
}

function normalizeChatPublicVisibility(string $visibility): string
{
    $normalized = trim($visibility);
    return array_key_exists($normalized, chatPublicVisibilityDefinitions()) ? $normalized : 'pending';
}

function chatPublicVisibilityLabel(string $visibility): string
{
    $normalized = normalizeChatPublicVisibility($visibility);
    return chatPublicVisibilityDefinitions()[$normalized]['label'] ?? 'Ke schválení';
}

function chatPublicVisibilityCounts(PDO $pdo): array
{
    $counts = array_fill_keys(array_keys(chatPublicVisibilityDefinitions()), 0);

    try {
        $rows = $pdo->query(
            "SELECT public_visibility, COUNT(*) AS cnt
             FROM cms_chat
             GROUP BY public_visibility"
        )->fetchAll();
        foreach ($rows as $row) {
            $visibilityKey = normalizeChatPublicVisibility((string)($row['public_visibility'] ?? 'pending'));
            $counts[$visibilityKey] = (int)($row['cnt'] ?? 0);
        }
    } catch (\PDOException $e) {
        return $counts;
    }

    return $counts;
}

function setChatMessagePublicVisibility(PDO $pdo, int $messageId, string $visibility, ?int $actorUserId = null): bool
{
    $normalizedVisibility = normalizeChatPublicVisibility($visibility);
    $approvalTimestamp = $normalizedVisibility === 'approved' ? date('Y-m-d H:i:s') : null;
    $approvalActorId = $normalizedVisibility === 'approved' ? $actorUserId : null;

    try {
        $stmt = $pdo->prepare(
            "UPDATE cms_chat
             SET public_visibility = ?,
                 approved_at = ?,
                 approved_by_user_id = ?,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        $stmt->execute([$normalizedVisibility, $approvalTimestamp, $approvalActorId, $messageId]);
        if ($stmt->rowCount() > 0) {
            return true;
        }
    } catch (\PDOException $e) {
        return false;
    }

    $exists = $pdo->prepare("SELECT COUNT(*) FROM cms_chat WHERE id = ?");
    $exists->execute([$messageId]);
    return (int)$exists->fetchColumn() > 0;
}

function chatMessageContainsUrl(string $message): bool
{
    return preg_match('~(?:https?://|www\.)\S+~iu', $message) === 1;
}

function chatPublicMessagesPerPage(): int
{
    return 20;
}

function chatAdminMessagesPerPage(): int
{
    return 25;
}

function normalizeChatSort(string $sort): string
{
    return in_array($sort, ['newest', 'oldest'], true) ? $sort : 'newest';
}

function chatSortLabel(string $sort): string
{
    return normalizeChatSort($sort) === 'oldest' ? 'Nejstarší první' : 'Nejnovější první';
}

function chatRetentionDays(): int
{
    return max(0, min(3650, (int)getSetting('chat_retention_days', '0')));
}

function chatHistoryCreate(PDO $pdo, int $chatId, ?int $actorUserId, string $eventType, string $message): void
{
    $pdo->prepare(
        "INSERT INTO cms_chat_history (chat_id, actor_user_id, event_type, message)
         VALUES (?, ?, ?, ?)"
    )->execute([
        $chatId,
        $actorUserId,
        trim($eventType) !== '' ? trim($eventType) : 'workflow',
        trim($message),
    ]);
}

function chatHistoryEntries(PDO $pdo, int $chatId): array
{
    $stmt = $pdo->prepare(
        "SELECT h.*,
                u.email AS actor_email,
                u.first_name AS actor_first_name,
                u.last_name AS actor_last_name,
                u.nickname AS actor_nickname,
                u.role AS actor_role,
                u.is_superadmin AS actor_is_superadmin
         FROM cms_chat_history h
         LEFT JOIN cms_users u ON u.id = h.actor_user_id
         WHERE h.chat_id = ?
         ORDER BY h.created_at DESC, h.id DESC"
    );
    $stmt->execute([$chatId]);
    return $stmt->fetchAll();
}

function chatHistoryActorLabel(array $entry): string
{
    $firstName = trim((string)($entry['actor_first_name'] ?? ''));
    $lastName = trim((string)($entry['actor_last_name'] ?? ''));
    $nickname = trim((string)($entry['actor_nickname'] ?? ''));
    $email = trim((string)($entry['actor_email'] ?? ''));
    $fullName = trim($firstName . ' ' . $lastName);

    if ($fullName !== '') {
        return $fullName;
    }
    if ($nickname !== '') {
        return $nickname;
    }
    if ($email !== '') {
        return $email;
    }

    return 'Systém';
}

function deleteChatMessage(PDO $pdo, int $messageId): void
{
    $pdo->prepare("DELETE FROM cms_chat_history WHERE chat_id = ?")->execute([$messageId]);
    $pdo->prepare("DELETE FROM cms_chat WHERE id = ?")->execute([$messageId]);
}

function pendingReviewSummary(PDO $pdo): array
{
    $summary = [];

    $addSummaryItem = static function (string $key, string $label, string $category, string $url, string $sql) use ($pdo, &$summary): void {
        try {
            $count = (int)$pdo->query($sql)->fetchColumn();
        } catch (\PDOException $e) {
            $count = 0;
        }

        if ($count < 1) {
            return;
        }

        $summary[$key] = [
            'key' => $key,
            'label' => $label,
            'category' => $category,
            'count' => $count,
            'url' => $url,
        ];
    };

    if (isModuleEnabled('blog') && currentUserHasCapability('blog_approve')) {
        $addSummaryItem('articles', 'Články blogu', 'content', BASE_URL . '/admin/blog.php', "SELECT COUNT(*) FROM cms_articles WHERE status = 'pending'");
    }

    if (isModuleEnabled('news') && currentUserHasCapability('news_approve')) {
        $addSummaryItem('news', 'Novinky', 'content', BASE_URL . '/admin/news.php', "SELECT COUNT(*) FROM cms_news WHERE status = 'pending'");
    }

    if (currentUserHasCapability('content_approve_shared')) {
        $sharedModules = [
            ['key' => 'pages', 'enabled' => true, 'label' => 'Stránky', 'url' => BASE_URL . '/admin/pages.php', 'sql' => "SELECT COUNT(*) FROM cms_pages WHERE status = 'pending'"],
            ['key' => 'faq', 'enabled' => isModuleEnabled('faq'), 'label' => 'FAQ', 'url' => BASE_URL . '/admin/faq.php', 'sql' => "SELECT COUNT(*) FROM cms_faqs WHERE status = 'pending'"],
            ['key' => 'board', 'enabled' => isModuleEnabled('board'), 'label' => boardModulePublicLabel(), 'url' => BASE_URL . '/admin/board.php', 'sql' => "SELECT COUNT(*) FROM cms_board WHERE status = 'pending'"],
            ['key' => 'downloads', 'enabled' => isModuleEnabled('downloads'), 'label' => 'Ke stažení', 'url' => BASE_URL . '/admin/downloads.php', 'sql' => "SELECT COUNT(*) FROM cms_downloads WHERE status = 'pending'"],
            ['key' => 'events', 'enabled' => isModuleEnabled('events'), 'label' => 'Události', 'url' => BASE_URL . '/admin/events.php', 'sql' => "SELECT COUNT(*) FROM cms_events WHERE status = 'pending'"],
            ['key' => 'places', 'enabled' => isModuleEnabled('places'), 'label' => 'Zajímavá místa', 'url' => BASE_URL . '/admin/places.php', 'sql' => "SELECT COUNT(*) FROM cms_places WHERE status = 'pending'"],
            ['key' => 'podcasts', 'enabled' => isModuleEnabled('podcast'), 'label' => 'Podcasty', 'url' => BASE_URL . '/admin/podcast_shows.php', 'sql' => "SELECT COUNT(*) FROM cms_podcasts WHERE status = 'pending'"],
            ['key' => 'food', 'enabled' => isModuleEnabled('food'), 'label' => 'Jídelní lístky', 'url' => BASE_URL . '/admin/food.php', 'sql' => "SELECT COUNT(*) FROM cms_food_cards WHERE status = 'pending'"],
        ];

        foreach ($sharedModules as $moduleItem) {
            if (!$moduleItem['enabled']) {
                continue;
            }

            $addSummaryItem(
                $moduleItem['key'],
                $moduleItem['label'],
                'content',
                $moduleItem['url'],
                $moduleItem['sql']
            );
        }
    }

    if (isModuleEnabled('blog') && currentUserHasCapability('comments_manage')) {
        $addSummaryItem('comments', 'Komentáře', 'comments', BASE_URL . '/admin/comments.php?filter=pending', "SELECT COUNT(*) FROM cms_comments WHERE status = 'pending'");
    }

    if (isModuleEnabled('reservations') && currentUserHasCapability('bookings_manage')) {
        $addSummaryItem('reservations', 'Rezervace', 'reservations', BASE_URL . '/admin/res_bookings.php?status=pending', "SELECT COUNT(*) FROM cms_res_bookings WHERE status = 'pending'");
    }

    return array_values($summary);
}

function pendingReviewTotalCount(PDO $pdo): int
{
    return array_sum(array_column(pendingReviewSummary($pdo), 'count'));
}
