<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
$action = $_POST['action'] ?? '';
$redirect = internalRedirectTarget(trim($_POST['redirect'] ?? ''), BASE_URL . '/admin/blog.php');

$setFlash = static function (string $type, string $message): void {
    $_SESSION['blog_transfer_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
};

if ($action === 'delete' && $ids !== []) {
    $pdo = db_connect();
    $dir = __DIR__ . '/../uploads/articles/';

    if (canManageOwnBlogOnly()) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge($ids, [currentUserId()]);
        $stmt = $pdo->prepare(
            "SELECT id, image_file, blog_id FROM cms_articles
             WHERE id IN ({$placeholders}) AND author_id = ?"
        );
        $stmt->execute($params);
        $articles = array_values(array_filter(
            $stmt->fetchAll(),
            static fn(array $article): bool => canCurrentUserWriteToBlog((int)($article['blog_id'] ?? 0))
        ));
    } else {
        $articles = [];
        foreach ($ids as $id) {
            $stmt = $pdo->prepare("SELECT id, image_file, blog_id FROM cms_articles WHERE id = ?");
            $stmt->execute([$id]);
            $article = $stmt->fetch();
            if ($article) {
                $articles[] = $article;
            }
        }
    }

    $deleteIds = [];
    foreach ($articles as $article) {
        if (!empty($article['image_file'])) {
            @unlink($dir . $article['image_file']);
            @unlink($dir . 'thumbs/' . $article['image_file']);
        }
        $deleteIds[] = (int)$article['id'];
    }

    foreach ($deleteIds as $deleteId) {
        $pdo->prepare("DELETE FROM cms_article_tags WHERE article_id = ?")->execute([$deleteId]);
        if (canManageOwnBlogOnly()) {
            $pdo->prepare("DELETE FROM cms_articles WHERE id = ? AND author_id = ?")->execute([$deleteId, currentUserId()]);
        } else {
            $pdo->prepare("DELETE FROM cms_articles WHERE id = ?")->execute([$deleteId]);
        }
    }

    if ($deleteIds !== []) {
        logAction('article_bulk_delete', 'ids=' . implode(',', $deleteIds));
    }
} elseif ($action === 'move' && $ids !== []) {
    $pdo = db_connect();
    $writableBlogs = getWritableBlogsForUser();
    if (count($writableBlogs) < 2) {
        unset($_SESSION['blog_transfer_selection']);
        $setFlash('error', 'Přesun článků se zobrazí až ve chvíli, kdy máte přístup alespoň do dvou blogů.');
        header('Location: ' . $redirect);
        exit;
    }

    $articles = loadTransferableBlogArticles($pdo, $ids);
    if (count($articles) !== count($ids)) {
        unset($_SESSION['blog_transfer_selection']);
        $setFlash('error', 'Vybraný seznam článků se změnil nebo obsahuje položky, které nemůžete přesouvat.');
        header('Location: ' . $redirect);
        exit;
    }

    $_SESSION['blog_transfer_selection'] = [
        'ids' => array_values(array_map('intval', $ids)),
        'redirect' => $redirect,
        'created_at' => time(),
    ];

    header('Location: ' . BASE_URL . '/admin/blog_transfer.php');
    exit;
}

header('Location: ' . $redirect);
exit;
