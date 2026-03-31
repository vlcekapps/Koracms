<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu statických stránek nemáte potřebné oprávnění.');
verifyCsrf();

$id = inputInt('post', 'id');
$redirect = internalRedirectTarget(trim((string)($_POST['redirect'] ?? '')), BASE_URL . '/admin/pages.php');

if ($id !== null) {
    $pdo = db_connect();
    $stmt = $pdo->prepare("SELECT blog_id FROM cms_pages WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $page = $stmt->fetch() ?: null;

    $pdo->prepare("UPDATE cms_pages SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL")->execute([$id]);

    if ($page && !empty($page['blog_id'])) {
        normalizeBlogPageNavigationOrder($pdo, (int)$page['blog_id']);
    } else {
        normalizePageNavigationOrder($pdo);
    }

    logAction('page_delete', 'id=' . $id);
}

header('Location: ' . $redirect);
exit;
