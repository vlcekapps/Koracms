<?php

require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
requireModuleEnabled('blog');
requireHttpMethods(['POST']);
verifyCsrf();

$articleId = inputInt('post', 'id');
if ($articleId === null) {
    header('Location: ' . BASE_URL . '/admin/blog.php');
    exit;
}

/**
 * @param array<string,mixed> $params
 */
$redirectToArticle = static function (array $params = []) use ($articleId): void {
    $target = appendUrlQuery(
        BASE_URL . '/admin/blog_form.php',
        array_merge(['id' => $articleId], $params)
    );
    header('Location: ' . $target . '#article-preview-sharing');
    exit;
};

$action = normalizeArticlePreviewAction((string)($_POST['action'] ?? ''));
if ($action === '') {
    $redirectToArticle(['preview_error' => 'invalid']);
}

$confirmField = 'confirm_article_preview_' . $action;
if (($_POST[$confirmField] ?? '') !== '1') {
    $redirectToArticle(['preview_error' => 'confirm_' . $action]);
}

$pdo = db_connect();
try {
    $pdo->beginTransaction();

    $params = [$articleId];
    $ownershipSql = '';
    if (canManageOwnBlogOnly()) {
        $ownershipSql = ' AND author_id = ?';
        $params[] = currentUserId();
    }

    $articleStmt = $pdo->prepare(
        "SELECT id, title, blog_id, preview_token
         FROM cms_articles
         WHERE id = ? AND deleted_at IS NULL{$ownershipSql}
         FOR UPDATE"
    );
    $articleStmt->execute($params);
    $article = $articleStmt->fetch() ?: null;
    if (!$article || (canManageOwnBlogOnly() && !canCurrentUserWriteToBlog((int)$article['blog_id']))) {
        $pdo->rollBack();
        $redirectToArticle(['preview_error' => 'invalid']);
    }

    $oldToken = trim((string)($article['preview_token'] ?? ''));
    $oldTokenIsActive = isValidArticlePreviewToken($oldToken);
    if (($action === 'enable' && $oldTokenIsActive)
        || (($action === 'rotate' || $action === 'revoke') && !$oldTokenIsActive)) {
        $pdo->rollBack();
        $redirectToArticle(['preview_error' => 'stale']);
    }

    $newToken = $action === 'revoke' ? '' : generateArticlePreviewToken();
    $updateStmt = $pdo->prepare('UPDATE cms_articles SET preview_token = ? WHERE id = ?');
    $updateStmt->execute([$newToken, $articleId]);
    if ($updateStmt->rowCount() !== 1) {
        throw new RuntimeException('Náhledový odkaz článku se nepodařilo změnit.');
    }

    $auditAction = match ($action) {
        'enable' => 'article_preview_enable',
        'rotate' => 'article_preview_rotate',
        'revoke' => 'article_preview_revoke',
    };
    logAction($auditAction, 'id=' . $articleId . ' title=' . (string)$article['title']);
    $pdo->commit();

    $result = match ($action) {
        'enable' => 'enabled',
        'rotate' => 'rotated',
        'revoke' => 'revoked',
    };
    $redirectToArticle(['preview_result' => $result]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    koraLog('warning', 'admin article preview token change failed', [
        'operation' => 'article_preview_token_change',
        'article_id' => $articleId,
        'action' => $action,
        'exception' => $e,
    ]);
    $redirectToArticle(['preview_error' => 'failed']);
}
