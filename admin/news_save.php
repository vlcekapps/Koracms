<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$pdo = db_connect();
$id = inputInt('post', 'id');
$title = trim((string)($_POST['title'] ?? ''));
$submittedSlug = trim((string)($_POST['slug'] ?? ''));
$content = trim((string)($_POST['content'] ?? ''));
$adminNote = trim((string)($_POST['admin_note'] ?? ''));
$metaTitle = mb_substr(trim((string)($_POST['meta_title'] ?? '')), 0, 160);
$metaDescription = trim((string)($_POST['meta_description'] ?? ''));

$redirectBase = BASE_URL . '/admin/news_form.php';
$redirectToForm = static function (string $errorCode) use ($redirectBase, $id): never {
    $query = $id !== null
        ? '?id=' . $id . '&err=' . rawurlencode($errorCode)
        : '?err=' . rawurlencode($errorCode);
    header('Location: ' . $redirectBase . $query);
    exit;
};

$publishAtInput = trim((string)($_POST['publish_at'] ?? ''));
$publishAtSql = null;
if ($publishAtInput !== '') {
    $publishAtSql = validateDateTimeLocal($publishAtInput);
    if ($publishAtSql === null) {
        $redirectToForm('publish_at');
    }
}

$unpublishAtInput = trim((string)($_POST['unpublish_at'] ?? ''));
$unpublishAtSql = null;
if ($unpublishAtInput !== '') {
    $unpublishAtSql = validateDateTimeLocal($unpublishAtInput);
    if ($unpublishAtSql === null) {
        $redirectToForm('unpublish_at');
    }
}

if ($title === '' || $content === '') {
    $redirectToForm('required');
}

$existingItem = null;
if ($id !== null) {
    if (canManageOwnNewsOnly()) {
        $existingStmt = $pdo->prepare(
            "SELECT id, author_id
             FROM cms_news
             WHERE id = ? AND author_id = ? AND deleted_at IS NULL"
        );
        $existingStmt->execute([$id, currentUserId()]);
    } else {
        $existingStmt = $pdo->prepare(
            "SELECT id, author_id
             FROM cms_news
             WHERE id = ? AND deleted_at IS NULL"
        );
        $existingStmt->execute([$id]);
    }
    $existingItem = $existingStmt->fetch() ?: null;
    if (!$existingItem) {
        header('Location: ' . BASE_URL . '/admin/news.php');
        exit;
    }
}

$slug = newsSlug($submittedSlug !== '' ? $submittedSlug : $title);
if ($slug === '') {
    $redirectToForm('slug');
}

$uniqueSlug = uniqueNewsSlug($pdo, $slug, $id);
if ($submittedSlug !== '' && $uniqueSlug !== $slug) {
    $redirectToForm('slug');
}
$slug = $uniqueSlug;

if ($existingItem) {
    $oldStmt = $pdo->prepare("SELECT * FROM cms_news WHERE id = ?");
    $oldStmt->execute([$id]);
    $oldData = $oldStmt->fetch() ?: null;

    if ($oldData && ($oldData['preview_token'] ?? '') === '') {
        $previewToken = bin2hex(random_bytes(16));
        $pdo->prepare("UPDATE cms_news SET preview_token = ? WHERE id = ?")->execute([$previewToken, $id]);
    }

    if ($oldData) {
        saveRevision(
            $pdo,
            'news',
            $id,
            newsRevisionSnapshot($oldData),
            newsRevisionSnapshot([
                'title' => $title,
                'slug' => $slug,
                'content' => $content,
                'unpublish_at' => $unpublishAtSql,
                'admin_note' => $adminNote,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
            ])
        );
    }

    $oldPath = $oldData ? newsPublicPath($oldData) : '';

    $requestedStatus = trim($_POST['article_status'] ?? '');
    if (!in_array($requestedStatus, ['draft', 'pending', 'published'], true)) {
        $requestedStatus = $oldData['status'] ?? 'published';
    }
    if ($requestedStatus === 'published' && !currentUserHasCapability('news_approve')) {
        $requestedStatus = (($oldData['status'] ?? '') === 'published') ? 'published' : 'pending';
    }
    $status = $requestedStatus;

    // Při první publikaci aktualizovat created_at na čas publikace
    $publishingNow = $status === 'published' && ($oldData['status'] ?? '') !== 'published';
    $createdAtClause = $publishingNow ? ', created_at = NOW()' : '';

    if (canManageOwnNewsOnly()) {
        $stmt = $pdo->prepare(
            "UPDATE cms_news
             SET title = ?, slug = ?, content = ?, publish_at = ?, unpublish_at = ?, status = ?, admin_note = ?, meta_title = ?, meta_description = ?,
                 author_id = COALESCE(author_id, ?), updated_at = NOW(){$createdAtClause}
             WHERE id = ? AND author_id = ?"
        );
        $stmt->execute([
            $title,
            $slug,
            $content,
            $publishAtSql,
            $unpublishAtSql,
            $status,
            $adminNote,
            $metaTitle,
            $metaDescription,
            currentUserId(),
            $id,
            currentUserId(),
        ]);
    } else {
        $stmt = $pdo->prepare(
            "UPDATE cms_news
             SET title = ?, slug = ?, content = ?, publish_at = ?, unpublish_at = ?, status = ?, admin_note = ?, meta_title = ?, meta_description = ?,
                 author_id = COALESCE(author_id, ?), updated_at = NOW(){$createdAtClause}
             WHERE id = ?"
        );
        $stmt->execute([
            $title,
            $slug,
            $content,
            $publishAtSql,
            $unpublishAtSql,
            $status,
            $adminNote,
            $metaTitle,
            $metaDescription,
            currentUserId(),
            $id,
        ]);
    }

    upsertPathRedirect($pdo, $oldPath, newsPublicPath(['id' => $id, 'slug' => $slug]));
    logAction('news_edit', "id={$id} title={$title} slug={$slug}");
} else {
    $requestedStatus = trim($_POST['article_status'] ?? '');
    if (!in_array($requestedStatus, ['draft', 'pending', 'published'], true)) {
        $requestedStatus = 'draft';
    }
    if ($requestedStatus === 'published' && !currentUserHasCapability('news_approve')) {
        $requestedStatus = 'pending';
    }
    $status = $requestedStatus;
    $authorId = currentUserId();
    $previewToken = bin2hex(random_bytes(16));
    $pdo->prepare(
        "INSERT INTO cms_news (
            title, slug, content, publish_at, unpublish_at, admin_note, meta_title, meta_description, preview_token, status, author_id
         ) VALUES (?,?,?,?,?,?,?,?,?,?,?)"
    )->execute([
        $title,
        $slug,
        $content,
        $publishAtSql,
        $unpublishAtSql,
        $adminNote,
        $metaTitle,
        $metaDescription,
        $previewToken,
        $status,
        $authorId,
    ]);
    $id = (int)$pdo->lastInsertId();
    logAction('news_add', "id={$id} title={$title} slug={$slug} status={$status}");
    if ($status === 'pending') {
        notifyPendingContent('Novinka', $title, '/admin/news.php');
    }
}

// Uvolnění zámku obsahu po úspěšném uložení
if ($id !== null) {
    releaseContentLock('news', $id);
}

header('Location: ' . BASE_URL . '/admin/news.php');
exit;
