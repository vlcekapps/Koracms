<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
$action = $_POST['action'] ?? '';
$redirect = internalRedirectTarget(trim($_POST['redirect'] ?? ''), BASE_URL . '/admin/blog.php');

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
}

header('Location: ' . $redirect);
exit;
