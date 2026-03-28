<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$pdo = db_connect();
$id = inputInt('post', 'id');
$title = trim($_POST['title'] ?? '');
$submittedSlug = trim($_POST['slug'] ?? '');
$content = trim($_POST['content'] ?? '');

if ($title === '' || $content === '') {
    header('Location: news_form.php?err=required' . ($id ? '&id=' . $id : ''));
    exit;
}

$existingItem = null;
if ($id !== null) {
    if (canManageOwnNewsOnly()) {
        $existingStmt = $pdo->prepare("SELECT id, author_id FROM cms_news WHERE id = ? AND author_id = ?");
        $existingStmt->execute([$id, currentUserId()]);
    } else {
        $existingStmt = $pdo->prepare("SELECT id, author_id FROM cms_news WHERE id = ?");
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
    header('Location: news_form.php?err=slug' . ($id ? '&id=' . $id : ''));
    exit;
}

$uniqueSlug = uniqueNewsSlug($pdo, $slug, $id);
if ($submittedSlug !== '' && $uniqueSlug !== $slug) {
    header('Location: news_form.php?err=slug' . ($id ? '&id=' . $id : ''));
    exit;
}
$slug = $uniqueSlug;

if ($existingItem) {
    $oldStmt = $pdo->prepare("SELECT title, slug, content FROM cms_news WHERE id = ?");
    $oldStmt->execute([$id]);
    $oldData = $oldStmt->fetch();
    if ($oldData) {
        saveRevision($pdo, 'news', $id, $oldData, [
            'title' => $title, 'slug' => $slug, 'content' => $content,
        ]);
    }

    if (canManageOwnNewsOnly()) {
        $stmt = $pdo->prepare(
            "UPDATE cms_news
             SET title = ?, slug = ?, content = ?, author_id = COALESCE(author_id, ?), updated_at = NOW()
             WHERE id = ? AND author_id = ?"
        );
        $stmt->execute([$title, $slug, $content, currentUserId(), $id, currentUserId()]);
    } else {
        $stmt = $pdo->prepare(
            "UPDATE cms_news
             SET title = ?, slug = ?, content = ?, author_id = COALESCE(author_id, ?), updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([$title, $slug, $content, currentUserId(), $id]);
    }
    logAction('news_edit', "id={$id} title={$title} slug={$slug}");
} else {
    $status = currentUserHasCapability('news_approve') ? 'published' : 'pending';
    $authorId = currentUserId();
    $pdo->prepare(
        "INSERT INTO cms_news (title, slug, content, status, author_id) VALUES (?,?,?,?,?)"
    )->execute([$title, $slug, $content, $status, $authorId]);
    $id = (int)$pdo->lastInsertId();
    logAction('news_add', "id={$id} title={$title} slug={$slug} status={$status}");
}

header('Location: ' . BASE_URL . '/admin/news.php');
exit;
