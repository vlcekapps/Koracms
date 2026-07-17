<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('blog')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$siteDesc = getSetting('site_description', '');
$perPage = max(1, (int)getSetting('blog_per_page', '10'));

$blogId = isset($_GET['blog_id']) ? (int)$_GET['blog_id'] : null;
$blog = $blogId !== null ? getBlogById($blogId) : ($GLOBALS['current_blog'] ?? getDefaultBlog());
if (!$blog) {
    $blog = getDefaultBlog();
}
$blogId = (int)$blog['id'];
$blogName = (string)$blog['name'];
$blogPages = [];
$blogSeries = [];

try {
    $blogPageStmt = $pdo->prepare(
        "SELECT id, title, slug, blog_id, blog_nav_order
         FROM cms_pages
         WHERE blog_id = ?
           AND deleted_at IS NULL
           AND status = 'published'
           AND is_published = 1
         ORDER BY blog_nav_order, title, id"
    );
    $blogPageStmt->execute([$blogId]);
    $blogPages = $blogPageStmt->fetchAll();
    foreach ($blogPages as &$blogPage) {
        $blogPage['item_type'] = 'page';
        $blogPage['sort_order'] = (int)($blogPage['blog_nav_order'] ?? 0);
        $blogPage['blog_slug'] = $blog['slug'];
    }
    unset($blogPage);
    foreach (loadNavigationLinks($pdo, $blogId, true) as $blogLink) {
        $blogLink['item_type'] = 'link';
        $blogLink['sort_order'] = (int)($blogLink['nav_order'] ?? 0);
        $blogPages[] = $blogLink;
    }
    usort($blogPages, static function (array $left, array $right): int {
        $leftOrder = (int)($left['sort_order'] ?? 0);
        $rightOrder = (int)($right['sort_order'] ?? 0);
        if ($leftOrder !== $rightOrder) {
            return $leftOrder <=> $rightOrder;
        }

        return strcasecmp((string)($left['title'] ?? ''), (string)($right['title'] ?? ''));
    });
} catch (\PDOException $e) {
    koraLog('warning', 'blog index pages query failed', ['blog_id' => $blogId, 'exception' => $e]);
}

$blogSeries = publicBlogSeries($pdo, $blogId, 6);

$katId = inputInt('get', 'kat');
$categoryPathSlug = blogCategorySlug(trim((string)($_GET['category_slug'] ?? '')));
$tagSlug = blogTagSlug(trim((string)($_GET['tag'] ?? '')));
$tagPathSlug = blogTagSlug(trim((string)($_GET['tag_slug'] ?? '')));
if ($tagPathSlug !== '') {
    $tagSlug = $tagPathSlug;
}
$authorSlug = authorSlug(trim((string)($_GET['autor'] ?? '')));
$searchQuery = trim((string)($_GET['q'] ?? ''));
$archiveFilter = normalizeBlogArchiveKey((string)($_GET['archiv'] ?? ''));

$taxonomyLandingColumnsAvailable = true;
try {
    $catStmt = $pdo->prepare(
        "SELECT id, name, slug, parent_id, description, meta_title, meta_description
         FROM cms_categories
         WHERE blog_id = ?
         ORDER BY name"
    );
    $catStmt->execute([$blogId]);
    $categories = $catStmt->fetchAll();
} catch (\PDOException $e) {
    $taxonomyLandingColumnsAvailable = false;
    koraLog('warning', 'blog index categories query failed', ['blog_id' => $blogId, 'exception' => $e]);
    $catStmt = $pdo->prepare("SELECT id, name, parent_id FROM cms_categories WHERE blog_id = ? ORDER BY name");
    $catStmt->execute([$blogId]);
    $categories = array_map(static function (array $category): array {
        $category['slug'] = '';
        $category['description'] = '';
        $category['meta_title'] = '';
        $category['meta_description'] = '';
        return $category;
    }, $catStmt->fetchAll());
}

$activeAuthor = $authorSlug !== '' ? fetchPublicAuthorBySlug($pdo, $authorSlug) : null;
$showAuthorsIndexLink = false;
$publicBlogs = isMultiBlog() ? getPublicBlogNavigationBlogs($blog) : [];
if (getSetting('blog_authors_index_enabled', '0') === '1') {
    try {
        $showAuthorsIndexLink = (int)$pdo->query(
            "SELECT COUNT(*) FROM cms_users WHERE author_public_enabled = 1 AND role != 'public'"
        )->fetchColumn() > 0;
    } catch (\PDOException $e) {
        $showAuthorsIndexLink = false;
    }
}

$allTags = [];
try {
    $tagStmt = $pdo->prepare(
        "SELECT id, name, slug, description, meta_title, meta_description
         FROM cms_tags
         WHERE blog_id = ?
         ORDER BY name"
    );
    $tagStmt->execute([$blogId]);
    $allTags = $tagStmt->fetchAll();
} catch (\PDOException $e) {
    koraLog('warning', 'blog index tags query failed', ['blog_id' => $blogId, 'exception' => $e]);
    try {
        $tagStmt = $pdo->prepare("SELECT id, name, slug FROM cms_tags WHERE blog_id = ? ORDER BY name");
        $tagStmt->execute([$blogId]);
        $allTags = array_map(static function (array $tag): array {
            $tag['description'] = '';
            $tag['meta_title'] = '';
            $tag['meta_description'] = '';
            return $tag;
        }, $tagStmt->fetchAll());
    } catch (\PDOException $fallbackException) {
        koraLog('warning', 'blog index tags fallback query failed', ['blog_id' => $blogId, 'exception' => $fallbackException]);
    }
}

$activeCategory = null;
if ($categoryPathSlug !== '') {
    foreach ($categories as $categoryRow) {
        if (blogCategorySlug((string)($categoryRow['slug'] ?? '')) === $categoryPathSlug) {
            $activeCategory = $categoryRow;
            $katId = (int)$categoryRow['id'];
            break;
        }
    }

    if ($activeCategory === null) {
        renderPublicNotFoundPage([
            'body_class' => 'page-not-found',
        ]);
    }
} elseif ($katId !== null) {
    foreach ($categories as $categoryRow) {
        if ((int)$categoryRow['id'] === $katId) {
            $activeCategory = $categoryRow;
            break;
        }
    }
}

$activeTag = null;
if ($tagSlug !== '') {
    foreach ($allTags as $tagRow) {
        if (blogTagSlug((string)($tagRow['slug'] ?? '')) === $tagSlug) {
            $activeTag = $tagRow;
            break;
        }
    }

    if ($tagPathSlug !== '' && $activeTag === null) {
        renderPublicNotFoundPage([
            'body_class' => 'page-not-found',
        ]);
    }
}

$blogArchives = [];
try {
    $archiveStmt = $pdo->prepare(
        "SELECT DATE_FORMAT(COALESCE(a.publish_at, a.created_at), '%Y-%m') AS archive_key,
                COUNT(*) AS item_count
         FROM cms_articles a
         WHERE a.status = 'published'
           AND a.deleted_at IS NULL
           AND (a.publish_at IS NULL OR a.publish_at <= NOW())
           AND a.blog_id = ?
         GROUP BY archive_key
         ORDER BY archive_key DESC"
    );
    $archiveStmt->execute([$blogId]);
    foreach ($archiveStmt->fetchAll() as $archiveRow) {
        $archiveKey = (string)($archiveRow['archive_key'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}$/', $archiveKey)) {
            continue;
        }
        $archiveDate = DateTimeImmutable::createFromFormat('Y-m', $archiveKey);
        $blogArchives[] = [
            'key' => $archiveKey,
            'label' => $archiveDate ? formatCzechMonthYear($archiveDate) : $archiveKey,
            'count' => (int)($archiveRow['item_count'] ?? 0),
        ];
    }
} catch (\PDOException $e) {
    koraLog('warning', 'blog index archives query failed', ['blog_id' => $blogId, 'exception' => $e]);
}

$where = "WHERE a.status = 'published' AND a.deleted_at IS NULL AND (a.publish_at IS NULL OR a.publish_at <= NOW()) AND a.blog_id = ?";
$params = [$blogId];
$categorySlugSelect = $taxonomyLandingColumnsAvailable ? 'c.slug AS category_slug' : "'' AS category_slug";

if ($katId !== null) {
    $catIds = categoryWithChildrenIds($pdo, $katId);
    $catPlaceholders = implode(',', array_fill(0, count($catIds), '?'));
    $where .= " AND a.category_id IN ({$catPlaceholders})";
    foreach ($catIds as $cid) {
        $params[] = $cid;
    }
}

if ($tagSlug !== '') {
    $where .= " AND EXISTS (
        SELECT 1 FROM cms_article_tags at2
        JOIN cms_tags t ON t.id = at2.tag_id
        WHERE at2.article_id = a.id AND t.blog_id = ? AND t.slug = ?
    )";
    $params[] = $blogId;
    $params[] = $tagSlug;
}

if ($authorSlug !== '') {
    if ($activeAuthor) {
        $where .= " AND a.author_id = ?";
        $params[] = (int)$activeAuthor['id'];
    } else {
        $where .= " AND 1 = 0";
    }
}

if ($searchQuery !== '') {
    $where .= " AND (a.title LIKE ? OR a.perex LIKE ? OR a.content LIKE ?)";
    $searchLike = '%' . $searchQuery . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

if ($archiveFilter !== '') {
    $archiveStart = $archiveFilter . '-01 00:00:00';
    $archiveEnd = date('Y-m-d H:i:s', strtotime($archiveStart . ' +1 month'));
    $where .= " AND COALESCE(a.publish_at, a.created_at) >= ? AND COALESCE(a.publish_at, a.created_at) < ?";
    $params[] = $archiveStart;
    $params[] = $archiveEnd;
}

$showFeaturedArticle = $katId === null
    && $tagSlug === ''
    && $authorSlug === ''
    && $searchQuery === ''
    && $archiveFilter === '';
$featuredArticle = null;
$featuredArticleId = 0;

if ($showFeaturedArticle) {
    try {
        $featuredStmt = $pdo->prepare(
            "SELECT a.id, a.title, a.slug, a.perex, a.content, a.image_file, a.created_at, a.category_id, a.blog_id,
                    c.name AS category, {$categorySlugSelect}, b.slug AS blog_slug,
                    a.view_count,
                    COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),'')) AS author_name,
                    u.author_public_enabled, u.author_slug, u.role AS author_role
             FROM cms_articles a
             LEFT JOIN cms_categories c ON c.id = a.category_id
             LEFT JOIN cms_blogs b ON b.id = a.blog_id
             LEFT JOIN cms_users u ON u.id = a.author_id
             WHERE a.status = 'published'
               AND a.deleted_at IS NULL
               AND (a.publish_at IS NULL OR a.publish_at <= NOW())
               AND a.blog_id = ?
               AND a.is_featured_in_blog = 1
             ORDER BY COALESCE(a.publish_at, a.created_at) DESC, a.id DESC
             LIMIT 1"
        );
        $featuredStmt->execute([$blogId]);
        $featuredArticle = $featuredStmt->fetch() ?: null;
        if ($featuredArticle) {
            $featuredArticle = hydrateAuthorPresentation($featuredArticle);
            $featuredArticleId = (int)$featuredArticle['id'];
            $where .= " AND a.id <> ?";
            $params[] = $featuredArticleId;
        }
    } catch (\PDOException $e) {
        koraLog('warning', 'blog index featured query failed', ['blog_id' => $blogId, 'exception' => $e]);
    }
}

$pag = paginate($pdo, "SELECT COUNT(*) FROM cms_articles a {$where}", $params, $perPage);
['totalPages' => $pages, 'page' => $page, 'offset' => $offset] = $pag;

$stmt = $pdo->prepare(
    "SELECT a.id, a.title, a.slug, a.perex, a.content, a.image_file, a.created_at, a.category_id, a.blog_id,
            c.name AS category, {$categorySlugSelect}, b.slug AS blog_slug,
            a.view_count,
            COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),'')) AS author_name,
            u.author_public_enabled, u.author_slug, u.role AS author_role
     FROM cms_articles a
     LEFT JOIN cms_categories c ON c.id = a.category_id
     LEFT JOIN cms_blogs b ON b.id = a.blog_id
     LEFT JOIN cms_users u ON u.id = a.author_id
     {$where}
     ORDER BY COALESCE(a.publish_at, a.created_at) DESC, a.id DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$articles = array_map(
    static fn (array $article): array => hydrateAuthorPresentation($article),
    $stmt->fetchAll()
);

$pageHeading = $blogName;
if ($activeCategory) {
    $pageHeading = $blogName . ' – ' . (string)$activeCategory['name'];
} elseif ($activeTag) {
    $pageHeading = $blogName . ' – #' . (string)$activeTag['name'];
} elseif ($activeAuthor) {
    $pageHeading = $blogName . ' – ' . $activeAuthor['author_display_name'];
} elseif ($archiveFilter !== '') {
    $archiveDate = DateTimeImmutable::createFromFormat('Y-m', $archiveFilter);
    $pageHeading = $blogName . ' – archiv ' . ($archiveDate ? formatCzechMonthYear($archiveDate) : $archiveFilter);
} elseif ($searchQuery !== '') {
    $pageHeading = $blogName . ' – hledání';
} elseif ($authorSlug !== '') {
    $pageHeading = $blogName . ' – autor';
}

$queryBase = [];
if ($searchQuery !== '') {
    $queryBase['q'] = $searchQuery;
}
if ($katId !== null && $activeCategory === null) {
    $queryBase['kat'] = $katId;
}
if ($tagSlug !== '' && $activeTag === null) {
    $queryBase['tag'] = $tagSlug;
}
if ($authorSlug !== '') {
    $queryBase['autor'] = $authorSlug;
}
if ($archiveFilter !== '') {
    $queryBase['archiv'] = $archiveFilter;
}

$blogIndexBase = blogIndexPath($blog);
if ($activeCategory) {
    $blogIndexBase = blogCategoryPath($blog, $activeCategory);
} elseif ($activeTag) {
    $blogIndexBase = blogTagPath($blog, $activeTag);
} elseif ($archiveFilter !== '') {
    $blogIndexBase = blogArchivePath($blog, $archiveFilter);
    unset($queryBase['archiv']);
}
$paginBase = $blogIndexBase . (str_contains($blogIndexBase, '?') ? '&' : '?');
if ($queryBase !== []) {
    $paginBase .= http_build_query($queryBase) . '&';
}

$blogMetaTitle = trim((string)($blog['meta_title'] ?? ''));
$blogMetaDescription = trim((string)($blog['meta_description'] ?? ''));
$metaTitle = $blogMetaTitle !== '' ? $blogMetaTitle : $blogName;
$metaDescription = $blogMetaDescription !== '' ? $blogMetaDescription : trim((string)($blog['description'] ?? ''));
$metaUrl = blogIndexUrl($blog);

if ($activeCategory) {
    $categoryMetaTitle = trim((string)($activeCategory['meta_title'] ?? ''));
    $categoryMetaDescription = trim((string)($activeCategory['meta_description'] ?? ''));
    $categoryDescription = trim((string)($activeCategory['description'] ?? ''));
    $metaTitle = $categoryMetaTitle !== '' ? $categoryMetaTitle : $pageHeading;
    $metaDescription = $categoryMetaDescription !== ''
        ? $categoryMetaDescription
        : ($categoryDescription !== '' ? normalizePlainText($categoryDescription) : $metaDescription);
    $metaUrl = blogCategoryUrl($blog, $activeCategory);
} elseif ($activeTag) {
    $tagMetaTitle = trim((string)($activeTag['meta_title'] ?? ''));
    $tagMetaDescription = trim((string)($activeTag['meta_description'] ?? ''));
    $tagDescription = trim((string)($activeTag['description'] ?? ''));
    $metaTitle = $tagMetaTitle !== '' ? $tagMetaTitle : $pageHeading;
    $metaDescription = $tagMetaDescription !== ''
        ? $tagMetaDescription
        : ($tagDescription !== '' ? normalizePlainText($tagDescription) : $metaDescription);
    $metaUrl = blogTagUrl($blog, $activeTag);
} elseif ($archiveFilter !== '') {
    $metaTitle = $pageHeading;
    $metaUrl = blogArchiveUrl($blog, $archiveFilter);
}

if ($searchQuery !== '' && !$activeCategory && !$activeTag) {
    $metaTitle = 'Hledání v blogu ' . $blogName;
}
if ($activeAuthor && !$activeCategory && !$activeTag) {
    $metaTitle = $pageHeading;
}
if ($metaDescription === '') {
    $metaDescription = $siteDesc !== '' ? $siteDesc : $blogName;
}

$blogLogoPath = blogLogoUrl($blog);
$blogMetaImage = $blogLogoPath !== '' ? siteUrl(str_replace(BASE_URL, '', $blogLogoPath)) : '';
$feedPath = blogFeedPath($blog);
$feedUrl = blogFeedUrl($blog);
$feedLinkLabel = 'RSS feed celého blogu';
$feedTitle = $blogName . ' – RSS feed';
$showFullBlogFeedLink = false;

if ($activeCategory) {
    $feedPath = blogCategoryFeedPath($blog, $activeCategory);
    $feedUrl = blogCategoryFeedUrl($blog, $activeCategory);
    $feedLinkLabel = 'RSS této kategorie';
    $feedTitle = $blogName . ' – kategorie ' . (string)$activeCategory['name'] . ' – RSS feed';
    $showFullBlogFeedLink = true;
} elseif ($activeTag) {
    $feedPath = blogTagFeedPath($blog, $activeTag);
    $feedUrl = blogTagFeedUrl($blog, $activeTag);
    $feedLinkLabel = 'RSS tohoto štítku';
    $feedTitle = $blogName . ' – štítek ' . (string)$activeTag['name'] . ' – RSS feed';
    $showFullBlogFeedLink = true;
}

$extraHeadHtml = '  <link rel="alternate" type="application/rss+xml" title="'
    . h($feedTitle) . '" href="' . h($feedUrl) . '">' . PHP_EOL;

renderPublicPage([
    'title' => $pageHeading . ' – ' . $siteName,
    'meta' => [
        'title' => $metaTitle . ' – ' . $siteName,
        'description' => $metaDescription,
        'image' => $blogMetaImage,
        'url' => $metaUrl,
        'type' => 'website',
    ],
    'extra_head_html' => $extraHeadHtml,
    'view' => 'modules/blog-index',
    'view_data' => [
        'pageHeading' => $pageHeading,
        'categories' => $categories,
        'allTags' => $allTags,
        'articles' => $articles,
        'featuredArticle' => $featuredArticle,
        'pages' => $pages,
        'page' => $page,
        'katId' => $katId,
        'tagSlug' => $tagSlug,
        'activeCategory' => $activeCategory,
        'activeTag' => $activeTag,
        'activeAuthor' => $activeAuthor,
        'showAuthorsIndexLink' => $showAuthorsIndexLink,
        'publicBlogs' => $publicBlogs,
        'paginBase' => $paginBase,
        'blog' => $blog,
        'searchQuery' => $searchQuery,
        'archiveFilter' => $archiveFilter,
        'blogArchives' => $blogArchives,
        'blogPages' => $blogPages,
        'blogSeries' => $blogSeries,
        'feedPath' => $feedPath,
        'feedLinkLabel' => $feedLinkLabel,
        'showFullBlogFeedLink' => $showFullBlogFeedLink,
    ],
    'current_nav' => 'blog:' . $blog['slug'],
    'body_class' => 'page-blog-index',
    'page_kind' => 'listing',
]);
