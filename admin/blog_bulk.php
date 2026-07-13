<?php

require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
requireModuleEnabled('blog');
verifyCsrf();

$ids = [];
$hasInvalidId = false;
foreach ((array)($_POST['ids'] ?? []) as $rawId) {
    $rawId = trim((string)$rawId);
    if ($rawId === '' || !ctype_digit($rawId) || (int)$rawId <= 0) {
        $hasInvalidId = true;
        continue;
    }
    $ids[] = (int)$rawId;
}
$ids = array_values(array_unique($ids));
$action = trim((string)($_POST['action'] ?? ''));
$redirect = internalRedirectTarget(trim($_POST['redirect'] ?? ''), BASE_URL . '/admin/blog.php');

/**
 * @param array<string,mixed> $context
 */
$setFlash = static function (string $type, string $message, array $context = []): void {
    $_SESSION['blog_transfer_flash'] = array_merge([
        'type' => $type,
        'message' => $message,
    ], $context);
};

/**
 * @param list<int> $ids
 * @return list<array{id:int,blog_id:int}>
 */
function adminBlogBulkDeletableArticles(PDO $pdo, array $ids): array
{
    if ($ids === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params = $ids;
    $ownershipSql = '';
    if (canManageOwnBlogOnly()) {
        $ownershipSql = ' AND author_id = ?';
        $params[] = currentUserId();
    }
    $stmt = $pdo->prepare(
        "SELECT id, blog_id FROM cms_articles
         WHERE id IN ({$placeholders}) AND deleted_at IS NULL{$ownershipSql}
         FOR UPDATE"
    );
    $stmt->execute($params);
    $articles = $stmt->fetchAll();

    if (canManageOwnBlogOnly()) {
        $articles = array_values(array_filter(
            $articles,
            static fn (array $article): bool => canCurrentUserWriteToBlog((int)$article['blog_id'])
        ));
    }

    return array_map(
        static fn (array $article): array => [
            'id' => (int)$article['id'],
            'blog_id' => (int)$article['blog_id'],
        ],
        $articles
    );
}

if ($action === 'delete') {
    $deleteFlashContext = ['selected_ids' => $ids];
    if ($ids === [] || $hasInvalidId) {
        $setFlash(
            'error',
            'Vyberte znovu články, které chcete přesunout do Koše.',
            array_merge($deleteFlashContext, ['code' => 'article_bulk_delete_selection_required'])
        );
        header('Location: ' . $redirect);
        exit;
    }
    if (($_POST['confirm_article_bulk_delete'] ?? '') !== '1') {
        $setFlash(
            'error',
            'Hromadný přesun článků do Koše nejde provést bez potvrzení kontroly dopadu.',
            array_merge($deleteFlashContext, ['code' => 'article_bulk_delete_confirm_required'])
        );
        header('Location: ' . $redirect);
        exit;
    }

    $pdo = db_connect();
    try {
        $pdo->beginTransaction();
        $articles = adminBlogBulkDeletableArticles($pdo, $ids);
        $allowedIds = array_map(static fn (array $article): int => $article['id'], $articles);
        sort($allowedIds);
        $requestedIds = $ids;
        sort($requestedIds);
        if ($allowedIds !== $requestedIds) {
            $pdo->rollBack();
            $setFlash(
                'error',
                'Vybraný seznam článků se změnil nebo obsahuje položky, které nemůžete přesunout do Koše. Zkontrolujte výběr a potvrďte akci znovu.',
                array_merge($deleteFlashContext, ['code' => 'article_bulk_delete_selection_invalid'])
            );
            header('Location: ' . $redirect);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $updateParams = $ids;
        $ownershipSql = '';
        if (canManageOwnBlogOnly()) {
            $ownershipSql = ' AND author_id = ?';
            $updateParams[] = currentUserId();
        }
        $updateStmt = $pdo->prepare(
            "UPDATE cms_articles SET deleted_at = NOW()
             WHERE id IN ({$placeholders}) AND deleted_at IS NULL{$ownershipSql}"
        );
        $updateStmt->execute($updateParams);
        if ($updateStmt->rowCount() !== count($ids)) {
            throw new RuntimeException('Počet přesunutých článků neodpovídá potvrzenému výběru.');
        }

        logAction('article_bulk_delete', 'ids=' . implode(',', $ids) . ' soft=true');
        $pdo->commit();
        $message = count($ids) === 1
            ? 'Vybraný článek byl přesunut do Koše. Lze jej obnovit ve správě Koše.'
            : 'Vybrané články (' . count($ids) . ') byly přesunuty do Koše. Lze je obnovit ve správě Koše.';
        $setFlash('success', $message, ['code' => 'article_bulk_delete_success']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        koraLog('warning', 'admin blog bulk soft delete failed', [
            'operation' => 'article_bulk_soft_delete',
            'article_count' => count($ids),
            'exception' => $e,
        ]);
        $setFlash(
            'error',
            'Články se nepodařilo přesunout do Koše. Data zůstala beze změny; zkontrolujte výběr a zkuste akci znovu.',
            array_merge($deleteFlashContext, ['code' => 'article_bulk_delete_failed'])
        );
    }

    header('Location: ' . $redirect);
    exit;
} elseif ($action === 'move' && $ids !== []) {
    $pdo = db_connect();
    $writableBlogs = getWritableBlogsForUser();
    if (count($writableBlogs) < 2) {
        unset($_SESSION['blog_transfer_selection']);
        $setFlash('error', 'Přesun článků se zobrazí až ve chvíli, kdy máte přístup alespoň do dvou blogů.');
        header('Location: ' . $redirect);
        exit;
    }

    $articles = loadTransferableBlogArticles($pdo, $ids);
    if (count($articles) !== count($ids)) {
        unset($_SESSION['blog_transfer_selection']);
        $setFlash('error', 'Vybraný seznam článků se změnil nebo obsahuje položky, které nemůžete přesouvat.');
        header('Location: ' . $redirect);
        exit;
    }

    $_SESSION['blog_transfer_selection'] = [
        'ids' => array_map('intval', $ids),
        'redirect' => $redirect,
        'created_at' => time(),
    ];

    header('Location: ' . BASE_URL . '/admin/blog_transfer.php');
    exit;
} elseif (in_array($action, ['set_draft', 'set_pending', 'set_published'], true) && $ids !== []) {
    $pdo = db_connect();
    $statusMap = ['set_draft' => 'draft', 'set_pending' => 'pending', 'set_published' => 'published'];
    $newStatus = $statusMap[$action];

    if ($newStatus === 'published' && !currentUserHasCapability('blog_approve')) {
        $newStatus = 'pending';
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    if (canManageOwnBlogOnly()) {
        $pdo->prepare("UPDATE cms_articles SET status = ? WHERE id IN ({$placeholders}) AND author_id = ?")
            ->execute(array_merge([$newStatus], $ids, [currentUserId()]));
    } else {
        $pdo->prepare("UPDATE cms_articles SET status = ? WHERE id IN ({$placeholders})")
            ->execute(array_merge([$newStatus], $ids));
    }
    logAction('article_bulk_status', "status={$newStatus} ids=" . implode(',', $ids));
}

header('Location: ' . $redirect);
exit;
