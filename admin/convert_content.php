<?php
/**
 * Převod obsahu: článek → stránka nebo stránka → článek.
 * POST: direction (article_to_page | page_to_article), id, csrf_token
 */
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$direction = trim((string)($_POST['direction'] ?? ''));
$id = inputInt('post', 'id');

if ($id === null || !in_array($direction, ['article_to_page', 'page_to_article'], true)) {
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}

$pdo = db_connect();

if ($direction === 'article_to_page') {
    $stmt = $pdo->prepare(
        "SELECT id, title, slug, perex, content, blog_id, status, created_at, updated_at
         FROM cms_articles
         WHERE id = ?"
    );
    $stmt->execute([$id]);
    $article = $stmt->fetch();

    if (!$article) {
        header('Location: ' . BASE_URL . '/admin/blog.php');
        exit;
    }

    $pageContent = '';
    if (trim((string)$article['perex']) !== '') {
        $pageContent = '<p><strong>' . h((string)$article['perex']) . '</strong></p>' . "\n\n";
    }
    $pageContent .= (string)$article['content'];

    $slug = uniquePageSlug($pdo, pageSlug((string)$article['slug'] ?: (string)$article['title']));
    $isPublished = (string)$article['status'] === 'published' ? 1 : 0;
    $pageBlogId = !empty($article['blog_id']) ? (int)$article['blog_id'] : null;
    $navOrder = $pageBlogId === null ? nextPageNavigationOrder($pdo) : 0;
    $blogNavOrder = $pageBlogId !== null ? nextBlogPageNavigationOrder($pdo, $pageBlogId) : 0;

    $pdo->prepare(
        "INSERT INTO cms_pages (title, slug, content, blog_id, blog_nav_order, is_published, show_in_nav, nav_order, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?)"
    )->execute([
        (string)$article['title'],
        $slug,
        $pageContent,
        $pageBlogId,
        $blogNavOrder,
        $isPublished,
        $navOrder,
        (string)$article['status'],
        (string)$article['created_at'],
        (string)($article['updated_at'] ?: $article['created_at']),
    ]);
    $newPageId = (int)$pdo->lastInsertId();

    $pdo->prepare("DELETE FROM cms_article_tags WHERE article_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_comments WHERE article_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_articles WHERE id = ?")->execute([$id]);

    logAction('convert_article_to_page', 'article_id=' . $id . ' page_id=' . $newPageId . ' title=' . (string)$article['title']);

    header('Location: ' . BASE_URL . '/admin/page_form.php?id=' . $newPageId);
    exit;
}

if ($direction === 'page_to_article') {
    $stmt = $pdo->prepare(
        "SELECT id, title, slug, content, blog_id, is_published, status, created_at, updated_at
         FROM cms_pages
         WHERE id = ?"
    );
    $stmt->execute([$id]);
    $page = $stmt->fetch();

    if (!$page) {
        header('Location: ' . BASE_URL . '/admin/pages.php');
        exit;
    }

    $defaultBlogId = (int)(getDefaultBlog()['id'] ?? 1);
    $targetBlogId = !empty($page['blog_id']) ? (int)$page['blog_id'] : $defaultBlogId;
    $slug = uniqueArticleSlug($pdo, articleSlug((string)$page['slug'] ?: (string)$page['title']), null, $targetBlogId);
    $status = (string)($page['status'] ?? 'published');
    if ($status === '' || $status === 'published') {
        $status = (int)$page['is_published'] ? 'published' : 'pending';
    }

    $pdo->prepare(
        "INSERT INTO cms_articles (title, slug, perex, content, blog_id, status, comments_enabled, created_at, updated_at)
         VALUES (?, ?, '', ?, ?, ?, 1, ?, ?)"
    )->execute([
        (string)$page['title'],
        $slug,
        (string)$page['content'],
        $targetBlogId,
        $status,
        (string)$page['created_at'],
        (string)($page['updated_at'] ?: $page['created_at']),
    ]);
    $newArticleId = (int)$pdo->lastInsertId();

    $pdo->prepare("DELETE FROM cms_pages WHERE id = ?")->execute([$id]);
    if (!empty($page['blog_id'])) {
        normalizeBlogPageNavigationOrder($pdo, (int)$page['blog_id']);
    } else {
        normalizePageNavigationOrder($pdo);
    }

    logAction('convert_page_to_article', 'page_id=' . $id . ' article_id=' . $newArticleId . ' title=' . (string)$page['title']);

    header('Location: ' . BASE_URL . '/admin/blog_form.php?id=' . $newArticleId);
    exit;
}
