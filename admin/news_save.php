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

$unpublishAtInput = trim((string)($_POST['unpublish_at'] ?? ''));
$unpublishAtSql = null;
if ($unpublishAtInput !== '') {
    $dateTime = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $unpublishAtInput);
    if (!$dateTime || $dateTime->format('Y-m-d\TH:i') !== $unpublishAtInput) {
        $redirectToForm('unpublish_at');
    }
    $unpublishAtSql = $dateTime->format('Y-m-d H:i:s');
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

    if (canManageOwnNewsOnly()) {
        $stmt = $pdo->prepare(
            "UPDATE cms_news
             SET title = ?, slug = ?, content = ?, unpublish_at = ?, admin_note = ?, meta_title = ?, meta_description = ?,
                 author_id = COALESCE(author_id, ?), updated_at = NOW()
             WHERE id = ? AND author_id = ?"
        );
        $stmt->execute([
            $title,
            $slug,
            $content,
            $unpublishAtSql,
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
             SET title = ?, slug = ?, content = ?, unpublish_at = ?, admin_note = ?, meta_title = ?, meta_description = ?,
                 author_id = COALESCE(author_id, ?), updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([
            $title,
            $slug,
            $content,
            $unpublishAtSql,
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
    $status = currentUserHasCapability('news_approve') ? 'published' : 'pending';
    $authorId = currentUserId();
    $pdo->prepare(
        "INSERT INTO cms_news (
            title, slug, content, unpublish_at, admin_note, meta_title, meta_description, status, author_id
         ) VALUES (?,?,?,?,?,?,?,?,?)"
    )->execute([
        $title,
        $slug,
        $content,
        $unpublishAtSql,
        $adminNote,
        $metaTitle,
        $metaDescription,
        $status,
        $authorId,
    ]);
    $id = (int)$pdo->lastInsertId();
    logAction('news_add', "id={$id} title={$title} slug={$slug} status={$status}");
    if ($status === 'pending') {
        notifyPendingContent('Novinka', $title, '/admin/news.php');
    }
}

header('Location: ' . BASE_URL . '/admin/news.php');
exit;
