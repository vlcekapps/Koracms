<?php

require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu statických stránek nemáte potřebné oprávnění.');
verifyCsrf();

/**
 * @param array<string, mixed> $values
 */
function pageSaveRedirectWithFlash(string $fallback, string $errorCode, array $values): void
{
    $_SESSION['page_form_flash'] = ['values' => $values];
    header('Location: ' . appendUrlQuery($fallback, ['err' => $errorCode]));
    exit;
}

$id = inputInt('post', 'id');
$title = trim((string)($_POST['title'] ?? ''));
$rawSlug = trim((string)($_POST['slug'] ?? ''));
$slug = pageSlug($rawSlug !== '' ? $rawSlug : $title);
$content = (string)($_POST['content'] ?? '');
$isPublished = isset($_POST['is_published']) ? 1 : 0;
$showInNav = isset($_POST['show_in_nav']) ? 1 : 0;
$adminNote = trim((string)($_POST['admin_note'] ?? ''));
$selectedBlogId = inputInt('post', 'blog_id');
$requestedStatusInput = trim((string)($_POST['article_status'] ?? ''));

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
$formValues = [
    'id' => $id,
    'title' => $title,
    'slug' => $rawSlug !== '' ? $rawSlug : $slug,
    'content' => $content,
    'blog_id' => $selectedBlogId,
    'is_published' => $isPublished,
    'show_in_nav' => $showInNav,
    'article_status' => $requestedStatusInput,
    'publish_at' => $publishAt,
    'unpublish_at' => $unpublishAt,
    'admin_note' => $adminNote,
];

if ($publishAt !== '') {
    $publishAtSql = validateDateTimeLocal($publishAt);
    if ($publishAtSql === null) {
        pageSaveRedirectWithFlash($fallback, 'publish_at', $formValues);
    }
}

if ($unpublishAt !== '') {
    $unpublishAtSql = validateDateTimeLocal($unpublishAt);
    if ($unpublishAtSql === null) {
        pageSaveRedirectWithFlash($fallback, 'unpublish_at', $formValues);
    }
}

if ($title === '' || $slug === '') {
    pageSaveRedirectWithFlash($fallback, 'required', $formValues);
}

$targetBlogId = null;
if ($selectedBlogId !== null) {
    $targetBlog = getBlogById($selectedBlogId);
    if (!$targetBlog) {
        pageSaveRedirectWithFlash($fallback, 'blog', $formValues);
    }
    $targetBlogId = (int)$targetBlog['id'];
}
$formValues['blog_id'] = $targetBlogId;

$pdo = db_connect();
$uniqueSlug = uniquePageSlug($pdo, $slug, $id, $targetBlogId);
if ($uniqueSlug !== $slug) {
    pageSaveRedirectWithFlash($fallback, $targetBlogId === null ? 'slug_global' : 'slug_blog', $formValues);
}

$pdo->beginTransaction();

try {
    if ($id !== null) {
        $oldStmt = $pdo->prepare(
            "SELECT id, title, slug, content, blog_id, blog_nav_order, is_published, show_in_nav, nav_order, publish_at, unpublish_at, status, admin_note, preview_token
             FROM cms_pages
             WHERE id = ? AND deleted_at IS NULL"
        );
        $oldStmt->execute([$id]);
        $oldData = $oldStmt->fetch();
        if (!$oldData) {
            $pdo->rollBack();
            header('Location: ' . $redirect);
            exit;
        }

        if (($oldData['preview_token'] ?? '') === '') {
            $previewToken = bin2hex(random_bytes(16));
            $pdo->prepare("UPDATE cms_pages SET preview_token = ? WHERE id = ?")->execute([$previewToken, $id]);
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

        $requestedStatus = $requestedStatusInput;
        if (!in_array($requestedStatus, ['draft', 'pending', 'published'], true)) {
            $requestedStatus = $oldData['status'] ?? 'published';
        }
        if ($requestedStatus === 'published' && !currentUserHasCapability('content_approve_shared')) {
            $requestedStatus = (($oldData['status'] ?? '') === 'published') ? 'published' : 'pending';
        }

        // Při první publikaci aktualizovat created_at na čas publikace
        $publishingNow = $requestedStatus === 'published' && ($oldData['status'] ?? '') !== 'published';

        $pdo->prepare(
            "UPDATE cms_pages
             SET title = ?, slug = ?, content = ?, blog_id = ?, blog_nav_order = ?, is_published = ?, show_in_nav = ?, nav_order = ?, publish_at = ?, unpublish_at = ?, status = ?, admin_note = ?"
             . ($publishingNow ? ", created_at = NOW()" : "") . "
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
        } elseif (!$oldWasGlobal && $oldBlogId !== $targetBlogId) {
            normalizeBlogPageNavigationOrder($pdo, $oldBlogId);
        }

        logAction('page_edit', 'id=' . $id . ', title=' . mb_substr($title, 0, 80));
    } else {
        $requestedStatus = $requestedStatusInput;
        if (!in_array($requestedStatus, ['draft', 'pending', 'published'], true)) {
            $requestedStatus = $isPublished === 1 ? 'published' : 'draft';
        }
        if ($requestedStatus === 'published' && !currentUserHasCapability('content_approve_shared')) {
            $requestedStatus = 'pending';
        }
        $status = $requestedStatus;
        $isPublished = currentUserHasCapability('content_approve_shared') ? $isPublished : 0;
        $persistedShowInNav = $targetBlogId === null ? $showInNav : 0;
        $navOrder = $targetBlogId === null ? nextPageNavigationOrder($pdo) : 0;
        $blogNavOrder = $targetBlogId !== null ? nextBlogPageNavigationOrder($pdo, $targetBlogId) : 0;
        $previewToken = bin2hex(random_bytes(16));

        $pdo->prepare(
            "INSERT INTO cms_pages (title, slug, content, blog_id, blog_nav_order, is_published, show_in_nav, nav_order, publish_at, unpublish_at, admin_note, preview_token, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
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
            $previewToken,
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
    if ($e instanceof \PDOException && (string)$e->getCode() === '23000') {
        pageSaveRedirectWithFlash($fallback, $targetBlogId === null ? 'slug_global' : 'slug_blog', $formValues);
    }
    throw $e;
}

// Uvolnění zámku obsahu po úspěšném uložení
if ($id !== null) {
    releaseContentLock('page', $id);
}

header('Location: ' . $redirect);
exit;
