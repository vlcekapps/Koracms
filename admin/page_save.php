<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu statických stránek nemáte potřebné oprávnění.');
verifyCsrf();

$id = inputInt('post', 'id');
$title = trim($_POST['title'] ?? '');
$rawSlug = trim($_POST['slug'] ?? '');
$slug = pageSlug($rawSlug !== '' ? $rawSlug : $title);
$content = (string)($_POST['content'] ?? '');
$isPublished = isset($_POST['is_published']) ? 1 : 0;
$showInNav = isset($_POST['show_in_nav']) ? 1 : 0;
$adminNote = trim($_POST['admin_note'] ?? '');

$unpublishAt = trim($_POST['unpublish_at'] ?? '');
$unpublishAtSql = null;
if ($unpublishAt !== '') {
    $dateTime = DateTime::createFromFormat('Y-m-d\TH:i', $unpublishAt);
    if ($dateTime) {
        $unpublishAtSql = $dateTime->format('Y-m-d H:i:s');
    }
}
$redirect = internalRedirectTarget(trim($_POST['redirect'] ?? ''), BASE_URL . '/admin/pages.php');

if ($title === '' || $slug === '') {
    $fallback = BASE_URL . '/admin/page_form.php' . ($id ? '?id=' . $id : '');
    header('Location: ' . appendUrlQuery($fallback, ['err' => 'required', 'redirect' => $redirect]));
    exit;
}

$pdo = db_connect();
$uniqueSlug = uniquePageSlug($pdo, $slug, $id);
if ($uniqueSlug !== $slug) {
    $fallback = BASE_URL . '/admin/page_form.php' . ($id ? '?id=' . $id : '');
    header('Location: ' . appendUrlQuery($fallback, ['err' => 'slug', 'redirect' => $redirect]));
    exit;
}

if ($id !== null) {
    $oldStmt = $pdo->prepare("SELECT title, slug, content FROM cms_pages WHERE id = ?");
    $oldStmt->execute([$id]);
    $oldData = $oldStmt->fetch();
    if ($oldData) {
        saveRevision($pdo, 'page', $id, $oldData, [
            'title' => $title, 'slug' => $slug, 'content' => $content,
        ]);
    }

    $pdo->prepare(
        "UPDATE cms_pages
         SET title = ?, slug = ?, content = ?, is_published = ?, show_in_nav = ?, unpublish_at = ?, admin_note = ?
         WHERE id = ?"
    )->execute([$title, $slug, $content, $isPublished, $showInNav, $unpublishAtSql, $adminNote, $id]);
    logAction('page_edit', "id={$id}, title=" . mb_substr($title, 0, 80));
} else {
    $status = currentUserHasCapability('content_approve_shared') ? 'published' : 'pending';
    $isPublished = currentUserHasCapability('content_approve_shared') ? $isPublished : 0;
    $navOrder = nextPageNavigationOrder($pdo);
    $pdo->prepare(
        "INSERT INTO cms_pages (title, slug, content, is_published, show_in_nav, nav_order, unpublish_at, admin_note, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    )->execute([$title, $slug, $content, $isPublished, $showInNav, $navOrder, $unpublishAtSql, $adminNote, $status]);
    $newId = (int)$pdo->lastInsertId();
    logAction('page_add', "id={$newId}, title=" . mb_substr($title, 0, 80));
    if ($status === 'pending') {
        notifyPendingContent('Stránka', $title, '/admin/pages.php');
    }
}

header('Location: ' . $redirect);
exit;
