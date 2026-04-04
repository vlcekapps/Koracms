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
$categorySelectionMode = trim((string)($_POST['category_selection_mode'] ?? ($id !== null ? 'auto' : 'manual')));
$tagSelectionMode = trim((string)($_POST['tag_selection_mode'] ?? ($id !== null ? 'auto' : 'manual')));
$missingCategoryAction = trim((string)($_POST['missing_category_action'] ?? 'drop'));
$missingTagsAction = trim((string)($_POST['missing_tags_action'] ?? 'drop'));
$publishAt = trim($_POST['publish_at'] ?? '');
$metaTitle = trim($_POST['meta_title'] ?? '');
$metaDescription = trim($_POST['meta_description'] ?? '');
$commentsEnabled = isset($_POST['comments_enabled']) ? 1 : 0;
$isFeaturedInBlog = isset($_POST['is_featured_in_blog']) ? 1 : 0;
$adminNote = trim($_POST['admin_note'] ?? '');
$blogId = inputInt('post', 'blog_id') ?? (int)(getDefaultBlog()['id'] ?? 0);
$defaultRedirect = BASE_URL . '/admin/blog.php' . ($blogId > 0 ? '?blog=' . $blogId : '');
$redirect = internalRedirectTarget($_POST['redirect'] ?? '', $defaultRedirect);
$redirectToForm = static function (?int $articleId, int $targetBlogId, string $errorCode) use ($blogId): never {
    $params = ['err' => $errorCode];
    if ($targetBlogId > 0) {
        $params['blog_id'] = $targetBlogId;
    }
    if ($articleId !== null) {
        $params['id'] = $articleId;
    } else if (!isset($params['blog_id'])) {
        $params['blog_id'] = $targetBlogId > 0 ? $targetBlogId : $blogId;
    }

    header('Location: ' . BASE_URL . '/admin/blog_form.php?' . http_build_query($params));
    exit;
};
$isValidDateTimeLocal = static function (string $value): bool {
    $dateTime = DateTime::createFromFormat('Y-m-d\TH:i', $value);
    $errors = DateTime::getLastErrors();
    $hasErrors = is_array($errors)
        && (((int)($errors['warning_count'] ?? 0)) > 0 || ((int)($errors['error_count'] ?? 0)) > 0);

    return $dateTime !== false && !$hasErrors && $dateTime->format('Y-m-d\TH:i') === $value;
};

if ($blogId <= 0 || !getBlogById($blogId)) {
    header('Location: ' . BASE_URL . '/admin/blogs.php');
    exit;
}

if (!canCurrentUserWriteToBlog($blogId)) {
    header('Location: ' . BASE_URL . '/admin/blog.php?msg=no_blog_access');
    exit;
}

if ($title === '' || $content === '') {
    $redirectToForm($id, $blogId, 'required');
}

$existingArticle = null;
if ($id !== null) {
    if (canManageOwnBlogOnly()) {
        $existingStmt = $pdo->prepare("SELECT id, image_file, preview_token, author_id, blog_id, category_id, status FROM cms_articles WHERE id = ? AND author_id = ?");
        $existingStmt->execute([$id, currentUserId()]);
    } else {
        $existingStmt = $pdo->prepare("SELECT id, image_file, preview_token, author_id, blog_id, category_id, status FROM cms_articles WHERE id = ?");
        $existingStmt->execute([$id]);
    }
    $existingArticle = $existingStmt->fetch() ?: null;
    if (!$existingArticle) {
        header('Location: ' . BASE_URL . '/admin/blog.php');
        exit;
    }
}

$articleIsMovingToAnotherBlog = $existingArticle !== null
    && (int)($existingArticle['blog_id'] ?? 0) > 0
    && (int)($existingArticle['blog_id'] ?? 0) !== $blogId;
$canCreateTargetTaxonomies = $articleIsMovingToAnotherBlog && canCurrentUserManageBlogTaxonomies($blogId);
$categorySelectionMode = in_array($categorySelectionMode, ['auto', 'manual'], true) ? $categorySelectionMode : 'manual';
$tagSelectionMode = in_array($tagSelectionMode, ['auto', 'manual'], true) ? $tagSelectionMode : 'manual';
$missingCategoryAction = in_array($missingCategoryAction, ['drop', 'create'], true) ? $missingCategoryAction : 'drop';
$missingTagsAction = in_array($missingTagsAction, ['drop', 'create'], true) ? $missingTagsAction : 'drop';

if ($articleIsMovingToAnotherBlog && $missingCategoryAction === 'create' && !$canCreateTargetTaxonomies) {
    $redirectToForm($id, $blogId, 'missing_category_action');
}
if ($articleIsMovingToAnotherBlog && $missingTagsAction === 'create' && !$canCreateTargetTaxonomies) {
    $redirectToForm($id, $blogId, 'missing_tags_action');
}

$slug = articleSlug($submittedSlug !== '' ? $submittedSlug : $title);
if ($slug === '') {
    $redirectToForm($id, $blogId, 'slug');
}

$uniqueSlug = uniqueArticleSlug($pdo, $slug, $id, $blogId);
if ($submittedSlug !== '' && $uniqueSlug !== $slug) {
    $redirectToForm($id, $blogId, 'slug');
}
$slug = $uniqueSlug;

$sourceCategoryName = '';
$sourceTagDetails = [];
$targetCategoryRows = [];
$targetCategoryLookup = [];
$targetTagRows = [];
$targetTagLookup = ['by_slug' => [], 'by_name' => []];
$articleMoveTaxonomyState = [
    'matched_category_id' => null,
    'matched_tag_ids' => [],
    'missing_category_name' => '',
    'missing_tags' => [],
];
if ($articleIsMovingToAnotherBlog) {
    $sourceCategoryId = (int)($existingArticle['category_id'] ?? 0);
    if ($sourceCategoryId > 0) {
        $sourceCategoryStmt = $pdo->prepare("SELECT name FROM cms_categories WHERE id = ? LIMIT 1");
        $sourceCategoryStmt->execute([$sourceCategoryId]);
        $sourceCategoryName = trim((string)$sourceCategoryStmt->fetchColumn());
    }

    $sourceTagDetails = loadArticleTagDetails($pdo, $id ?? 0);

    $targetCategoryStmt = $pdo->prepare("SELECT id, name FROM cms_categories WHERE blog_id = ? ORDER BY name ASC, id ASC");
    $targetCategoryStmt->execute([$blogId]);
    $targetCategoryRows = $targetCategoryStmt->fetchAll() ?: [];
    $targetCategoryLookup = blogCategoryLookupByNormalizedName($targetCategoryRows);

    $targetTagStmt = $pdo->prepare("SELECT id, name, slug FROM cms_tags WHERE blog_id = ? ORDER BY name ASC, id ASC");
    $targetTagStmt->execute([$blogId]);
    $targetTagRows = $targetTagStmt->fetchAll() ?: [];
    $targetTagLookup = blogTagLookupMaps($targetTagRows);

    $articleMoveTaxonomyState = resolveArticleMoveTaxonomyState(
        $sourceCategoryName,
        $sourceTagDetails,
        $targetCategoryRows,
        $targetTagRows
    );
}

if ($articleIsMovingToAnotherBlog && $categoryId === null && $categorySelectionMode !== 'manual') {
    $matchedCategoryId = (int)($articleMoveTaxonomyState['matched_category_id'] ?? 0);
    if ($matchedCategoryId > 0) {
        $categoryId = $matchedCategoryId;
    } elseif (($articleMoveTaxonomyState['missing_category_name'] ?? '') !== '' && $missingCategoryAction === 'create' && $canCreateTargetTaxonomies) {
        $missingCategoryName = trim((string)$articleMoveTaxonomyState['missing_category_name']);
        if ($missingCategoryName !== '') {
            $insertCategoryStmt = $pdo->prepare("INSERT INTO cms_categories (name, blog_id) VALUES (?, ?)");
            $insertCategoryStmt->execute([$missingCategoryName, $blogId]);
            $categoryId = (int)$pdo->lastInsertId();
            $targetCategoryLookup[normalizeBlogTaxonomyName($missingCategoryName)] = [
                'id' => $categoryId,
                'name' => $missingCategoryName,
            ];
        }
    }
}

$validTagIds = [];
$allowedTagRows = [];
if ($tagIds !== []) {
    $requestedTagIds = array_values(array_unique(array_filter(array_map('intval', $tagIds), static fn(int $tagId): bool => $tagId > 0)));
    $tagCheckStmt = $pdo->prepare("SELECT id, name, slug FROM cms_tags WHERE blog_id = ? ORDER BY id");
    $tagCheckStmt->execute([$blogId]);
    $allowedTagRows = $tagCheckStmt->fetchAll() ?: [];
    $allowedTagIds = array_map('intval', array_column($allowedTagRows, 'id'));
    $validTagIds = array_values(array_intersect($allowedTagIds, $requestedTagIds));
    if (count($validTagIds) !== count($requestedTagIds)) {
        $redirectToForm($id, $blogId, 'tags_target');
    }
}

if ($categoryId !== null) {
    $categoryCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM cms_categories WHERE id = ? AND blog_id = ?");
    $categoryCheckStmt->execute([$categoryId, $blogId]);
    if ((int)$categoryCheckStmt->fetchColumn() === 0) {
        $redirectToForm($id, $blogId, 'category_target');
    }
}

if ($articleIsMovingToAnotherBlog && $validTagIds === [] && $tagSelectionMode !== 'manual') {
    $resolvedTagIds = array_values(array_map('intval', (array)($articleMoveTaxonomyState['matched_tag_ids'] ?? [])));
    $missingSourceTags = array_values(array_filter(
        (array)($articleMoveTaxonomyState['missing_tags'] ?? []),
        static function (array $tag): bool {
            return trim((string)($tag['name'] ?? '')) !== '';
        }
    ));

    if ($missingSourceTags !== [] && $missingTagsAction === 'create' && $canCreateTargetTaxonomies) {
        $insertTagStmt = $pdo->prepare("INSERT INTO cms_tags (name, slug, blog_id) VALUES (?, ?, ?)");
        foreach ($missingSourceTags as $missingSourceTag) {
            $missingTagName = trim((string)($missingSourceTag['name'] ?? ''));
            $missingTagSlug = trim((string)($missingSourceTag['slug'] ?? ''));
            if ($missingTagName === '') {
                continue;
            }

            if ($missingTagSlug !== '' && isset($targetTagLookup['by_slug'][$missingTagSlug]['id'])) {
                $existingTargetTagId = (int)$targetTagLookup['by_slug'][$missingTagSlug]['id'];
                if ($existingTargetTagId > 0 && !in_array($existingTargetTagId, $resolvedTagIds, true)) {
                    $resolvedTagIds[] = $existingTargetTagId;
                }
                continue;
            }

            $normalizedMissingTagName = normalizeBlogTaxonomyName($missingTagName);
            if ($normalizedMissingTagName !== '' && isset($targetTagLookup['by_name'][$normalizedMissingTagName]['id'])) {
                $existingTargetTagId = (int)$targetTagLookup['by_name'][$normalizedMissingTagName]['id'];
                if ($existingTargetTagId > 0 && !in_array($existingTargetTagId, $resolvedTagIds, true)) {
                    $resolvedTagIds[] = $existingTargetTagId;
                }
                continue;
            }

            $candidateSlug = $missingTagSlug !== '' ? $missingTagSlug : slugify($missingTagName);
            if ($candidateSlug === '') {
                $candidateSlug = 'tag-' . substr(sha1($missingTagName), 0, 8);
            }
            $uniqueSlug = $candidateSlug;
            $slugSuffix = 2;
            while (isset($targetTagLookup['by_slug'][$uniqueSlug])) {
                $uniqueSlug = $candidateSlug . '-' . $slugSuffix;
                $slugSuffix++;
            }

            $insertTagStmt->execute([$missingTagName, $uniqueSlug, $blogId]);
            $newTagId = (int)$pdo->lastInsertId();
            if ($newTagId <= 0) {
                continue;
            }

            $newTagData = [
                'id' => $newTagId,
                'name' => $missingTagName,
                'slug' => $uniqueSlug,
            ];
            $targetTagLookup['by_slug'][$uniqueSlug] = $newTagData;
            if ($normalizedMissingTagName !== '') {
                $targetTagLookup['by_name'][$normalizedMissingTagName] = $newTagData;
            }
            if (!in_array($newTagId, $resolvedTagIds, true)) {
                $resolvedTagIds[] = $newTagId;
            }
        }
    }

    $validTagIds = $resolvedTagIds;
}

$publishAtSql = null;
if ($publishAt !== '') {
    if (!$isValidDateTimeLocal($publishAt)) {
        $redirectToForm($id, $blogId, 'publish_at');
    }
    $dateTime = DateTime::createFromFormat('Y-m-d\TH:i', $publishAt);
    $publishAtSql = $dateTime->format('Y-m-d H:i:s');
}

$unpublishAt = trim($_POST['unpublish_at'] ?? '');
$unpublishAtSql = null;
if ($unpublishAt !== '') {
    if (!$isValidDateTimeLocal($unpublishAt)) {
        $redirectToForm($id, $blogId, 'unpublish_at');
    }
    $dateTime = DateTime::createFromFormat('Y-m-d\TH:i', $unpublishAt);
    $unpublishAtSql = $dateTime->format('Y-m-d H:i:s');
}
if ($publishAtSql !== null && $unpublishAtSql !== null && $unpublishAtSql <= $publishAtSql) {
    $redirectToForm($id, $blogId, 'publish_range');
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
            generateWebp($dir . $filename);
            generateWebp($thumbDir . $filename);
            generateResponsiveSizes($dir . $filename, $dir, $filename);
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

$pdo->beginTransaction();
try {
    if ($existingArticle) {
        $previewToken = (string)($existingArticle['preview_token'] ?? '');
        if ($previewToken === '') {
            $previewToken = bin2hex(random_bytes(16));
            $pdo->prepare("UPDATE cms_articles SET preview_token = ? WHERE id = ?")->execute([$previewToken, $id]);
        }

        // Revize – snapshot starých hodnot
        $oldStmt = $pdo->prepare("SELECT title, slug, perex, content, publish_at, unpublish_at, meta_title, meta_description, admin_note FROM cms_articles WHERE id = ?");
        $oldStmt->execute([$id]);
        $oldData = $oldStmt->fetch();
        if ($oldData) {
            saveRevision($pdo, 'article', $id, $oldData, [
                'title' => $title,
                'slug' => $slug,
                'perex' => $perex,
                'content' => $content,
                'publish_at' => $publishAtSql,
                'unpublish_at' => $unpublishAtSql,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'admin_note' => $adminNote,
            ]);
        }

        $requestedStatus = trim($_POST['article_status'] ?? '');
        if (!in_array($requestedStatus, ['draft', 'pending', 'published'], true)) {
            $requestedStatus = $existingArticle['status'];
        }
        if ($requestedStatus === 'published' && !currentUserHasCapability('blog_approve')) {
            $requestedStatus = ($existingArticle['status'] === 'published') ? 'published' : 'pending';
        }
        $status = $requestedStatus;

        // Při první publikaci (draft/pending → published) aktualizovat created_at na čas publikace
        $publishingNow = $status === 'published' && $existingArticle['status'] !== 'published';
        $setClauses = "title=?, slug=?, perex=?, content=?, category_id=?, comments_enabled=?, is_featured_in_blog=?, publish_at=?, unpublish_at=?, meta_title=?, meta_description=?, admin_note=?, author_id=COALESCE(author_id, ?), blog_id=?, status=?, updated_at=NOW()";
        if ($publishingNow) {
            $setClauses .= ", created_at=NOW()";
        }
        $params = [$title, $slug, $perex, $content, $categoryId, $commentsEnabled, $isFeaturedInBlog, $publishAtSql, $unpublishAtSql, $metaTitle, $metaDescription, $adminNote, currentUserId(), $blogId, $status];
        if ($imageFile !== null) {
            $setClauses .= ", image_file=?";
            $params[] = $imageFile;
        }
        $params[] = $id;
        $pdo->prepare("UPDATE cms_articles SET {$setClauses} WHERE id = ?")->execute($params);
        if ($isFeaturedInBlog === 1) {
            $pdo->prepare(
                "UPDATE cms_articles
                 SET is_featured_in_blog = 0
                 WHERE blog_id = ? AND id <> ?"
            )->execute([$blogId, $id]);
        }
        logAction('article_edit', "id={$id} title={$title} slug={$slug} status={$status}");
        if ($status === 'pending' && ($existingArticle['status'] ?? '') !== 'pending') {
            notifyPendingContent('Článek', $title, '/admin/blog.php');
        }
    } else {
        $previewToken = bin2hex(random_bytes(16));
        $authorId = currentUserId();
        $requestedStatus = trim($_POST['article_status'] ?? '');
        if (!in_array($requestedStatus, ['draft', 'pending', 'published'], true)) {
            $requestedStatus = 'draft';
        }
        // Non-approvers cannot directly publish
        if ($requestedStatus === 'published' && !currentUserHasCapability('blog_approve')) {
            $requestedStatus = 'pending';
        }
        $status = $requestedStatus;
        $pdo->prepare(
            "INSERT INTO cms_articles
             (title, slug, perex, content, category_id, comments_enabled, is_featured_in_blog, image_file, publish_at, unpublish_at,
              meta_title, meta_description, preview_token, author_id, status, blog_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $title,
            $slug,
            $perex,
            $content,
            $categoryId,
            $commentsEnabled,
            $isFeaturedInBlog,
            $imageFile ?? '',
            $publishAtSql,
            $unpublishAtSql,
            $metaTitle,
            $metaDescription,
            $previewToken,
            $authorId,
            $status,
            $blogId,
        ]);
        $id = (int)$pdo->lastInsertId();
        logAction('article_add', "id={$id} title={$title} slug={$slug} status={$status}");
        if ($status === 'pending') {
            notifyPendingContent('Článek', $title, '/admin/blog.php');
        }
    }

    if ($isFeaturedInBlog === 1 && !$existingArticle) {
        $pdo->prepare(
            "UPDATE cms_articles
             SET is_featured_in_blog = 0
             WHERE blog_id = ? AND id <> ?"
        )->execute([$blogId, $id]);
    }

    $pdo->prepare("DELETE FROM cms_article_tags WHERE article_id = ?")->execute([$id]);
    if ($validTagIds !== []) {
        $insertTag = $pdo->prepare("INSERT IGNORE INTO cms_article_tags (article_id, tag_id) VALUES (?, ?)");
        foreach ($validTagIds as $tagId) {
            $insertTag->execute([$id, $tagId]);
        }
    }

    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    error_log('admin/blog_save: ' . $e->getMessage());
    header('Location: ' . $redirect);
    exit;
}

// Uvolnění zámku obsahu po úspěšném uložení
if ($id !== null) {
    releaseContentLock('article', $id);
}

header('Location: ' . $redirect);
exit;
