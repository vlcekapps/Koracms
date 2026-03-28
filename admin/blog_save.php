<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$pdo = db_connect();
$id = inputInt('post', 'id');
$title = trim($_POST['title'] ?? '');
$submittedSlug = trim($_POST['slug'] ?? '');
$perex = trim($_POST['perex'] ?? '');
$content = trim($_POST['content'] ?? '');
$categoryId = inputInt('post', 'category_id');
$tagIds = array_map('intval', (array)($_POST['tags'] ?? []));
$publishAt = trim($_POST['publish_at'] ?? '');
$metaTitle = trim($_POST['meta_title'] ?? '');
$metaDescription = trim($_POST['meta_description'] ?? '');
$commentsEnabled = isset($_POST['comments_enabled']) ? 1 : 0;

if ($title === '' || $content === '') {
    header('Location: blog_form.php' . ($id ? "?id={$id}" : ''));
    exit;
}

$existingArticle = null;
if ($id !== null) {
    if (canManageOwnBlogOnly()) {
        $existingStmt = $pdo->prepare("SELECT id, image_file, preview_token, author_id FROM cms_articles WHERE id = ? AND author_id = ?");
        $existingStmt->execute([$id, currentUserId()]);
    } else {
        $existingStmt = $pdo->prepare("SELECT id, image_file, preview_token, author_id FROM cms_articles WHERE id = ?");
        $existingStmt->execute([$id]);
    }
    $existingArticle = $existingStmt->fetch() ?: null;
    if (!$existingArticle) {
        header('Location: ' . BASE_URL . '/admin/blog.php');
        exit;
    }
}

$slug = articleSlug($submittedSlug !== '' ? $submittedSlug : $title);
if ($slug === '') {
    header('Location: blog_form.php?err=slug' . ($id ? "&id={$id}" : ''));
    exit;
}

$uniqueSlug = uniqueArticleSlug($pdo, $slug, $id);
if ($submittedSlug !== '' && $uniqueSlug !== $slug) {
    header('Location: blog_form.php?err=slug' . ($id ? "&id={$id}" : ''));
    exit;
}
$slug = $uniqueSlug;

$publishAtSql = null;
if ($publishAt !== '') {
    $dateTime = DateTime::createFromFormat('Y-m-d\TH:i', $publishAt);
    if ($dateTime) {
        $publishAtSql = $dateTime->format('Y-m-d H:i:s');
    }
}

$imageFile = null;
if (!empty($_FILES['image']['name'])) {
    $tmp = $_FILES['image']['tmp_name'];
    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $fileInfo->file($tmp);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    if (isset($allowed[$mime])) {
        $ext = $allowed[$mime];
        $filename = uniqid('img_', true) . '.' . $ext;
        $dir = __DIR__ . '/../uploads/articles/';
        $thumbDir = $dir . 'thumbs/';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0755, true);
        }

        if (move_uploaded_file($tmp, $dir . $filename)) {
            gallery_make_thumb($dir . $filename, $thumbDir . $filename, 400);
            $imageFile = $filename;

            if ($existingArticle && !empty($existingArticle['image_file'])) {
                @unlink($dir . $existingArticle['image_file']);
                @unlink($thumbDir . $existingArticle['image_file']);
            }
        }
    }
} elseif (isset($_POST['image_delete']) && $_POST['image_delete'] === '1' && $existingArticle) {
    if (!empty($existingArticle['image_file'])) {
        $dir = __DIR__ . '/../uploads/articles/';
        @unlink($dir . $existingArticle['image_file']);
        @unlink($dir . 'thumbs/' . $existingArticle['image_file']);
    }
    $imageFile = '';
}

if ($existingArticle) {
    $previewToken = (string)($existingArticle['preview_token'] ?? '');
    if ($previewToken === '') {
        $previewToken = bin2hex(random_bytes(16));
        $pdo->prepare("UPDATE cms_articles SET preview_token = ? WHERE id = ?")->execute([$previewToken, $id]);
    }

    // Revize – snapshot starých hodnot
    $oldStmt = $pdo->prepare("SELECT title, slug, perex, content FROM cms_articles WHERE id = ?");
    $oldStmt->execute([$id]);
    $oldData = $oldStmt->fetch();
    if ($oldData) {
        saveRevision($pdo, 'article', $id, $oldData, [
            'title' => $title, 'slug' => $slug, 'perex' => $perex, 'content' => $content,
        ]);
    }

    $setClauses = "title=?, slug=?, perex=?, content=?, category_id=?, comments_enabled=?, publish_at=?, meta_title=?, meta_description=?, author_id=COALESCE(author_id, ?), updated_at=NOW()";
    $params = [$title, $slug, $perex, $content, $categoryId, $commentsEnabled, $publishAtSql, $metaTitle, $metaDescription, currentUserId()];
    if ($imageFile !== null) {
        $setClauses .= ", image_file=?";
        $params[] = $imageFile;
    }
    $params[] = $id;
    $pdo->prepare("UPDATE cms_articles SET {$setClauses} WHERE id = ?")->execute($params);
    logAction('article_edit', "id={$id} title={$title} slug={$slug}");
} else {
    $previewToken = bin2hex(random_bytes(16));
    $authorId = currentUserId();
    $status = currentUserHasCapability('blog_approve') ? 'published' : 'pending';
    $pdo->prepare(
        "INSERT INTO cms_articles
         (title, slug, perex, content, category_id, comments_enabled, image_file, publish_at,
          meta_title, meta_description, preview_token, author_id, status)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
    )->execute([
        $title,
        $slug,
        $perex,
        $content,
        $categoryId,
        $commentsEnabled,
        $imageFile ?? '',
        $publishAtSql,
        $metaTitle,
        $metaDescription,
        $previewToken,
        $authorId,
        $status,
    ]);
    $id = (int)$pdo->lastInsertId();
    logAction('article_add', "id={$id} title={$title} slug={$slug} status={$status}");
}

try {
    $pdo->prepare("DELETE FROM cms_article_tags WHERE article_id = ?")->execute([$id]);
    if ($tagIds !== []) {
        $insertTag = $pdo->prepare("INSERT IGNORE INTO cms_article_tags (article_id, tag_id) VALUES (?, ?)");
        foreach ($tagIds as $tagId) {
            $insertTag->execute([$id, $tagId]);
        }
    }
} catch (\PDOException $e) {
    error_log('admin/blog_save tags: ' . $e->getMessage());
}

header('Location: ' . BASE_URL . '/admin/blog.php');
exit;
