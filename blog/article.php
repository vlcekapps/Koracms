<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('blog')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$id = inputInt('get', 'id');
if ($id === null) {
    header('Location: index.php');
    exit;
}

$pdo = db_connect();

$previewToken = trim($_GET['preview'] ?? '');
if ($previewToken !== '') {
    $stmt = $pdo->prepare(
        "SELECT a.*, c.name AS category, c.id AS category_id,
                COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),'')) AS author_name
         FROM cms_articles a
         LEFT JOIN cms_categories c ON c.id = a.category_id
         LEFT JOIN cms_users u ON u.id = a.author_id
         WHERE a.id = ? AND a.preview_token = ?"
    );
    $stmt->execute([$id, $previewToken]);
} else {
    $stmt = $pdo->prepare(
        "SELECT a.*, c.name AS category, c.id AS category_id,
                COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),'')) AS author_name
         FROM cms_articles a
         LEFT JOIN cms_categories c ON c.id = a.category_id
         LEFT JOIN cms_users u ON u.id = a.author_id
         WHERE a.id = ? AND a.status = 'published' AND (a.publish_at IS NULL OR a.publish_at <= NOW())"
    );
    $stmt->execute([$id]);
}
$article = $stmt->fetch();
if (!$article) {
    header('Location: index.php');
    exit;
}

if ($previewToken === '' && !isset($_SESSION['cms_user_id'])) {
    trackPageView('article', (int)$article['id']);
    try {
        $pdo->prepare("UPDATE cms_articles SET view_count = view_count + 1 WHERE id = ?")->execute([$id]);
    } catch (\PDOException $e) {
        error_log('article view_count: ' . $e->getMessage());
    }
}

$tags = [];
try {
    $ts = $pdo->prepare(
        "SELECT t.name, t.slug FROM cms_tags t
         JOIN cms_article_tags at2 ON at2.tag_id = t.id
         WHERE at2.article_id = ? ORDER BY t.name"
    );
    $ts->execute([$id]);
    $tags = $ts->fetchAll();
} catch (\PDOException $e) {
    error_log('article tags: ' . $e->getMessage());
}

$siteName       = getSetting('site_name', 'Kora CMS');
$commentErrors  = [];
$commentSuccess = isset($_GET['komentar']) && $_GET['komentar'] === 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('comment', 5, 120);

    if (honeypotTriggered()) {
        header('Location: ' . BASE_URL . '/blog/article.php?id=' . $id . '&komentar=ok');
        exit;
    }

    verifyCsrf();

    if (!captchaVerify($_POST['captcha'] ?? '')) {
        $commentErrors[] = 'Nesprávná odpověď na ověřovací příklad.';
    }

    $authorName  = trim($_POST['author_name'] ?? '');
    $authorEmail = trim($_POST['author_email'] ?? '');
    $content     = trim($_POST['comment'] ?? '');

    if ($authorName === '') {
        $commentErrors[] = 'Jméno je povinné.';
    }
    if (mb_strlen($authorName) > 100) {
        $commentErrors[] = 'Jméno je příliš dlouhé.';
    }
    if ($authorEmail !== '' && !filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) {
        $commentErrors[] = 'Neplatná e-mailová adresa.';
    }
    if ($content === '') {
        $commentErrors[] = 'Text komentáře je povinný.';
    }

    if (empty($commentErrors)) {
        $pdo->prepare(
            "INSERT INTO cms_comments (article_id, author_name, author_email, content)
             VALUES (?, ?, ?, ?)"
        )->execute([$id, $authorName, $authorEmail, $content]);

        header('Location: ' . BASE_URL . '/blog/article.php?id=' . $id . '&komentar=ok');
        exit;
    }
}

$commentsStmt = $pdo->prepare(
    "SELECT author_name, author_email, content, created_at
     FROM cms_comments
     WHERE article_id = ? AND is_approved = 1
     ORDER BY created_at ASC"
);
$commentsStmt->execute([$id]);
$comments = $commentsStmt->fetchAll();

$captchaExpr = captchaGenerate();

renderPublicPage([
    'title' => (!empty($article['meta_title']) ? $article['meta_title'] : $article['title']) . ' – ' . $siteName,
    'meta' => [
        'title' => !empty($article['meta_title']) ? $article['meta_title'] : $article['title'] . ' – ' . $siteName,
        'description' => !empty($article['meta_description']) ? $article['meta_description'] : ($article['perex'] ?? ''),
        'image' => !empty($article['image_file'])
            ? BASE_URL . '/uploads/articles/' . rawurlencode($article['image_file'])
            : '',
        'url' => BASE_URL . '/blog/article.php?id=' . (int)$article['id'],
        'type' => 'article',
    ],
    'view' => 'modules/blog-article',
    'view_data' => [
        'article' => $article,
        'tags' => $tags,
        'comments' => $comments,
        'commentErrors' => $commentErrors,
        'commentSuccess' => $commentSuccess,
        'captchaExpr' => $captchaExpr,
        'formData' => [
            'author_name' => trim($_POST['author_name'] ?? ''),
            'author_email' => trim($_POST['author_email'] ?? ''),
            'comment' => trim($_POST['comment'] ?? ''),
        ],
    ],
    'current_nav' => 'blog',
    'body_class' => 'page-blog-article',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/blog_form.php?id=' . (int)$article['id'],
]);
