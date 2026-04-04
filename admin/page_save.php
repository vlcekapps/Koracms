<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu statických stránek nemáte potřebné oprávnění.');
verifyCsrf();

$id = inputInt('post', 'id');
$title = trim((string)($_POST['title'] ?? ''));
$rawSlug = trim((string)($_POST['slug'] ?? ''));
$slug = pageSlug($rawSlug !== '' ? $rawSlug : $title);
$content = (string)($_POST['content'] ?? '');
$isPublished = isset($_POST['is_published']) ? 1 : 0;
$showInNav = isset($_POST['show_in_nav']) ? 1 : 0;
$adminNote = trim((string)($_POST['admin_note'] ?? ''));
$selectedBlogId = inputInt('post', 'blog_id');

$publishAt = trim((string)($_POST['publish_at'] ?? ''));
$publishAtSql = null;
$unpublishAt = trim((string)($_POST['unpublish_at'] ?? ''));
$unpublishAtSql = null;
$redirect = internalRedirectTarget(trim((string)($_POST['redirect'] ?? '')), BASE_URL . '/admin/pages.php');
$fallbackParams = ['redirect' => $redirect];
if ($id !== null) {
    $fallbackParams['id'] = $id;
} elseif ($selectedBlogId !== null) {
    $fallbackParams['blog_id'] = $selectedBlogId;
}
$fallback = BASE_URL . '/admin/page_form.php?' . http_build_query($fallbackParams);

if ($publishAt !== '') {
    $dateTime = DateTime::createFromFormat('Y-m-d\TH:i', $publishAt);
    $errors = DateTime::getLastErrors();
    $hasDateTimeErrors = is_array($errors)
        && (((int)($errors['warning_count'] ?? 0)) > 0 || ((int)($errors['error_count'] ?? 0)) > 0);
    if ($dateTime === false || $hasDateTimeErrors || $dateTime->format('Y-m-d\TH:i') !== $publishAt) {
        header('Location: ' . appendUrlQuery($fallback, ['err' => 'publish_at']));
        exit;
    }
    $publishAtSql = $dateTime->format('Y-m-d H:i:s');
}

if ($unpublishAt !== '') {
    $dateTime = DateTime::createFromFormat('Y-m-d\TH:i', $unpublishAt);
    $errors = DateTime::getLastErrors();
    $hasDateTimeErrors = is_array($errors)
        && (((int)($errors['warning_count'] ?? 0)) > 0 || ((int)($errors['error_count'] ?? 0)) > 0);
    if ($dateTime === false || $hasDateTimeErrors || $dateTime->format('Y-m-d\TH:i') !== $unpublishAt) {
        header('Location: ' . appendUrlQuery($fallback, ['err' => 'unpublish_at']));
        exit;
    }
    $unpublishAtSql = $dateTime->format('Y-m-d H:i:s');
}

if ($title === '' || $slug === '') {
    header('Location: ' . appendUrlQuery($fallback, ['err' => 'required']));
    exit;
}

$targetBlogId = null;
if ($selectedBlogId !== null) {
    $targetBlog = getBlogById($selectedBlogId);
    if (!$targetBlog) {
        header('Location: ' . appendUrlQuery($fallback, ['err' => 'blog']));
        exit;
    }
    $targetBlogId = (int)$targetBlog['id'];
}

$pdo = db_connect();
$uniqueSlug = uniquePageSlug($pdo, $slug, $id);
if ($uniqueSlug !== $slug) {
    header('Location: ' . appendUrlQuery($fallback, ['err' => 'slug']));
    exit;
}

$pdo->beginTransaction();

try {
    if ($id !== null) {
        $oldStmt = $pdo->prepare(
            "SELECT id, title, slug, content, blog_id, blog_nav_order, is_published, show_in_nav, nav_order, unpublish_at, admin_note
             FROM cms_pages
             WHERE id = ?"
        );
        $oldStmt->execute([$id]);
        $oldData = $oldStmt->fetch();
        if (!$oldData) {
            $pdo->rollBack();
            header('Location: ' . $redirect);
            exit;
        }

        $oldBlogId = !empty($oldData['blog_id']) ? (int)$oldData['blog_id'] : null;
        $oldWasGlobal = $oldBlogId === null;
        $newIsGlobal = $targetBlogId === null;

        $persistedShowInNav = $newIsGlobal ? $showInNav : 0;
        $persistedNavOrder = (int)($oldData['nav_order'] ?? 0);
        $persistedBlogNavOrder = (int)($oldData['blog_nav_order'] ?? 0);

        if ($newIsGlobal) {
            if (!$oldWasGlobal) {
                $persistedNavOrder = nextPageNavigationOrder($pdo);
                $persistedBlogNavOrder = 0;
            } elseif ($persistedNavOrder <= 0) {
                $persistedNavOrder = nextPageNavigationOrder($pdo);
            }
        } else {
            $persistedShowInNav = 0;
            if ($oldBlogId !== $targetBlogId || $persistedBlogNavOrder <= 0) {
                $persistedBlogNavOrder = nextBlogPageNavigationOrder($pdo, $targetBlogId);
            }
        }

        saveRevision($pdo, 'page', $id, $oldData, [
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'blog_id' => $targetBlogId,
            'blog_nav_order' => $newIsGlobal ? 0 : $persistedBlogNavOrder,
            'is_published' => $isPublished,
            'show_in_nav' => $persistedShowInNav,
            'nav_order' => $newIsGlobal ? $persistedNavOrder : (int)($oldData['nav_order'] ?? 0),
            'unpublish_at' => $unpublishAtSql,
            'admin_note' => $adminNote,
        ]);

        $requestedStatus = trim($_POST['article_status'] ?? '');
        if (!in_array($requestedStatus, ['draft', 'pending', 'published'], true)) {
            $requestedStatus = $oldData['status'] ?? 'published';
        }
        if ($requestedStatus === 'published' && !currentUserHasCapability('content_approve_shared')) {
            $requestedStatus = (($oldData['status'] ?? '') === 'published') ? 'published' : 'pending';
        }

        $pdo->prepare(
            "UPDATE cms_pages
             SET title = ?, slug = ?, content = ?, blog_id = ?, blog_nav_order = ?, is_published = ?, show_in_nav = ?, nav_order = ?, publish_at = ?, unpublish_at = ?, status = ?, admin_note = ?
             WHERE id = ?"
        )->execute([
            $title,
            $slug,
            $content,
            $targetBlogId,
            $newIsGlobal ? 0 : $persistedBlogNavOrder,
            $isPublished,
            $persistedShowInNav,
            $newIsGlobal ? $persistedNavOrder : (int)($oldData['nav_order'] ?? 0),
            $publishAtSql,
            $unpublishAtSql,
            $requestedStatus,
            $adminNote,
            $id,
        ]);

        if ($oldWasGlobal && !$newIsGlobal) {
            normalizePageNavigationOrder($pdo);
        } elseif (!$oldWasGlobal && $oldBlogId !== null && $oldBlogId !== $targetBlogId) {
            normalizeBlogPageNavigationOrder($pdo, $oldBlogId);
        }

        logAction('page_edit', 'id=' . $id . ', title=' . mb_substr($title, 0, 80));
    } else {
        $requestedStatus = trim($_POST['article_status'] ?? '');
        if (!in_array($requestedStatus, ['draft', 'pending', 'published'], true)) {
            $requestedStatus = 'draft';
        }
        if ($requestedStatus === 'published' && !currentUserHasCapability('content_approve_shared')) {
            $requestedStatus = 'pending';
        }
        $status = $requestedStatus;
        $isPublished = currentUserHasCapability('content_approve_shared') ? $isPublished : 0;
        $persistedShowInNav = $targetBlogId === null ? $showInNav : 0;
        $navOrder = $targetBlogId === null ? nextPageNavigationOrder($pdo) : 0;
        $blogNavOrder = $targetBlogId !== null ? nextBlogPageNavigationOrder($pdo, $targetBlogId) : 0;

        $pdo->prepare(
            "INSERT INTO cms_pages (title, slug, content, blog_id, blog_nav_order, is_published, show_in_nav, nav_order, publish_at, unpublish_at, admin_note, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $title,
            $slug,
            $content,
            $targetBlogId,
            $blogNavOrder,
            $isPublished,
            $persistedShowInNav,
            $navOrder,
            $publishAtSql,
            $unpublishAtSql,
            $adminNote,
            $status,
        ]);
        $newId = (int)$pdo->lastInsertId();
        logAction('page_add', 'id=' . $newId . ', title=' . mb_substr($title, 0, 80));
        if ($status === 'pending') {
            notifyPendingContent('Stránka', $title, '/admin/pages.php');
        }
    }

    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

header('Location: ' . $redirect);
exit;
