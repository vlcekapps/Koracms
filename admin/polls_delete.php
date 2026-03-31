<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu anket nemáte potřebné oprávnění.');
verifyCsrf();

$pdo = db_connect();
$id = inputInt('post', 'id');
$redirectTarget = internalRedirectTarget(trim((string)($_POST['redirect'] ?? '')), BASE_URL . '/admin/polls.php');

if ($id !== null) {
    $pollStmt = $pdo->prepare("SELECT id, slug FROM cms_polls WHERE id = ?");
    $pollStmt->execute([$id]);
    $poll = $pollStmt->fetch() ?: null;

    $pdo->prepare("DELETE FROM cms_poll_votes WHERE poll_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_poll_options WHERE poll_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_polls WHERE id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'poll' AND entity_id = ?")->execute([$id]);

    if ($poll) {
        $pdo->prepare("DELETE FROM cms_redirects WHERE new_path = ?")->execute([pollPublicPath($poll)]);
    }

    logAction('poll_delete', "id={$id}");
}

header('Location: ' . $redirectTarget);
exit;
