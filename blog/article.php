<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('blog')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$id = inputInt('get', 'id');
$slug = articleSlug(trim($_GET['slug'] ?? ''));
if ($id === null && $slug === '') {
    header('Location: index.php');
    exit;
}

$pdo = db_connect();

$previewToken = trim($_GET['preview'] ?? '');
if ($previewToken !== '') {
    if ($slug !== '') {
        $stmt = $pdo->prepare(
            "SELECT a.*, c.name AS category, c.id AS category_id, c.slug AS category_slug, b.slug AS blog_slug,
                    COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),'')) AS author_name,
                    u.author_public_enabled, u.author_slug, u.author_bio, u.author_avatar, u.author_website, u.role AS author_role
             FROM cms_articles a
             LEFT JOIN cms_categories c ON c.id = a.category_id
             LEFT JOIN cms_blogs b ON b.id = a.blog_id
             LEFT JOIN cms_users u ON u.id = a.author_id
             WHERE a.slug = ? AND a.preview_token = ?"
        );
        $stmt->execute([$slug, $previewToken]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT a.*, c.name AS category, c.id AS category_id, c.slug AS category_slug, b.slug AS blog_slug,
                    COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),'')) AS author_name,
                    u.author_public_enabled, u.author_slug, u.author_bio, u.author_avatar, u.author_website, u.role AS author_role
             FROM cms_articles a
             LEFT JOIN cms_categories c ON c.id = a.category_id
             LEFT JOIN cms_blogs b ON b.id = a.blog_id
             LEFT JOIN cms_users u ON u.id = a.author_id
             WHERE a.id = ? AND a.preview_token = ?"
        );
        $stmt->execute([$id, $previewToken]);
    }
} else {
    if ($slug !== '') {
        $stmt = $pdo->prepare(
            "SELECT a.*, c.name AS category, c.id AS category_id, c.slug AS category_slug, b.slug AS blog_slug,
                    COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),'')) AS author_name,
                    u.author_public_enabled, u.author_slug, u.author_bio, u.author_avatar, u.author_website, u.role AS author_role
             FROM cms_articles a
             LEFT JOIN cms_categories c ON c.id = a.category_id
             LEFT JOIN cms_blogs b ON b.id = a.blog_id
             LEFT JOIN cms_users u ON u.id = a.author_id
             WHERE a.slug = ? AND a.deleted_at IS NULL AND a.status = 'published' AND (a.publish_at IS NULL OR a.publish_at <= NOW())"
        );
        $stmt->execute([$slug]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT a.*, c.name AS category, c.id AS category_id, c.slug AS category_slug, b.slug AS blog_slug,
                    COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),'')) AS author_name,
                    u.author_public_enabled, u.author_slug, u.author_bio, u.author_avatar, u.author_website, u.role AS author_role
             FROM cms_articles a
             LEFT JOIN cms_categories c ON c.id = a.category_id
             LEFT JOIN cms_blogs b ON b.id = a.blog_id
             LEFT JOIN cms_users u ON u.id = a.author_id
             WHERE a.id = ? AND a.deleted_at IS NULL AND a.status = 'published' AND (a.publish_at IS NULL OR a.publish_at <= NOW())"
        );
        $stmt->execute([$id]);
    }
}
$article = $stmt->fetch();
if (!$article) {
    header('Location: index.php');
    exit;
}
$article = hydrateAuthorPresentation($article);

if ($previewToken === '' && $slug === '' && !empty($article['slug'])) {
    $commentRedirect = trim($_GET['komentar'] ?? '');
    header('Location: ' . articlePublicPath($article, $commentRedirect !== '' ? ['komentar' => $commentRedirect] : []));
    exit;
}

$articleId = (int)$article['id'];

if ($previewToken === '' && !isset($_SESSION['cms_user_id'])) {
    trackPageView('article', (int)$article['id']);
    try {
        $pdo->prepare("UPDATE cms_articles SET view_count = view_count + 1 WHERE id = ?")->execute([$articleId]);
        $article['view_count'] = (int)($article['view_count'] ?? 0) + 1;
    } catch (\PDOException $e) {
        koraLog('warning', 'article view count update failed', ['article_id' => $articleId, 'exception' => $e]);
    }
}

$tags = [];
try {
    $ts = $pdo->prepare(
        "SELECT t.name, t.slug FROM cms_tags t
         JOIN cms_article_tags at2 ON at2.tag_id = t.id
         WHERE at2.article_id = ? ORDER BY t.name"
    );
    $ts->execute([$articleId]);
    $tags = $ts->fetchAll();
} catch (\PDOException $e) {
    koraLog('warning', 'article tags query failed', ['article_id' => $articleId, 'exception' => $e]);
}

$siteName = getSetting('site_name', 'Kora CMS');
$commentErrors = [];
$commentFieldErrors = [];
$commentContactDefaults = currentUserContactDefaults($pdo);
$isPostRequest = $_SERVER['REQUEST_METHOD'] === 'POST';
$addCommentError = static function (string $field, string $message) use (&$commentErrors, &$commentFieldErrors): void {
    $commentErrors[] = $message;
    $commentFieldErrors[$field] = $message;
};
$commentResult = trim($_GET['komentar'] ?? '');
if (!in_array($commentResult, ['approved', 'pending'], true)) {
    $commentResult = '';
}
$commentsState = articleCommentsState($article);
if ($previewToken !== '') {
    $commentsState = [
        'enabled' => false,
        'reason' => 'preview',
        'message' => 'V náhledu článku nejsou komentáře dostupné.',
    ];
}

if ($isPostRequest) {
    if (!$commentsState['enabled']) {
        $commentErrors[] = $commentsState['message'];
    } else {
        rateLimit('comment', 5, 120);

        if (honeypotTriggered()) {
            header('Location: ' . articlePublicPath($article, ['komentar' => 'pending']));
            exit;
        }

        verifyCsrf();

        if (!captchaVerify($_POST['captcha'] ?? '')) {
            $addCommentError('captcha', publicCaptchaErrorMessage());
        }

        $authorName = trim($_POST['author_name'] ?? '');
        $authorEmail = trim($_POST['author_email'] ?? '');
        $content = trim($_POST['comment'] ?? '');

        if ($authorName === '') {
            $addCommentError('author_name', 'Doplňte jméno, pod kterým se má komentář zobrazit.');
        }
        if (mb_strlen($authorName) > 100) {
            $addCommentError('author_name', 'Zkraťte jméno na nejvýše 100 znaků.');
        }
        if ($authorEmail !== '' && !filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) {
            $addCommentError('author_email', 'Zadejte úplnou e-mailovou adresu ve tvaru jmeno@example.cz, nebo pole nechte prázdné.');
        }
        if ($content === '') {
            $addCommentError('comment', 'Napište text komentáře.');
        }

        if (empty($commentErrors)) {
            $commentDecision = determineCommentStatus($pdo, $authorName, $authorEmail, $content);
            $commentStatus = $commentDecision['status'];
            try {
                $pdo->prepare(
                    "INSERT INTO cms_comments (article_id, author_name, author_email, content, status, is_approved)
                     VALUES (?, ?, ?, ?, ?, ?)"
                )->execute([
                    $articleId,
                    $authorName,
                    $authorEmail,
                    $content,
                    $commentStatus,
                    commentStatusApprovalValue($commentStatus),
                ]);
            } catch (\PDOException $e) {
                $pdo->prepare(
                    "INSERT INTO cms_comments (article_id, author_name, author_email, content, is_approved)
                     VALUES (?, ?, ?, ?, ?)"
                )->execute([
                    $articleId,
                    $authorName,
                    $authorEmail,
                    $content,
                    commentStatusApprovalValue($commentStatus),
                ]);
            }

            if ($commentStatus === 'pending') {
                notifyAdminAboutPendingComment($article, $authorName, $authorEmail, $content);
            }

            header('Location: ' . articlePublicPath($article, ['komentar' => $commentDecision['public_result']]));
            exit;
        }
    }
}

$comments = [];
try {
    $commentsStmt = $pdo->prepare(
        "SELECT author_name, author_email, content, created_at
         FROM cms_comments
         WHERE article_id = ? AND status = 'approved'
         ORDER BY created_at ASC"
    );
    $commentsStmt->execute([$articleId]);
    $comments = $commentsStmt->fetchAll();
} catch (\PDOException $e) {
    $commentsStmt = $pdo->prepare(
        "SELECT author_name, author_email, content, created_at
         FROM cms_comments
         WHERE article_id = ? AND is_approved = 1
         ORDER BY created_at ASC"
    );
    $commentsStmt->execute([$articleId]);
    $comments = $commentsStmt->fetchAll();
}

$captchaExpr = captchaGenerate();

$articleContent = buildBlogArticleTableOfContents(renderContent((string)($article['content'] ?? '')));
$articleSeries = ($previewToken === '') ? articleSeriesNavigation($pdo, $article) : [];
$related = ($previewToken === '') ? relatedArticles($pdo, $article, 3) : [];
$articleBlog = $GLOBALS['current_blog'] ?? getBlogById((int)($article['blog_id'] ?? 1));
$articleSeoTitle = trim((string)(!empty($article['meta_title']) ? $article['meta_title'] : $article['title']));
if ($articleSeoTitle === '') {
    $articleSeoTitle = 'Článek';
}
$articleSeoDescription = trim((string)($article['meta_description'] ?? ''));
if ($articleSeoDescription === '') {
    $articleSeoDescription = trim((string)($article['perex'] ?? ''));
}
if ($articleSeoDescription === '') {
    $articleSeoDescription = articleExcerpt((string)($article['content'] ?? ''), 220);
}
$articleSeoImage = !empty($article['image_file'])
    ? BASE_URL . '/uploads/articles/' . rawurlencode($article['image_file'])
    : '';
if ($articleSeoImage === '' && is_array($articleBlog)) {
    $articleSeoImage = blogLogoUrl($articleBlog);
}

renderPublicPage([
    'title' => $articleSeoTitle . ' – ' . $siteName,
    'meta' => [
        'title' => $articleSeoTitle,
        'description' => $articleSeoDescription,
        'image' => $articleSeoImage,
        'image_alt' => $articleSeoTitle,
        'url' => articlePublicUrl($article),
        'type' => 'article',
        'published_time' => (string)($article['publish_at'] ?? $article['created_at'] ?? ''),
        'modified_time' => (string)($article['updated_at'] ?? $article['publish_at'] ?? $article['created_at'] ?? ''),
    ],
    'view' => 'modules/blog-article',
    'view_data' => [
        'article' => $article,
        'articleContentHtml' => $articleContent['html'],
        'articleToc' => $articleContent['items'],
        'blog' => $articleBlog,
        'tags' => $tags,
        'articleSeries' => $articleSeries,
        'comments' => $comments,
        'commentErrors' => $commentErrors,
        'commentFieldErrors' => $commentFieldErrors,
        'commentResult' => $commentResult,
        'commentsState' => $commentsState,
        'captchaExpr' => $captchaExpr,
        'related' => $related,
        'formData' => [
            'author_name' => $isPostRequest ? trim((string)($_POST['author_name'] ?? '')) : $commentContactDefaults['name'],
            'author_email' => $isPostRequest ? trim((string)($_POST['author_email'] ?? '')) : $commentContactDefaults['email'],
            'comment' => trim($_POST['comment'] ?? ''),
        ],
    ],
    'current_nav' => 'blog:' . articleBlogSlug($article),
    'body_class' => 'page-blog-article',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/blog_form.php?id=' . $articleId,
]);
