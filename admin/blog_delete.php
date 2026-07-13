<?php

require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
requireModuleEnabled('blog');
requireHttpMethods(['POST']);
verifyCsrf();

$id = inputInt('post', 'id');
$redirectTarget = internalRedirectTarget(
    trim((string)($_POST['redirect'] ?? '')),
    BASE_URL . '/admin/blog.php'
);

/**
 * @param array<string,mixed> $params
 */
$redirectWithDeleteState = static function (array $params) use ($redirectTarget): void {
    header('Location: ' . appendUrlQuery($redirectTarget, $params));
    exit;
};

if ($id === null) {
    $redirectWithDeleteState(['delete_error' => 'invalid']);
}

$confirmFieldName = 'confirm_article_delete_' . $id;
$confirmedArticleDelete = isset($_POST[$confirmFieldName])
    && (string)$_POST[$confirmFieldName] === '1';
if (!$confirmedArticleDelete) {
    $redirectWithDeleteState(['delete_error' => 'confirm_required', 'delete_error_id' => $id]);
}

$pdo = db_connect();
try {
    $pdo->beginTransaction();
    $params = [$id];
    $ownershipSql = '';
    if (canManageOwnBlogOnly()) {
        $ownershipSql = ' AND author_id = ?';
        $params[] = currentUserId();
    }
    $articleStmt = $pdo->prepare(
        "SELECT id, blog_id
         FROM cms_articles
         WHERE id = ? AND deleted_at IS NULL{$ownershipSql}
         FOR UPDATE"
    );
    $articleStmt->execute($params);
    $article = $articleStmt->fetch() ?: null;
    if (!$article || (canManageOwnBlogOnly() && !canCurrentUserWriteToBlog((int)$article['blog_id']))) {
        $pdo->rollBack();
        $redirectWithDeleteState(['delete_error' => 'invalid', 'delete_error_id' => $id]);
    }

    $updateParams = [$id];
    $updateOwnershipSql = '';
    if (canManageOwnBlogOnly()) {
        $updateOwnershipSql = ' AND author_id = ?';
        $updateParams[] = currentUserId();
    }

    $updateStmt = $pdo->prepare(
        "UPDATE cms_articles SET deleted_at = NOW()
         WHERE id = ? AND deleted_at IS NULL{$updateOwnershipSql}"
    );
    $updateStmt->execute($updateParams);
    if ($updateStmt->rowCount() !== 1) {
        throw new RuntimeException('Článek se nepodařilo přesunout do Koše.');
    }

    logAction('article_delete', "id={$id} soft=true");
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    koraLog('warning', 'admin article soft delete failed', [
        'operation' => 'article_soft_delete',
        'article_id' => $id,
        'exception' => $e,
    ]);
    $redirectWithDeleteState(['delete_error' => 'failed', 'delete_error_id' => $id]);
}

$redirectWithDeleteState(['deleted' => '1']);
