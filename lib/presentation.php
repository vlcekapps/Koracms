<?php

// Prezentační funkce – slugy, URL, excerpty, hydratace, autoři – extrahováno z db.php

// ──────────────────────── Multiblog helper funkce ────────────────────────────

/**
 * @return list<array<string, mixed>>
 */
function getAllBlogs(): array
{
    global $_CMS_BLOGS_CACHE;
    if (!isset($_CMS_BLOGS_CACHE)) {
        try {
            $_CMS_BLOGS_CACHE = db_connect()->query(
                "SELECT * FROM cms_blogs ORDER BY sort_order, name"
            )->fetchAll();
        } catch (\PDOException $e) {
            $_CMS_BLOGS_CACHE = [];
        }
    }
    return $_CMS_BLOGS_CACHE;
}

function clearBlogCache(): void
{
    global $_CMS_BLOGS_CACHE, $_CMS_BLOG_MEMBERSHIPS_ENABLED_CACHE, $_CMS_BLOG_MEMBERSHIP_CACHE, $_CMS_BLOG_MEMBERSHIP_BLOG_MAP_CACHE;
    unset($_CMS_BLOGS_CACHE, $_CMS_BLOG_MEMBERSHIPS_ENABLED_CACHE, $_CMS_BLOG_MEMBERSHIP_CACHE, $_CMS_BLOG_MEMBERSHIP_BLOG_MAP_CACHE);
}

function presentationLogFileDeleteFailure(string $scope, string $path): void
{
    koraLog('warning', 'presentation file delete failed', [
        'scope' => $scope,
        'filename' => basename($path),
        'path_hash' => hash('sha256', $path),
    ]);
}

/**
 * @return array<string, mixed>|null
 */
function getBlogById(int $id): ?array
{
    foreach (getAllBlogs() as $blog) {
        if ((int)$blog['id'] === $id) {
            return $blog;
        }
    }
    return null;
}

/**
 * @return array<string, mixed>|null
 */
function getBlogBySlug(string $slug): ?array
{
    foreach (getAllBlogs() as $blog) {
        if ((string)$blog['slug'] === $slug) {
            return $blog;
        }
    }
    return null;
}

/**
 * @return array<string, mixed>|null
 */
function getBlogByLegacySlug(string $slug): ?array
{
    $slug = slugify(trim($slug));
    if ($slug === '') {
        return null;
    }

    try {
        $stmt = db_connect()->prepare(
            "SELECT b.*
             FROM cms_blog_slug_redirects r
             INNER JOIN cms_blogs b ON b.id = r.blog_id
             WHERE r.old_slug = ?
             ORDER BY r.id DESC
             LIMIT 1"
        );
        $stmt->execute([$slug]);
        $blog = $stmt->fetch() ?: null;
        return $blog ?: null;
    } catch (\PDOException $e) {
        return null;
    }
}

/**
 * @return array<string, mixed>|null
 */
function getDefaultBlog(): ?array
{
    $blogs = getAllBlogs();
    return $blogs[0] ?? null;
}

function hasAnyBlogs(): bool
{
    return getAllBlogs() !== [];
}

function isMultiBlog(): bool
{
    return count(getAllBlogs()) > 1;
}

/**
 * @return array<string, string>
 */
function blogMembershipRoleDefinitions(): array
{
    return [
        'author' => 'Autor blogu',
        'manager' => 'Správce blogu',
    ];
}

function blogMembershipsEnabled(): bool
{
    global $_CMS_BLOG_MEMBERSHIPS_ENABLED_CACHE;
    if (!isset($_CMS_BLOG_MEMBERSHIPS_ENABLED_CACHE)) {
        try {
            $_CMS_BLOG_MEMBERSHIPS_ENABLED_CACHE = (int)db_connect()->query(
                "SELECT COUNT(*) FROM cms_blog_members"
            )->fetchColumn() > 0;
        } catch (\PDOException $e) {
            $_CMS_BLOG_MEMBERSHIPS_ENABLED_CACHE = false;
        }
    }

    return $_CMS_BLOG_MEMBERSHIPS_ENABLED_CACHE;
}

/**
 * @return array<int, true>
 */
function getBlogsWithExplicitMembers(): array
{
    global $_CMS_BLOG_MEMBERSHIP_BLOG_MAP_CACHE;

    if (!isset($_CMS_BLOG_MEMBERSHIP_BLOG_MAP_CACHE)) {
        $_CMS_BLOG_MEMBERSHIP_BLOG_MAP_CACHE = [];
        try {
            $rows = db_connect()->query(
                "SELECT DISTINCT blog_id
                 FROM cms_blog_members
                 WHERE blog_id IS NOT NULL"
            )->fetchAll();
            foreach ($rows as $row) {
                $blogId = (int)($row['blog_id'] ?? 0);
                if ($blogId > 0) {
                    $_CMS_BLOG_MEMBERSHIP_BLOG_MAP_CACHE[$blogId] = true;
                }
            }
        } catch (\PDOException $e) {
            $_CMS_BLOG_MEMBERSHIP_BLOG_MAP_CACHE = [];
        }
    }

    return $_CMS_BLOG_MEMBERSHIP_BLOG_MAP_CACHE;
}

function blogHasExplicitMembers(int $blogId): bool
{
    if ($blogId <= 0) {
        return false;
    }

    $blogMap = getBlogsWithExplicitMembers();
    return isset($blogMap[$blogId]);
}

/**
 * @return list<array{blog_id:mixed, member_role:mixed}>
 */
function getUserBlogMemberships(?int $userId = null): array
{
    global $_CMS_BLOG_MEMBERSHIP_CACHE;

    $userId = $userId ?? currentUserId();
    if ($userId === null || $userId <= 0) {
        return [];
    }

    if (!isset($_CMS_BLOG_MEMBERSHIP_CACHE)) {
        $_CMS_BLOG_MEMBERSHIP_CACHE = [];
    }

    if (!array_key_exists($userId, $_CMS_BLOG_MEMBERSHIP_CACHE)) {
        try {
            $stmt = db_connect()->prepare(
                "SELECT blog_id, member_role
                 FROM cms_blog_members
                 WHERE user_id = ?
                 ORDER BY blog_id"
            );
            $stmt->execute([$userId]);
            $_CMS_BLOG_MEMBERSHIP_CACHE[$userId] = $stmt->fetchAll();
        } catch (\PDOException $e) {
            $_CMS_BLOG_MEMBERSHIP_CACHE[$userId] = [];
        }
    }

    return $_CMS_BLOG_MEMBERSHIP_CACHE[$userId];
}

/**
 * @return array<int, string>
 */
function getUserBlogMembershipMap(?int $userId = null): array
{
    $map = [];
    foreach (getUserBlogMemberships($userId) as $membership) {
        $map[(int)($membership['blog_id'] ?? 0)] = (string)($membership['member_role'] ?? 'author');
    }

    return $map;
}

/**
 * @return list<array<string, mixed>>
 */
function getWritableBlogsForUser(?int $userId = null): array
{
    $userId = $userId ?? currentUserId();
    if ($userId === null || $userId <= 0) {
        return [];
    }

    if (currentUserHasCapability('blog_manage_all')) {
        return getAllBlogs();
    }

    if (!currentUserHasCapability('blog_manage_own')) {
        return [];
    }

    if (!blogMembershipsEnabled()) {
        return getAllBlogs();
    }

    $explicitMembershipBlogs = getBlogsWithExplicitMembers();
    $memberships = getUserBlogMembershipMap($userId);
    return array_values(array_filter(
        getAllBlogs(),
        static function (array $blog) use ($explicitMembershipBlogs, $memberships): bool {
            $blogId = (int)($blog['id'] ?? 0);
            if ($blogId <= 0) {
                return false;
            }

            if (!isset($explicitMembershipBlogs[$blogId])) {
                return true;
            }

            return isset($memberships[$blogId]);
        }
    ));
}

/**
 * @return list<array<string, mixed>>
 */
function getTaxonomyManagedBlogsForUser(?int $userId = null): array
{
    $userId = $userId ?? currentUserId();
    if ($userId === null || $userId <= 0) {
        return [];
    }

    if (currentUserHasCapability('blog_taxonomies_manage')) {
        return getAllBlogs();
    }

    if (!blogMembershipsEnabled()) {
        return [];
    }

    $explicitMembershipBlogs = getBlogsWithExplicitMembers();
    if ($explicitMembershipBlogs === []) {
        return [];
    }

    $memberships = getUserBlogMembershipMap($userId);
    if ($memberships === []) {
        return [];
    }

    return array_values(array_filter(
        getAllBlogs(),
        static function (array $blog) use ($memberships): bool {
            $blogId = (int)($blog['id'] ?? 0);
            return blogHasExplicitMembers($blogId) && ($memberships[$blogId] ?? '') === 'manager';
        }
    ));
}

function canCurrentUserWriteToBlog(int $blogId): bool
{
    if ($blogId <= 0 || !getBlogById($blogId)) {
        return false;
    }

    if (currentUserHasCapability('blog_manage_all')) {
        return true;
    }

    if (!currentUserHasCapability('blog_manage_own')) {
        return false;
    }

    if (!blogMembershipsEnabled()) {
        return true;
    }

    if (!blogHasExplicitMembers($blogId)) {
        return true;
    }

    $memberships = getUserBlogMembershipMap();
    return isset($memberships[$blogId]);
}

function canCurrentUserManageBlogTaxonomies(int $blogId): bool
{
    if ($blogId <= 0 || !getBlogById($blogId)) {
        return false;
    }

    if (currentUserHasCapability('blog_taxonomies_manage')) {
        return true;
    }

    if (!blogMembershipsEnabled()) {
        return false;
    }

    if (!blogHasExplicitMembers($blogId)) {
        return false;
    }

    $memberships = getUserBlogMembershipMap();
    return ($memberships[$blogId] ?? '') === 'manager';
}

function canCurrentUserManageAnyBlogTaxonomies(): bool
{
    if (currentUserHasCapability('blog_taxonomies_manage')) {
        return hasAnyBlogs();
    }

    foreach (getUserBlogMembershipMap() as $memberRole) {
        if ($memberRole === 'manager') {
            return true;
        }
    }

    return false;
}

/**
 * Načte články blogu, se kterými může aktuální uživatel pracovat v rámci přesunu.
 *
 * @param int[] $ids
 * @return array<int, array<string, mixed>>
 */
function loadTransferableBlogArticles(PDO $pdo, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    if ($ids === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT a.id, a.title, a.blog_id, a.category_id, a.author_id, a.is_featured_in_blog,
                b.name AS blog_name,
                c.name AS category_name
         FROM cms_articles a
         LEFT JOIN cms_blogs b ON b.id = a.blog_id
         LEFT JOIN cms_categories c ON c.id = a.category_id
         WHERE a.deleted_at IS NULL
           AND a.id IN ({$placeholders})"
    );
    $stmt->execute($ids);
    $articles = $stmt->fetchAll() ?: [];

    if (!canManageOwnBlogOnly()) {
        return $articles;
    }

    return array_values(array_filter(
        $articles,
        static function (array $article): bool {
            return (int)($article['author_id'] ?? 0) === (int)(currentUserId() ?? 0)
                && canCurrentUserWriteToBlog((int)($article['blog_id'] ?? 0));
        }
    ));
}

function normalizeBlogTaxonomyName(string $value): string
{
    $normalized = preg_replace('/\s+/u', ' ', trim($value));
    if ($normalized === null) {
        $normalized = trim($value);
    }

    return function_exists('mb_strtolower')
        ? mb_strtolower($normalized, 'UTF-8')
        : strtolower($normalized);
}

/**
 * @param array<int, array<string, mixed>> $categories
 * @return array<string, array{id:int,name:string}>
 */
function blogCategoryLookupByNormalizedName(array $categories): array
{
    $map = [];
    foreach ($categories as $category) {
        $name = trim((string)($category['name'] ?? ''));
        $categoryId = (int)($category['id'] ?? 0);
        if ($categoryId <= 0 || $name === '') {
            continue;
        }

        $normalizedName = normalizeBlogTaxonomyName($name);
        if (!isset($map[$normalizedName])) {
            $map[$normalizedName] = [
                'id' => $categoryId,
                'name' => $name,
            ];
        }
    }

    return $map;
}

/**
 * @param array<int, array<string, mixed>> $tags
 * @return array{by_slug: array<string, array{id:int,name:string,slug:string}>, by_name: array<string, array{id:int,name:string,slug:string}>}
 */
function blogTagLookupMaps(array $tags): array
{
    $bySlug = [];
    $byName = [];

    foreach ($tags as $tag) {
        $tagId = (int)($tag['id'] ?? 0);
        $tagName = trim((string)($tag['name'] ?? ''));
        $tagSlug = trim((string)($tag['slug'] ?? ''));
        if ($tagId <= 0 || ($tagName === '' && $tagSlug === '')) {
            continue;
        }

        $tagData = [
            'id' => $tagId,
            'name' => $tagName,
            'slug' => $tagSlug,
        ];

        if ($tagSlug !== '' && !isset($bySlug[$tagSlug])) {
            $bySlug[$tagSlug] = $tagData;
        }

        if ($tagName !== '') {
            $normalizedName = normalizeBlogTaxonomyName($tagName);
            if (!isset($byName[$normalizedName])) {
                $byName[$normalizedName] = $tagData;
            }
        }
    }

    return [
        'by_slug' => $bySlug,
        'by_name' => $byName,
    ];
}

/**
 * @param array<int, array{id?:mixed,name?:mixed,slug?:mixed}> $sourceTags
 * @param array<int, array<string, mixed>> $targetCategories
 * @param array<int, array<string, mixed>> $targetTags
 * @return array{
 *   matched_category_id:?int,
 *   matched_tag_ids: array<int>,
 *   missing_category_name:string,
 *   missing_tags: array<int, array{name:string,slug:string}>
 * }
 */
function resolveArticleMoveTaxonomyState(
    string $sourceCategoryName,
    array $sourceTags,
    array $targetCategories,
    array $targetTags
): array {
    $matchedCategoryId = null;
    $missingCategoryName = '';
    $matchedTagIds = [];
    $missingTags = [];

    $normalizedSourceCategory = normalizeBlogTaxonomyName($sourceCategoryName);
    if ($normalizedSourceCategory !== '') {
        $targetCategoryLookup = blogCategoryLookupByNormalizedName($targetCategories);
        if (isset($targetCategoryLookup[$normalizedSourceCategory]['id'])) {
            $matchedCategoryId = (int)$targetCategoryLookup[$normalizedSourceCategory]['id'];
        } else {
            $missingCategoryName = trim($sourceCategoryName);
        }
    }

    $targetTagLookup = blogTagLookupMaps($targetTags);
    foreach ($sourceTags as $sourceTag) {
        $sourceTagName = trim((string)($sourceTag['name'] ?? ''));
        $sourceTagSlug = trim((string)($sourceTag['slug'] ?? ''));
        if ($sourceTagName === '' && $sourceTagSlug === '') {
            continue;
        }

        $matchedTargetTag = null;
        if ($sourceTagSlug !== '' && isset($targetTagLookup['by_slug'][$sourceTagSlug])) {
            $matchedTargetTag = $targetTagLookup['by_slug'][$sourceTagSlug];
        } elseif ($sourceTagName !== '') {
            $normalizedSourceTagName = normalizeBlogTaxonomyName($sourceTagName);
            if (isset($targetTagLookup['by_name'][$normalizedSourceTagName])) {
                $matchedTargetTag = $targetTagLookup['by_name'][$normalizedSourceTagName];
            }
        }

        $matchedTargetTagId = (int)($matchedTargetTag['id'] ?? 0);
        if ($matchedTargetTagId > 0) {
            if (!in_array($matchedTargetTagId, $matchedTagIds, true)) {
                $matchedTagIds[] = $matchedTargetTagId;
            }
            continue;
        }

        if ($sourceTagName !== '') {
            $missingKey = $sourceTagSlug !== ''
                ? 'slug:' . $sourceTagSlug
                : 'name:' . normalizeBlogTaxonomyName($sourceTagName);
            if (!isset($missingTags[$missingKey])) {
                $missingTags[$missingKey] = [
                    'name' => $sourceTagName,
                    'slug' => $sourceTagSlug,
                ];
            }
        }
    }

    return [
        'matched_category_id' => $matchedCategoryId,
        'matched_tag_ids' => $matchedTagIds,
        'missing_category_name' => $missingCategoryName,
        'missing_tags' => array_values($missingTags),
    ];
}

/**
 * @return array<int, array{id:int,name:string,slug:string}>
 */
function loadArticleTagDetails(PDO $pdo, int $articleId): array
{
    if ($articleId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT t.id, t.name, t.slug
         FROM cms_article_tags at
         INNER JOIN cms_tags t ON t.id = at.tag_id
         WHERE at.article_id = ?
         ORDER BY t.name ASC, t.id ASC"
    );
    $stmt->execute([$articleId]);

    return array_map(
        static function (array $row): array {
            return [
                'id' => (int)($row['id'] ?? 0),
                'name' => (string)($row['name'] ?? ''),
                'slug' => (string)($row['slug'] ?? ''),
            ];
        },
        $stmt->fetchAll() ?: []
    );
}

/**
 * @return list<array<string, mixed>>
 */
function getBlogMembers(int $blogId): array
{
    if ($blogId <= 0) {
        return [];
    }

    try {
        $stmt = db_connect()->prepare(
            "SELECT m.blog_id, m.user_id, m.member_role,
                    u.email, u.first_name, u.last_name, u.nickname, u.role, u.is_superadmin
             FROM cms_blog_members m
             INNER JOIN cms_users u ON u.id = m.user_id
             WHERE m.blog_id = ?
             ORDER BY m.member_role DESC, COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email)"
        );
        $stmt->execute([$blogId]);
        return $stmt->fetchAll();
    } catch (\PDOException $e) {
        return [];
    }
}

/**
 * @param array<string, mixed>|null $currentBlog
 * @return list<array<string, mixed>>
 */
function getPublicBlogNavigationBlogs(?array $currentBlog = null): array
{
    $currentBlogId = (int)($currentBlog['id'] ?? 0);
    $visibleBlogs = [];

    foreach (getAllBlogs() as $blog) {
        $blogId = (int)($blog['id'] ?? 0);
        $showInNav = (int)($blog['show_in_nav'] ?? 1) === 1;
        if (!$showInNav && $blogId !== $currentBlogId) {
            continue;
        }
        $visibleBlogs[] = $blog;
    }

    return $visibleBlogs;
}

/**
 * @param array<string, mixed> $article
 */
function articleBlogSlug(array $article): string
{
    if (!empty($article['blog_slug'])) {
        return (string)$article['blog_slug'];
    }
    $blogId = (int)($article['blog_id'] ?? 1);
    $blog = getBlogById($blogId);
    return $blog ? (string)$blog['slug'] : 'blog';
}

/**
 * @param array<string, mixed> $blog
 */
function blogIndexPath(array $blog): string
{
    $slug = (string)($blog['slug'] ?? 'blog');
    if ($slug === 'blog') {
        return BASE_URL . '/blog/index.php';
    }
    return BASE_URL . '/' . rawurlencode($slug) . '/';
}

/**
 * @param array<string, mixed> $blog
 */
function blogIndexUrl(array $blog): string
{
    $path = (string)($blog['slug'] ?? 'blog');
    if ($path === 'blog') {
        return siteUrl('/blog/index.php');
    }
    return siteUrl('/' . rawurlencode($path) . '/');
}

/**
 * @param array<string, mixed> $blog
 */
function blogFeedRequestPath(array $blog, string $categorySlug = '', string $tagSlug = ''): string
{
    $blogSlug = blogTaxonomySlug((string)($blog['slug'] ?? 'blog'));
    $categorySlug = blogCategorySlug($categorySlug);
    $tagSlug = blogTagSlug($tagSlug);
    $query = ['blog' => $blogSlug !== '' ? $blogSlug : 'blog'];
    if ($categorySlug !== '') {
        $query['category'] = $categorySlug;
    } elseif ($tagSlug !== '') {
        $query['tag'] = $tagSlug;
    }

    return appendUrlQuery(BASE_URL . '/feed.php', $query);
}

/**
 * @param array<string, mixed> $blog
 */
function blogFeedPath(array $blog, string $categorySlug = '', string $tagSlug = ''): string
{
    return blogFeedRequestPath($blog, $categorySlug, $tagSlug);
}

/**
 * @param array<string, mixed> $blog
 */
function blogFeedUrl(array $blog, string $categorySlug = '', string $tagSlug = ''): string
{
    $blogSlug = blogTaxonomySlug((string)($blog['slug'] ?? 'blog'));
    $categorySlug = blogCategorySlug($categorySlug);
    $tagSlug = blogTagSlug($tagSlug);
    $query = ['blog' => $blogSlug !== '' ? $blogSlug : 'blog'];
    if ($categorySlug !== '') {
        $query['category'] = $categorySlug;
    } elseif ($tagSlug !== '') {
        $query['tag'] = $tagSlug;
    }

    return appendUrlQuery(siteUrl('/feed.php'), $query);
}

/**
 * @param array<string, mixed> $blog
 * @param array<string, mixed> $category
 */
function blogCategoryFeedPath(array $blog, array $category): string
{
    return blogFeedPath($blog, blogCategorySlug((string)($category['slug'] ?? '')));
}

/**
 * @param array<string, mixed> $blog
 * @param array<string, mixed> $category
 */
function blogCategoryFeedUrl(array $blog, array $category): string
{
    return blogFeedUrl($blog, blogCategorySlug((string)($category['slug'] ?? '')));
}

/**
 * @param array<string, mixed> $blog
 * @param array<string, mixed> $tag
 */
function blogTagFeedPath(array $blog, array $tag): string
{
    return blogFeedPath($blog, '', blogTagSlug((string)($tag['slug'] ?? '')));
}

/**
 * @param array<string, mixed> $blog
 * @param array<string, mixed> $tag
 */
function blogTagFeedUrl(array $blog, array $tag): string
{
    return blogFeedUrl($blog, '', blogTagSlug((string)($tag['slug'] ?? '')));
}

function normalizeBlogArchiveKey(string $archiveKey): string
{
    $archiveKey = trim($archiveKey);
    if (preg_match('/^(\d{4})-(\d{2})$/', $archiveKey, $matches) !== 1) {
        return '';
    }

    $year = (int)$matches[1];
    $month = (int)$matches[2];
    if ($year < 1000 || $year > 9999 || $month < 1 || $month > 12) {
        return '';
    }

    return sprintf('%04d-%02d', $year, $month);
}

/**
 * @param array<string, mixed> $blog
 * @param array<string, mixed> $query
 */
function blogArchiveRequestPath(array $blog, string $archiveKey, array $query = []): string
{
    $normalizedKey = normalizeBlogArchiveKey($archiveKey);
    if ($normalizedKey === '') {
        return appendUrlQuery('/blog/index.php', $query);
    }

    [$year, $month] = explode('-', $normalizedKey, 2);
    $blogSlug = blogTaxonomySlug((string)($blog['slug'] ?? 'blog'));
    if ($blogSlug === '') {
        $blogSlug = 'blog';
    }

    return appendUrlQuery(
        '/' . rawurlencode($blogSlug) . '/archiv/' . rawurlencode($year) . '/' . rawurlencode($month),
        $query
    );
}

/**
 * @param array<string, mixed> $blog
 * @param array<string, mixed> $query
 */
function blogArchivePath(array $blog, string $archiveKey, array $query = []): string
{
    return BASE_URL . blogArchiveRequestPath($blog, $archiveKey, $query);
}

/**
 * @param array<string, mixed> $blog
 * @param array<string, mixed> $query
 */
function blogArchiveUrl(array $blog, string $archiveKey, array $query = []): string
{
    return siteUrl(blogArchiveRequestPath($blog, $archiveKey, $query));
}

/**
 * @param array<string, mixed> $blog
 * @param array<string, mixed> $series
 */
function blogSeriesPath(array $blog, array $series): string
{
    $blogSlug = (string)($blog['slug'] ?? ($series['blog_slug'] ?? 'blog'));
    $seriesSlug = blogSeriesSlug((string)($series['slug'] ?? ''));
    if ($blogSlug === 'blog') {
        return BASE_URL . '/blog/series.php?slug=' . rawurlencode($seriesSlug);
    }

    return BASE_URL . '/' . rawurlencode($blogSlug) . '/serie/' . rawurlencode($seriesSlug);
}

/**
 * @param array<string, mixed> $blog
 * @param array<string, mixed> $series
 */
function blogSeriesUrl(array $blog, array $series): string
{
    $blogSlug = (string)($blog['slug'] ?? ($series['blog_slug'] ?? 'blog'));
    $seriesSlug = blogSeriesSlug((string)($series['slug'] ?? ''));
    if ($blogSlug === 'blog') {
        return siteUrl('/blog/series.php?slug=' . rawurlencode($seriesSlug));
    }

    return siteUrl('/' . rawurlencode($blogSlug) . '/serie/' . rawurlencode($seriesSlug));
}

function blogTaxonomySlug(string $value): string
{
    return articleSlug($value);
}

function blogCategorySlug(string $value): string
{
    return blogTaxonomySlug($value);
}

function blogTagSlug(string $value): string
{
    return blogTaxonomySlug($value);
}

/**
 * @param array<int, string> $existingSlugs
 */
function nextBlogTaxonomySlug(string $candidate, array $existingSlugs, string $fallback): string
{
    $base = blogTaxonomySlug($candidate);
    if ($base === '') {
        $base = blogTaxonomySlug($fallback);
    }
    if ($base === '') {
        $base = 'polozka';
    }

    $used = [];
    foreach ($existingSlugs as $existingSlug) {
        $normalized = blogTaxonomySlug((string)$existingSlug);
        if ($normalized !== '') {
            $used[$normalized] = true;
        }
    }

    $slug = $base;
    $suffix = 2;
    while (isset($used[$slug])) {
        $slug = $base . '-' . $suffix;
        $suffix++;
    }

    return $slug;
}

function uniqueBlogCategorySlug(PDO $pdo, string $candidate, int $blogId, ?int $excludeId = null): string
{
    $base = blogCategorySlug($candidate);
    if ($base === '') {
        $base = 'kategorie';
    }

    $slug = $base;
    $suffix = 2;
    while (true) {
        $params = [$blogId, $slug];
        $sql = "SELECT id FROM cms_categories WHERE blog_id = ? AND slug = ?";
        if ($excludeId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $excludeId;
        }
        $sql .= " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetch()) {
            return $slug;
        }

        $slug = $base . '-' . $suffix;
        $suffix++;
    }
}

function uniqueBlogTagSlug(PDO $pdo, string $candidate, int $blogId, ?int $excludeId = null): string
{
    $base = blogTagSlug($candidate);
    if ($base === '') {
        $base = 'stitek';
    }

    $slug = $base;
    $suffix = 2;
    while (true) {
        $params = [$blogId, $slug];
        $sql = "SELECT id FROM cms_tags WHERE blog_id = ? AND slug = ?";
        if ($excludeId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $excludeId;
        }
        $sql .= " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetch()) {
            return $slug;
        }

        $slug = $base . '-' . $suffix;
        $suffix++;
    }
}

/**
 * @param array<string, mixed> $blog
 * @param array<string, mixed> $category
 * @param array<string, mixed> $query
 */
function blogCategoryRequestPath(array $blog, array $category, array $query = []): string
{
    $blogSlug = blogTaxonomySlug((string)($blog['slug'] ?? ($category['blog_slug'] ?? 'blog')));
    if ($blogSlug === '') {
        $blogSlug = 'blog';
    }
    $categorySlug = blogCategorySlug((string)($category['slug'] ?? ''));
    if ($categorySlug === '') {
        return appendUrlQuery('/blog/index.php', ['kat' => (int)($category['id'] ?? 0)] + $query);
    }

    return appendUrlQuery('/' . rawurlencode($blogSlug) . '/kategorie/' . rawurlencode($categorySlug), $query);
}

/**
 * @param array<string, mixed> $blog
 * @param array<string, mixed> $category
 * @param array<string, mixed> $query
 */
function blogCategoryPath(array $blog, array $category, array $query = []): string
{
    return BASE_URL . blogCategoryRequestPath($blog, $category, $query);
}

/**
 * @param array<string, mixed> $blog
 * @param array<string, mixed> $category
 * @param array<string, mixed> $query
 */
function blogCategoryUrl(array $blog, array $category, array $query = []): string
{
    return siteUrl(blogCategoryRequestPath($blog, $category, $query));
}

/**
 * @param array<string, mixed> $blog
 * @param array<string, mixed> $tag
 * @param array<string, mixed> $query
 */
function blogTagRequestPath(array $blog, array $tag, array $query = []): string
{
    $blogSlug = blogTaxonomySlug((string)($blog['slug'] ?? ($tag['blog_slug'] ?? 'blog')));
    if ($blogSlug === '') {
        $blogSlug = 'blog';
    }
    $tagSlug = blogTagSlug((string)($tag['slug'] ?? ''));
    if ($tagSlug === '') {
        return appendUrlQuery('/blog/index.php', ['tag' => (string)($tag['slug'] ?? '')] + $query);
    }

    return appendUrlQuery('/' . rawurlencode($blogSlug) . '/stitky/' . rawurlencode($tagSlug), $query);
}

/**
 * @param array<string, mixed> $blog
 * @param array<string, mixed> $tag
 * @param array<string, mixed> $query
 */
function blogTagPath(array $blog, array $tag, array $query = []): string
{
    return BASE_URL . blogTagRequestPath($blog, $tag, $query);
}

/**
 * @param array<string, mixed> $blog
 * @param array<string, mixed> $tag
 * @param array<string, mixed> $query
 */
function blogTagUrl(array $blog, array $tag, array $query = []): string
{
    return siteUrl(blogTagRequestPath($blog, $tag, $query));
}

function saveBlogSlugRedirect(PDO $pdo, int $blogId, string $oldSlug): void
{
    $oldSlug = slugify(trim($oldSlug));
    if ($blogId <= 0 || $oldSlug === '') {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO cms_blog_slug_redirects (blog_id, old_slug)
             VALUES (?, ?)"
        );
        $stmt->execute([$blogId, $oldSlug]);
    } catch (\PDOException $e) {
        koraLog('warning', 'blog slug redirect save failed', [
            'blog_id' => $blogId,
            'old_slug' => $oldSlug,
            'exception' => $e,
        ]);
    }
}

/**
 * @return list<string>
 */
function reservedBlogSlugs(): array
{
    return [
        'admin', 'auth', 'authors', 'author', 'board', 'build', 'chat',
        'contact', 'dist', 'docs', 'downloads', 'events', 'faq', 'feed',
        'food', 'forms', 'gallery', 'lib', 'news', 'places', 'podcast',
        'polls', 'public', 'reservations', 'search', 'sitemap', 'themes',
        'uploads', 'index', 'page', 'register', 'subscribe', 'unsubscribe',
    ];
}

function formatCzechDate(string $datetime): string
{
    if (trim($datetime) === '') {
        return '';
    }
    static $months = [
        '', 'ledna', 'února', 'března', 'dubna', 'května', 'června',
        'července', 'srpna', 'září', 'října', 'listopadu', 'prosince',
    ];
    try {
        $dt = new \DateTime($datetime);
    } catch (\Exception $e) {
        return h($datetime);
    }
    return $dt->format('j') . '. ' . $months[(int)$dt->format('n')]
         . ' ' . $dt->format('Y, H:i');
}

function formatCzechDateTime(string $datetime): string
{
    return formatCzechDate($datetime);
}

function formatCzechMonthYear(\DateTimeInterface $date): string
{
    static $months = [
        '', 'leden', 'únor', 'březen', 'duben', 'květen', 'červen',
        'červenec', 'srpen', 'září', 'říjen', 'listopad', 'prosinec',
    ];

    return $months[(int)$date->format('n')] . ' ' . $date->format('Y');
}

/**
 * Odhadne dobu čtení textu v minutách (průměr 200 slov/min pro češtinu).
 */
function readingTime(string $text): int
{
    $plain = strip_tags($text);
    $words = preg_match_all('/\S+/u', $plain);
    return max(1, (int)round($words / 200));
}

function articleReadingMeta(string $text, int $viewCount): string
{
    return 'přibližná doba čtení ' . readingTime($text) . ' min, přečteno ' . max(0, $viewCount) . ' krát';
}

function blogArticleHeadingAttribute(string $attributes, string $name): ?string
{
    $pattern = '/\s' . preg_quote($name, '/') . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i';
    if (preg_match($pattern, $attributes, $matches) !== 1) {
        return null;
    }

    return html_entity_decode((string)($matches[1] ?? $matches[2] ?? $matches[3] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function blogArticleHeadingIsVisibleForToc(string $attributes): bool
{
    $class = blogArticleHeadingAttribute($attributes, 'class') ?? '';
    if (preg_match('/(?:^|\s)sr-only(?:\s|$)/i', $class) === 1) {
        return false;
    }

    if (preg_match('/\shidden(?:\s|=|$)/i', $attributes) === 1) {
        return false;
    }

    return strtolower(blogArticleHeadingAttribute($attributes, 'aria-hidden') ?? '') !== 'true';
}

function blogArticleHeadingSetId(string $attributes, string $id): string
{
    $idAttribute = ' id="' . h($id) . '"';
    if (preg_match('/\sid\s*=/i', $attributes) !== 1) {
        return rtrim($attributes) . $idAttribute;
    }

    return preg_replace(
        '/\sid\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i',
        $idAttribute,
        $attributes,
        1
    ) ?? $attributes;
}

/**
 * @return array{html:string,items:list<array{level:int,id:string,title:string}>}
 */
function buildBlogArticleTableOfContents(string $html): array
{
    if ($html === '' || preg_match('/<h[23]\b/i', $html) !== 1) {
        return [
            'html' => $html,
            'items' => [],
        ];
    }

    /** @var array<string,true> $reservedIds */
    $reservedIds = [];
    if (preg_match_all('/\sid\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $html, $idMatches, PREG_SET_ORDER) >= 1) {
        foreach ($idMatches as $idMatch) {
            $reservedId = trim(html_entity_decode((string)($idMatch[1] ?? $idMatch[2] ?? $idMatch[3] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($reservedId !== '') {
                $reservedIds[$reservedId] = true;
            }
        }
    }

    /** @var list<array{level:int,id:string,title:string}> $items */
    $items = [];
    $generatedIds = $reservedIds;
    $processedHtml = preg_replace_callback(
        '/<h([23])\b([^>]*)>(.*?)<\/h\1>/is',
        static function (array $matches) use (&$items, &$generatedIds): string {
            $level = (int)$matches[1];
            $attributes = (string)$matches[2];
            $innerHtml = (string)$matches[3];
            $title = normalizePlainText(html_entity_decode(strip_tags($innerHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ($title === '' || !blogArticleHeadingIsVisibleForToc($attributes)) {
                return (string)$matches[0];
            }

            $headingId = trim((string)(blogArticleHeadingAttribute($attributes, 'id') ?? ''));
            if ($headingId === '') {
                $baseId = slugify($title);
                if ($baseId === '') {
                    $baseId = 'cast';
                }

                $headingId = $baseId;
                $suffix = 2;
                while (isset($generatedIds[$headingId])) {
                    $headingId = $baseId . '-' . $suffix;
                    $suffix++;
                }
                $generatedIds[$headingId] = true;
                $attributes = blogArticleHeadingSetId($attributes, $headingId);
            }

            $items[] = [
                'level' => $level,
                'id' => $headingId,
                'title' => $title,
            ];

            return '<h' . $level . $attributes . '>' . $innerHtml . '</h' . $level . '>';
        },
        $html
    );

    return [
        'html' => $processedHtml ?? $html,
        'items' => $items,
    ];
}

// ─────────────────────────────── Statické stránky ────────────────────────

/**
 * Převede text na URL slug (podporuje českou diakritiku).
 */
function slugify(string $text): string
{
    $map = [
        'á' => 'a','č' => 'c','ď' => 'd','é' => 'e','ě' => 'e','í' => 'i','ň' => 'n',
        'ó' => 'o','ř' => 'r','š' => 's','ť' => 't','ú' => 'u','ů' => 'u','ý' => 'y','ž' => 'z',
        'Á' => 'a','Č' => 'c','Ď' => 'd','É' => 'e','Ě' => 'e','Í' => 'i','Ň' => 'n',
        'Ó' => 'o','Ř' => 'r','Š' => 's','Ť' => 't','Ú' => 'u','Ů' => 'u','Ý' => 'y','Ž' => 'z',
        // slovenština
        'ľ' => 'l','Ľ' => 'l','ŕ' => 'r','Ŕ' => 'r','ĺ' => 'l','Ĺ' => 'l','ô' => 'o','Ô' => 'o',
        // polština
        'ą' => 'a','Ą' => 'a','ć' => 'c','Ć' => 'c','ę' => 'e','Ę' => 'e','ł' => 'l','Ł' => 'l',
        'ń' => 'n','Ń' => 'n','ś' => 's','Ś' => 's','ź' => 'z','Ź' => 'z','ż' => 'z','Ż' => 'z',
        // němčina
        'ä' => 'ae','Ä' => 'ae','ö' => 'oe','Ö' => 'oe','ü' => 'ue','Ü' => 'ue','ß' => 'ss',
    ];
    $text = strtr($text, $map);
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

function articleSlug(string $value): string
{
    return slugify(trim($value));
}

function pageSlug(string $value): string
{
    return slugify(trim($value));
}

function newsSlug(string $value): string
{
    return slugify(trim($value));
}

function eventSlug(string $value): string
{
    return slugify(trim($value));
}

function eventTypeSlug(string $value): string
{
    return slugify(trim($value));
}

function placeSlug(string $value): string
{
    return slugify(trim($value));
}

function foodCardSlug(string $value): string
{
    return slugify(trim($value));
}

function reservationResourceSlug(string $value): string
{
    return slugify(trim($value));
}

function downloadSlug(string $value): string
{
    return slugify(trim($value));
}

function downloadCategorySlug(string $value): string
{
    return downloadSlug($value);
}

function downloadSeriesSlug(string $value): string
{
    return downloadSlug($value);
}

function normalizeDownloadSeriesKey(string $value): string
{
    return downloadSlug(trim($value));
}

function normalizeDownloadChecksum(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/\s+/u', '', $value);
    $value = is_string($value) ? $value : '';

    if ($value === '') {
        return '';
    }

    return preg_match('/^[a-f0-9]{64}$/', $value) ? $value : '';
}

function boardSlug(string $value): string
{
    return slugify(trim($value));
}

function boardCategorySlug(string $value): string
{
    return slugify(trim($value));
}

function galleryAlbumSlug(string $value): string
{
    return slugify(trim($value));
}

function galleryPhotoSlug(string $value): string
{
    return slugify(trim($value));
}

function pollSlug(string $value): string
{
    return slugify(trim($value));
}

/**
 * @return array<string, string>
 */
function pollVoteModeOptions(): array
{
    return [
        'single' => 'Jedna možnost',
        'multiple' => 'Více možností',
    ];
}

function pollVoteMode(string $value): string
{
    $value = strtolower(trim($value));
    return array_key_exists($value, pollVoteModeOptions()) ? $value : 'single';
}

/**
 * @return array<string, string>
 */
function pollResultsVisibilityOptions(): array
{
    return [
        'after_vote' => 'Po hlasování',
        'always' => 'Vždy',
        'closed' => 'Až po uzavření',
        'hidden' => 'Neveřejné',
    ];
}

function pollResultsVisibility(string $value): string
{
    $value = strtolower(trim($value));
    return array_key_exists($value, pollResultsVisibilityOptions()) ? $value : 'after_vote';
}

/**
 * @param array<string, mixed> $poll
 */
function pollAllowsMultipleChoices(array $poll): bool
{
    return pollVoteMode((string)($poll['vote_mode'] ?? 'single')) === 'multiple';
}

/**
 * @param array<string, mixed> $poll
 */
function pollConfiguredMaxChoices(array $poll, int $optionCount): int
{
    $optionCount = max(1, $optionCount);
    if (!pollAllowsMultipleChoices($poll)) {
        return 1;
    }

    $configured = (int)($poll['max_choices'] ?? 0);
    if ($configured <= 0) {
        $configured = min(2, $optionCount);
    }

    return max(1, min($configured, $optionCount));
}

/**
 * @param mixed $rawValue
 * @return list<int>
 */
function pollSelectedOptionIds(mixed $rawValue): array
{
    $values = is_array($rawValue) ? $rawValue : [$rawValue];
    $ids = [];

    foreach ($values as $value) {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            continue;
        }

        $ids[] = (int)$id;
    }

    return array_values(array_unique($ids));
}

function pollVoterHash(string $remoteAddress, int $pollId): string
{
    return hash('sha256', $remoteAddress . '|poll_' . $pollId);
}

/**
 * @param array<string, mixed> $poll
 */
function pollResultsAreVisible(array $poll, bool $hasVoted = false, bool $justVoted = false): bool
{
    return match (pollResultsVisibility((string)($poll['results_visibility'] ?? 'after_vote'))) {
        'always' => true,
        'closed' => (string)($poll['state'] ?? '') === 'closed',
        'hidden' => false,
        default => $hasVoted || $justVoted || (string)($poll['state'] ?? '') === 'closed',
    };
}

function pollResultPercentage(int $selections, int $voterCount): float
{
    if ($voterCount <= 0) {
        return 0.0;
    }

    return round(($selections / $voterCount) * 100, 1);
}

function pollVoteSelectionLabel(int $count, bool $multiple): string
{
    $count = max(0, $count);
    if (!$multiple) {
        return $count === 1 ? '1 hlas' : $count . ' hlasů';
    }

    if ($count === 1) {
        return '1 výběr';
    }

    $mod100 = $count % 100;
    $mod10 = $count % 10;
    if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
        return $count . ' výběry';
    }

    return $count . ' výběrů';
}

function faqSlug(string $value): string
{
    return slugify(trim($value));
}

function faqCategorySlug(string $value): string
{
    return slugify(trim($value));
}

/**
 * @param array<int, array<string, mixed>> $catById
 * @return list<array<string, mixed>>
 */
function faqCategoryBreadcrumbs(array $catById, int $categoryId): array
{
    $crumbs = [];
    $current = $categoryId;
    $safety = 0;

    while ($current > 0 && isset($catById[$current]) && $safety < 20) {
        $crumbs[] = $catById[$current];
        $current = (int)($catById[$current]['parent_id'] ?? 0);
        $safety++;
    }

    return array_reverse($crumbs);
}

/**
 * @param array<int, list<array<string, mixed>>> $catTree
 * @return list<int>
 */
function faqCategoryDescendantIds(array $catTree, int $categoryId): array
{
    $allowedCatIds = [$categoryId];
    $queue = [$categoryId];

    while ($queue !== []) {
        $parentId = array_shift($queue);
        foreach ($catTree[$parentId] ?? [] as $child) {
            $childId = (int)($child['id'] ?? 0);
            if ($childId <= 0) {
                continue;
            }

            $allowedCatIds[] = $childId;
            $queue[] = $childId;
        }
    }

    return array_values(array_unique($allowedCatIds));
}

function faqCountLabel(int $count): string
{
    $count = max(0, $count);
    if ($count === 1) {
        return '1 otázka';
    }

    $mod100 = $count % 100;
    $mod10 = $count % 10;
    if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
        return $count . ' otázky';
    }

    return $count . ' otázek';
}

function podcastShowSlug(string $value): string
{
    return slugify(trim($value));
}

function podcastEpisodeSlug(string $value): string
{
    return slugify(trim($value));
}

function authorSlug(string $value): string
{
    return slugify(trim($value));
}

function normalizePlainText(string $text): string
{
    $plain = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $plain = preg_replace('/\s+/u', ' ', $plain);
    return trim((string)$plain);
}

function newsTitleCandidate(string $title, string $content = ''): string
{
    $normalizedTitle = trim($title);
    if ($normalizedTitle !== '') {
        return mb_substr($normalizedTitle, 0, 255);
    }

    $plain = normalizePlainText($content);
    if ($plain === '') {
        return 'Novinka';
    }

    return mb_strimwidth($plain, 0, 120, '…', 'UTF-8');
}

function newsExcerpt(string $content, int $limit = 220): string
{
    $plain = normalizePlainText($content);
    if ($plain === '') {
        return '';
    }

    return mb_strimwidth($plain, 0, $limit, '…', 'UTF-8');
}

function articleExcerpt(string $content, int $limit = 220): string
{
    $plain = normalizePlainText($content);
    if ($plain === '') {
        return '';
    }

    return mb_strimwidth($plain, 0, $limit, '…', 'UTF-8');
}

function newsPublicVisibilitySql(string $alias = ''): string
{
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';

    return "{$prefix}deleted_at IS NULL"
        . " AND COALESCE({$prefix}status, 'published') = 'published'"
        . " AND ({$prefix}publish_at IS NULL OR {$prefix}publish_at <= NOW())"
        . " AND ({$prefix}unpublish_at IS NULL OR {$prefix}unpublish_at > NOW())";
}

/**
 * @param array<string, mixed> $news
 * @return array<string, mixed>
 */
function newsRevisionSnapshot(array $news): array
{
    $title = str_replace('â€¦', '…', newsTitleCandidate((string)($news['title'] ?? ''), (string)($news['content'] ?? '')));

    return [
        'title' => $title,
        'slug' => newsSlug((string)($news['slug'] ?? '')),
        'content' => (string)($news['content'] ?? ''),
        'unpublish_at' => trim((string)($news['unpublish_at'] ?? '')),
        'admin_note' => trim((string)($news['admin_note'] ?? '')),
        'meta_title' => trim((string)($news['meta_title'] ?? '')),
        'meta_description' => trim((string)($news['meta_description'] ?? '')),
    ];
}

function podcastShowPublicVisibilitySql(string $alias = ''): string
{
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';

    return "{$prefix}deleted_at IS NULL"
        . " AND COALESCE({$prefix}status, 'published') = 'published'"
        . " AND COALESCE({$prefix}is_published, 1) = 1";
}

function podcastEpisodePublicVisibilitySql(string $episodeAlias = '', string $showAlias = ''): string
{
    $episodePrefix = $episodeAlias !== '' ? rtrim($episodeAlias, '.') . '.' : '';
    $visibility = "{$episodePrefix}deleted_at IS NULL"
        . " AND COALESCE({$episodePrefix}status, 'published') = 'published'"
        . " AND ({$episodePrefix}publish_at IS NULL OR {$episodePrefix}publish_at <= NOW())";

    if ($showAlias !== '') {
        $visibility .= ' AND ' . podcastShowPublicVisibilitySql($showAlias);
    }

    return $visibility;
}

function databaseDateTimeIsInFuture(string $dateTime): bool
{
    static $cache = [];

    $dateTime = trim($dateTime);
    if ($dateTime === '') {
        return false;
    }

    if (array_key_exists($dateTime, $cache)) {
        return $cache[$dateTime];
    }

    try {
        $stmt = db_connect()->prepare(
            "SELECT CASE WHEN CAST(? AS DATETIME) > NOW() THEN 1 ELSE 0 END"
        );
        $stmt->execute([$dateTime]);
        $cache[$dateTime] = (int)$stmt->fetchColumn() === 1;
        return $cache[$dateTime];
    } catch (\Throwable) {
        $timestamp = strtotime($dateTime);
        $cache[$dateTime] = $timestamp !== false && $timestamp > time();
        return $cache[$dateTime];
    }
}

/**
 * @param array<string, mixed> $show
 */
function podcastShowIsPublic(array $show): bool
{
    return trim((string)($show['status'] ?? 'published')) === 'published'
        && (int)($show['is_published'] ?? 1) === 1;
}

/**
 * @param array<string, mixed> $episode
 */
function podcastEpisodeIsScheduled(array $episode): bool
{
    $publishAt = trim((string)($episode['publish_at'] ?? ''));
    if ($publishAt === '') {
        return false;
    }

    return databaseDateTimeIsInFuture($publishAt);
}

/**
 * @param array<string, mixed> $episode
 */
function podcastEpisodeIsPublic(array $episode): bool
{
    if (trim((string)($episode['status'] ?? 'published')) !== 'published') {
        return false;
    }

    if (podcastEpisodeIsScheduled($episode)) {
        return false;
    }

    if (array_key_exists('show_status', $episode) || array_key_exists('show_is_published', $episode)) {
        return trim((string)($episode['show_status'] ?? 'published')) === 'published'
            && (int)($episode['show_is_published'] ?? 1) === 1;
    }

    return true;
}

/**
 * @param array<string, mixed> $show
 * @return array<string, mixed>
 */
function podcastShowRevisionSnapshot(array $show): array
{
    return [
        'title' => trim((string)($show['title'] ?? '')),
        'slug' => podcastShowSlug((string)($show['slug'] ?? '')),
        'description' => (string)($show['description'] ?? ''),
        'author' => trim((string)($show['author'] ?? '')),
        'subtitle' => trim((string)($show['subtitle'] ?? '')),
        'language' => trim((string)($show['language'] ?? 'cs')),
        'category' => trim((string)($show['category'] ?? '')),
        'owner_name' => trim((string)($show['owner_name'] ?? '')),
        'owner_email' => normalizePodcastOwnerEmail((string)($show['owner_email'] ?? '')),
        'explicit_mode' => normalizePodcastExplicitMode((string)($show['explicit_mode'] ?? 'no')),
        'show_type' => normalizePodcastShowType((string)($show['show_type'] ?? 'episodic')),
        'feed_complete' => (string)((int)!empty($show['feed_complete'])),
        'feed_episode_limit' => (string)normalizePodcastFeedEpisodeLimit($show['feed_episode_limit'] ?? 100),
        'website_url' => normalizePodcastWebsiteUrl((string)($show['website_url'] ?? '')),
        'status' => trim((string)($show['status'] ?? 'published')),
        'is_published' => (string)((int)($show['is_published'] ?? 1)),
    ];
}

/**
 * @param array<string, mixed> $episode
 * @return array<string, mixed>
 */
function podcastEpisodeRevisionSnapshot(array $episode): array
{
    return [
        'title' => trim((string)($episode['title'] ?? '')),
        'slug' => podcastEpisodeSlug((string)($episode['slug'] ?? '')),
        'description' => (string)($episode['description'] ?? ''),
        'transcript' => (string)($episode['transcript'] ?? ''),
        'audio_url' => normalizePodcastEpisodeAudioUrl((string)($episode['audio_url'] ?? '')),
        'audio_mime_type' => normalizePodcastAudioMimeType((string)($episode['audio_mime_type'] ?? '')),
        'audio_file_size' => (string)normalizePodcastAudioFileSize($episode['audio_file_size'] ?? 0),
        'subtitle' => trim((string)($episode['subtitle'] ?? '')),
        'duration' => trim((string)($episode['duration'] ?? '')),
        'episode_num' => (string)($episode['episode_num'] ?? ''),
        'season_num' => (string)($episode['season_num'] ?? ''),
        'episode_type' => normalizePodcastEpisodeType((string)($episode['episode_type'] ?? 'full')),
        'explicit_mode' => normalizePodcastEpisodeExplicitMode((string)($episode['explicit_mode'] ?? 'inherit')),
        'block_from_feed' => (string)((int)!empty($episode['block_from_feed'])),
        'publish_at' => trim((string)($episode['publish_at'] ?? '')),
        'status' => trim((string)($episode['status'] ?? 'published')),
    ];
}

/**
 * @param array<string, mixed> $event
 */
function eventExcerpt(array $event, int $limit = 220): string
{
    $excerpt = normalizePlainText((string)($event['excerpt'] ?? ''));
    if ($excerpt === '') {
        $excerpt = normalizePlainText((string)($event['description'] ?? ''));
    }

    if ($excerpt === '') {
        return '';
    }

    return mb_strimwidth($excerpt, 0, $limit, '…', 'UTF-8');
}

function boardTypeLabel(string $type): string
{
    $definitions = boardTypeDefinitions();
    return $definitions[normalizeBoardType($type)]['label'];
}

/**
 * @param array<string, mixed> $document
 */
function boardExcerpt(array $document, int $limit = 220): string
{
    $explicitExcerpt = normalizePlainText((string)($document['excerpt'] ?? ''));
    if ($explicitExcerpt !== '') {
        return mb_strimwidth($explicitExcerpt, 0, $limit, '...', 'UTF-8');
    }

    $descriptionExcerpt = normalizePlainText((string)($document['description'] ?? ''));
    if ($descriptionExcerpt === '') {
        return '';
    }

    return mb_strimwidth($descriptionExcerpt, 0, $limit, '...', 'UTF-8');
}

/**
 * @param array<string, mixed> $poll
 */
function pollExcerpt(array $poll, int $limit = 220): string
{
    $descriptionExcerpt = normalizePlainText((string)($poll['description'] ?? ''));
    if ($descriptionExcerpt === '') {
        return '';
    }

    return mb_strimwidth($descriptionExcerpt, 0, $limit, '...', 'UTF-8');
}

/**
 * @param array<string, mixed> $faq
 */
function faqExcerpt(array $faq, int $limit = 220): string
{
    $explicitExcerpt = normalizePlainText((string)($faq['excerpt'] ?? ''));
    if ($explicitExcerpt !== '') {
        return mb_strimwidth($explicitExcerpt, 0, $limit, '...', 'UTF-8');
    }

    $answerExcerpt = normalizePlainText((string)($faq['answer'] ?? ''));
    if ($answerExcerpt === '') {
        return '';
    }

    return mb_strimwidth($answerExcerpt, 0, $limit, '...', 'UTF-8');
}

function faqPublicVisibilitySql(string $alias = ''): string
{
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';

    return "{$prefix}deleted_at IS NULL"
        . " AND COALESCE({$prefix}status,'published') = 'published'"
        . " AND {$prefix}is_published = 1";
}

/**
 * @param array<string, mixed> $faq
 * @param list<string> $categoryNames
 * @return array<string, mixed>
 */
function faqRevisionSnapshot(array $faq, array $categoryNames = []): array
{
    $categoryId = isset($faq['category_id']) && (int)$faq['category_id'] > 0
        ? (int)$faq['category_id']
        : 0;
    $categoryName = $categoryId > 0 ? trim((string)($categoryNames[$categoryId] ?? '')) : '';

    return [
        'question' => trim((string)($faq['question'] ?? '')),
        'slug' => faqSlug((string)($faq['slug'] ?? '')),
        'category' => $categoryName,
        'excerpt' => trim((string)($faq['excerpt'] ?? '')),
        'answer' => trim((string)($faq['answer'] ?? '')),
        'meta_title' => trim((string)($faq['meta_title'] ?? '')),
        'meta_description' => trim((string)($faq['meta_description'] ?? '')),
        'is_published' => (string)((int)($faq['is_published'] ?? 1)),
        'status' => (string)($faq['status'] ?? 'published'),
    ];
}

function placeKindLabel(string $kind): string
{
    $definitions = [
        'sight' => 'Památka a zajímavost',
        'trip' => 'Tip na výlet',
        'service' => 'Služba',
        'food' => 'Občerstvení',
        'accommodation' => 'Ubytování',
        'experience' => 'Zážitek',
        'info' => 'Informační místo',
    ];

    return $definitions[normalizePlaceKind($kind)] ?? $definitions['sight'];
}

/**
 * @param array<string, mixed> $place
 */
function placeExcerpt(array $place, int $limit = 220): string
{
    $explicitExcerpt = normalizePlainText((string)($place['excerpt'] ?? ''));
    if ($explicitExcerpt !== '') {
        return mb_strimwidth($explicitExcerpt, 0, $limit, '...', 'UTF-8');
    }

    $descriptionExcerpt = normalizePlainText((string)($place['description'] ?? ''));
    if ($descriptionExcerpt === '') {
        return '';
    }

    return mb_strimwidth($descriptionExcerpt, 0, $limit, '...', 'UTF-8');
}

function pollPublicVisibilitySql(string $alias = '', string $scope = 'all'): string
{
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';

    $deletedSql = "{$prefix}deleted_at IS NULL";
    $activeSql = "COALESCE({$prefix}status, 'active') = 'active'"
        . " AND ({$prefix}start_date IS NULL OR {$prefix}start_date <= NOW())"
        . " AND ({$prefix}end_date IS NULL OR {$prefix}end_date > NOW())";
    $archiveSql = "(COALESCE({$prefix}status, 'active') = 'closed'"
        . " OR ({$prefix}end_date IS NOT NULL AND {$prefix}end_date <= NOW()))";

    return match ($scope) {
        'active' => $deletedSql . ' AND ' . $activeSql,
        'archive' => $deletedSql . ' AND ' . $archiveSql,
        default => $deletedSql . ' AND (' . $activeSql . ' OR ' . $archiveSql . ')',
    };
}

/**
 * @param array<string, mixed> $poll
 * @param array<int, array{option_text?:mixed, text?:mixed}|string> $options
 * @return array<string, mixed>
 */
function pollRevisionSnapshot(array $poll, array $options = []): array
{
    $normalizedOptions = [];
    foreach ($options as $option) {
        if (is_array($option)) {
            $optionText = trim((string)($option['option_text'] ?? $option['text'] ?? ''));
        } else {
            $optionText = trim((string)$option);
        }

        if ($optionText !== '') {
            $normalizedOptions[] = $optionText;
        }
    }

    return [
        'question' => trim((string)($poll['question'] ?? '')),
        'slug' => pollSlug((string)($poll['slug'] ?? '')),
        'description' => (string)($poll['description'] ?? ''),
        'vote_mode' => pollVoteMode((string)($poll['vote_mode'] ?? 'single')),
        'max_choices' => (int)($poll['max_choices'] ?? 0),
        'results_visibility' => pollResultsVisibility((string)($poll['results_visibility'] ?? 'after_vote')),
        'status' => trim((string)($poll['status'] ?? 'active')),
        'start_date' => trim((string)($poll['start_date'] ?? '')),
        'end_date' => trim((string)($poll['end_date'] ?? '')),
        'meta_title' => trim((string)($poll['meta_title'] ?? '')),
        'meta_description' => trim((string)($poll['meta_description'] ?? '')),
        'options' => implode("\n", $normalizedOptions),
    ];
}

/**
 * @return array<string, array{label:string}>
 */
function placeKindOptions(): array
{
    return [
        'sight' => ['label' => 'Památka a zajímavost'],
        'trip' => ['label' => 'Tip na výlet'],
        'service' => ['label' => 'Služba'],
        'food' => ['label' => 'Občerstvení'],
        'accommodation' => ['label' => 'Ubytování'],
        'experience' => ['label' => 'Zážitek'],
        'info' => ['label' => 'Informační místo'],
    ];
}

function placePublicVisibilitySql(string $alias = ''): string
{
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';

    return "{$prefix}deleted_at IS NULL AND COALESCE({$prefix}status, 'published') = 'published'"
        . " AND COALESCE({$prefix}is_published, 1) = 1";
}

/**
 * @param array<string, mixed> $place
 * @return array<string, mixed>
 */
function placeRevisionSnapshot(array $place): array
{
    return [
        'name' => trim((string)($place['name'] ?? '')),
        'slug' => placeSlug((string)($place['slug'] ?? '')),
        'place_kind' => normalizePlaceKind((string)($place['place_kind'] ?? 'sight')),
        'category' => trim((string)($place['category'] ?? '')),
        'excerpt' => trim((string)($place['excerpt'] ?? '')),
        'description' => (string)($place['description'] ?? ''),
        'url' => normalizePlaceUrl((string)($place['url'] ?? '')),
        'address' => trim((string)($place['address'] ?? '')),
        'locality' => trim((string)($place['locality'] ?? '')),
        'latitude' => trim((string)($place['latitude'] ?? '')),
        'longitude' => trim((string)($place['longitude'] ?? '')),
        'opening_hours' => trim((string)($place['opening_hours'] ?? '')),
        'contact_phone' => trim((string)($place['contact_phone'] ?? '')),
        'contact_email' => trim((string)($place['contact_email'] ?? '')),
        'meta_title' => trim((string)($place['meta_title'] ?? '')),
        'meta_description' => trim((string)($place['meta_description'] ?? '')),
        'is_published' => !empty($place['is_published']) ? '1' : '0',
        'status' => trim((string)($place['status'] ?? 'published')),
    ];
}

/**
 * @param array<string, mixed> $data
 */
function structuredDataScript(array $data, string $indent = ''): string
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        return '';
    }

    $nonce = function_exists('cspNonce') ? ' nonce="' . h(cspNonce()) . '"' : '';
    return $indent . '<script type="application/ld+json"' . $nonce . '>' . $json . '</script>';
}

/**
 * @param array<string, mixed> $place
 */
function placeStructuredData(array $place): string
{
    $name = trim((string)($place['name'] ?? ''));
    if ($name === '') {
        return '';
    }

    $description = trim((string)($place['meta_description'] ?? ''));
    if ($description === '') {
        $description = placeExcerpt($place, 320);
    }

    $schemaType = match (normalizePlaceKind((string)($place['place_kind'] ?? 'sight'))) {
        'food' => 'Restaurant',
        'accommodation' => 'LodgingBusiness',
        'service' => 'LocalBusiness',
        'info' => 'TouristInformationCenter',
        'sight', 'trip', 'experience' => 'TouristAttraction',
        default => 'Place',
    };

    $imageUrl = trim((string)($place['image_url'] ?? ''));
    if ($imageUrl !== '') {
        $imageUrl = siteUrl(str_replace(BASE_URL, '', $imageUrl));
    }

    $data = array_filter([
        '@context' => 'https://schema.org',
        '@type' => $schemaType,
        'name' => $name,
        'description' => $description,
        'url' => placePublicUrl($place),
        'image' => $imageUrl !== '' ? [$imageUrl] : null,
        'telephone' => trim((string)($place['contact_phone'] ?? '')),
        'email' => trim((string)($place['contact_email'] ?? '')),
        'sameAs' => trim((string)($place['url'] ?? '')),
        'openingHours' => trim((string)($place['opening_hours'] ?? '')),
        'address' => !empty($place['full_address']) ? array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => trim((string)($place['address'] ?? '')),
            'addressLocality' => trim((string)($place['locality'] ?? '')),
        ], static fn ($value): bool => $value !== '') : null,
        'geo' => !empty($place['has_coordinates']) ? [
            '@type' => 'GeoCoordinates',
            'latitude' => (string)($place['latitude'] ?? ''),
            'longitude' => (string)($place['longitude'] ?? ''),
        ] : null,
    ], static fn ($value): bool => $value !== '' && $value !== null);

    return structuredDataScript($data);
}

function downloadTypeLabel(string $type): string
{
    $definitions = downloadTypeDefinitions();
    return $definitions[normalizeDownloadType($type)]['label'];
}

/**
 * @param array<string, mixed> $download
 */
function downloadExcerpt(array $download, int $limit = 220): string
{
    $explicitExcerpt = normalizePlainText((string)($download['excerpt'] ?? ''));
    if ($explicitExcerpt !== '') {
        return mb_strimwidth($explicitExcerpt, 0, $limit, '...', 'UTF-8');
    }

    $descriptionExcerpt = normalizePlainText((string)($download['description'] ?? ''));
    if ($descriptionExcerpt === '') {
        return '';
    }

    return mb_strimwidth($descriptionExcerpt, 0, $limit, '...', 'UTF-8');
}

/**
 * @param array<string, mixed> $episode
 */
function podcastEpisodeExcerpt(array $episode, int $limit = 220): string
{
    $descriptionExcerpt = normalizePlainText((string)($episode['description'] ?? ''));
    if ($descriptionExcerpt === '') {
        $descriptionExcerpt = normalizePlainText((string)($episode['transcript'] ?? ''));
    }
    if ($descriptionExcerpt === '') {
        return '';
    }

    return mb_strimwidth($descriptionExcerpt, 0, $limit, '...', 'UTF-8');
}

/**
 * @param array<string, mixed> $download
 */
function downloadImageUrl(array $download): string
{
    $filename = trim((string)($download['image_file'] ?? ''));
    if ($filename === '') {
        return '';
    }

    return BASE_URL . '/uploads/downloads/images/' . rawurlencode($filename);
}

/**
 * @param array<string, mixed> $show
 */
function podcastCoverUrl(array $show): string
{
    $filename = trim((string)($show['cover_image'] ?? ''));
    $showId = isset($show['id']) ? (int)$show['id'] : 0;
    if ($filename === '' || $showId < 1) {
        return '';
    }

    return BASE_URL . '/podcast/cover.php?id=' . $showId;
}

/**
 * @param array<string, mixed> $episode
 */
function podcastEpisodeImageUrl(array $episode): string
{
    $filename = trim((string)($episode['image_file'] ?? ''));
    $episodeId = isset($episode['id']) ? (int)$episode['id'] : 0;
    if ($filename === '' || $episodeId < 1) {
        return '';
    }

    return BASE_URL . '/podcast/image.php?id=' . $episodeId;
}

/**
 * @param array<string, mixed> $episode
 */
function podcastEpisodeAudioUrl(array $episode): string
{
    $audioFile = trim((string)($episode['audio_file'] ?? ''));
    $episodeId = isset($episode['id']) ? (int)$episode['id'] : 0;
    if ($audioFile !== '' && $episodeId > 0) {
        return BASE_URL . '/podcast/audio.php?id=' . $episodeId;
    }

    return trim((string)($episode['audio_url'] ?? ''));
}

function podcastAudioMimeType(string $filename): string
{
    return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
        'ogg' => 'audio/ogg',
        'wav' => 'audio/wav',
        'aac' => 'audio/aac',
        'm4a' => 'audio/mp4',
        default => 'audio/mpeg',
    };
}

function podcastCoverFilePath(string $filename): string
{
    return dirname(__DIR__) . '/uploads/podcasts/covers/' . basename($filename);
}

function podcastEpisodeImageFilePath(string $filename): string
{
    return dirname(__DIR__) . '/uploads/podcasts/images/' . basename($filename);
}

function podcastAudioFilePath(string $filename): string
{
    return dirname(__DIR__) . '/uploads/podcasts/' . basename($filename);
}

function normalizePodcastWebsiteUrl(string $value): string
{
    return normalizeHttpExternalUrl($value);
}

function normalizePodcastEpisodeAudioUrl(string $value): string
{
    return normalizePodcastWebsiteUrl($value);
}

function newPodcastFeedGuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);

    return 'urn:uuid:'
        . substr($hex, 0, 8) . '-'
        . substr($hex, 8, 4) . '-'
        . substr($hex, 12, 4) . '-'
        . substr($hex, 16, 4) . '-'
        . substr($hex, 20, 12);
}

function normalizePodcastFeedGuid(string $value): string
{
    $value = trim((string)preg_replace('/[\x00-\x1F\x7F]/u', '', $value));
    return mb_substr($value, 0, 255);
}

function uniquePodcastFeedGuid(PDO $pdo, string $tableName, string $candidate = ''): string
{
    if (!in_array($tableName, ['cms_podcast_shows', 'cms_podcasts'], true)) {
        throw new InvalidArgumentException('Nepovolená podcastová tabulka pro RSS GUID.');
    }

    $guid = normalizePodcastFeedGuid($candidate);
    $stmt = $pdo->prepare("SELECT id FROM {$tableName} WHERE feed_guid = ? LIMIT 1");
    if ($guid !== '') {
        $stmt->execute([$guid]);
        if (!$stmt->fetchColumn()) {
            return $guid;
        }
    }

    do {
        $guid = newPodcastFeedGuid();
        $stmt->execute([$guid]);
    } while ($stmt->fetchColumn());

    return $guid;
}

function normalizePodcastAudioMimeType(string $value): string
{
    $value = strtolower(trim($value));
    return preg_match('~^audio/[a-z0-9][a-z0-9.+-]{0,79}$~', $value) === 1 ? $value : '';
}

function normalizePodcastAudioFileSize(mixed $value): int
{
    $value = trim((string)$value);
    if ($value === '' || preg_match('/^\d+$/', $value) !== 1) {
        return 0;
    }

    return max(0, (int)$value);
}

function podcastChapterStartSeconds(mixed $value): ?float
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    if (preg_match('/^\d+(?:\.\d{1,3})?$/', $value) === 1) {
        return round((float)$value, 3);
    }
    if (preg_match('/^(?:(\d+):)?([0-5]?\d):([0-5]\d)(?:\.(\d{1,3}))?$/', $value, $matches) !== 1) {
        return null;
    }

    $hours = (int)$matches[1];
    $minutes = (int)$matches[2];
    $seconds = (int)$matches[3];
    $milliseconds = isset($matches[4])
        ? (float)('0.' . str_pad($matches[4], 3, '0'))
        : 0.0;

    return round(($hours * 3600) + ($minutes * 60) + $seconds + $milliseconds, 3);
}

function podcastChapterTimeLabel(mixed $seconds): string
{
    $totalMilliseconds = (int)round(max(0.0, (float)$seconds) * 1000);
    $wholeSeconds = intdiv($totalMilliseconds, 1000);
    $milliseconds = $totalMilliseconds % 1000;
    $hours = intdiv($wholeSeconds, 3600);
    $minutes = intdiv($wholeSeconds % 3600, 60);
    $remainingSeconds = $wholeSeconds % 60;
    $label = $hours > 0
        ? sprintf('%d:%02d:%02d', $hours, $minutes, $remainingSeconds)
        : sprintf('%d:%02d', $minutes, $remainingSeconds);

    return $milliseconds > 0 ? $label . '.' . rtrim(str_pad((string)$milliseconds, 3, '0', STR_PAD_LEFT), '0') : $label;
}

function normalizePodcastChapterUrl(string $value): string
{
    return normalizeHttpExternalUrl($value);
}

/**
 * @param list<array<string, mixed>> $chapters
 * @return array{version:string,chapters:list<array<string, mixed>>}
 */
function podcastChaptersPayload(array $chapters): array
{
    $items = [];
    foreach ($chapters as $chapter) {
        $title = trim((string)($chapter['title'] ?? ''));
        $startTime = podcastChapterStartSeconds($chapter['start_time_seconds'] ?? '');
        if ($title === '' || $startTime === null) {
            continue;
        }
        $item = ['startTime' => $startTime, 'title' => $title];
        $url = normalizePodcastChapterUrl((string)($chapter['url'] ?? ''));
        $imageUrl = normalizePodcastChapterUrl((string)($chapter['image_url'] ?? ''));
        if ($url !== '') {
            $item['url'] = $url;
        }
        if ($imageUrl !== '') {
            $item['img'] = $imageUrl;
        }
        $items[] = $item;
    }

    usort($items, static fn (array $left, array $right): int => $left['startTime'] <=> $right['startTime']);
    return ['version' => '1.2.0', 'chapters' => $items];
}

/**
 * @param array<string, mixed> $episode
 */
function podcastTranscriptPublicUrl(array $episode): string
{
    $episodeId = (int)($episode['id'] ?? 0);
    return $episodeId > 0 ? siteUrl('/podcast/transcript.php?id=' . $episodeId) : '';
}

/**
 * @param array<string, mixed> $episode
 */
function podcastChaptersPublicUrl(array $episode): string
{
    $episodeId = (int)($episode['id'] ?? 0);
    return $episodeId > 0 ? siteUrl('/podcast/chapters.php?id=' . $episodeId) : '';
}

/**
 * @return array<string, string>
 */
function podcastPersonRoleOptions(): array
{
    return [
        'host' => 'Moderátor',
        'co-host' => 'Spolumoderátor',
        'guest' => 'Host',
        'narrator' => 'Vypravěč',
        'producer' => 'Producent',
        'editor' => 'Editor',
        'reporter' => 'Reportér',
        'composer' => 'Skladatel',
        'musician' => 'Hudebník',
    ];
}

function normalizePodcastPersonRole(string $value): string
{
    $value = strtolower(trim($value));
    return array_key_exists($value, podcastPersonRoleOptions()) ? $value : 'guest';
}

function podcastPersonRoleLabel(string $value): string
{
    $role = normalizePodcastPersonRole($value);
    return podcastPersonRoleOptions()[$role];
}

function normalizePodcastPersonGroup(string $value): string
{
    $value = strtolower(trim($value));
    return in_array($value, ['cast', 'crew'], true) ? $value : 'cast';
}

function podcastPersonGroupLabel(string $value): string
{
    return normalizePodcastPersonGroup($value) === 'crew' ? 'Tvůrčí tým' : 'Účinkující';
}

function normalizePodcastPersonUrl(string $value): string
{
    return normalizeHttpExternalUrl($value);
}

/**
 * @param array<string, mixed> $person
 */
function podcastPersonFeedTag(array $person, string $indent = ''): string
{
    $name = trim((string)($person['name'] ?? ''));
    if ($name === '') {
        return '';
    }
    $attributes = [
        'role' => normalizePodcastPersonRole((string)($person['role_key'] ?? 'guest')),
        'group' => normalizePodcastPersonGroup((string)($person['group_key'] ?? 'cast')),
    ];
    $imageUrl = normalizePodcastPersonUrl((string)($person['image_url'] ?? ''));
    $profileUrl = normalizePodcastPersonUrl((string)($person['profile_url'] ?? ''));
    if ($imageUrl !== '') {
        $attributes['img'] = $imageUrl;
    }
    if ($profileUrl !== '') {
        $attributes['href'] = $profileUrl;
    }
    $attributeHtml = '';
    foreach ($attributes as $attribute => $value) {
        $attributeHtml .= ' ' . $attribute . '="' . htmlspecialchars($value, ENT_XML1, 'UTF-8') . '"';
    }

    return $indent . '<podcast:person' . $attributeHtml . '>'
        . htmlspecialchars($name, ENT_XML1, 'UTF-8') . '</podcast:person>';
}

/**
 * @return array<string, string>
 */
function podcastPlatformOptions(): array
{
    return [
        'apple' => 'Apple Podcasts',
        'spotify' => 'Spotify',
        'youtube' => 'YouTube',
        'youtube-music' => 'YouTube Music',
        'pocket-casts' => 'Pocket Casts',
        'overcast' => 'Overcast',
        'amazon-music' => 'Amazon Music',
        'deezer' => 'Deezer',
        'castbox' => 'Castbox',
        'other' => 'Jiná platforma',
    ];
}

function normalizePodcastPlatformKey(string $value): string
{
    $value = strtolower(trim($value));
    return array_key_exists($value, podcastPlatformOptions()) ? $value : 'other';
}

/**
 * @param array<string, mixed> $link
 */
function podcastPlatformLabel(array $link): string
{
    $customLabel = trim((string)($link['label'] ?? ''));
    if ($customLabel !== '') {
        return $customLabel;
    }

    return podcastPlatformOptions()[normalizePodcastPlatformKey((string)($link['platform_key'] ?? 'other'))];
}

function normalizePodcastPlatformUrl(string $value): string
{
    return normalizeHttpExternalUrl($value);
}

/**
 * @param list<array<string, mixed>> $episodes Ascending chronological order.
 * @return array{previous:?array<string, mixed>,next:?array<string, mixed>}
 */
function podcastEpisodeNeighbors(array $episodes, int $currentEpisodeId): array
{
    foreach ($episodes as $index => $episode) {
        if ((int)($episode['id'] ?? 0) !== $currentEpisodeId) {
            continue;
        }

        return [
            'previous' => $index > 0 ? $episodes[$index - 1] : null,
            'next' => isset($episodes[$index + 1]) ? $episodes[$index + 1] : null,
        ];
    }

    return ['previous' => null, 'next' => null];
}

function normalizePodcastDiscoveryQuery(string $query): string
{
    return mb_substr(trim(normalizePlainText($query)), 0, 100);
}

function normalizePodcastCategoryFilter(string $category): string
{
    return mb_substr(trim(normalizePlainText($category)), 0, 100);
}

function normalizePodcastOwnerEmail(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $validated = filter_var($value, FILTER_VALIDATE_EMAIL);
    return is_string($validated) ? $validated : '';
}

function normalizePodcastExplicitMode(string $value, string $default = 'no'): string
{
    $value = trim(strtolower($value));
    return in_array($value, ['no', 'clean', 'yes'], true) ? $value : $default;
}

function normalizePodcastEpisodeExplicitMode(string $value): string
{
    $value = trim(strtolower($value));
    return in_array($value, ['inherit', 'no', 'clean', 'yes'], true) ? $value : 'inherit';
}

function normalizePodcastShowType(string $value): string
{
    $value = trim(strtolower($value));
    return in_array($value, ['episodic', 'serial'], true) ? $value : 'episodic';
}

function normalizePodcastEpisodeType(string $value): string
{
    $value = trim(strtolower($value));
    return in_array($value, ['full', 'trailer', 'bonus'], true) ? $value : 'full';
}

function normalizePodcastFeedEpisodeLimit(mixed $value): int
{
    $limit = (int)$value;
    if ($limit < 1) {
        return 100;
    }
    return min($limit, 1000);
}

function podcastFeedSubtitle(string $value, int $limit = 255): string
{
    $value = normalizePlainText($value);
    if ($value === '') {
        return '';
    }

    return mb_strimwidth($value, 0, $limit, '...', 'UTF-8');
}

function podcastFeedSummary(string $value, int $limit = 4000): string
{
    $value = normalizePlainText($value);
    if ($value === '') {
        return '';
    }

    return mb_strimwidth($value, 0, $limit, '...', 'UTF-8');
}

/**
 * @param array<string, mixed> $show
 */
function podcastFeedManagingEditor(array $show): string
{
    $ownerEmail = normalizePodcastOwnerEmail((string)($show['owner_email'] ?? ''));
    if ($ownerEmail !== '') {
        $ownerName = trim((string)($show['owner_name'] ?? ''));
        return $ownerName !== '' ? $ownerEmail . ' (' . $ownerName . ')' : $ownerEmail;
    }

    return trim((string)($show['author'] ?? ''));
}

/**
 * @param array<string, mixed> $episode
 */
function podcastEpisodeEnclosureLength(array $episode): int
{
    $filename = trim((string)($episode['audio_file'] ?? ''));
    if ($filename === '') {
        return normalizePodcastAudioFileSize($episode['audio_file_size'] ?? 0);
    }

    $path = podcastAudioFilePath($filename);
    if (!is_file($path)) {
        return 0;
    }

    $size = filesize($path);
    return $size === false ? 0 : (int)$size;
}

/**
 * @param array<string, mixed> $episode
 */
function podcastEpisodeEnclosureMimeType(array $episode): string
{
    $filename = trim((string)($episode['audio_file'] ?? ''));
    if ($filename !== '') {
        return podcastAudioMimeType($filename);
    }

    $mimeType = normalizePodcastAudioMimeType((string)($episode['audio_mime_type'] ?? ''));
    return $mimeType !== '' ? $mimeType : 'audio/mpeg';
}

/**
 * @param array<string, mixed> $show
 * @param list<array<string, mixed>> $episodes
 * @return list<array{severity:string,message:string,episode_id:int}>
 */
function podcastFeedHealthIssues(array $show, array $episodes): array
{
    $issues = [];
    $add = static function (string $severity, string $message, int $episodeId = 0) use (&$issues): void {
        $issues[] = ['severity' => $severity, 'message' => $message, 'episode_id' => $episodeId];
    };

    if (normalizePodcastFeedGuid((string)($show['feed_guid'] ?? '')) === '') {
        $add('error', 'Podcast nemá trvalý RSS identifikátor. Spusťte migraci databáze.');
    }
    foreach (['title' => 'název', 'description' => 'popis', 'author' => 'autora', 'category' => 'kategorii'] as $field => $label) {
        if (trim((string)($show[$field] ?? '')) === '') {
            $add('warning', 'Podcast nemá vyplněný ' . $label . '.');
        }
    }
    if (trim((string)($show['cover_image'] ?? '')) === '') {
        $add('warning', 'Podcast nemá cover obrázek.');
    }
    if ($episodes === []) {
        $add('warning', 'RSS feed zatím neobsahuje žádnou veřejnou epizodu.');
    }

    foreach ($episodes as $episode) {
        $episodeId = (int)($episode['id'] ?? 0);
        $title = trim((string)($episode['title'] ?? '')) ?: 'Epizoda #' . $episodeId;
        if (normalizePodcastFeedGuid((string)($episode['feed_guid'] ?? '')) === '') {
            $add('error', $title . ': chybí trvalý RSS identifikátor.', $episodeId);
        }
        if (podcastEpisodeAudioUrl($episode) === '') {
            $add('error', $title . ': chybí audio soubor nebo externí audio odkaz.', $episodeId);
            continue;
        }
        if (trim((string)($episode['audio_file'] ?? '')) !== '' && !is_file(podcastAudioFilePath((string)$episode['audio_file']))) {
            $add('error', $title . ': lokální audio soubor na disku neexistuje.', $episodeId);
        }
        if (trim((string)($episode['audio_url'] ?? '')) !== '') {
            if (normalizePodcastAudioMimeType((string)($episode['audio_mime_type'] ?? '')) === '') {
                $add('error', $title . ': externí audio nemá platný MIME typ.', $episodeId);
            }
            if (normalizePodcastAudioFileSize($episode['audio_file_size'] ?? 0) < 1) {
                $add('error', $title . ': externí audio nemá velikost souboru v bajtech.', $episodeId);
            }
        }
        if (trim((string)($episode['transcript'] ?? '')) === '') {
            $add('warning', $title . ': chybí přepis epizody jako textová alternativa audia.', $episodeId);
        }
    }

    return $issues;
}

/**
 * @param array<string, mixed> $show
 */
function podcastShowStructuredData(array $show): string
{
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'PodcastSeries',
        'name' => (string)($show['title'] ?? ''),
        'url' => podcastShowPublicUrl($show),
        'inLanguage' => (string)($show['language'] ?? 'cs'),
    ];

    $description = trim((string)($show['description_plain'] ?? ''));
    if ($description !== '') {
        $data['description'] = $description;
    }

    $imageUrl = podcastCoverUrl($show);
    if ($imageUrl !== '') {
        $data['image'] = siteUrl(str_starts_with($imageUrl, BASE_URL) ? substr($imageUrl, strlen(BASE_URL)) : $imageUrl);
    }

    $author = trim((string)($show['author'] ?? ''));
    if ($author !== '') {
        $data['author'] = [
            '@type' => 'Organization',
            'name' => $author,
        ];
    }

    $websiteUrl = normalizePodcastWebsiteUrl((string)($show['website_url'] ?? ''));
    if ($websiteUrl !== '') {
        $data['sameAs'] = [$websiteUrl];
    }

    return structuredDataScript($data, '  ') . PHP_EOL;
}

/**
 * @param array<string, mixed> $show
 * @param array<string, mixed> $episode
 */
function podcastEpisodeStructuredData(array $show, array $episode): string
{
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'PodcastEpisode',
        'name' => (string)($episode['title'] ?? ''),
        'url' => podcastEpisodePublicUrl($episode),
        'partOfSeries' => [
            '@type' => 'PodcastSeries',
            'name' => (string)($show['title'] ?? ''),
            'url' => podcastShowPublicUrl($show),
        ],
    ];

    $description = trim((string)($episode['excerpt'] ?? ''));
    if ($description === '') {
        $description = podcastFeedSummary((string)($episode['description'] ?? ''), 4000);
    }
    if ($description !== '') {
        $data['description'] = $description;
    }

    $imageUrl = trim((string)($episode['display_image_url'] ?? ''));
    if ($imageUrl !== '') {
        $data['image'] = siteUrl(str_starts_with($imageUrl, BASE_URL) ? substr($imageUrl, strlen(BASE_URL)) : $imageUrl);
    }

    $publishedAt = trim((string)($episode['display_date'] ?? ''));
    if ($publishedAt !== '') {
        $data['datePublished'] = date(DATE_ATOM, strtotime($publishedAt));
    }

    $audioUrl = trim((string)($episode['audio_src'] ?? ''));
    if ($audioUrl !== '') {
        $data['associatedMedia'] = [
            '@type' => 'MediaObject',
            'contentUrl' => siteUrl(str_starts_with($audioUrl, BASE_URL) ? substr($audioUrl, strlen(BASE_URL)) : $audioUrl),
            'encodingFormat' => 'audio/mpeg',
        ];
    }

    $duration = trim((string)($episode['duration'] ?? ''));
    if ($duration !== '' && preg_match('/^(?:(\d+):)?(\d{1,2}):(\d{2})$/', $duration, $matches)) {
        $hours = $matches[1] !== '' ? (int)$matches[1] : 0;
        $minutes = (int)$matches[2];
        $seconds = (int)$matches[3];
        $data['duration'] = sprintf('PT%dH%dM%dS', $hours, $minutes, $seconds);
    }

    return structuredDataScript($data, '  ') . PHP_EOL;
}

function deletePodcastCoverFile(string $filename): void
{
    $filename = basename($filename);
    if ($filename === '') {
        return;
    }

    $path = podcastCoverFilePath($filename);
    if (is_file($path)) {
        if (!unlink($path)) {
            presentationLogFileDeleteFailure('podcast_cover', $path);
        }
    }
}

function deletePodcastEpisodeImageFile(string $filename): void
{
    $filename = basename($filename);
    if ($filename === '') {
        return;
    }

    $path = podcastEpisodeImageFilePath($filename);
    if (is_file($path)) {
        if (!unlink($path)) {
            presentationLogFileDeleteFailure('podcast_episode_image', $path);
        }
    }
}

function deletePodcastAudioFile(string $filename): void
{
    $filename = basename($filename);
    if ($filename === '') {
        return;
    }

    $path = podcastAudioFilePath($filename);
    if (is_file($path)) {
        if (!unlink($path)) {
            presentationLogFileDeleteFailure('podcast_audio', $path);
        }
    }
}

/**
 * @param array<string,mixed> $options
 * @return array{filename:string,uploaded:bool,error:string}
 */
/**
 * @param array<string, mixed> $file
 * @param array<string, mixed> $options
 * @return array<string, mixed>
 */
function storePresentationUploadedFile(array $file, string $existingFilename, array $options): array
{
    if (!koraUploadHasFile($file)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => '',
        ];
    }

    $upload = koraInspectUploadedFile($file, [
        'upload_error' => (string)($options['upload_error'] ?? 'Soubor se nepodařilo nahrát.'),
        'invalid_upload_error' => (string)($options['invalid_upload_error'] ?? 'Soubor se nepodařilo zpracovat.'),
        'allowed_mime_map' => (array)($options['allowed_mime_map'] ?? []),
        'unsupported_type_error' => (string)($options['unsupported_type_error'] ?? 'Tento typ souboru není povolený.'),
    ]);
    if (empty($upload['ok'])) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => (string)($upload['error'] ?? 'Soubor se nepodařilo nahrát.'),
        ];
    }

    $imageValidator = $options['image_validator'] ?? null;
    if (is_callable($imageValidator)) {
        $imageError = (string)$imageValidator((string)$upload['tmp_path']);
        if ($imageError !== '') {
            return [
                'filename' => $existingFilename,
                'uploaded' => false,
                'error' => $imageError,
            ];
        }
    }

    $extension = (string)($upload['extension'] ?? '');
    $filename = uniqid((string)($options['prefix'] ?? 'upload_'), true) . ($extension !== '' ? '.' . $extension : '');
    $storedUpload = koraStoreInspectedUpload(
        $upload,
        (string)($options['directory'] ?? ''),
        $filename,
        [
            'mkdir_error' => (string)($options['mkdir_error'] ?? 'Adresář pro soubory se nepodařilo vytvořit.'),
            'move_error' => (string)($options['move_error'] ?? 'Soubor se nepodařilo uložit.'),
        ]
    );
    if (empty($storedUpload['ok'])) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => (string)($storedUpload['error'] ?? 'Soubor se nepodařilo uložit.'),
        ];
    }

    if (!empty($options['generate_webp'])) {
        generateWebp((string)$storedUpload['path']);
    }

    $deleteCallback = $options['delete_callback'] ?? null;
    if ($existingFilename !== '' && $existingFilename !== $filename && is_callable($deleteCallback)) {
        $deleteCallback($existingFilename);
    }

    return [
        'filename' => $filename,
        'uploaded' => true,
        'error' => '',
    ];
}

function squarePodcastImageUploadError(string $tmpPath, string $label): string
{
    $imageInfo = @getimagesize($tmpPath);
    if (!is_array($imageInfo) || empty($imageInfo[0]) || empty($imageInfo[1])) {
        return $label . ' se nepodařilo zkontrolovat.';
    }

    $width = (int)$imageInfo[0];
    $height = (int)$imageInfo[1];
    if ($width !== $height || $width < 1024 || $width > 3000) {
        return $label . ' musí být čtvercový JPG nebo PNG v rozmezí 1024×1024 až 3000×3000 px.';
    }

    return '';
}

/**
 * @return array<string, string>
 */
function presentationImageMimeMap(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
}

function articleImageDirectory(): string
{
    return dirname(__DIR__) . '/uploads/articles/';
}

function articleImageThumbDirectory(): string
{
    return articleImageDirectory() . 'thumbs/';
}

function presentationWebpVariantPath(string $path): string
{
    $webpPath = preg_replace('/\.[a-z0-9]+$/i', '.webp', $path);
    return is_string($webpPath) && $webpPath !== '' ? $webpPath : $path . '.webp';
}

function deleteArticleImageFile(string $filename): void
{
    $filename = basename($filename);
    if ($filename === '') {
        return;
    }

    $directory = articleImageDirectory();
    $thumbDirectory = articleImageThumbDirectory();
    $baseName = pathinfo($filename, PATHINFO_FILENAME);
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $paths = [
        $directory . $filename,
        $thumbDirectory . $filename,
    ];

    if ($baseName !== '' && $extension !== '') {
        foreach ([400, 800, 1200] as $width) {
            $paths[] = $directory . $baseName . '-' . $width . 'w.' . $extension;
        }
    }

    $webpPaths = [];
    foreach ($paths as $path) {
        $webpPath = presentationWebpVariantPath($path);
        if ($webpPath !== $path) {
            $webpPaths[] = $webpPath;
        }
    }

    foreach (array_unique(array_merge($paths, $webpPaths)) as $path) {
        if (is_file($path) && !unlink($path)) {
            presentationLogFileDeleteFailure('article_image', $path);
        }
    }
}

/**
 * @param array<string, mixed> $file
 * @return array{filename:string,uploaded:bool,error:string}
 */
function uploadArticleImage(array $file, string $existingFilename = ''): array
{
    if (!koraUploadHasFile($file)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => '',
        ];
    }

    $upload = koraInspectUploadedFile($file, [
        'upload_error' => 'Náhledový obrázek článku se nepodařilo nahrát.',
        'invalid_upload_error' => 'Náhledový obrázek článku se nepodařilo zpracovat.',
        'allowed_mime_map' => presentationImageMimeMap(),
        'unsupported_type_error' => 'Náhledový obrázek článku musí být ve formátu JPEG, PNG, GIF nebo WebP.',
    ]);
    if (empty($upload['ok'])) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => (string)($upload['error'] ?? 'Náhledový obrázek článku se nepodařilo nahrát.'),
        ];
    }

    $extension = (string)($upload['extension'] ?? '');
    $filename = uniqid('img_', true) . ($extension !== '' ? '.' . $extension : '');
    $storedUpload = koraStoreInspectedUpload($upload, articleImageDirectory(), $filename, [
        'mkdir_error' => 'Adresář pro obrázky článků se nepodařilo vytvořit.',
        'move_error' => 'Náhledový obrázek článku se nepodařilo uložit.',
    ]);
    if (empty($storedUpload['ok'])) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => (string)($storedUpload['error'] ?? 'Náhledový obrázek článku se nepodařilo uložit.'),
        ];
    }

    $thumbDirectory = articleImageThumbDirectory();
    if (!is_dir($thumbDirectory) && !mkdir($thumbDirectory, 0755, true) && !is_dir($thumbDirectory)) {
        deleteArticleImageFile($filename);
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Adresář pro náhledy článků se nepodařilo vytvořit.',
        ];
    }

    $storedPath = (string)$storedUpload['path'];
    gallery_make_thumb($storedPath, $thumbDirectory . $filename, 400);
    generateWebp($storedPath);
    generateWebp($thumbDirectory . $filename);
    generateResponsiveSizes($storedPath, articleImageDirectory(), $filename);

    if ($existingFilename !== '' && $existingFilename !== $filename) {
        deleteArticleImageFile($existingFilename);
    }

    return [
        'filename' => $filename,
        'uploaded' => true,
        'error' => '',
    ];
}

/**
 * @return array{filename:string,uploaded:bool,error:string}
 */
/**
 * @param array<string, mixed> $file
 * @return array<string, mixed>
 */
function uploadPodcastCoverImage(array $file, string $existingFilename = ''): array
{
    return storePresentationUploadedFile($file, $existingFilename, [
        'upload_error' => 'Obrázek coveru se nepodařilo nahrát.',
        'invalid_upload_error' => 'Obrázek coveru se nepodařilo zpracovat.',
        'allowed_mime_map' => ['image/jpeg' => 'jpg', 'image/png' => 'png'],
        'unsupported_type_error' => 'Cover musí být ve formátu JPG nebo PNG.',
        'image_validator' => static fn (string $tmpPath): string => squarePodcastImageUploadError($tmpPath, 'Cover'),
        'directory' => dirname(__DIR__) . '/uploads/podcasts/covers/',
        'mkdir_error' => 'Adresář pro cover obrázky se nepodařilo vytvořit.',
        'move_error' => 'Cover obrázek se nepodařilo uložit.',
        'prefix' => 'podcast_cover_',
        'generate_webp' => true,
        'delete_callback' => 'deletePodcastCoverFile',
    ]);
}

/**
 * @return array{filename:string,uploaded:bool,error:string}
 */
/**
 * @param array<string, mixed> $file
 * @return array<string, mixed>
 */
function uploadPodcastEpisodeImage(array $file, string $existingFilename = ''): array
{
    return storePresentationUploadedFile($file, $existingFilename, [
        'upload_error' => 'Obrázek epizody se nepodařilo nahrát.',
        'invalid_upload_error' => 'Obrázek epizody se nepodařilo zpracovat.',
        'allowed_mime_map' => ['image/jpeg' => 'jpg', 'image/png' => 'png'],
        'unsupported_type_error' => 'Obrázek epizody musí být ve formátu JPG nebo PNG.',
        'image_validator' => static fn (string $tmpPath): string => squarePodcastImageUploadError($tmpPath, 'Obrázek epizody'),
        'directory' => dirname(__DIR__) . '/uploads/podcasts/images/',
        'mkdir_error' => 'Adresář pro obrázky epizod se nepodařilo vytvořit.',
        'move_error' => 'Obrázek epizody se nepodařilo uložit.',
        'prefix' => 'podcast_episode_image_',
        'generate_webp' => true,
        'delete_callback' => 'deletePodcastEpisodeImageFile',
    ]);
}

/**
 * @return array{filename:string,uploaded:bool,error:string}
 */
/**
 * @param array<string, mixed> $file
 * @return array<string, mixed>
 */
function uploadPodcastAudioFile(array $file, string $existingFilename = ''): array
{
    return storePresentationUploadedFile($file, $existingFilename, [
        'upload_error' => 'Audio soubor se nepodařilo nahrát.',
        'invalid_upload_error' => 'Audio soubor se nepodařilo zpracovat.',
        'allowed_mime_map' => [
            'audio/mpeg' => 'mp3',
            'audio/mp3' => 'mp3',
            'audio/ogg' => 'ogg',
            'audio/wav' => 'wav',
            'audio/x-wav' => 'wav',
            'audio/mp4' => 'm4a',
            'audio/x-m4a' => 'm4a',
            'audio/aac' => 'aac',
        ],
        'unsupported_type_error' => 'Audio musí být ve formátu MP3, OGG, WAV, M4A nebo AAC.',
        'directory' => dirname(__DIR__) . '/uploads/podcasts/',
        'mkdir_error' => 'Adresář pro podcastová audia se nepodařilo vytvořit.',
        'move_error' => 'Audio soubor se nepodařilo uložit.',
        'prefix' => 'podcast_episode_',
        'delete_callback' => 'deletePodcastAudioFile',
    ]);
}

function deleteDownloadImageFile(string $filename): void
{
    $filename = basename($filename);
    if ($filename === '') {
        return;
    }

    $path = dirname(__DIR__) . '/uploads/downloads/images/' . $filename;
    if (is_file($path)) {
        if (!unlink($path)) {
            presentationLogFileDeleteFailure('download_image', $path);
        }
    }
}

function deleteDownloadStoredFile(string $filename): void
{
    $filename = basename($filename);
    if ($filename === '') {
        return;
    }

    $path = dirname(__DIR__) . '/uploads/downloads/' . $filename;
    if (is_file($path)) {
        if (!unlink($path)) {
            presentationLogFileDeleteFailure('download_file', $path);
        }
    }
}

/**
 * @return list<string>
 */
function downloadAllowedFileExtensions(): array
{
    return [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'odt', 'ods', 'odp', 'zip', '7z', 'tar', 'gz', 'bz2',
        'txt', 'exe', 'msi', 'apk', 'jar', 'dmg', 'pkg', 'deb', 'rpm', 'appimage',
    ];
}

/**
 * @param array<string, mixed> $file
 * @return array{filename:string,original_name:string,file_size:int,checksum:string,uploaded:bool,error:string}
 */
function uploadDownloadStoredFile(array $file, string $existingFilename = ''): array
{
    if (!koraUploadHasFile($file)) {
        return [
            'filename' => $existingFilename,
            'original_name' => '',
            'file_size' => 0,
            'checksum' => '',
            'uploaded' => false,
            'error' => '',
        ];
    }

    $upload = koraInspectUploadedFile($file, [
        'upload_error' => 'Soubor se nepodařilo nahrát.',
        'invalid_upload_error' => 'Soubor se nepodařilo zpracovat.',
    ]);
    if (empty($upload['ok'])) {
        return [
            'filename' => $existingFilename,
            'original_name' => '',
            'file_size' => 0,
            'checksum' => '',
            'uploaded' => false,
            'error' => (string)($upload['error'] ?? 'Soubor se nepodařilo nahrát.'),
        ];
    }

    $extension = strtolower((string)($upload['extension'] ?? ''));
    if ($extension === '' || !in_array($extension, downloadAllowedFileExtensions(), true)) {
        return [
            'filename' => $existingFilename,
            'original_name' => '',
            'file_size' => 0,
            'checksum' => '',
            'uploaded' => false,
            'error' => 'Soubor má nepovolený formát.',
        ];
    }

    $storedName = uniqid('dl_', true) . '.' . $extension;
    $storedUpload = koraStoreInspectedUpload(
        $upload,
        dirname(__DIR__) . '/uploads/downloads/',
        $storedName,
        [
            'mkdir_error' => 'Adresář pro soubory ke stažení se nepodařilo vytvořit.',
            'move_error' => 'Soubor se nepodařilo uložit.',
        ]
    );
    if (empty($storedUpload['ok'])) {
        return [
            'filename' => $existingFilename,
            'original_name' => '',
            'file_size' => 0,
            'checksum' => '',
            'uploaded' => false,
            'error' => (string)($storedUpload['error'] ?? 'Soubor se nepodařilo uložit.'),
        ];
    }

    $checksum = hash_file('sha256', (string)$storedUpload['path']);
    if ($checksum === false) {
        deleteDownloadStoredFile($storedName);
        return [
            'filename' => $existingFilename,
            'original_name' => '',
            'file_size' => 0,
            'checksum' => '',
            'uploaded' => false,
            'error' => 'Kontrolní součet souboru se nepodařilo dopočítat.',
        ];
    }

    if ($existingFilename !== '' && $existingFilename !== $storedName) {
        deleteDownloadStoredFile($existingFilename);
    }

    return [
        'filename' => $storedName,
        'original_name' => basename((string)($upload['original_name'] ?? '')),
        'file_size' => (int)($upload['file_size'] ?? 0),
        'checksum' => normalizeDownloadChecksum($checksum),
        'uploaded' => true,
        'error' => '',
    ];
}

/**
 * @return array{filename:string,uploaded:bool,error:string}
 */
/**
 * @param array<string, mixed> $file
 * @return array<string, mixed>
 */
function uploadDownloadImage(array $file, string $existingFilename = ''): array
{
    return storePresentationUploadedFile($file, $existingFilename, [
        'upload_error' => 'Obrázek se nepodařilo nahrát.',
        'invalid_upload_error' => 'Obrázek se nepodařilo zpracovat.',
        'allowed_mime_map' => presentationImageMimeMap(),
        'unsupported_type_error' => 'Obrázek musí být ve formátu JPEG, PNG, GIF nebo WebP.',
        'directory' => dirname(__DIR__) . '/uploads/downloads/images/',
        'mkdir_error' => 'Adresář pro obrázky ke stažení se nepodařilo vytvořit.',
        'move_error' => 'Obrázek se nepodařilo uložit.',
        'prefix' => 'download_image_',
        'generate_webp' => true,
        'delete_callback' => 'deleteDownloadImageFile',
    ]);
}

function normalizeDownloadExternalUrl(string $value): string
{
    return normalizeHttpExternalUrl($value);
}

function downloadExternalHostLabel(string $value): string
{
    $normalizedUrl = normalizeDownloadExternalUrl($value);
    if ($normalizedUrl === '') {
        return '';
    }

    $host = parse_url($normalizedUrl, PHP_URL_HOST);
    return is_string($host) ? strtolower(rtrim($host, '.')) : '';
}

/**
 * @param array<string, mixed> $download
 * @return array<string, mixed>
 */
function downloadRevisionSnapshot(array $download): array
{
    return [
        'title' => trim((string)($download['title'] ?? '')),
        'slug' => downloadSlug((string)($download['slug'] ?? '')),
        'download_type' => normalizeDownloadType((string)($download['download_type'] ?? 'document')),
        'category' => (string)($download['dl_category_id'] ?? ''),
        'excerpt' => trim((string)($download['excerpt'] ?? '')),
        'description' => trim((string)($download['description'] ?? '')),
        'version_label' => trim((string)($download['version_label'] ?? '')),
        'platform_label' => trim((string)($download['platform_label'] ?? '')),
        'license_label' => trim((string)($download['license_label'] ?? '')),
        'project_url' => normalizeDownloadExternalUrl((string)($download['project_url'] ?? '')),
        'release_date' => trim((string)($download['release_date'] ?? '')),
        'requirements' => trim((string)($download['requirements'] ?? '')),
        'checksum_sha256' => normalizeDownloadChecksum((string)($download['checksum_sha256'] ?? '')),
        'series_key' => normalizeDownloadSeriesKey((string)($download['series_key'] ?? '')),
        'download_series_id' => (string)((int)($download['download_series_id'] ?? 0)),
        'is_current_version' => (string)((int)($download['is_current_version'] ?? 0)),
        'external_url' => normalizeDownloadExternalUrl((string)($download['external_url'] ?? '')),
        'is_featured' => (string)((int)($download['is_featured'] ?? 0)),
        'is_published' => (string)((int)($download['is_published'] ?? 1)),
    ];
}

/**
 * @param array<string, mixed> $download
 * @return array<string, mixed>
 */
function hydrateDownloadPresentation(array $download): array
{
    $download['slug'] = downloadSlug((string)($download['slug'] ?? ''));
    $download['download_type'] = normalizeDownloadType((string)($download['download_type'] ?? 'document'));
    $download['download_type_label'] = downloadTypeLabel((string)$download['download_type']);
    $download['excerpt_plain'] = downloadExcerpt($download);
    $download['image_url'] = downloadImageUrl($download);
    $download['version_label'] = trim((string)($download['version_label'] ?? ''));
    $download['platform_label'] = trim((string)($download['platform_label'] ?? ''));
    $download['license_label'] = trim((string)($download['license_label'] ?? ''));
    $download['external_url'] = normalizeDownloadExternalUrl((string)($download['external_url'] ?? ''));
    $download['external_host_label'] = downloadExternalHostLabel((string)$download['external_url']);
    $download['project_url'] = normalizeDownloadExternalUrl((string)($download['project_url'] ?? ''));
    $download['release_date'] = trim((string)($download['release_date'] ?? ''));
    $download['requirements'] = trim((string)($download['requirements'] ?? ''));
    $download['checksum_sha256'] = normalizeDownloadChecksum((string)($download['checksum_sha256'] ?? ''));
    $download['series_key'] = normalizeDownloadSeriesKey((string)($download['series_key'] ?? ''));
    $download['download_series_id'] = isset($download['download_series_id']) ? (int)$download['download_series_id'] : null;
    if ((int)$download['download_series_id'] <= 0) {
        $download['download_series_id'] = null;
    }
    $download['is_current_version'] = (int)($download['is_current_version'] ?? 0) === 1 ? 1 : 0;
    $download['series_title'] = trim((string)($download['series_title'] ?? ''));
    $download['series_slug'] = downloadSeriesSlug((string)($download['series_slug'] ?? ''));
    $download['series_description'] = trim((string)($download['series_description'] ?? ''));
    $download['is_featured'] = (int)($download['is_featured'] ?? 0) === 1 ? 1 : 0;
    $download['download_count'] = max(0, (int)($download['download_count'] ?? 0));
    $download['external_click_count'] = max(0, (int)($download['external_click_count'] ?? 0));
    $download['source_interaction_count'] = $download['download_count'] + $download['external_click_count'];
    $download['release_date_label'] = $download['release_date'] !== ''
        ? formatCzechDate((string)$download['release_date'])
        : '';
    $download['download_count_label'] = $download['download_count'] === 1
        ? 'Staženo 1×'
        : 'Staženo ' . $download['download_count'] . '×';
    $download['external_click_count_label'] = $download['external_click_count'] === 1
        ? 'Externí zdroj otevřen 1×'
        : 'Externí zdroj otevřen ' . $download['external_click_count'] . '×';
    $download['has_external_url'] = $download['external_url'] !== '';
    $download['has_project_url'] = $download['project_url'] !== '';
    $download['filename'] = trim((string)($download['filename'] ?? ''));
    $download['original_name'] = trim((string)($download['original_name'] ?? ''));
    $download['has_file'] = $download['filename'] !== '';
    $download['has_requirements'] = $download['requirements'] !== '';
    $download['has_checksum'] = $download['checksum_sha256'] !== '';
    $download['is_publicly_visible'] = ((string)($download['status'] ?? 'published') === 'published')
        && (int)($download['is_published'] ?? 1) === 1;

    return $download;
}

function foodCardTypeLabel(string $type): string
{
    return $type === 'beverage' ? 'Nápojový lístek' : 'Jídelní lístek';
}

/**
 * @param array<string, mixed> $card
 */
function foodCardValidityLabel(array $card): string
{
    $from = !empty($card['valid_from']) ? formatCzechDate((string)$card['valid_from']) : null;
    $to = !empty($card['valid_to']) ? formatCzechDate((string)$card['valid_to']) : null;

    if ($from && $to) {
        return 'Platnost: ' . $from . ' – ' . $to;
    }
    if ($from) {
        return 'Platnost od ' . $from;
    }
    if ($to) {
        return 'Platnost do ' . $to;
    }

    return '';
}

/**
 * @param array<string, mixed> $card
 */
function foodCardMetaLabel(array $card): string
{
    $parts = [];
    $validityLabel = foodCardValidityLabel($card);
    if ($validityLabel !== '') {
        $parts[] = $validityLabel;
    }

    $description = trim((string)($card['description'] ?? ''));
    if ($description !== '') {
        $parts[] = $description;
    }

    return implode(' | ', $parts);
}

function foodCardPublicVisibilitySql(string $alias = ''): string
{
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';

    return "{$prefix}deleted_at IS NULL"
        . " AND {$prefix}status = 'published'"
        . " AND {$prefix}is_published = 1";
}

function foodCardCurrentWindowSql(string $alias = ''): string
{
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';

    return "({$prefix}valid_from IS NULL OR {$prefix}valid_from <= CURDATE())"
        . " AND ({$prefix}valid_to IS NULL OR {$prefix}valid_to >= CURDATE())";
}

function foodCardScopeVisibilitySql(string $scope, string $alias = ''): string
{
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';

    return match ($scope) {
        'upcoming' => "{$prefix}valid_from IS NOT NULL AND {$prefix}valid_from > CURDATE()",
        'archive' => "{$prefix}valid_to IS NOT NULL AND {$prefix}valid_to < CURDATE()",
        'all' => '1 = 1',
        default => foodCardCurrentWindowSql($alias),
    };
}

/**
 * @param array<string, mixed> $card
 */
function foodCardCurrentState(array $card): string
{
    $today = new \DateTimeImmutable('today');
    $validFrom = trim((string)($card['valid_from'] ?? ''));
    $validTo = trim((string)($card['valid_to'] ?? ''));

    if ($validFrom !== '') {
        try {
            if ((new \DateTimeImmutable($validFrom)) > $today) {
                return 'upcoming';
            }
        } catch (\Exception) {
            // Ignore invalid date in state classification.
        }
    }

    if ($validTo !== '') {
        try {
            if ((new \DateTimeImmutable($validTo)) < $today) {
                return 'archive';
            }
        } catch (\Exception) {
            // Ignore invalid date in state classification.
        }
    }

    return 'current';
}

function foodCardStateLabel(string $state): string
{
    return match ($state) {
        'upcoming' => 'Připravujeme',
        'archive' => 'Archivní',
        default => 'Platí nyní',
    };
}

/**
 * @return array<int, string>
 */
function foodAllergenDefinitions(): array
{
    return [
        1 => 'Obiloviny obsahující lepek',
        2 => 'Korýši',
        3 => 'Vejce',
        4 => 'Ryby',
        5 => 'Podzemnice olejná',
        6 => 'Sója',
        7 => 'Mléko',
        8 => 'Skořápkové plody',
        9 => 'Celer',
        10 => 'Hořčice',
        11 => 'Sezam',
        12 => 'Oxid siřičitý a siřičitany',
        13 => 'Vlčí bob',
        14 => 'Měkkýši',
    ];
}

/**
 * @return array<string, string>
 */
function foodDietaryFlagDefinitions(): array
{
    return [
        'vegetarian' => 'Vegetariánské',
        'vegan' => 'Veganské',
        'gluten_free' => 'Bez lepku',
        'lactose_free' => 'Bez laktózy',
        'spicy' => 'Pikantní',
        'alcohol' => 'Obsahuje alkohol',
    ];
}

/**
 * @param mixed $value
 * @return list<int>
 */
function normalizeFoodAllergenList($value): array
{
    $rawValues = is_array($value) ? $value : preg_split('/\s*,\s*/', (string)$value);
    if (!is_array($rawValues)) {
        return [];
    }

    $allowed = array_keys(foodAllergenDefinitions());
    $allergens = [];
    foreach ($rawValues as $rawValue) {
        $allergen = (int)$rawValue;
        if (in_array($allergen, $allowed, true) && !in_array($allergen, $allergens, true)) {
            $allergens[] = $allergen;
        }
    }
    sort($allergens);

    return $allergens;
}

/**
 * @param mixed $value
 * @return list<string>
 */
function normalizeFoodDietaryFlags($value): array
{
    $rawValues = is_array($value) ? $value : preg_split('/\s*,\s*/', (string)$value);
    if (!is_array($rawValues)) {
        return [];
    }

    $allowed = array_keys(foodDietaryFlagDefinitions());
    $flags = [];
    foreach ($rawValues as $rawValue) {
        $flag = trim((string)$rawValue);
        if (in_array($flag, $allowed, true) && !in_array($flag, $flags, true)) {
            $flags[] = $flag;
        }
    }

    return $flags;
}

/**
 * @param list<int> $allergens
 * @return list<string>
 */
function foodAllergenLabels(array $allergens): array
{
    $definitions = foodAllergenDefinitions();
    $labels = [];
    foreach ($allergens as $allergen) {
        if (isset($definitions[$allergen])) {
            $labels[] = $allergen . ' - ' . $definitions[$allergen];
        }
    }

    return $labels;
}

/**
 * @param list<string> $flags
 * @return list<string>
 */
function foodDietaryFlagLabels(array $flags): array
{
    $definitions = foodDietaryFlagDefinitions();
    $labels = [];
    foreach ($flags as $flag) {
        if (isset($definitions[$flag])) {
            $labels[] = $definitions[$flag];
        }
    }

    return $labels;
}

/**
 * @param array<string,mixed> $source
 * @return array{dietary_flags:list<string>,excluded_allergens:list<int>,available_only:bool,active:bool}
 */
function normalizeFoodStructuredFilters(array $source): array
{
    $dietaryFlags = normalizeFoodDietaryFlags($source['dieta'] ?? []);
    $excludedAllergens = normalizeFoodAllergenList($source['bez_alergenu'] ?? []);
    $availableOnly = trim((string)($source['pouze_dostupne'] ?? '')) === '1';

    return [
        'dietary_flags' => $dietaryFlags,
        'excluded_allergens' => $excludedAllergens,
        'available_only' => $availableOnly,
        'active' => $dietaryFlags !== [] || $excludedAllergens !== [] || $availableOnly,
    ];
}

/**
 * @param array{dietary_flags?:list<string>,excluded_allergens?:list<int>,available_only?:bool} $filters
 */
function foodStructuredFiltersAreActive(array $filters): bool
{
    return !empty($filters['dietary_flags'])
        || !empty($filters['excluded_allergens'])
        || !empty($filters['available_only']);
}

/**
 * @param array{dietary_flags?:list<string>,excluded_allergens?:list<int>,available_only?:bool} $filters
 * @return array<string,mixed>
 */
function foodStructuredFilterQueryParams(array $filters): array
{
    $query = [];
    $dietaryFlags = normalizeFoodDietaryFlags($filters['dietary_flags'] ?? []);
    $excludedAllergens = normalizeFoodAllergenList($filters['excluded_allergens'] ?? []);
    if ($dietaryFlags !== []) {
        $query['dieta'] = $dietaryFlags;
    }
    if ($excludedAllergens !== []) {
        $query['bez_alergenu'] = $excludedAllergens;
    }
    if (!empty($filters['available_only'])) {
        $query['pouze_dostupne'] = '1';
    }

    return $query;
}

/**
 * @param array{dietary_flags?:list<string>,excluded_allergens?:list<int>,available_only?:bool} $filters
 * @return list<string>
 */
function foodStructuredFilterSummary(array $filters): array
{
    $summary = [];
    $dietaryLabels = foodDietaryFlagLabels(normalizeFoodDietaryFlags($filters['dietary_flags'] ?? []));
    if ($dietaryLabels !== []) {
        $summary[] = 'štítky: ' . implode(', ', $dietaryLabels);
    }

    $excludedAllergens = normalizeFoodAllergenList($filters['excluded_allergens'] ?? []);
    if ($excludedAllergens !== []) {
        $summary[] = 'bez alergenů: ' . implode(', ', foodAllergenLabels($excludedAllergens));
    }

    if (!empty($filters['available_only'])) {
        $summary[] = 'jen dostupné položky';
    }

    return $summary;
}

function normalizeFoodServingDate(string $value): string
{
    $value = trim($value);
    if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return '';
    }

    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = \DateTimeImmutable::getLastErrors();
    if (!$date instanceof \DateTimeImmutable || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return '';
    }

    return $date->format('Y-m-d');
}

function foodServingDateLabel(string $date): string
{
    $date = normalizeFoodServingDate($date);
    if ($date === '') {
        return '';
    }

    return formatCzechDate($date);
}

function normalizeFoodServingTime(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value, $match) !== 1) {
        return '';
    }

    return $match[1] . ':' . $match[2];
}

/**
 * @param array<string,mixed> $section
 */
function foodSectionServingLabel(array $section): string
{
    $parts = [];
    $date = normalizeFoodServingDate((string)($section['serving_date'] ?? ''));
    if ($date !== '') {
        $parts[] = foodServingDateLabel($date);
    }

    $timeFrom = normalizeFoodServingTime(substr((string)($section['serving_time_from'] ?? ''), 0, 5));
    $timeTo = normalizeFoodServingTime(substr((string)($section['serving_time_to'] ?? ''), 0, 5));
    if ($timeFrom !== '' && $timeTo !== '') {
        $parts[] = $timeFrom . '–' . $timeTo;
    } elseif ($timeFrom !== '') {
        $parts[] = 'od ' . $timeFrom;
    } elseif ($timeTo !== '') {
        $parts[] = 'do ' . $timeTo;
    }

    $note = trim((string)($section['serving_note'] ?? ''));
    if ($note !== '') {
        $parts[] = $note;
    }

    return implode(', ', $parts);
}

/**
 * @param list<array<string,mixed>> $sections
 * @return list<array<string,mixed>>
 */
function foodFilterSectionsByServingDate(array $sections, string $servingDate): array
{
    $servingDate = normalizeFoodServingDate($servingDate);
    if ($servingDate === '') {
        return $sections;
    }

    return array_values(array_filter(
        $sections,
        static fn (array $section): bool => normalizeFoodServingDate((string)($section['serving_date'] ?? '')) === $servingDate
    ));
}

/**
 * @return array{sql:string,params:list<string>}
 */
function foodServingDateExistsSql(string $servingDate, string $cardAlias = 'cms_food_cards'): array
{
    $servingDate = normalizeFoodServingDate($servingDate);
    if ($servingDate === '') {
        return ['sql' => '', 'params' => []];
    }

    return [
        'sql' => "EXISTS (SELECT 1 FROM cms_food_sections fs WHERE fs.card_id = {$cardAlias}.id AND fs.serving_date = ?)",
        'params' => [$servingDate],
    ];
}

/**
 * @return array<string,string>
 */
function foodServingDateQueryParams(string $servingDate): array
{
    $servingDate = normalizeFoodServingDate($servingDate);

    return $servingDate !== '' ? ['den' => $servingDate] : [];
}

/**
 * @return string|null|false
 */
function normalizeFoodNutritionDecimalInput(string $value)
{
    $normalized = str_replace(',', '.', trim($value));
    if ($normalized === '') {
        return null;
    }
    if (!preg_match('/^\d{1,5}(?:\.\d{1,2})?$/', $normalized)) {
        return false;
    }

    return number_format((float)$normalized, 2, '.', '');
}

/**
 * @return int|null|false
 */
function normalizeFoodNutritionIntegerInput(string $value)
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    if (!preg_match('/^\d{1,6}$/', $value)) {
        return false;
    }

    return (int)$value;
}

function foodNutritionDecimalLabel(?string $value, string $unit): string
{
    $value = $value !== null ? trim($value) : '';
    if ($value === '') {
        return '';
    }
    $number = (float)$value;
    $formatted = number_format($number, (floor($number) === $number ? 0 : 2), ',', ' ');

    return $formatted . ' ' . $unit;
}

/**
 * @param array<string,mixed> $item
 * @return list<array{label:string,value:string}>
 */
function foodItemNutritionLabels(array $item): array
{
    $labels = [];
    $portion = trim((string)($item['portion_label'] ?? ''));
    if ($portion !== '') {
        $labels[] = ['label' => 'Porce', 'value' => $portion];
    }
    foreach ([
        'energy_kj' => ['Energie', 'kJ'],
        'energy_kcal' => ['Energie', 'kcal'],
    ] as $key => [$label, $unit]) {
        $value = $item[$key] ?? null;
        if ($value !== null && $value !== '') {
            $labels[] = ['label' => $label, 'value' => (int)$value . ' ' . $unit];
        }
    }
    foreach ([
        'protein_g' => 'Bílkoviny',
        'carbs_g' => 'Sacharidy',
        'fat_g' => 'Tuky',
        'salt_g' => 'Sůl',
    ] as $key => $label) {
        $value = foodNutritionDecimalLabel(isset($item[$key]) ? (string)$item[$key] : null, 'g');
        if ($value !== '') {
            $labels[] = ['label' => $label, 'value' => $value];
        }
    }

    return $labels;
}

/**
 * @param array<string,mixed> $item
 * @param array{dietary_flags?:list<string>,excluded_allergens?:list<int>,available_only?:bool} $filters
 */
function foodItemMatchesStructuredFilters(array $item, array $filters): bool
{
    if (!empty($filters['available_only']) && (int)($item['is_available'] ?? 1) !== 1) {
        return false;
    }

    $itemDietaryFlags = normalizeFoodDietaryFlags($item['dietary_flag_values'] ?? ($item['dietary_flags'] ?? []));
    foreach (normalizeFoodDietaryFlags($filters['dietary_flags'] ?? []) as $requiredFlag) {
        if (!in_array($requiredFlag, $itemDietaryFlags, true)) {
            return false;
        }
    }

    $itemAllergens = normalizeFoodAllergenList($item['allergen_values'] ?? ($item['allergens'] ?? []));
    foreach (normalizeFoodAllergenList($filters['excluded_allergens'] ?? []) as $excludedAllergen) {
        if (in_array($excludedAllergen, $itemAllergens, true)) {
            return false;
        }
    }

    return true;
}

/**
 * @param list<array<string,mixed>> $sections
 * @param array{dietary_flags?:list<string>,excluded_allergens?:list<int>,available_only?:bool} $filters
 * @return list<array<string,mixed>>
 */
function foodFilterStructuredSections(array $sections, array $filters): array
{
    if (!foodStructuredFiltersAreActive($filters)) {
        return $sections;
    }

    $filteredSections = [];
    foreach ($sections as $section) {
        $items = [];
        foreach (($section['items'] ?? []) as $item) {
            if (is_array($item) && foodItemMatchesStructuredFilters($item, $filters)) {
                $items[] = $item;
            }
        }
        if ($items === []) {
            continue;
        }
        $section['items'] = $items;
        $section['item_count'] = count($items);
        $filteredSections[] = $section;
    }

    return $filteredSections;
}

/**
 * @param list<array<string,mixed>> $sections
 * @return list<array{number:int,label:string}>
 */
function foodStructuredAllergenLegend(array $sections): array
{
    $usedAllergens = [];
    foreach ($sections as $section) {
        foreach (($section['items'] ?? []) as $item) {
            foreach (normalizeFoodAllergenList($item['allergen_values'] ?? ($item['allergens'] ?? [])) as $allergen) {
                $usedAllergens[$allergen] = true;
            }
        }
    }
    if ($usedAllergens === []) {
        return [];
    }

    $definitions = foodAllergenDefinitions();
    $numbers = array_keys($usedAllergens);
    sort($numbers);
    $legend = [];
    foreach ($numbers as $number) {
        if (isset($definitions[$number])) {
            $legend[] = ['number' => (int)$number, 'label' => $definitions[$number]];
        }
    }

    return $legend;
}

/**
 * @param array{dietary_flags?:list<string>,excluded_allergens?:list<int>,available_only?:bool} $filters
 * @return array{sql:string,params:list<int|string>}
 */
function foodStructuredFilterExistsSql(array $filters, string $cardAlias = 'cms_food_cards'): array
{
    if (!foodStructuredFiltersAreActive($filters)) {
        return ['sql' => '', 'params' => []];
    }

    $conditions = ["fi.card_id = {$cardAlias}.id"];
    $params = [];
    if (!empty($filters['available_only'])) {
        $conditions[] = 'fi.is_available = 1';
    }
    foreach (normalizeFoodDietaryFlags($filters['dietary_flags'] ?? []) as $flag) {
        $conditions[] = 'FIND_IN_SET(?, fi.dietary_flags) > 0';
        $params[] = $flag;
    }
    foreach (normalizeFoodAllergenList($filters['excluded_allergens'] ?? []) as $allergen) {
        $conditions[] = "(fi.allergens = '' OR FIND_IN_SET(?, fi.allergens) = 0)";
        $params[] = $allergen;
    }

    return [
        'sql' => 'EXISTS (SELECT 1 FROM cms_food_items fi WHERE ' . implode(' AND ', $conditions) . ')',
        'params' => $params,
    ];
}

/**
 * @return string|null|false
 */
function normalizeFoodPriceInput(string $value)
{
    $normalized = str_replace(',', '.', trim($value));
    if ($normalized === '') {
        return null;
    }
    if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $normalized)) {
        return false;
    }

    return number_format((float)$normalized, 2, '.', '');
}

function normalizeFoodCurrency(string $value): string
{
    $currency = strtoupper(trim($value));
    if (!preg_match('/^[A-Z]{3}$/', $currency)) {
        return 'CZK';
    }

    return $currency;
}

function foodPriceLabel(?string $amount, string $currency = 'CZK', string $note = ''): string
{
    $amount = $amount !== null ? trim($amount) : '';
    if ($amount === '') {
        return trim($note);
    }

    $number = (float)$amount;
    $formatted = number_format($number, (floor($number) === $number ? 0 : 2), ',', ' ');
    $normalizedCurrency = normalizeFoodCurrency($currency);
    $currencyLabel = $normalizedCurrency === 'CZK' ? 'Kč' : $normalizedCurrency;
    $label = trim($formatted . ' ' . $currencyLabel);
    $note = trim($note);
    if ($note !== '') {
        $label .= ' (' . $note . ')';
    }

    return $label;
}

/**
 * @param array<string, mixed> $item
 * @return array<string, mixed>
 */
function hydrateFoodItemPresentation(array $item): array
{
    $allergens = normalizeFoodAllergenList($item['allergens'] ?? '');
    $dietaryFlags = normalizeFoodDietaryFlags($item['dietary_flags'] ?? '');
    $priceAmount = $item['price_amount'] !== null && $item['price_amount'] !== ''
        ? number_format((float)$item['price_amount'], 2, '.', '')
        : null;

    $item['allergens'] = implode(',', $allergens);
    $item['dietary_flags'] = implode(',', $dietaryFlags);
    $item['allergen_values'] = $allergens;
    $item['allergen_labels'] = foodAllergenLabels($allergens);
    $item['dietary_flag_values'] = $dietaryFlags;
    $item['dietary_flag_labels'] = foodDietaryFlagLabels($dietaryFlags);
    $item['price_amount'] = $priceAmount;
    $item['price_currency'] = normalizeFoodCurrency((string)($item['price_currency'] ?? 'CZK'));
    $item['price_note'] = trim((string)($item['price_note'] ?? ''));
    $item['price_label'] = foodPriceLabel($priceAmount, (string)$item['price_currency'], (string)$item['price_note']);
    $item['portion_label'] = mb_substr(trim((string)($item['portion_label'] ?? '')), 0, 80);
    $item['energy_kj'] = isset($item['energy_kj']) && $item['energy_kj'] !== '' ? max(0, (int)$item['energy_kj']) : null;
    $item['energy_kcal'] = isset($item['energy_kcal']) && $item['energy_kcal'] !== '' ? max(0, (int)$item['energy_kcal']) : null;
    foreach (['protein_g', 'carbs_g', 'fat_g', 'salt_g'] as $nutritionKey) {
        $item[$nutritionKey] = isset($item[$nutritionKey]) && $item[$nutritionKey] !== ''
            ? number_format(max(0.0, (float)$item[$nutritionKey]), 2, '.', '')
            : null;
    }
    $item['nutrition_labels'] = foodItemNutritionLabels($item);
    $item['has_nutrition'] = $item['nutrition_labels'] !== [];
    $item['is_available'] = (int)($item['is_available'] ?? 1) === 1 ? 1 : 0;
    $item['media_id'] = (int)($item['media_id'] ?? 0);
    $item['image_alt_text'] = mb_substr(trim((string)($item['image_alt_text'] ?? '')), 0, 255);
    $item['image_url'] = '';
    $item['image_thumb_url'] = '';
    $item['image_alt'] = '';

    $media = null;
    if ($item['media_id'] > 0 && isset($item['media_filename'], $item['media_mime_type'])) {
        $media = [
            'id' => $item['media_id'],
            'filename' => (string)$item['media_filename'],
            'original_name' => (string)($item['media_original_name'] ?? ''),
            'mime_type' => (string)$item['media_mime_type'],
            'visibility' => (string)($item['media_visibility'] ?? 'private'),
            'alt_text' => (string)($item['media_alt_text'] ?? ''),
        ];
    } elseif ($item['media_id'] > 0 && function_exists('mediaGetById')) {
        $media = mediaGetById($item['media_id']);
    }

    if (is_array($media)
        && function_exists('mediaIsPublic')
        && function_exists('mediaCanPreviewImage')
        && function_exists('mediaFileUrl')
        && mediaIsPublic($media)
        && mediaCanPreviewImage($media)
    ) {
        $fallbackAlt = trim((string)($media['alt_text'] ?? ''));
        if ($fallbackAlt === '') {
            $fallbackAlt = trim((string)($item['title'] ?? ''));
        }
        $item['image_url'] = mediaFileUrl($media);
        $item['image_thumb_url'] = function_exists('mediaThumbUrl') ? mediaThumbUrl($media) : $item['image_url'];
        $item['image_alt'] = $item['image_alt_text'] !== '' ? $item['image_alt_text'] : $fallbackAlt;
    }

    return $item;
}

/**
 * @return list<array<string, mixed>>
 */
function foodLoadCardSections(PDO $pdo, int $cardId): array
{
    $sectionStmt = $pdo->prepare(
        "SELECT id, card_id, title, description, serving_date, serving_time_from, serving_time_to, serving_note, sort_order
         FROM cms_food_sections
         WHERE card_id = ?
         ORDER BY sort_order, id"
    );
    $sectionStmt->execute([$cardId]);
    $sections = $sectionStmt->fetchAll();
    if ($sections === []) {
        return [];
    }

    $itemStmt = $pdo->prepare(
        "SELECT fi.id, fi.card_id, fi.section_id, fi.title, fi.description, fi.price_amount, fi.price_currency,
                fi.price_note, fi.portion_label, fi.energy_kj, fi.energy_kcal, fi.protein_g, fi.carbs_g, fi.fat_g, fi.salt_g,
                fi.media_id, fi.image_alt_text, fi.allergens, fi.dietary_flags, fi.is_available, fi.sort_order,
                m.filename AS media_filename, m.original_name AS media_original_name, m.mime_type AS media_mime_type,
                m.visibility AS media_visibility, m.alt_text AS media_alt_text
         FROM cms_food_items fi
         LEFT JOIN cms_media m ON m.id = fi.media_id
         WHERE fi.card_id = ?
         ORDER BY fi.section_id, fi.sort_order, fi.id"
    );
    $itemStmt->execute([$cardId]);
    $itemsBySection = [];
    foreach ($itemStmt->fetchAll() as $item) {
        $itemsBySection[(int)$item['section_id']][] = hydrateFoodItemPresentation($item);
    }

    $result = [];
    $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
    foreach ($sections as $section) {
        $sectionId = (int)$section['id'];
        $section['serving_date'] = normalizeFoodServingDate((string)($section['serving_date'] ?? ''));
        $section['serving_time_from'] = normalizeFoodServingTime(substr((string)($section['serving_time_from'] ?? ''), 0, 5));
        $section['serving_time_to'] = normalizeFoodServingTime(substr((string)($section['serving_time_to'] ?? ''), 0, 5));
        $section['serving_note'] = mb_substr(trim((string)($section['serving_note'] ?? '')), 0, 255);
        $section['serving_label'] = foodSectionServingLabel($section);
        $section['is_today'] = $section['serving_date'] !== '' && $section['serving_date'] === $today;
        $section['items'] = $itemsBySection[$sectionId] ?? [];
        $section['item_count'] = count($section['items']);
        $result[] = $section;
    }

    return $result;
}

/**
 * @param list<array<string, mixed>> $cards
 * @return list<array<string, mixed>>
 */
function foodAttachSectionsToCards(PDO $pdo, array $cards): array
{
    foreach ($cards as &$card) {
        $card['sections'] = foodLoadCardSections($pdo, (int)($card['id'] ?? 0));
        $card['has_structured_items'] = foodCardHasStructuredItems($card['sections']);
        $card['structured_item_count'] = foodCardStructuredItemCount($card['sections']);
    }
    unset($card);

    return $cards;
}

/**
 * @param array<string,mixed> $card
 * @param array{dietary_flags?:list<string>,excluded_allergens?:list<int>,available_only?:bool} $filters
 * @return array<string,mixed>
 */
function foodApplyStructuredFiltersToCard(array $card, array $filters): array
{
    $sourceSections = is_array($card['sections'] ?? null) ? $card['sections'] : [];
    $card['source_sections'] = $sourceSections;
    $card['structured_filter_active'] = foodStructuredFiltersAreActive($filters);
    $card['has_structured_source_items'] = foodCardHasStructuredItems($sourceSections);
    if (!empty($card['structured_filter_active'])) {
        $card['sections'] = foodFilterStructuredSections($sourceSections, $filters);
    } else {
        $card['sections'] = $sourceSections;
    }
    $card['has_structured_items'] = foodCardHasStructuredItems($card['sections']);
    $card['structured_item_count'] = foodCardStructuredItemCount($card['sections']);

    return $card;
}

/**
 * @param array<string,mixed> $card
 * @return array<string,mixed>
 */
function foodApplyServingDateToCard(array $card, string $servingDate): array
{
    $servingDate = normalizeFoodServingDate($servingDate);
    $sourceSections = is_array($card['sections'] ?? null) ? $card['sections'] : [];
    $card['serving_date_filter'] = $servingDate;
    $card['serving_date_filter_active'] = $servingDate !== '';
    $card['has_structured_source_items'] = $card['has_structured_source_items'] ?? foodCardHasStructuredItems($sourceSections);
    if ($servingDate === '') {
        return $card;
    }
    $card['source_sections'] = $card['source_sections'] ?? $sourceSections;
    $card['sections'] = foodFilterSectionsByServingDate($sourceSections, $servingDate);
    $card['has_structured_items'] = foodCardHasStructuredItems($card['sections']);
    $card['structured_item_count'] = foodCardStructuredItemCount($card['sections']);

    return $card;
}

/**
 * @param array<string,mixed> $card
 */
function foodCardHasTodaySection(array $card): bool
{
    foreach (($card['sections'] ?? []) as $section) {
        if (!empty($section['is_today'])) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<array<string, mixed>> $sections
 */
function foodCardHasStructuredItems(array $sections): bool
{
    foreach ($sections as $section) {
        if (!empty($section['items'])) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<array<string, mixed>> $sections
 */
function foodCardStructuredItemCount(array $sections): int
{
    $count = 0;
    foreach ($sections as $section) {
        $count += count($section['items'] ?? []);
    }

    return $count;
}

/**
 * @param list<array<string, mixed>> $sections
 * @return list<string>
 */
function foodCardItemPreviewLabels(array $sections, int $limit = 3): array
{
    $labels = [];
    foreach ($sections as $section) {
        foreach ($section['items'] ?? [] as $item) {
            $labels[] = (string)($item['title'] ?? '');
            if (count($labels) >= $limit) {
                return array_values(array_filter($labels, static fn (string $label): bool => $label !== ''));
            }
        }
    }

    return array_values(array_filter($labels, static fn (string $label): bool => $label !== ''));
}

/**
 * @param array<string, mixed> $card
 * @return array<string, mixed>
 */
function foodRevisionSnapshot(array $card): array
{
    return [
        'type' => (string)($card['type'] ?? ''),
        'title' => (string)($card['title'] ?? ''),
        'slug' => (string)($card['slug'] ?? ''),
        'description' => (string)($card['description'] ?? ''),
        'content' => (string)($card['content'] ?? ''),
        'valid_from' => (string)($card['valid_from'] ?? ''),
        'valid_to' => (string)($card['valid_to'] ?? ''),
        'orders_enabled' => (string)(int)($card['orders_enabled'] ?? 0),
        'order_email' => (string)($card['order_email'] ?? ''),
        'order_instructions' => (string)($card['order_instructions'] ?? ''),
        'is_current' => (string)(int)($card['is_current'] ?? 0),
        'is_published' => (string)(int)($card['is_published'] ?? 0),
        'status' => (string)($card['status'] ?? 'published'),
    ];
}

/**
 * @param array<string, mixed> $card
 */
function foodCardStructuredData(array $card): string
{
    $name = trim((string)($card['title'] ?? ''));
    if ($name === '') {
        return '';
    }

    $description = trim((string)($card['description'] ?? ''));
    if ($description === '') {
        $description = trim(preg_replace('/\s+/u', ' ', strip_tags((string)($card['content'] ?? ''))) ?? '');
    }
    if (mb_strlen($description) > 300) {
        $description = mb_substr($description, 0, 297) . '...';
    }

    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'Menu',
        'name' => $name,
        'url' => foodCardPublicUrl($card),
        'description' => $description,
        'inLanguage' => 'cs-CZ',
    ];

    $createdAt = trim((string)($card['created_at'] ?? ''));
    if ($createdAt !== '') {
        try {
            $data['datePublished'] = (new \DateTimeImmutable($createdAt))->format(DATE_ATOM);
        } catch (\Exception) {
            // Ignore invalid published date in structured data.
        }
    }

    $updatedAt = trim((string)($card['updated_at'] ?? ''));
    if ($updatedAt !== '') {
        try {
            $data['dateModified'] = (new \DateTimeImmutable($updatedAt))->format(DATE_ATOM);
        } catch (\Exception) {
            // Ignore invalid updated date in structured data.
        }
    }

    $validFrom = trim((string)($card['valid_from'] ?? ''));
    if ($validFrom !== '') {
        try {
            $data['temporalCoverage'] = (new \DateTimeImmutable($validFrom))->format('Y-m-d');
            $validTo = trim((string)($card['valid_to'] ?? ''));
            if ($validTo !== '') {
                $data['temporalCoverage'] .= '/' . (new \DateTimeImmutable($validTo))->format('Y-m-d');
            }
        } catch (\Exception) {
            unset($data['temporalCoverage']);
        }
    }

    $menuSections = [];
    foreach (($card['sections'] ?? []) as $section) {
        $menuItems = [];
        foreach (($section['items'] ?? []) as $item) {
            $itemName = trim((string)($item['title'] ?? ''));
            if ($itemName === '') {
                continue;
            }

            $menuItem = [
                '@type' => 'MenuItem',
                'name' => $itemName,
            ];
            $itemDescription = trim((string)($item['description'] ?? ''));
            if ($itemDescription !== '') {
                $menuItem['description'] = $itemDescription;
            }
            $itemImageUrl = trim((string)($item['image_url'] ?? ''));
            if ($itemImageUrl !== '') {
                $menuItem['image'] = siteUrl(str_starts_with($itemImageUrl, BASE_URL) ? substr($itemImageUrl, strlen(BASE_URL)) : $itemImageUrl);
            }
            if (!empty($item['has_nutrition'])) {
                $nutrition = ['@type' => 'NutritionInformation'];
                if (!empty($item['energy_kcal'])) {
                    $nutrition['calories'] = (int)$item['energy_kcal'] . ' kcal';
                }
                if (!empty($item['protein_g'])) {
                    $nutrition['proteinContent'] = foodNutritionDecimalLabel((string)$item['protein_g'], 'g');
                }
                if (!empty($item['carbs_g'])) {
                    $nutrition['carbohydrateContent'] = foodNutritionDecimalLabel((string)$item['carbs_g'], 'g');
                }
                if (!empty($item['fat_g'])) {
                    $nutrition['fatContent'] = foodNutritionDecimalLabel((string)$item['fat_g'], 'g');
                }
                if (!empty($item['salt_g'])) {
                    $nutrition['sodiumContent'] = foodNutritionDecimalLabel((string)$item['salt_g'], 'g soli');
                }
                if (count($nutrition) > 1) {
                    $menuItem['nutrition'] = $nutrition;
                }
            }
            $priceAmount = trim((string)($item['price_amount'] ?? ''));
            if ($priceAmount !== '') {
                $menuItem['offers'] = [
                    '@type' => 'Offer',
                    'price' => $priceAmount,
                    'priceCurrency' => normalizeFoodCurrency((string)($item['price_currency'] ?? 'CZK')),
                    'availability' => (int)($item['is_available'] ?? 1) === 1
                        ? 'https://schema.org/InStock'
                        : 'https://schema.org/OutOfStock',
                ];
            }
            $menuItems[] = $menuItem;
        }

        if ($menuItems === []) {
            continue;
        }

        $menuSection = [
            '@type' => 'MenuSection',
            'name' => trim((string)($section['title'] ?? '')),
            'hasMenuItem' => $menuItems,
        ];
        $sectionDescription = trim((string)($section['description'] ?? ''));
        if ($sectionDescription !== '') {
            $menuSection['description'] = $sectionDescription;
        }
        $menuSections[] = $menuSection;
    }
    if ($menuSections !== []) {
        $data['hasMenuSection'] = $menuSections;
    }

    return structuredDataScript($data);
}

/**
 * @param array<string, mixed> $card
 * @return array<string, mixed>
 */
function hydrateFoodCardPresentation(array $card): array
{
    $card['slug'] = foodCardSlug((string)($card['slug'] ?? ''));
    $card['type'] = in_array((string)($card['type'] ?? 'food'), ['food', 'beverage'], true)
        ? (string)$card['type']
        : 'food';
    $card['type_label'] = foodCardTypeLabel((string)$card['type']);
    $card['validity_label'] = foodCardValidityLabel($card);
    $card['meta_label'] = foodCardMetaLabel($card);
    $card['state_key'] = foodCardCurrentState($card);
    $card['state_label'] = foodCardStateLabel((string)$card['state_key']);
    $card['public_path'] = foodCardPublicPath($card);
    $card['orders_enabled'] = (int)($card['orders_enabled'] ?? 0) === 1 ? 1 : 0;
    $card['order_email'] = trim((string)($card['order_email'] ?? ''));
    $card['order_instructions'] = trim((string)($card['order_instructions'] ?? ''));
    $card['is_publicly_visible'] = ((string)($card['status'] ?? 'published') === 'published')
        && (int)($card['is_published'] ?? 1) === 1;
    $card['is_temporally_active'] = (string)$card['state_key'] === 'current';
    $card['sections'] = is_array($card['sections'] ?? null) ? $card['sections'] : [];
    $card['has_structured_items'] = foodCardHasStructuredItems($card['sections']);
    $card['structured_item_count'] = foodCardStructuredItemCount($card['sections']);

    return $card;
}

/**
 * @return array<string,string>
 */
function foodOrderStatusLabels(): array
{
    return [
        'new' => 'Nová',
        'confirmed' => 'Potvrzená',
        'rejected' => 'Odmítnutá',
        'completed' => 'Vyřízená',
        'cancelled' => 'Zrušená',
    ];
}

function normalizeFoodOrderStatus(string $status): string
{
    return array_key_exists($status, foodOrderStatusLabels()) ? $status : 'new';
}

function foodOrderStatusLabel(string $status): string
{
    $status = normalizeFoodOrderStatus($status);

    return foodOrderStatusLabels()[$status];
}

/**
 * @param array<string,mixed> $card
 */
function foodCardOrderRecipient(array $card): string
{
    $email = trim((string)($card['order_email'] ?? ''));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
    }
    foreach ([getSetting('contact_email', ''), getSetting('admin_email', '')] as $fallback) {
        $fallback = trim((string)$fallback);
        if ($fallback !== '' && filter_var($fallback, FILTER_VALIDATE_EMAIL)) {
            return $fallback;
        }
    }

    return '';
}

/**
 * @param list<array<string,mixed>> $sections
 * @return list<array<string,mixed>>
 */
function foodOrderSelectableItems(array $sections): array
{
    $items = [];
    foreach ($sections as $section) {
        foreach (($section['items'] ?? []) as $item) {
            if (is_array($item) && (int)($item['is_available'] ?? 1) === 1) {
                $items[] = $item;
            }
        }
    }

    return $items;
}

/**
 * @param array<string,mixed> $card
 */
function foodCardCanAcceptOrders(array $card): bool
{
    return !empty($card['orders_enabled'])
        && !empty($card['is_publicly_visible'])
        && foodOrderSelectableItems(is_array($card['sections'] ?? null) ? $card['sections'] : []) !== [];
}

function uniqueFoodOrderReferenceCode(PDO $pdo): string
{
    $date = date('Ymd');
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $code = 'JDL-' . $date . '-' . strtoupper(bin2hex(random_bytes(2)));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cms_food_orders WHERE reference_code = ?");
        $stmt->execute([$code]);
        if ((int)$stmt->fetchColumn() === 0) {
            return $code;
        }
    }

    return 'JDL-' . $date . '-' . strtoupper(bin2hex(random_bytes(4)));
}

/**
 * @param array<int,array<string,mixed>> $itemsById
 * @param array<int,int> $quantities
 * @return array{items:list<array<string,mixed>>,total:string|null,currency:string}
 */
function foodBuildOrderSnapshot(array $itemsById, array $quantities): array
{
    $items = [];
    $total = 0.0;
    $hasPricedItem = false;
    $currency = 'CZK';
    $sortOrder = 10;
    foreach ($quantities as $itemId => $quantity) {
        if ($quantity <= 0 || !isset($itemsById[$itemId])) {
            continue;
        }
        $item = $itemsById[$itemId];
        $unitPrice = $item['price_amount'] !== null && $item['price_amount'] !== '' ? (string)$item['price_amount'] : null;
        $itemCurrency = normalizeFoodCurrency((string)($item['price_currency'] ?? 'CZK'));
        if ($unitPrice !== null) {
            $hasPricedItem = true;
            $total += ((float)$unitPrice) * $quantity;
            $currency = $itemCurrency;
        }
        $items[] = [
            'item_id' => $itemId,
            'item_title' => (string)($item['title'] ?? ''),
            'quantity' => $quantity,
            'unit_price_amount' => $unitPrice,
            'price_currency' => $itemCurrency,
            'price_note' => (string)($item['price_note'] ?? ''),
            'sort_order' => $sortOrder,
        ];
        $sortOrder += 10;
    }

    return [
        'items' => $items,
        'total' => $hasPricedItem ? number_format($total, 2, '.', '') : null,
        'currency' => $currency,
    ];
}

/**
 * @param array<string, mixed> $blog
 */
function blogLogoUrl(array $blog): string
{
    $filename = trim((string)($blog['logo_file'] ?? ''));
    if ($filename === '') {
        return '';
    }

    return BASE_URL . '/uploads/blogs/' . rawurlencode($filename);
}

/**
 * @param array<string, mixed> $blog
 */
function blogLogoAltText(array $blog): string
{
    if (blogLogoUrl($blog) === '') {
        return '';
    }

    return trim((string)($blog['logo_alt_text'] ?? ''));
}

function deleteBlogLogoFile(string $filename): void
{
    $filename = basename($filename);
    if ($filename === '') {
        return;
    }

    $path = dirname(__DIR__) . '/uploads/blogs/' . $filename;
    if (is_file($path)) {
        if (!unlink($path)) {
            presentationLogFileDeleteFailure('blog_logo', $path);
        }
    }
}

/**
 * @return array{filename:string,uploaded:bool,error:string}
 */
/**
 * @param array<string, mixed> $file
 * @return array<string, mixed>
 */
function uploadBlogLogo(array $file, string $existingFilename = ''): array
{
    return storePresentationUploadedFile($file, $existingFilename, [
        'upload_error' => 'Logo blogu se nepodařilo nahrát.',
        'invalid_upload_error' => 'Logo blogu se nepodařilo zpracovat.',
        'allowed_mime_map' => presentationImageMimeMap(),
        'unsupported_type_error' => 'Logo blogu musí být ve formátu JPEG, PNG, GIF nebo WebP.',
        'directory' => dirname(__DIR__) . '/uploads/blogs/',
        'mkdir_error' => 'Adresář pro loga blogů se nepodařilo vytvořit.',
        'move_error' => 'Logo blogu se nepodařilo uložit.',
        'prefix' => 'blog_logo_',
        'generate_webp' => true,
        'delete_callback' => 'deleteBlogLogoFile',
    ]);
}

/**
 * @param array<string, mixed> $place
 */
function placeImageUrl(array $place): string
{
    $requestPath = placeImageRequestPath($place);
    if ($requestPath === '') {
        return '';
    }

    return BASE_URL . $requestPath;
}

/**
 * @param array<string, mixed> $place
 */
function placeImageRequestPath(array $place): string
{
    $filename = trim((string)($place['image_file'] ?? ''));
    $placeId = (int)($place['id'] ?? 0);
    if ($filename === '' || $placeId <= 0) {
        return '';
    }

    return '/places/image.php?id=' . $placeId;
}

function deletePlaceImageFile(string $filename): void
{
    $filename = basename($filename);
    if ($filename === '') {
        return;
    }

    $path = dirname(__DIR__) . '/uploads/places/' . $filename;
    if (is_file($path)) {
        if (!unlink($path)) {
            presentationLogFileDeleteFailure('place_image', $path);
        }
    }
}

/**
 * @return array{filename:string,uploaded:bool,error:string}
 */
/**
 * @param array<string, mixed> $file
 * @return array<string, mixed>
 */
function uploadPlaceImage(array $file, string $existingFilename = ''): array
{
    return storePresentationUploadedFile($file, $existingFilename, [
        'upload_error' => 'Obrázek se nepodařilo nahrát.',
        'invalid_upload_error' => 'Obrázek se nepodařilo zpracovat.',
        'allowed_mime_map' => presentationImageMimeMap(),
        'unsupported_type_error' => 'Obrázek musí být ve formátu JPEG, PNG, GIF nebo WebP.',
        'directory' => dirname(__DIR__) . '/uploads/places/',
        'mkdir_error' => 'Adresář pro obrázky míst se nepodařilo vytvořit.',
        'move_error' => 'Obrázek se nepodařilo uložit.',
        'prefix' => 'place_image_',
        'generate_webp' => true,
        'delete_callback' => 'deletePlaceImageFile',
    ]);
}

/**
 * @param array<string, mixed> $event
 */
function eventImageUrl(array $event): string
{
    $filename = trim((string)($event['image_file'] ?? ''));
    if ($filename === '') {
        return '';
    }

    return BASE_URL . '/uploads/events/images/' . rawurlencode($filename);
}

function deleteEventImageFile(string $filename): void
{
    $filename = basename($filename);
    if ($filename === '') {
        return;
    }

    $path = dirname(__DIR__) . '/uploads/events/images/' . $filename;
    if (is_file($path) && !unlink($path)) {
        presentationLogFileDeleteFailure('event_image', $path);
    }
}

/**
 * @return array{filename:string,uploaded:bool,error:string}
 */
/**
 * @param array<string, mixed> $file
 * @return array<string, mixed>
 */
function uploadEventImage(array $file, string $existingFilename = ''): array
{
    return storePresentationUploadedFile($file, $existingFilename, [
        'upload_error' => 'Obrázek akce se nepodařilo nahrát.',
        'invalid_upload_error' => 'Obrázek akce se nepodařilo zpracovat.',
        'allowed_mime_map' => presentationImageMimeMap(),
        'unsupported_type_error' => 'Obrázek akce musí být ve formátu JPEG, PNG, GIF nebo WebP.',
        'directory' => dirname(__DIR__) . '/uploads/events/images/',
        'mkdir_error' => 'Adresář pro obrázky akcí se nepodařilo vytvořit.',
        'move_error' => 'Obrázek akce se nepodařilo uložit.',
        'prefix' => 'event_image_',
        'generate_webp' => true,
        'delete_callback' => 'deleteEventImageFile',
    ]);
}

function normalizePlaceUrl(string $value): string
{
    return normalizeHttpExternalUrl($value);
}

/**
 * @param array<string, mixed> $place
 * @return array<string, mixed>
 */
function hydratePlacePresentation(array $place): array
{
    $place['slug'] = placeSlug((string)($place['slug'] ?? ''));
    $place['place_kind'] = normalizePlaceKind((string)($place['place_kind'] ?? 'sight'));
    $place['place_kind_label'] = placeKindLabel((string)$place['place_kind']);
    $place['category'] = trim((string)($place['category'] ?? ''));
    $place['excerpt_plain'] = placeExcerpt($place);
    $place['meta_title'] = trim((string)($place['meta_title'] ?? ''));
    $place['meta_description'] = trim((string)($place['meta_description'] ?? ''));
    $place['image_url'] = placeImageUrl($place);
    $place['url'] = normalizePlaceUrl((string)($place['url'] ?? ''));
    $place['address'] = trim((string)($place['address'] ?? ''));
    $place['locality'] = trim((string)($place['locality'] ?? ''));
    $place['contact_phone'] = trim((string)($place['contact_phone'] ?? ''));
    $place['contact_email'] = trim((string)($place['contact_email'] ?? ''));
    $place['opening_hours'] = trim((string)($place['opening_hours'] ?? ''));
    $place['has_contact'] = $place['contact_phone'] !== '' || $place['contact_email'] !== '';
    $place['full_address'] = trim(
        implode(', ', array_filter([
            $place['address'],
            $place['locality'],
        ], static fn (string $value): bool => $value !== ''))
    );

    $latitude = trim((string)($place['latitude'] ?? ''));
    $longitude = trim((string)($place['longitude'] ?? ''));
    $place['latitude'] = $latitude;
    $place['longitude'] = $longitude;
    $place['has_coordinates'] = $latitude !== '' && $longitude !== '';
    $place['map_url'] = $place['has_coordinates']
        ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($latitude . ',' . $longitude)
        : '';
    $place['is_publicly_visible'] = ($place['deleted_at'] ?? null) === null
        && ((string)($place['status'] ?? 'published') === 'published')
        && (int)($place['is_published'] ?? 1) === 1;
    $place['public_path'] = placePublicPath($place);
    $place['public_url'] = placePublicUrl($place);

    return $place;
}

/**
 * @param array<string, mixed> $document
 */
function boardImageUrl(array $document): string
{
    $filename = trim((string)($document['image_file'] ?? ''));
    if ($filename === '') {
        return '';
    }

    return BASE_URL . '/uploads/board/images/' . rawurlencode($filename);
}

function deleteBoardImageFile(string $filename): void
{
    $filename = basename($filename);
    if ($filename === '') {
        return;
    }

    $path = dirname(__DIR__) . '/uploads/board/images/' . $filename;
    if (is_file($path)) {
        if (!unlink($path)) {
            presentationLogFileDeleteFailure('board_image', $path);
        }
    }
}

function deleteBoardStoredFile(string $filename): void
{
    $filename = basename($filename);
    if ($filename === '') {
        return;
    }

    $path = dirname(__DIR__) . '/uploads/board/' . $filename;
    if (is_file($path) && !unlink($path)) {
        presentationLogFileDeleteFailure('board_file', $path);
    }
}

/**
 * @return array<string, string>
 */
function boardAttachmentMimeMap(): array
{
    return [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.oasis.opendocument.text' => 'odt',
        'application/vnd.oasis.opendocument.spreadsheet' => 'ods',
        'application/vnd.oasis.opendocument.presentation' => 'odp',
        'application/zip' => 'zip',
        'application/x-zip-compressed' => 'zip',
        'text/plain' => 'txt',
    ];
}

/**
 * @param array<string, mixed> $file
 * @return array{filename:string,original_name:string,file_size:int,uploaded:bool,error:string}
 */
function uploadBoardStoredFile(array $file, string $existingFilename = ''): array
{
    if (!koraUploadHasFile($file)) {
        return [
            'filename' => $existingFilename,
            'original_name' => '',
            'file_size' => 0,
            'uploaded' => false,
            'error' => '',
        ];
    }

    $upload = koraInspectUploadedFile($file, [
        'upload_error' => 'Soubor přílohy se nepodařilo nahrát.',
        'invalid_upload_error' => 'Soubor přílohy se nepodařilo zpracovat.',
        'allowed_mime_map' => boardAttachmentMimeMap(),
        'unsupported_type_error' => 'Soubor přílohy má nepovolený formát.',
    ]);
    if (empty($upload['ok'])) {
        return [
            'filename' => $existingFilename,
            'original_name' => '',
            'file_size' => 0,
            'uploaded' => false,
            'error' => (string)($upload['error'] ?? 'Soubor přílohy se nepodařilo nahrát.'),
        ];
    }

    $extension = (string)($upload['extension'] ?? '');
    $storedName = uniqid('board_', true) . ($extension !== '' ? '.' . $extension : '');
    $storedUpload = koraStoreInspectedUpload(
        $upload,
        dirname(__DIR__) . '/uploads/board/',
        $storedName,
        [
            'mkdir_error' => 'Adresář pro přílohy vývěsky se nepodařilo vytvořit.',
            'move_error' => 'Soubor přílohy se nepodařilo uložit.',
        ]
    );
    if (empty($storedUpload['ok'])) {
        return [
            'filename' => $existingFilename,
            'original_name' => '',
            'file_size' => 0,
            'uploaded' => false,
            'error' => (string)($storedUpload['error'] ?? 'Soubor přílohy se nepodařilo uložit.'),
        ];
    }

    if ($existingFilename !== '' && $existingFilename !== $storedName) {
        deleteBoardStoredFile($existingFilename);
    }

    return [
        'filename' => $storedName,
        'original_name' => basename((string)($upload['original_name'] ?? '')),
        'file_size' => (int)($upload['file_size'] ?? 0),
        'uploaded' => true,
        'error' => '',
    ];
}

/**
 * @return array{filename:string,uploaded:bool,error:string}
 */
/**
 * @param array<string, mixed> $file
 * @return array<string, mixed>
 */
function uploadBoardImage(array $file, string $existingFilename = ''): array
{
    return storePresentationUploadedFile($file, $existingFilename, [
        'upload_error' => 'Obrázek se nepodařilo nahrát.',
        'invalid_upload_error' => 'Obrázek se nepodařilo zpracovat.',
        'allowed_mime_map' => presentationImageMimeMap(),
        'unsupported_type_error' => 'Obrázek musí být ve formátu JPEG, PNG, GIF nebo WebP.',
        'directory' => dirname(__DIR__) . '/uploads/board/images/',
        'mkdir_error' => 'Adresář pro obrázky vývěsky se nepodařilo vytvořit.',
        'move_error' => 'Obrázek se nepodařilo uložit.',
        'prefix' => 'board_image_',
        'generate_webp' => true,
        'delete_callback' => 'deleteBoardImageFile',
    ]);
}

/**
 * @param array<string, mixed> $document
 * @return array<string, mixed>
 */
function hydrateBoardPresentation(array $document): array
{
    $document['board_type'] = normalizeBoardType((string)($document['board_type'] ?? 'document'));
    $document['board_type_label'] = boardTypeLabel((string)$document['board_type']);
    $document['board_type_help'] = boardTypeHelp((string)$document['board_type']);
    $document['excerpt_plain'] = boardExcerpt($document);
    $document['image_url'] = boardImageUrl($document);
    $document['contact_name'] = trim((string)($document['contact_name'] ?? ''));
    $document['contact_phone'] = trim((string)($document['contact_phone'] ?? ''));
    $document['contact_email'] = trim((string)($document['contact_email'] ?? ''));
    $document['has_contact'] = $document['contact_name'] !== ''
        || $document['contact_phone'] !== ''
        || $document['contact_email'] !== '';
    $document['is_pinned'] = (int)($document['is_pinned'] ?? 0);

    return $document;
}

/**
 * @param array<string, mixed> $account
 */
function authorSlugCandidate(array $account): string
{
    $nickname = trim((string)($account['nickname'] ?? ''));
    if ($nickname !== '') {
        return $nickname;
    }

    $fullName = trim(
        trim((string)($account['first_name'] ?? '')) . ' ' . trim((string)($account['last_name'] ?? ''))
    );
    if ($fullName !== '') {
        return $fullName;
    }

    $email = trim((string)($account['email'] ?? ''));
    if ($email !== '') {
        $localPart = strstr($email, '@', true);
        return $localPart !== false && $localPart !== '' ? $localPart : $email;
    }

    return 'autor';
}

/**
 * @param array<string, mixed> $params
 */
function appendUrlQuery(string $path, array $params): string
{
    $query = http_build_query(array_filter(
        $params,
        static fn ($value): bool => $value !== null && $value !== ''
    ));

    if ($query === '') {
        return $path;
    }

    return $path . (str_contains($path, '?') ? '&' : '?') . $query;
}

/**
 * @param array<string, mixed> $article
 */
function articlePublicRequestPath(array $article): string
{
    $slug = articleSlug((string)($article['slug'] ?? ''));
    $blogSlug = articleBlogSlug($article);
    if ($slug !== '') {
        return '/' . rawurlencode($blogSlug) . '/' . rawurlencode($slug);
    }

    return '/blog/article.php?id=' . (int)($article['id'] ?? 0);
}

/**
 * @param array<string, mixed> $article
 * @param array<string, mixed> $query
 */
function articlePublicPath(array $article, array $query = []): string
{
    return BASE_URL . appendUrlQuery(articlePublicRequestPath($article), $query);
}

/**
 * @param array<string, mixed> $article
 * @param array<string, mixed> $query
 */
function articlePublicUrl(array $article, array $query = []): string
{
    return siteUrl(appendUrlQuery(articlePublicRequestPath($article), $query));
}

/**
 * @param array<string, mixed> $article
 */
function blogArticleIsPubliclyReachable(array $article): bool
{
    if (trim((string)($article['deleted_at'] ?? '')) !== '') {
        return false;
    }
    if ((string)($article['status'] ?? '') !== 'published') {
        return false;
    }

    $now = time();
    $publishAt = trim((string)($article['publish_at'] ?? ''));
    if ($publishAt !== '') {
        $publishTimestamp = strtotime($publishAt);
        if ($publishTimestamp !== false && $publishTimestamp > $now) {
            return false;
        }
    }

    $unpublishAt = trim((string)($article['unpublish_at'] ?? ''));
    if ($unpublishAt !== '') {
        $unpublishTimestamp = strtotime($unpublishAt);
        if ($unpublishTimestamp !== false && $unpublishTimestamp <= $now) {
            return false;
        }
    }

    return articleSlug((string)($article['slug'] ?? '')) !== '';
}

/**
 * @param array<string, mixed> $page
 * @return array<string, mixed>|null
 */
function pageBlogContext(array $page): ?array
{
    $blogId = (int)($page['blog_id'] ?? 0);
    if ($blogId > 0) {
        $blog = null;
        if (!empty($page['blog_slug'])) {
            $blog = getBlogBySlug((string)$page['blog_slug']);
        }
        if (!$blog) {
            $blog = getBlogById($blogId);
        }
        return $blog ?: null;
    }

    $pageId = (int)($page['id'] ?? 0);
    if ($pageId <= 0) {
        return null;
    }

    static $pageBlogContextCache = [];
    if (array_key_exists($pageId, $pageBlogContextCache)) {
        return $pageBlogContextCache[$pageId];
    }

    try {
        $stmt = db_connect()->prepare(
            "SELECT p.blog_id, b.*
             FROM cms_pages p
             LEFT JOIN cms_blogs b ON b.id = p.blog_id
             WHERE p.id = ?
             LIMIT 1"
        );
        $stmt->execute([$pageId]);
        $row = $stmt->fetch() ?: null;
        if ($row && (int)($row['blog_id'] ?? 0) > 0 && !empty($row['slug'])) {
            $pageBlogContextCache[$pageId] = $row;
            return $row;
        }
    } catch (\PDOException $e) {
    }

    $pageBlogContextCache[$pageId] = null;
    return null;
}

/**
 * @param array<string, mixed> $page
 */
function pagePublicRequestPath(array $page): string
{
    $slug = pageSlug((string)($page['slug'] ?? ''));
    if ($slug !== '') {
        $blog = pageBlogContext($page);
        if ($blog) {
            return '/' . rawurlencode((string)$blog['slug']) . '/stranka/' . rawurlencode($slug);
        }
        return '/page.php?slug=' . rawurlencode($slug);
    }

    return '/';
}

/**
 * @param array<string, mixed> $page
 * @param array<string, mixed> $query
 */
function pagePublicPath(array $page, array $query = []): string
{
    return BASE_URL . appendUrlQuery(pagePublicRequestPath($page), $query);
}

/**
 * @param array<string, mixed> $page
 * @param array<string, mixed> $query
 */
function pagePublicUrl(array $page, array $query = []): string
{
    return siteUrl(appendUrlQuery(pagePublicRequestPath($page), $query));
}

/**
 * @param array<string, mixed> $article
 */
function articlePreviewPath(array $article): string
{
    $previewToken = trim((string)($article['preview_token'] ?? ''));
    return articlePublicPath($article, $previewToken !== '' ? ['preview' => $previewToken] : []);
}

function normalizeArticlePreviewAction(string $action): string
{
    $action = strtolower(trim($action));

    return in_array($action, ['enable', 'rotate', 'revoke'], true) ? $action : '';
}

function generateArticlePreviewToken(): string
{
    return bin2hex(random_bytes(16));
}

function isValidArticlePreviewToken(string $token): bool
{
    return preg_match('/\A[a-f0-9]{32}\z/D', trim($token)) === 1;
}

/**
 * @param array<string, mixed> $news
 */
function newsPublicRequestPath(array $news): string
{
    $slug = newsSlug((string)($news['slug'] ?? ''));
    if ($slug !== '') {
        return '/news/' . rawurlencode($slug);
    }

    return '/news/article.php?id=' . (int)($news['id'] ?? 0);
}

/**
 * @param array<string, mixed> $show
 */
function podcastShowPublicRequestPath(array $show): string
{
    $slug = podcastShowSlug((string)($show['slug'] ?? ''));
    if ($slug !== '') {
        return '/podcast/' . rawurlencode($slug);
    }

    return '/podcast/index.php';
}

/**
 * @param array<string, mixed> $show
 * @param array<string, mixed> $query
 */
function podcastShowPublicPath(array $show, array $query = []): string
{
    return BASE_URL . appendUrlQuery(podcastShowPublicRequestPath($show), $query);
}

/**
 * @param array<string, mixed> $show
 * @param array<string, mixed> $query
 */
function podcastShowPublicUrl(array $show, array $query = []): string
{
    return siteUrl(appendUrlQuery(podcastShowPublicRequestPath($show), $query));
}

/**
 * @param array<string, mixed> $episode
 */
function podcastEpisodePublicRequestPath(array $episode): string
{
    $showSlug = podcastShowSlug((string)($episode['show_slug'] ?? ''));
    $episodeSlug = podcastEpisodeSlug((string)($episode['slug'] ?? ''));
    if ($showSlug !== '' && $episodeSlug !== '') {
        return '/podcast/' . rawurlencode($showSlug) . '/' . rawurlencode($episodeSlug);
    }

    return '/podcast/episode.php?id=' . (int)($episode['id'] ?? 0);
}

/**
 * @param array<string, mixed> $episode
 * @param array<string, mixed> $query
 */
function podcastEpisodePublicPath(array $episode, array $query = []): string
{
    return BASE_URL . appendUrlQuery(podcastEpisodePublicRequestPath($episode), $query);
}

/**
 * @param array<string, mixed> $episode
 * @param array<string, mixed> $query
 */
function podcastEpisodePublicUrl(array $episode, array $query = []): string
{
    return siteUrl(appendUrlQuery(podcastEpisodePublicRequestPath($episode), $query));
}

/**
 * @param array<string, mixed> $category
 */
function faqCategoryRequestPath(array $category): string
{
    $slug = faqCategorySlug((string)($category['slug'] ?? ''));
    if ($slug !== '') {
        return '/faq/kategorie/' . rawurlencode($slug);
    }

    return '/faq/index.php?kat=' . (int)($category['id'] ?? 0);
}

/**
 * @param array<string, mixed> $category
 * @param array<string, mixed> $query
 */
function faqCategoryPath(array $category, array $query = []): string
{
    return BASE_URL . appendUrlQuery(faqCategoryRequestPath($category), $query);
}

/**
 * @param array<string, mixed> $category
 * @param array<string, mixed> $query
 */
function faqCategoryUrl(array $category, array $query = []): string
{
    return siteUrl(appendUrlQuery(faqCategoryRequestPath($category), $query));
}

/**
 * @param array<string, mixed> $faq
 */
function faqPublicRequestPath(array $faq): string
{
    $slug = faqSlug((string)($faq['slug'] ?? ''));
    if ($slug !== '') {
        return '/faq/' . rawurlencode($slug);
    }

    return '/faq/item.php?id=' . (int)($faq['id'] ?? 0);
}

/**
 * @param array<string, mixed> $faq
 * @param array<string, mixed> $query
 */
function faqPublicPath(array $faq, array $query = []): string
{
    return BASE_URL . appendUrlQuery(faqPublicRequestPath($faq), $query);
}

/**
 * @param array<string, mixed> $faq
 * @param array<string, mixed> $query
 */
function faqPublicUrl(array $faq, array $query = []): string
{
    return siteUrl(appendUrlQuery(faqPublicRequestPath($faq), $query));
}

/**
 * @param array<string, mixed> $poll
 */
function pollPublicRequestPath(array $poll): string
{
    $slug = pollSlug((string)($poll['slug'] ?? ''));
    if ($slug !== '') {
        return '/polls/' . rawurlencode($slug);
    }

    return '/polls/index.php?id=' . (int)($poll['id'] ?? 0);
}

/**
 * @param array<string, mixed> $poll
 * @param array<string, mixed> $query
 */
function pollPublicPath(array $poll, array $query = []): string
{
    return BASE_URL . appendUrlQuery(pollPublicRequestPath($poll), $query);
}

/**
 * @param array<string, mixed> $poll
 * @param array<string, mixed> $query
 */
function pollPublicUrl(array $poll, array $query = []): string
{
    return siteUrl(appendUrlQuery(pollPublicRequestPath($poll), $query));
}

/**
 * @param array<string, mixed> $card
 */
function foodCardPublicRequestPath(array $card): string
{
    $slug = foodCardSlug((string)($card['slug'] ?? ''));
    if ($slug !== '') {
        return '/food/card/' . rawurlencode($slug);
    }

    return '/food/card.php?id=' . (int)($card['id'] ?? 0);
}

/**
 * @param array<string, mixed> $card
 * @param array<string, mixed> $query
 */
function foodCardPublicPath(array $card, array $query = []): string
{
    return BASE_URL . appendUrlQuery(foodCardPublicRequestPath($card), $query);
}

/**
 * @param array<string, mixed> $card
 * @param array<string, mixed> $query
 */
function foodCardPublicUrl(array $card, array $query = []): string
{
    return siteUrl(appendUrlQuery(foodCardPublicRequestPath($card), $query));
}

/**
 * @param array<string, mixed> $resource
 */
function reservationResourcePublicRequestPath(array $resource): string
{
    $slug = reservationResourceSlug((string)($resource['slug'] ?? ''));
    if ($slug !== '') {
        return '/reservations/resource.php?slug=' . rawurlencode($slug);
    }

    return '/reservations/index.php';
}

/**
 * @param array<string, mixed> $resource
 * @param array<string, mixed> $query
 */
function reservationResourcePublicPath(array $resource, array $query = []): string
{
    return BASE_URL . appendUrlQuery(reservationResourcePublicRequestPath($resource), $query);
}

/**
 * @param array<string, mixed> $resource
 * @param array<string, mixed> $query
 */
function reservationResourcePublicUrl(array $resource, array $query = []): string
{
    return siteUrl(appendUrlQuery(reservationResourcePublicRequestPath($resource), $query));
}

/**
 * @param array<string, mixed> $booking
 */
function reservationBookingContactName(array $booking): string
{
    $name = trim((string)($booking['guest_name'] ?? ''));
    if ($name !== '') {
        return $name;
    }

    return trim((string)($booking['user_first_name'] ?? '') . ' ' . (string)($booking['user_last_name'] ?? ''));
}

/**
 * @param array<string, mixed> $booking
 */
function reservationBookingContactEmail(array $booking): string
{
    $email = trim((string)($booking['guest_email'] ?? ''));
    if ($email !== '') {
        return $email;
    }

    return trim((string)($booking['user_email'] ?? ''));
}

function reservationCalendarToken(): string
{
    return bin2hex(random_bytes(16));
}

/**
 * @param array<string, mixed> $booking
 */
function reservationCalendarUrl(array $booking): string
{
    $token = trim((string)($booking['calendar_token'] ?? ''));
    if ($token === '') {
        return '';
    }

    return siteUrl('/reservations/calendar.php?token=' . rawurlencode($token));
}

function reservationIcsEscape(string $value): string
{
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $value = str_replace('\\', '\\\\', $value);
    $value = str_replace(';', '\;', $value);
    $value = str_replace(',', '\,', $value);
    return str_replace("\n", '\n', $value);
}

function reservationIcsFoldLine(string $line): string
{
    if (strlen($line) <= 73) {
        return $line;
    }

    $chunks = [];
    $remaining = $line;
    while ($remaining !== '') {
        $chunk = mb_strcut($remaining, 0, 73, 'UTF-8');
        if ($chunk === '') {
            $chunk = substr($remaining, 0, 73);
        }
        $chunks[] = $chunk;
        $remaining = substr($remaining, strlen($chunk));
    }

    return implode("\r\n ", $chunks);
}

/**
 * @param array<string, mixed> $booking
 */
function reservationIcsFilename(array $booking): string
{
    $date = preg_replace('/[^0-9]+/', '', (string)($booking['booking_date'] ?? '')) ?: date('Ymd');
    $resourceSlug = reservationResourceSlug((string)($booking['resource_slug'] ?? $booking['resource_name'] ?? 'rezervace'));
    if ($resourceSlug === '') {
        $resourceSlug = 'rezervace';
    }

    return 'rezervace-' . $resourceSlug . '-' . $date . '.ics';
}

/**
 * @param array<string, mixed> $booking
 */
function reservationBookingLocationLabel(array $booking): string
{
    $location = trim((string)($booking['location_label'] ?? ''));
    if ($location !== '') {
        return $location;
    }

    return trim((string)($booking['resource_location'] ?? ''));
}

/**
 * @param array<string, mixed> $booking
 */
function reservationBuildIcs(array $booking): string
{
    $resourceName = trim((string)($booking['resource_name'] ?? $booking['name'] ?? 'Rezervace'));
    if ($resourceName === '') {
        $resourceName = 'Rezervace';
    }
    $start = new DateTime((string)$booking['booking_date'] . ' ' . (string)$booking['start_time']);
    $end = new DateTime((string)$booking['booking_date'] . ' ' . (string)$booking['end_time']);
    $token = trim((string)($booking['calendar_token'] ?? ''));
    $uidToken = $token !== '' ? $token : ('booking-' . (int)($booking['id'] ?? 0));
    $host = (string)(parse_url(siteUrl('/'), PHP_URL_HOST) ?: 'localhost');
    $location = reservationBookingLocationLabel($booking);
    $contactName = reservationBookingContactName($booking);
    $cancelUrl = trim((string)($booking['confirmation_token'] ?? '')) !== ''
        ? siteUrl('/reservations/cancel_booking.php?token=' . rawurlencode((string)$booking['confirmation_token']))
        : '';

    $description = 'Rezervace: ' . $resourceName;
    if ($contactName !== '') {
        $description .= "\nZákazník: " . $contactName;
    }
    if ((int)($booking['party_size'] ?? 0) > 0) {
        $description .= "\nPočet osob: " . (int)$booking['party_size'];
    }
    if ($cancelUrl !== '') {
        $description .= "\nZrušení rezervace: " . $cancelUrl;
    }

    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Kora CMS//Reservations//CS',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:' . reservationIcsEscape($uidToken . '@' . $host),
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        'DTSTART:' . $start->format('Ymd\THis'),
        'DTEND:' . $end->format('Ymd\THis'),
        'SUMMARY:' . reservationIcsEscape('Rezervace: ' . $resourceName),
        'DESCRIPTION:' . reservationIcsEscape($description),
        'STATUS:CONFIRMED',
    ];
    if ($location !== '') {
        $lines[] = 'LOCATION:' . reservationIcsEscape($location);
    }
    if ($cancelUrl !== '') {
        $lines[] = 'URL:' . reservationIcsEscape($cancelUrl);
    }
    $lines[] = 'END:VEVENT';
    $lines[] = 'END:VCALENDAR';

    return implode("\r\n", array_map('reservationIcsFoldLine', $lines)) . "\r\n";
}

/**
 * @param array<string, mixed> $booking
 * @return array{filename:string, content_type:string, content:string}
 */
function reservationBookingIcsAttachment(array $booking): array
{
    return [
        'filename' => reservationIcsFilename($booking),
        'content_type' => 'text/calendar; charset=UTF-8; method=PUBLISH',
        'content' => reservationBuildIcs($booking),
    ];
}

/**
 * @return array<string, string>
 */
function reservationBookingEventLabels(): array
{
    return [
        'created' => 'Vytvoření rezervace',
        'approved' => 'Schválení rezervace',
        'rejected' => 'Zamítnutí rezervace',
        'cancelled' => 'Zrušení rezervace',
        'completed' => 'Dokončení rezervace',
        'no_show' => 'Neomluvená absence',
        'auto_completed' => 'Automatické dokončení',
        'auto_cancelled' => 'Automatické zrušení',
        'reminder_sent' => 'Odeslání připomínky',
        'reminder_failed' => 'Chyba připomínky',
    ];
}

/**
 * @param array<string, mixed> $metadata
 */
function reservationRecordBookingEvent(
    PDO $pdo,
    int $bookingId,
    string $eventType,
    string $description = '',
    ?int $actorUserId = null,
    array $metadata = []
): void {
    if ($bookingId <= 0) {
        return;
    }

    $eventType = preg_replace('/[^a-z0-9_:-]+/i', '_', $eventType) ?: 'event';
    $description = trim($description);
    if ($description === '') {
        $labels = reservationBookingEventLabels();
        $description = $labels[$eventType] ?? 'Změna rezervace';
    }
    $metadataJson = $metadata !== []
        ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : null;
    if (!is_string($metadataJson)) {
        $metadataJson = null;
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO cms_res_booking_events
             (booking_id, event_type, description, actor_user_id, metadata_json, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$bookingId, $eventType, $description, $actorUserId, $metadataJson]);
    } catch (\PDOException $e) {
        // Historie nesmí rozbít hlavní rezervační operaci při deployi před migrací.
    }
}

/**
 * @return array<string, mixed>|null
 */
function reservationBookingForNotification(PDO $pdo, int $bookingId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT b.*, r.name AS resource_name, r.slug AS resource_slug, r.location AS resource_location,
                r.reminders_enabled, r.reminder_hours_before, r.reminder_message, r.calendar_invite_enabled,
                u.email AS user_email, u.first_name AS user_first_name, u.last_name AS user_last_name,
                (
                    SELECT GROUP_CONCAT(
                        TRIM(CONCAT(l.name, CASE WHEN l.address <> '' THEN CONCAT(' (', l.address, ')') ELSE '' END))
                        ORDER BY l.name SEPARATOR ', '
                    )
                    FROM cms_res_resource_locations rl
                    JOIN cms_res_locations l ON l.id = rl.location_id
                    WHERE rl.resource_id = r.id
                ) AS location_label
         FROM cms_res_bookings b
         JOIN cms_res_resources r ON r.id = b.resource_id
         LEFT JOIN cms_users u ON u.id = b.user_id
         WHERE b.id = ?"
    );
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();

    return is_array($booking) ? $booking : null;
}

/**
 * @return array<string, mixed>|null
 */
function reservationBookingForCalendarToken(PDO $pdo, string $token): ?array
{
    $token = trim($token);
    if ($token === '' || preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT b.*, r.name AS resource_name, r.slug AS resource_slug, r.location AS resource_location,
                r.reminders_enabled, r.reminder_hours_before, r.reminder_message, r.calendar_invite_enabled,
                u.email AS user_email, u.first_name AS user_first_name, u.last_name AS user_last_name,
                (
                    SELECT GROUP_CONCAT(
                        TRIM(CONCAT(l.name, CASE WHEN l.address <> '' THEN CONCAT(' (', l.address, ')') ELSE '' END))
                        ORDER BY l.name SEPARATOR ', '
                    )
                    FROM cms_res_resource_locations rl
                    JOIN cms_res_locations l ON l.id = rl.location_id
                    WHERE rl.resource_id = r.id
                ) AS location_label
         FROM cms_res_bookings b
         JOIN cms_res_resources r ON r.id = b.resource_id
         LEFT JOIN cms_users u ON u.id = b.user_id
         WHERE b.calendar_token = ?
           AND b.status = 'confirmed'
         LIMIT 1"
    );
    $stmt->execute([$token]);
    $booking = $stmt->fetch();

    return is_array($booking) ? $booking : null;
}

/**
 * @param array<string, mixed> $booking
 */
function reservationReminderIsDue(array $booking, ?DateTimeInterface $now = null): bool
{
    if ((int)($booking['reminders_enabled'] ?? 0) !== 1) {
        return false;
    }
    if ((string)($booking['status'] ?? '') !== 'confirmed') {
        return false;
    }
    if (!empty($booking['reminder_sent_at'])) {
        return false;
    }

    $hoursBefore = max(1, (int)($booking['reminder_hours_before'] ?? 24));
    $now = $now ?? new DateTimeImmutable();
    $start = new DateTimeImmutable((string)$booking['booking_date'] . ' ' . (string)$booking['start_time']);
    if ($start <= $now) {
        return false;
    }

    return $start->modify('-' . $hoursBefore . ' hours') <= $now;
}

/**
 * @param array<string, mixed> $booking
 * @return list<array{filename:string, content_type:string, content:string}>
 */
function reservationMailAttachments(array $booking): array
{
    if ((int)($booking['calendar_invite_enabled'] ?? 0) !== 1) {
        return [];
    }
    if ((string)($booking['status'] ?? '') !== 'confirmed') {
        return [];
    }
    if (trim((string)($booking['calendar_token'] ?? '')) === '') {
        return [];
    }

    return [reservationBookingIcsAttachment($booking)];
}

/**
 * @param array<string, mixed> $booking
 */
function reservationSendMail(
    array $booking,
    string $subject,
    string $body,
    string $notification,
    bool $includeCalendar = true
): bool {
    $recipient = reservationBookingContactEmail($booking);
    if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $options = [];
    if ($includeCalendar) {
        $attachments = reservationMailAttachments($booking);
        if ($attachments !== []) {
            $options['attachments'] = $attachments;
        }
    }

    if (!sendMail($recipient, $subject, $body, $options)) {
        mailLogFailure('notification_failed', [
            'notification' => $notification,
            'booking_id' => (int)($booking['id'] ?? 0),
            'recipient_domain' => mailEmailDomain($recipient),
        ]);
        return false;
    }

    return true;
}

/**
 * @param array<string, mixed> $booking
 */
function reservationReminderSubject(array $booking): string
{
    return 'Připomínka rezervace – ' . trim((string)($booking['resource_name'] ?? 'Rezervace'));
}

/**
 * @param array<string, mixed> $booking
 */
function reservationReminderBody(array $booking): string
{
    $resourceName = trim((string)($booking['resource_name'] ?? 'Rezervace'));
    $message = trim((string)($booking['reminder_message'] ?? ''));
    $cancelUrl = trim((string)($booking['confirmation_token'] ?? '')) !== ''
        ? siteUrl('/reservations/cancel_booking.php?token=' . rawurlencode((string)$booking['confirmation_token']))
        : '';

    $body = "Dobrý den,\n\n"
        . "připomínáme vaši rezervaci:\n\n"
        . "Zdroj: {$resourceName}\n"
        . "Datum: " . (string)$booking['booking_date'] . "\n"
        . "Čas: " . substr((string)$booking['start_time'], 0, 5) . " – " . substr((string)$booking['end_time'], 0, 5) . "\n"
        . "Počet osob: " . (int)($booking['party_size'] ?? 1) . "\n";

    if ($message !== '') {
        $body .= "\n" . $message . "\n";
    }
    if ($cancelUrl !== '') {
        $body .= "\nPokud chcete rezervaci zrušit, klikněte na tento odkaz:\n" . $cancelUrl . "\n";
    }

    return $body . "\nDěkujeme.\n";
}

/**
 * @param array<string, mixed> $booking
 */
function reservationStatusMailBody(array $booking, string $statusLabel, string $adminNote = ''): string
{
    $body = "Dobrý den,\n\n";
    $body .= "vaše rezervace byla " . $statusLabel . ".\n\n";
    $body .= "Zdroj: " . (string)$booking['resource_name'] . "\n";
    $body .= "Datum: " . (string)$booking['booking_date'] . "\n";
    $body .= "Čas: " . substr((string)$booking['start_time'], 0, 5) . ' – ' . substr((string)$booking['end_time'], 0, 5) . "\n";
    $body .= "Počet osob: " . (int)($booking['party_size'] ?? 1) . "\n";

    if ($adminNote !== '') {
        $body .= "\nPoznámka administrátora:\n" . $adminNote . "\n";
    }

    if ((string)($booking['status'] ?? '') === 'confirmed' && trim((string)($booking['confirmation_token'] ?? '')) !== '') {
        $cancelUrl = siteUrl('/reservations/cancel_booking.php?token=' . rawurlencode((string)$booking['confirmation_token']));
        $body .= "\nPokud chcete rezervaci zrušit, klikněte na tento odkaz:\n" . $cancelUrl . "\n";
    }

    return $body . "\nDěkujeme.\n";
}

/**
 * @param array<string, mixed> $album
 */
function galleryAlbumPublicRequestPath(array $album): string
{
    $slug = galleryAlbumSlug((string)($album['slug'] ?? ''));
    if ($slug !== '') {
        return '/gallery/album/' . rawurlencode($slug);
    }

    return '/gallery/album.php?id=' . (int)($album['id'] ?? 0);
}

/**
 * @param array<string, mixed> $album
 * @param array<string, mixed> $query
 */
function galleryAlbumPublicPath(array $album, array $query = []): string
{
    return BASE_URL . appendUrlQuery(galleryAlbumPublicRequestPath($album), $query);
}

/**
 * @param array<string, mixed> $album
 * @param array<string, mixed> $query
 */
function galleryAlbumPublicUrl(array $album, array $query = []): string
{
    return siteUrl(appendUrlQuery(galleryAlbumPublicRequestPath($album), $query));
}

function galleryAlbumPublicVisibilitySql(string $alias = ''): string
{
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';

    return "{$prefix}deleted_at IS NULL"
        . " AND COALESCE({$prefix}status, 'published') = 'published'"
        . " AND COALESCE({$prefix}is_published, 1) = 1";
}

/**
 * @param array<string, mixed> $photo
 */
function galleryPhotoPublicRequestPath(array $photo): string
{
    $slug = galleryPhotoSlug((string)($photo['slug'] ?? ''));
    if ($slug !== '') {
        return '/gallery/photo/' . rawurlencode($slug);
    }

    return '/gallery/photo.php?id=' . (int)($photo['id'] ?? 0);
}

/**
 * @param array<string, mixed> $photo
 * @param array<string, mixed> $query
 */
function galleryPhotoPublicPath(array $photo, array $query = []): string
{
    return BASE_URL . appendUrlQuery(galleryPhotoPublicRequestPath($photo), $query);
}

/**
 * @param array<string, mixed> $photo
 * @param array<string, mixed> $query
 */
function galleryPhotoPublicUrl(array $photo, array $query = []): string
{
    return siteUrl(appendUrlQuery(galleryPhotoPublicRequestPath($photo), $query));
}

function galleryPhotoPublicVisibilitySql(string $photoAlias = '', string $albumAlias = ''): string
{
    $photoPrefix = $photoAlias !== '' ? rtrim($photoAlias, '.') . '.' : '';
    $conditions = "{$photoPrefix}deleted_at IS NULL"
        . " AND COALESCE({$photoPrefix}status, 'published') = 'published'"
        . " AND COALESCE({$photoPrefix}is_published, 1) = 1";

    if ($albumAlias !== '') {
        $conditions .= ' AND ' . galleryAlbumPublicVisibilitySql($albumAlias);
    }

    return $conditions;
}

/**
 * @param array<string, mixed> $photo
 */
function galleryPhotoMediaRequestPath(array $photo, string $size = 'full'): string
{
    $photoId = (int)($photo['id'] ?? 0);
    if ($photoId <= 0) {
        return '';
    }

    $normalizedSize = $size === 'thumb' ? 'thumb' : 'full';
    return '/gallery/image.php?id=' . $photoId . '&size=' . $normalizedSize;
}

/**
 * @param array<string, mixed> $photo
 */
function galleryPhotoMediaPath(array $photo, string $size = 'full'): string
{
    $requestPath = galleryPhotoMediaRequestPath($photo, $size);
    return $requestPath !== '' ? BASE_URL . $requestPath : '';
}

/**
 * @param array<string, mixed> $photo
 */
function galleryPhotoMediaUrl(array $photo, string $size = 'full'): string
{
    $requestPath = galleryPhotoMediaRequestPath($photo, $size);
    return $requestPath !== '' ? siteUrl($requestPath) : '';
}

function galleryPhotoUploadDirectory(): string
{
    return dirname(__DIR__) . '/uploads/gallery/';
}

function galleryPhotoThumbDirectory(): string
{
    return galleryPhotoUploadDirectory() . 'thumbs/';
}

function deleteGalleryPhotoFile(string $filename): void
{
    $filename = basename($filename);
    if ($filename === '') {
        return;
    }

    foreach ([
        galleryPhotoUploadDirectory() . $filename,
        galleryPhotoThumbDirectory() . $filename,
    ] as $path) {
        if (is_file($path) && !unlink($path)) {
            presentationLogFileDeleteFailure('gallery_photo', $path);
        }
    }
}

/**
 * @param array<string, mixed> $file
 * @return array{filename:string,original_name:string,uploaded:bool,error:string}
 */
function uploadGalleryPhotoImage(array $file): array
{
    if (!koraUploadHasFile($file)) {
        return [
            'filename' => '',
            'original_name' => '',
            'uploaded' => false,
            'error' => '',
        ];
    }

    $upload = koraInspectUploadedFile($file, [
        'upload_error' => 'Fotografii se nepodařilo nahrát.',
        'invalid_upload_error' => 'Fotografii se nepodařilo zpracovat.',
        'allowed_mime_map' => presentationImageMimeMap(),
        'unsupported_type_error' => 'Fotografie musí být ve formátu JPEG, PNG, GIF nebo WebP.',
        'max_bytes' => koraDefaultUploadMaxSizeBytes(),
        'too_large_error' => 'Fotografie je příliš velká.',
    ]);
    if (empty($upload['ok'])) {
        return [
            'filename' => '',
            'original_name' => '',
            'uploaded' => false,
            'error' => (string)($upload['error'] ?? 'Fotografii se nepodařilo nahrát.'),
        ];
    }

    $extension = (string)($upload['extension'] ?? '');
    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . ($extension !== '' ? '.' . $extension : '');
    $storedUpload = koraStoreInspectedUpload(
        $upload,
        galleryPhotoUploadDirectory(),
        $filename,
        [
            'mkdir_error' => 'Adresář pro fotografie se nepodařilo vytvořit.',
            'move_error' => 'Fotografii se nepodařilo uložit.',
        ]
    );
    if (empty($storedUpload['ok'])) {
        return [
            'filename' => '',
            'original_name' => '',
            'uploaded' => false,
            'error' => (string)($storedUpload['error'] ?? 'Fotografii se nepodařilo uložit.'),
        ];
    }

    $thumbDirectory = galleryPhotoThumbDirectory();
    if (!is_dir($thumbDirectory) && !mkdir($thumbDirectory, 0755, true) && !is_dir($thumbDirectory)) {
        deleteGalleryPhotoFile($filename);
        return [
            'filename' => '',
            'original_name' => '',
            'uploaded' => false,
            'error' => 'Adresář pro miniatury galerie se nepodařilo vytvořit.',
        ];
    }

    gallery_make_thumb((string)$storedUpload['path'], $thumbDirectory . $filename, 300);
    generateWebp((string)$storedUpload['path']);
    generateWebp($thumbDirectory . $filename);

    return [
        'filename' => $filename,
        'original_name' => basename((string)($upload['original_name'] ?? '')),
        'uploaded' => true,
        'error' => '',
    ];
}

/**
 * @param array<string, mixed> $album
 * @param array<int, string> $albumNames
 * @param array<int, string> $photoLabels
 * @return array<string, mixed>
 */
function galleryAlbumRevisionSnapshot(array $album, array $albumNames = [], array $photoLabels = []): array
{
    $parentId = isset($album['parent_id']) && (int)$album['parent_id'] > 0 ? (int)$album['parent_id'] : 0;
    $coverPhotoId = isset($album['cover_photo_id']) && (int)$album['cover_photo_id'] > 0 ? (int)$album['cover_photo_id'] : 0;

    return [
        'name' => trim((string)($album['name'] ?? '')),
        'slug' => galleryAlbumSlug((string)($album['slug'] ?? '')),
        'description' => trim((string)($album['description'] ?? '')),
        'parent_album' => $parentId > 0 ? trim((string)($albumNames[$parentId] ?? '')) : '',
        'cover_photo' => $coverPhotoId > 0 ? trim((string)($photoLabels[$coverPhotoId] ?? '')) : '',
        'default_credit' => trim((string)($album['default_credit'] ?? '')),
        'default_license_label' => trim((string)($album['default_license_label'] ?? '')),
        'default_license_url' => normalizeGalleryLicenseUrl((string)($album['default_license_url'] ?? '')),
        'is_published' => (string)((int)($album['is_published'] ?? 1)),
        'status' => (string)($album['status'] ?? 'published'),
    ];
}

/**
 * @param array<string, mixed> $photo
 * @return array<string, mixed>
 */
function galleryPhotoRevisionSnapshot(array $photo, string $albumName = ''): array
{
    return [
        'title' => trim((string)($photo['title'] ?? '')),
        'slug' => galleryPhotoSlug((string)($photo['slug'] ?? '')),
        'alt_text' => trim((string)($photo['alt_text'] ?? '')),
        'caption' => trim((string)($photo['caption'] ?? '')),
        'description' => trim((string)($photo['description'] ?? '')),
        'credit' => trim((string)($photo['credit'] ?? '')),
        'license_label' => trim((string)($photo['license_label'] ?? '')),
        'license_url' => normalizeGalleryLicenseUrl((string)($photo['license_url'] ?? '')),
        'taken_at' => trim((string)($photo['taken_at'] ?? '')),
        'location_label' => trim((string)($photo['location_label'] ?? '')),
        'album' => trim($albumName),
        'sort_order' => (string)((int)($photo['sort_order'] ?? 0)),
        'is_published' => (string)((int)($photo['is_published'] ?? 1)),
        'status' => (string)($photo['status'] ?? 'published'),
    ];
}

/**
 * @param array<string, mixed> $album
 * @param list<array<string, mixed>> $photos
 */
function galleryAlbumStructuredData(array $album, array $photos = []): string
{
    $items = [];
    foreach ($photos as $photo) {
        $imageUrl = trim((string)($photo['image_url'] ?? galleryPhotoMediaUrl($photo, 'full')));
        if ($imageUrl === '') {
            continue;
        }

        $items[] = array_filter([
            '@type' => 'ImageObject',
            'name' => galleryPhotoLabel($photo),
            'contentUrl' => $imageUrl,
            'thumbnailUrl' => trim((string)($photo['thumb_url'] ?? galleryPhotoMediaUrl($photo, 'thumb'))),
            'url' => galleryPhotoPublicUrl($photo),
            'caption' => galleryPhotoCaption($photo),
            'creditText' => trim((string)($photo['credit'] ?? '')),
            'license' => normalizeGalleryLicenseUrl((string)($photo['license_url'] ?? '')),
            'dateCreated' => trim((string)($photo['taken_at'] ?? '')),
        ], static fn ($value): bool => $value !== '');
    }

    $data = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'ImageGallery',
        'name' => trim((string)($album['name'] ?? 'Album')),
        'description' => trim((string)($album['excerpt'] ?? galleryAlbumExcerpt($album, 500))),
        'url' => galleryAlbumPublicUrl($album),
        'image' => trim((string)($album['cover_url'] ?? '')),
        'associatedMedia' => $items !== [] ? $items : null,
    ], static fn ($value): bool => $value !== '' && $value !== null);

    return structuredDataScript($data);
}

/**
 * @param array<string, mixed> $photo
 * @param array<string, mixed> $album
 */
function galleryPhotoStructuredData(array $photo, array $album = []): string
{
    $imageUrl = trim((string)($photo['image_url'] ?? galleryPhotoMediaUrl($photo, 'full')));
    if ($imageUrl === '') {
        return '';
    }

    $data = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'ImageObject',
        'name' => galleryPhotoLabel($photo),
        'caption' => galleryPhotoCaption($photo),
        'description' => trim((string)($photo['description'] ?? '')),
        'creditText' => trim((string)($photo['credit'] ?? '')),
        'copyrightNotice' => trim((string)($photo['license_label'] ?? '')),
        'license' => normalizeGalleryLicenseUrl((string)($photo['license_url'] ?? '')),
        'dateCreated' => trim((string)($photo['taken_at'] ?? '')),
        'contentUrl' => $imageUrl,
        'thumbnailUrl' => trim((string)($photo['thumb_url'] ?? galleryPhotoMediaUrl($photo, 'thumb'))),
        'url' => galleryPhotoPublicUrl($photo),
        'isPartOf' => !empty($album) ? array_filter([
            '@type' => 'ImageGallery',
            'name' => trim((string)($album['name'] ?? 'Galerie')),
            'url' => galleryAlbumPublicUrl($album),
        ], static fn ($value): bool => $value !== '') : null,
    ], static fn ($value): bool => $value !== '' && $value !== null);

    return structuredDataScript($data);
}

/**
 * @param array<string, mixed> $news
 * @param array<string, mixed> $query
 */
function newsPublicPath(array $news, array $query = []): string
{
    return BASE_URL . appendUrlQuery(newsPublicRequestPath($news), $query);
}

/**
 * @param array<string, mixed> $news
 */
function newsPreviewPath(array $news): string
{
    $previewToken = trim((string)($news['preview_token'] ?? ''));
    return newsPublicPath($news, $previewToken !== '' ? ['preview' => $previewToken] : []);
}

/**
 * @param array<string, mixed> $page
 */
function pagePreviewPath(array $page): string
{
    $previewToken = trim((string)($page['preview_token'] ?? ''));
    return pagePublicPath($page, $previewToken !== '' ? ['preview' => $previewToken] : []);
}

/**
 * @param array<string, mixed> $event
 */
function eventPreviewPath(array $event): string
{
    $previewToken = trim((string)($event['preview_token'] ?? ''));
    return eventPublicPath($event, $previewToken !== '' ? ['preview' => $previewToken] : []);
}

/**
 * @param array<string, mixed> $item
 */
function boardPreviewPath(array $item): string
{
    $previewToken = trim((string)($item['preview_token'] ?? ''));
    return boardPublicPath($item, $previewToken !== '' ? ['preview' => $previewToken] : []);
}

/**
 * @param array<string, mixed> $news
 * @param array<string, mixed> $query
 */
function newsPublicUrl(array $news, array $query = []): string
{
    return siteUrl(appendUrlQuery(newsPublicRequestPath($news), $query));
}

/**
 * @param array<string, mixed> $news
 */
function newsStructuredData(array $news): string
{
    $headline = trim((string)($news['title'] ?? ''));
    $url = newsPublicUrl($news);
    if ($headline === '' || $url === '') {
        return '';
    }

    $description = trim((string)($news['meta_description'] ?? ''));
    if ($description === '') {
        $description = newsExcerpt((string)($news['content'] ?? ''), 220);
    }
    $description = str_replace('â€¦', '…', $description);

    $authorData = null;
    if (!empty($news['author_name'])) {
        $authorData = array_filter([
            '@type' => 'Person',
            'name' => trim((string)$news['author_name']),
            'url' => trim((string)($news['author_public_url'] ?? '')),
        ], static fn ($value): bool => $value !== '');
    }

    $data = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'NewsArticle',
        'headline' => $headline,
        'description' => $description,
        'datePublished' => !empty($news['created_at']) ? date(DATE_ATOM, strtotime((string)$news['created_at'])) : '',
        'dateModified' => !empty($news['updated_at']) ? date(DATE_ATOM, strtotime((string)$news['updated_at'])) : '',
        'mainEntityOfPage' => $url,
        'url' => $url,
        'author' => $authorData,
        'publisher' => array_filter([
            '@type' => 'Organization',
            'name' => getSetting('site_name', 'Kora CMS'),
            'url' => siteUrl('/'),
        ], static fn ($value): bool => $value !== ''),
    ], static fn ($value): bool => $value !== '' && $value !== null);

    return structuredDataScript($data);
}

/**
 * @param array<string, mixed> $category
 */
function downloadCategoryRequestPath(array $category): string
{
    $slug = downloadCategorySlug((string)($category['slug'] ?? ''));
    if ($slug !== '') {
        return '/downloads/kategorie/' . rawurlencode($slug);
    }

    return '/downloads/index.php?kat=' . (int)($category['id'] ?? 0);
}

/**
 * @param array<string, mixed> $category
 * @param array<string, mixed> $query
 */
function downloadCategoryPath(array $category, array $query = []): string
{
    return BASE_URL . appendUrlQuery(downloadCategoryRequestPath($category), $query);
}

/**
 * @param array<string, mixed> $category
 * @param array<string, mixed> $query
 */
function downloadCategoryUrl(array $category, array $query = []): string
{
    return siteUrl(appendUrlQuery(downloadCategoryRequestPath($category), $query));
}

/**
 * @param array<string, mixed> $series
 */
function downloadSeriesRequestPath(array $series): string
{
    $slug = downloadSeriesSlug((string)($series['slug'] ?? ''));
    if ($slug !== '') {
        return '/downloads/serie/' . rawurlencode($slug);
    }

    return '/downloads/index.php';
}

/**
 * @param array<string, mixed> $series
 * @param array<string, mixed> $query
 */
function downloadSeriesPath(array $series, array $query = []): string
{
    return BASE_URL . appendUrlQuery(downloadSeriesRequestPath($series), $query);
}

/**
 * @param array<string, mixed> $series
 * @param array<string, mixed> $query
 */
function downloadSeriesUrl(array $series, array $query = []): string
{
    return siteUrl(appendUrlQuery(downloadSeriesRequestPath($series), $query));
}

/**
 * @param array<string, mixed> $download
 */
function downloadPublicRequestPath(array $download): string
{
    $slug = downloadSlug((string)($download['slug'] ?? ''));
    if ($slug !== '') {
        return '/downloads/' . rawurlencode($slug);
    }

    return '/downloads/item.php?id=' . (int)($download['id'] ?? 0);
}

/**
 * @param array<string, mixed> $download
 * @param array<string, mixed> $query
 */
function downloadPublicPath(array $download, array $query = []): string
{
    return BASE_URL . appendUrlQuery(downloadPublicRequestPath($download), $query);
}

/**
 * @param array<string, mixed> $download
 * @param array<string, mixed> $query
 */
function downloadPublicUrl(array $download, array $query = []): string
{
    return siteUrl(appendUrlQuery(downloadPublicRequestPath($download), $query));
}

/**
 * @param array<string, mixed> $download
 */
function downloadExternalOpenPath(array $download): string
{
    return BASE_URL . '/downloads/external.php?id=' . max(0, (int)($download['id'] ?? 0));
}

/**
 * @param array<string, mixed> $document
 */
function boardPublicRequestPath(array $document): string
{
    $slug = boardSlug((string)($document['slug'] ?? ''));
    if ($slug !== '') {
        return '/board/' . rawurlencode($slug);
    }

    return '/board/document.php?id=' . (int)($document['id'] ?? 0);
}

/**
 * @param array<string, mixed> $document
 * @param array<string, mixed> $query
 */
function boardPublicPath(array $document, array $query = []): string
{
    return BASE_URL . appendUrlQuery(boardPublicRequestPath($document), $query);
}

/**
 * @param array<string, mixed> $document
 * @param array<string, mixed> $query
 */
function boardPublicUrl(array $document, array $query = []): string
{
    return siteUrl(appendUrlQuery(boardPublicRequestPath($document), $query));
}

/**
 * @param array<string, mixed> $category
 */
function boardCategoryRequestPath(array $category): string
{
    $slug = boardCategorySlug((string)($category['slug'] ?? ''));
    if ($slug !== '') {
        return '/board/kategorie/' . rawurlencode($slug);
    }

    return '/board/index.php?kat=' . (int)($category['id'] ?? 0);
}

/**
 * @param array<string, mixed> $category
 * @param array<string, mixed> $query
 */
function boardCategoryPath(array $category, array $query = []): string
{
    return BASE_URL . appendUrlQuery(boardCategoryRequestPath($category), $query);
}

/**
 * @param array<string, mixed> $category
 * @param array<string, mixed> $query
 */
function boardCategoryUrl(array $category, array $query = []): string
{
    return siteUrl(appendUrlQuery(boardCategoryRequestPath($category), $query));
}

function boardPublicVisibilitySql(string $alias = ''): string
{
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';

    return "{$prefix}deleted_at IS NULL AND {$prefix}status = 'published' AND {$prefix}is_published = 1 AND {$prefix}posted_date <= CURDATE()"
        . " AND ({$prefix}publish_at IS NULL OR {$prefix}publish_at <= NOW())"
        . " AND ({$prefix}unpublish_at IS NULL OR {$prefix}unpublish_at > NOW())";
}

function boardScopeVisibilitySql(string $scope, string $alias = ''): string
{
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';

    return match ($scope) {
        'archive' => "{$prefix}removal_date IS NOT NULL AND {$prefix}removal_date < CURDATE()",
        'all' => '1 = 1',
        default => "{$prefix}removal_date IS NULL OR {$prefix}removal_date >= CURDATE()",
    };
}

/**
 * @param array<string, mixed> $document
 */
function boardIsPubliclyReachable(array $document): bool
{
    if ((int)($document['is_published'] ?? 0) !== 1) {
        return false;
    }
    if ((string)($document['status'] ?? 'published') !== 'published') {
        return false;
    }
    if (!empty($document['deleted_at'])) {
        return false;
    }
    if (boardSlug((string)($document['slug'] ?? '')) === '') {
        return false;
    }

    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');
    $postedDate = trim((string)($document['posted_date'] ?? ''));
    $publishAt = trim((string)($document['publish_at'] ?? ''));
    $unpublishAt = trim((string)($document['unpublish_at'] ?? ''));

    if ($postedDate !== '' && $postedDate > $today) {
        return false;
    }
    if ($publishAt !== '' && $publishAt > $now) {
        return false;
    }
    if ($unpublishAt !== '' && $unpublishAt <= $now) {
        return false;
    }

    return true;
}

/**
 * @param array<string, mixed> $document
 */
function boardAttachmentChecksum(array $document): string
{
    $storedName = basename(trim((string)($document['filename'] ?? '')));
    if ($storedName === '') {
        return '';
    }

    $path = dirname(__DIR__) . '/uploads/board/' . $storedName;
    if (!is_file($path)) {
        return '';
    }

    $checksum = hash_file('sha256', $path);
    return is_string($checksum) ? $checksum : '';
}

/**
 * @return array<string, string>
 */
function boardPublicationEventLabels(): array
{
    return [
        'published' => 'Zveřejnění',
        'url_changed' => 'Změna veřejné adresy',
        'attachment_changed' => 'Výměna přílohy',
        'removed' => 'Sejmutí',
        'hidden' => 'Skrytí',
        'deleted' => 'Smazání',
        'restored' => 'Obnovení',
    ];
}

function boardPublicationEventLabel(string $eventType): string
{
    $labels = boardPublicationEventLabels();
    return $labels[$eventType] ?? 'Změna položky';
}

/**
 * @param array<string, mixed> $document
 */
function recordBoardPublicationEvent(PDO $pdo, array $document, string $eventType, ?int $actorUserId = null): void
{
    if (!array_key_exists($eventType, boardPublicationEventLabels())) {
        $eventType = 'published';
    }

    $boardId = (int)($document['id'] ?? 0);
    if ($boardId <= 0) {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO cms_board_publication_events
             (board_id, event_type, event_date, actor_user_id, public_path, attachment_name, attachment_size, attachment_checksum)
             VALUES (?, ?, NOW(), ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $boardId,
            $eventType,
            $actorUserId,
            boardPublicPath($document),
            trim((string)($document['original_name'] ?? '')),
            max(0, (int)($document['file_size'] ?? 0)),
            boardAttachmentChecksum($document),
        ]);
    } catch (\PDOException $e) {
        koraLog('warning', 'board publication event save failed', [
            'board_id' => $boardId,
            'event_type' => $eventType,
            'exception' => $e,
        ]);
    }
}

/**
 * @return list<array<string, mixed>>
 */
function boardPublicationEvents(PDO $pdo, int $boardId): array
{
    if ($boardId <= 0) {
        return [];
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT e.*,
                    COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), '') AS actor_name
             FROM cms_board_publication_events e
             LEFT JOIN cms_users u ON u.id = e.actor_user_id
             WHERE e.board_id = ?
             ORDER BY e.event_date DESC, e.id DESC"
        );
        $stmt->execute([$boardId]);
        return $stmt->fetchAll() ?: [];
    } catch (\PDOException $e) {
        koraLog('warning', 'board publication events load failed', [
            'board_id' => $boardId,
            'exception' => $e,
        ]);
        return [];
    }
}

/**
 * @param array<int, mixed> $values
 * @param array<int, mixed> $validIds
 * @return list<int>
 */
function normalizeBoardSubscriberCategoryIds(array $values, array $validIds): array
{
    $validMap = [];
    foreach ($validIds as $validId) {
        $id = (int)$validId;
        if ($id > 0) {
            $validMap[$id] = true;
        }
    }

    $selected = [];
    foreach ($values as $value) {
        $id = (int)$value;
        if ($id > 0 && isset($validMap[$id])) {
            $selected[$id] = $id;
        }
    }

    $result = array_values($selected);
    sort($result);
    return $result;
}

/**
 * @param array<string, mixed> $oldDocument
 * @param array<string, mixed> $newDocument
 */
function shouldSendBoardPublicationNotice(array $oldDocument, array $newDocument): bool
{
    return !boardIsPubliclyReachable($oldDocument) && boardIsPubliclyReachable($newDocument);
}

/**
 * @param array<string, mixed> $document
 */
function notifyBoardSubscribers(PDO $pdo, array $document): int
{
    if (!boardIsPubliclyReachable($document)) {
        return 0;
    }

    $categoryId = (int)($document['category_id'] ?? 0);
    try {
        if ($categoryId > 0) {
            $stmt = $pdo->prepare(
                "SELECT DISTINCT s.email, s.token
                 FROM cms_board_subscribers s
                 WHERE s.confirmed = 1
                   AND (
                       s.all_categories = 1
                       OR EXISTS (
                           SELECT 1
                           FROM cms_board_subscriber_categories sc
                           WHERE sc.subscriber_id = s.id AND sc.category_id = ?
                       )
                   )"
            );
            $stmt->execute([$categoryId]);
        } else {
            $stmt = $pdo->query(
                "SELECT DISTINCT s.email, s.token
                 FROM cms_board_subscribers s
                 WHERE s.confirmed = 1 AND s.all_categories = 1"
            );
        }
        $subscribers = $stmt->fetchAll() ?: [];
    } catch (\PDOException $e) {
        koraLog('warning', 'board subscriber load failed', [
            'board_id' => (int)($document['id'] ?? 0),
            'exception' => $e,
        ]);
        return 0;
    }

    $sent = 0;
    foreach ($subscribers as $subscriber) {
        $email = trim((string)($subscriber['email'] ?? ''));
        $token = trim((string)($subscriber['token'] ?? ''));
        if ($email === '' || $token === '') {
            continue;
        }
        if (sendBoardItemNotification($email, $token, $document)) {
            $sent++;
        }
    }

    return $sent;
}

function upsertPathRedirect(PDO $pdo, string $oldPath, string $newPath, int $statusCode = 301): void
{
    $oldPath = internalRedirectTarget($oldPath, '');
    $newPath = storedRedirectTarget($newPath, '');
    if ($oldPath === '' || $newPath === '' || $oldPath === $newPath) {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO cms_redirects (old_path, new_path, status_code)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE new_path = VALUES(new_path), status_code = VALUES(status_code)"
        );
        $stmt->execute([$oldPath, $newPath, in_array($statusCode, [301, 302], true) ? $statusCode : 301]);
    } catch (\PDOException $e) {
        koraLog('warning', 'path redirect save failed', [
            'old_path_hash' => hash('sha256', $oldPath),
            'new_path_hash' => hash('sha256', $newPath),
            'status_code' => in_array($statusCode, [301, 302], true) ? $statusCode : 301,
            'exception' => $e,
        ]);
    }
}

function deleteRedirectsTargetingPath(PDO $pdo, string $targetPath): void
{
    $targetPath = storedRedirectTarget($targetPath, '');
    if ($targetPath === '') {
        return;
    }

    try {
        $pdo->prepare("DELETE FROM cms_redirects WHERE new_path = ?")->execute([$targetPath]);
    } catch (\PDOException $e) {
        koraLog('warning', 'path redirect cleanup failed', [
            'target_path_hash' => hash('sha256', $targetPath),
            'exception' => $e,
        ]);
    }
}

/**
 * @param array<string, mixed> $event
 */
function eventPublicRequestPath(array $event): string
{
    $slug = eventSlug((string)($event['slug'] ?? ''));
    if ($slug !== '') {
        return '/events/' . rawurlencode($slug);
    }

    return '/events/event.php?id=' . (int)($event['id'] ?? 0);
}

/**
 * @param array<string, mixed> $eventType
 */
function eventTypeRequestPath(array $eventType): string
{
    $slug = eventTypeSlug((string)($eventType['slug'] ?? ''));
    if ($slug !== '') {
        return '/events/typ/' . rawurlencode($slug);
    }

    return '/events/index.php';
}

/**
 * @param array<string, mixed> $eventType
 * @param array<string, mixed> $query
 */
function eventTypePath(array $eventType, array $query = []): string
{
    return BASE_URL . appendUrlQuery(eventTypeRequestPath($eventType), $query);
}

/**
 * @param array<string, mixed> $eventType
 * @param array<string, mixed> $query
 */
function eventTypeUrl(array $eventType, array $query = []): string
{
    return siteUrl(appendUrlQuery(eventTypeRequestPath($eventType), $query));
}

/**
 * @return list<array<string, mixed>>
 */
function loadEventTypes(PDO $pdo, bool $activeOnly = false): array
{
    try {
        $where = $activeOnly ? 'WHERE is_active = 1' : '';
        $rows = $pdo->query(
            "SELECT id, legacy_key, title, slug, description, meta_title, meta_description,
                    is_active, sort_order, created_at, updated_at
             FROM cms_event_types
             {$where}
             ORDER BY sort_order, title, id"
        )->fetchAll();
    } catch (\PDOException) {
        return [];
    }

    return array_values(array_map(static function (array $row): array {
        $row['id'] = (int)($row['id'] ?? 0);
        $row['legacy_key'] = trim((string)($row['legacy_key'] ?? ''));
        $row['title'] = trim((string)($row['title'] ?? ''));
        $row['slug'] = eventTypeSlug((string)($row['slug'] ?? ''));
        $row['description'] = trim((string)($row['description'] ?? ''));
        $row['meta_title'] = trim((string)($row['meta_title'] ?? ''));
        $row['meta_description'] = trim((string)($row['meta_description'] ?? ''));
        $row['is_active'] = (int)($row['is_active'] ?? 0);
        $row['sort_order'] = (int)($row['sort_order'] ?? 0);
        $row['public_path'] = eventTypePath($row);
        $row['public_url'] = eventTypeUrl($row);

        return $row;
    }, $rows ?: []));
}

/**
 * @param array<string, mixed> $place
 */
function placePublicRequestPath(array $place): string
{
    $slug = placeSlug((string)($place['slug'] ?? ''));
    if ($slug !== '') {
        return '/places/' . rawurlencode($slug);
    }

    return '/places/place.php?id=' . (int)($place['id'] ?? 0);
}

/**
 * @param array<string, mixed> $place
 * @param array<string, mixed> $query
 */
function placePublicPath(array $place, array $query = []): string
{
    return BASE_URL . appendUrlQuery(placePublicRequestPath($place), $query);
}

/**
 * @param array<string, mixed> $place
 * @param array<string, mixed> $query
 */
function placePublicUrl(array $place, array $query = []): string
{
    return siteUrl(appendUrlQuery(placePublicRequestPath($place), $query));
}

/**
 * @param array<string, mixed> $event
 * @param array<string, mixed> $query
 */
function eventPublicPath(array $event, array $query = []): string
{
    return BASE_URL . appendUrlQuery(eventPublicRequestPath($event), $query);
}

/**
 * @param array<string, mixed> $event
 * @param array<string, mixed> $query
 */
function eventPublicUrl(array $event, array $query = []): string
{
    return siteUrl(appendUrlQuery(eventPublicRequestPath($event), $query));
}

/**
 * @param array<string, mixed> $event
 */
function eventIcsRequestPath(array $event): string
{
    $slug = eventSlug((string)($event['slug'] ?? ''));
    if ($slug !== '') {
        return '/events/' . rawurlencode($slug) . '.ics';
    }

    return '/events/ics.php?id=' . (int)($event['id'] ?? 0);
}

/**
 * @param array<string, mixed> $event
 * @param array<string, mixed> $query
 */
function eventIcsPath(array $event, array $query = []): string
{
    return BASE_URL . appendUrlQuery(eventIcsRequestPath($event), $query);
}

/**
 * @param array<string, mixed> $event
 * @param array<string, mixed> $query
 */
function eventIcsUrl(array $event, array $query = []): string
{
    return siteUrl(appendUrlQuery(eventIcsRequestPath($event), $query));
}

/**
 * @param array<string, mixed> $event
 * @return array<string, mixed>
 */
function eventRevisionSnapshot(array $event): array
{
    return [
        'title' => trim((string)($event['title'] ?? '')),
        'slug' => eventSlug((string)($event['slug'] ?? '')),
        'event_kind' => normalizeEventKind((string)($event['event_kind'] ?? 'general')),
        'event_type_id' => (string)((int)($event['event_type_id'] ?? 0)),
        'place_id' => (string)((int)($event['place_id'] ?? 0)),
        'excerpt' => trim((string)($event['excerpt'] ?? '')),
        'description' => trim((string)($event['description'] ?? '')),
        'program_note' => trim((string)($event['program_note'] ?? '')),
        'location' => trim((string)($event['location'] ?? '')),
        'event_date' => trim((string)($event['event_date'] ?? '')),
        'event_end' => trim((string)($event['event_end'] ?? '')),
        'organizer_name' => trim((string)($event['organizer_name'] ?? '')),
        'organizer_email' => trim((string)($event['organizer_email'] ?? '')),
        'registration_url' => normalizeDownloadExternalUrl((string)($event['registration_url'] ?? '')),
        'price_note' => trim((string)($event['price_note'] ?? '')),
        'accessibility_note' => trim((string)($event['accessibility_note'] ?? '')),
        'unpublish_at' => trim((string)($event['unpublish_at'] ?? '')),
        'admin_note' => trim((string)($event['admin_note'] ?? '')),
        'is_published' => (string)((int)($event['is_published'] ?? 1)),
    ];
}

/**
 * @param array<string, mixed> $event
 * @return array<string, mixed>
 */
function hydrateEventPresentation(array $event): array
{
    $event['slug'] = eventSlug((string)($event['slug'] ?? ''));
    $legacyEventKind = normalizeEventKind((string)($event['event_kind'] ?? 'general'));
    $event['event_kind'] = $legacyEventKind;

    $eventTypeTitle = trim((string)($event['event_type_title'] ?? ''));
    $eventTypeSlug = eventTypeSlug((string)($event['event_type_slug'] ?? ''));
    $eventTypeId = (int)($event['event_type_id'] ?? 0);
    $event['event_type'] = null;
    $event['event_type_path'] = '';
    if ($eventTypeId > 0 && $eventTypeTitle !== '') {
        $eventType = [
            'id' => $eventTypeId,
            'legacy_key' => trim((string)($event['event_type_legacy_key'] ?? '')),
            'title' => $eventTypeTitle,
            'slug' => $eventTypeSlug,
            'description' => trim((string)($event['event_type_description'] ?? '')),
            'meta_title' => trim((string)($event['event_type_meta_title'] ?? '')),
            'meta_description' => trim((string)($event['event_type_meta_description'] ?? '')),
            'is_active' => (int)($event['event_type_is_active'] ?? 0),
        ];
        $event['event_type'] = $eventType;
        $event['event_type_path'] = $eventTypeSlug !== '' ? eventTypePath($eventType) : '';
        $event['event_kind_label'] = $eventTypeTitle;
        $event['event_kind_help'] = (string)$eventType['description'];
    } else {
        $event['event_kind_label'] = (string)(eventKindDefinitions()[$legacyEventKind]['label'] ?? 'Akce');
        $event['event_kind_help'] = eventKindHelp($legacyEventKind);
    }

    $event['excerpt_plain'] = eventExcerpt($event);
    $event['image_url'] = eventImageUrl($event);
    $event['location'] = trim((string)($event['location'] ?? ''));
    $event['place'] = null;
    $event['place_path'] = '';
    $event['place_map_url'] = '';
    $placeName = trim((string)($event['place_name'] ?? ''));
    if ((int)($event['place_id'] ?? 0) > 0 && $placeName !== '') {
        $place = hydratePlacePresentation([
            'id' => (int)$event['place_id'],
            'name' => $placeName,
            'slug' => (string)($event['place_slug'] ?? ''),
            'address' => (string)($event['place_address'] ?? ''),
            'locality' => (string)($event['place_locality'] ?? ''),
            'latitude' => $event['place_latitude'] ?? '',
            'longitude' => $event['place_longitude'] ?? '',
            'status' => (string)($event['place_status'] ?? 'published'),
            'is_published' => (int)($event['place_is_published'] ?? 1),
        ]);
        if (!function_exists('isModuleEnabled') || (isModuleEnabled('places') && !empty($place['is_publicly_visible']))) {
            $event['place'] = $place;
            $event['place_path'] = (string)$place['public_path'];
            $event['place_map_url'] = (string)$place['map_url'];
        }
    }
    $event['location_display'] = trim(implode(', ', array_filter([
        is_array($event['place']) ? (string)($event['place']['name'] ?? '') : '',
        $event['location'],
    ], static fn (string $value): bool => $value !== '')));
    $event['organizer_name'] = trim((string)($event['organizer_name'] ?? ''));
    $event['organizer_email'] = trim((string)($event['organizer_email'] ?? ''));
    $event['registration_url'] = normalizeDownloadExternalUrl((string)($event['registration_url'] ?? ''));
    $event['price_note'] = trim((string)($event['price_note'] ?? ''));
    $event['accessibility_note'] = trim((string)($event['accessibility_note'] ?? ''));
    $event['program_note'] = trim((string)($event['program_note'] ?? ''));
    $event['has_registration_url'] = $event['registration_url'] !== '';
    $event['has_organizer'] = $event['organizer_name'] !== '' || $event['organizer_email'] !== '';
    $event['has_accessibility_note'] = $event['accessibility_note'] !== '';
    $event['has_program_note'] = $event['program_note'] !== '';
    $event['has_price_note'] = $event['price_note'] !== '';
    $event['recurrence_group_id'] = trim((string)($event['recurrence_group_id'] ?? ''));
    $event['event_status_key'] = eventCurrentStatus($event);
    $event['event_status_label'] = match ($event['event_status_key']) {
        'ongoing' => 'Právě probíhá',
        'past' => 'Proběhlo',
        default => 'Připravujeme',
    };

    return $event;
}

/**
 * @param array<string, mixed> $event
 */
function eventCurrentStatus(array $event, ?\DateTimeInterface $now = null): string
{
    $nowTs = $now ? $now->getTimestamp() : time();
    $startRaw = trim((string)($event['event_date'] ?? ''));
    if ($startRaw === '') {
        return 'upcoming';
    }

    try {
        $startTs = (new \DateTimeImmutable($startRaw))->getTimestamp();
    } catch (\Exception) {
        return 'upcoming';
    }

    $endRaw = trim((string)($event['event_end'] ?? ''));
    if ($endRaw !== '') {
        try {
            $endTs = (new \DateTimeImmutable($endRaw))->getTimestamp();
        } catch (\Exception) {
            $endTs = $startTs;
        }
    } else {
        $endTs = $startTs;
    }

    if ($startTs <= $nowTs && $endTs >= $nowTs) {
        return 'ongoing';
    }

    if ($endTs < $nowTs) {
        return 'past';
    }

    return 'upcoming';
}

function eventPublicVisibilitySql(string $alias = ''): string
{
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';

    return "{$prefix}status = 'published'"
        . " AND {$prefix}is_published = 1"
        . " AND {$prefix}deleted_at IS NULL"
        . " AND ({$prefix}unpublish_at IS NULL OR {$prefix}unpublish_at > NOW())"
        . " AND ({$prefix}publish_at IS NULL OR {$prefix}publish_at <= NOW())";
}

function eventEffectiveEndSql(string $alias = ''): string
{
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';
    return "COALESCE({$prefix}event_end, {$prefix}event_date)";
}

function eventScopeVisibilitySql(string $scope, string $alias = ''): string
{
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';
    $effectiveEnd = eventEffectiveEndSql($alias);

    return match ($scope) {
        'ongoing' => "{$prefix}event_date <= NOW() AND {$effectiveEnd} >= NOW()",
        'past' => "{$effectiveEnd} < NOW()",
        'all' => '1 = 1',
        default => "{$prefix}event_date > NOW()",
    };
}

function normalizeEventRecurrenceFrequency(string $frequency): string
{
    return in_array($frequency, ['none', 'daily', 'weekly', 'monthly'], true) ? $frequency : 'none';
}

function eventRecurrenceShift(DateTimeImmutable $date, string $frequency, int $offset): DateTimeImmutable
{
    return match (normalizeEventRecurrenceFrequency($frequency)) {
        'daily' => $date->modify('+' . $offset . ' day'),
        'weekly' => $date->modify('+' . $offset . ' week'),
        'monthly' => $date->modify('+' . $offset . ' month'),
        default => $date,
    };
}

function eventRecurrenceGroupId(): string
{
    return bin2hex(random_bytes(16));
}

/**
 * @param array<string, mixed> $event
 */
function eventStructuredData(array $event): string
{
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => trim((string)($event['title'] ?? '')),
        'startDate' => '',
        'url' => eventPublicUrl($event),
        'description' => eventExcerpt($event, 500),
        'eventStatus' => match (eventCurrentStatus($event)) {
            'ongoing' => 'https://schema.org/EventScheduled',
            'past' => 'https://schema.org/EventCompleted',
            default => 'https://schema.org/EventScheduled',
        },
    ];

    try {
        $start = new \DateTimeImmutable((string)($event['event_date'] ?? ''));
        $data['startDate'] = $start->format(DATE_ATOM);
    } catch (\Exception) {
        $data['startDate'] = '';
    }

    if ($data['startDate'] === '') {
        return '';
    }

    $endRaw = trim((string)($event['event_end'] ?? ''));
    if ($endRaw !== '') {
        try {
            $data['endDate'] = (new \DateTimeImmutable($endRaw))->format(DATE_ATOM);
        } catch (\Exception) {
            // Ignore invalid end date in structured data.
        }
    }

    $imageUrl = trim((string)($event['image_url'] ?? eventImageUrl($event)));
    if ($imageUrl !== '') {
        $data['image'] = [siteUrl(str_replace(BASE_URL, '', $imageUrl))];
    }

    $place = is_array($event['place'] ?? null) ? $event['place'] : null;
    if ($place !== null) {
        $data['location'] = array_filter([
            '@type' => 'Place',
            'name' => trim((string)($place['name'] ?? '')),
            'url' => trim((string)($place['public_url'] ?? '')),
            'address' => !empty($place['full_address']) ? array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => trim((string)($place['address'] ?? '')),
                'addressLocality' => trim((string)($place['locality'] ?? '')),
            ], static fn ($value): bool => $value !== '') : null,
            'geo' => !empty($place['has_coordinates']) ? [
                '@type' => 'GeoCoordinates',
                'latitude' => (string)($place['latitude'] ?? ''),
                'longitude' => (string)($place['longitude'] ?? ''),
            ] : null,
        ], static fn ($value): bool => $value !== '' && $value !== null);
    } else {
        $location = trim((string)($event['location'] ?? ''));
        if ($location !== '') {
            $data['location'] = [
                '@type' => 'Place',
                'name' => $location,
            ];
        }
    }

    $organizerName = trim((string)($event['organizer_name'] ?? ''));
    $organizerEmail = trim((string)($event['organizer_email'] ?? ''));
    if ($organizerName !== '' || $organizerEmail !== '') {
        $data['organizer'] = array_filter([
            '@type' => 'Organization',
            'name' => $organizerName,
            'email' => $organizerEmail !== '' ? 'mailto:' . $organizerEmail : '',
        ], static fn ($value): bool => $value !== '');
    }

    $registrationUrl = trim((string)($event['registration_url'] ?? ''));
    if ($registrationUrl !== '') {
        $data['offers'] = [
            '@type' => 'Offer',
            'url' => $registrationUrl,
            'availability' => 'https://schema.org/InStock',
        ];
    }

    return structuredDataScript($data);
}

/**
 * @param list<array<string, mixed>> $faqs
 */
function faqStructuredData(array $faqs, string $pageUrl = ''): string
{
    $mainEntities = [];

    foreach ($faqs as $faq) {
        $question = trim((string)($faq['question'] ?? ''));
        $answer = normalizePlainText((string)($faq['answer'] ?? ''));

        if ($question === '' || $answer === '') {
            continue;
        }

        $mainEntities[] = [
            '@type' => 'Question',
            'name' => $question,
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $answer,
            ],
            'url' => faqPublicUrl($faq),
        ];
    }

    if ($mainEntities === []) {
        return '';
    }

    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $mainEntities,
    ];

    if ($pageUrl !== '') {
        $data['url'] = $pageUrl;
    }

    return structuredDataScript($data);
}

function faqFeedbackVisitorHash(int $faqId): string
{
    $ip = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    $userAgent = is_string($_SERVER['HTTP_USER_AGENT'] ?? null) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $pepper = defined('CRON_TOKEN') && trim((string)CRON_TOKEN) !== ''
        ? (string)CRON_TOKEN
        : 'kora-faq-feedback';

    return hash_hmac('sha256', $faqId . '|' . $ip . '|' . $userAgent, $pepper);
}

/**
 * @param array<string, mixed> $event
 */
function eventIcsFilename(array $event): string
{
    $slug = eventSlug((string)($event['slug'] ?? ''));
    if ($slug === '') {
        $slug = 'udalost-' . (int)($event['id'] ?? 0);
    }

    return $slug . '.ics';
}

/**
 * @param array<string, mixed> $event
 */
function eventIcsContent(array $event): string
{
    $siteHost = (string)(parse_url(siteUrl('/'), PHP_URL_HOST) ?: 'localhost');
    $uid = 'event-' . (int)($event['id'] ?? 0) . '@' . $siteHost;
    $dtStamp = gmdate('Ymd\THis\Z');

    $escape = static function (string $value): string {
        return str_replace(
            ["\\", ';', ',', "\r\n", "\n", "\r"],
            ['\\\\', '\;', '\,', '\n', '\n', '\n'],
            $value
        );
    };

    $toUtc = static function (string $value): string {
        return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
    };

    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Kora CMS//Events//CS',
        'CALSCALE:GREGORIAN',
        'BEGIN:VEVENT',
        'UID:' . $uid,
        'DTSTAMP:' . $dtStamp,
        'DTSTART:' . $toUtc((string)($event['event_date'] ?? '')),
        'SUMMARY:' . $escape(trim((string)($event['title'] ?? 'Událost'))),
        'DESCRIPTION:' . $escape(eventExcerpt($event, 800)),
        'URL:' . eventPublicUrl($event),
    ];

    $endRaw = trim((string)($event['event_end'] ?? ''));
    if ($endRaw !== '') {
        $lines[] = 'DTEND:' . $toUtc($endRaw);
    }

    $location = trim((string)($event['location_display'] ?? $event['location'] ?? ''));
    if ($location !== '') {
        $lines[] = 'LOCATION:' . $escape($location);
    }

    $lines[] = 'END:VEVENT';
    $lines[] = 'END:VCALENDAR';

    return implode("\r\n", $lines) . "\r\n";
}

function uniqueArticleSlug(PDO $pdo, string $candidate, ?int $excludeId = null, int $blogId = 1): string
{
    $baseSlug = articleSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'clanek';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_articles WHERE slug = ? AND blog_id = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $blogId, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniquePageSlug(PDO $pdo, string $candidate, ?int $excludeId = null, ?int $blogId = null): string
{
    $baseSlug = pageSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'stranka';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $excludeId = $excludeId ?? 0;
    $stmt = $blogId === null
        ? $pdo->prepare("SELECT id FROM cms_pages WHERE slug = ? AND blog_id IS NULL AND id != ?")
        : $pdo->prepare("SELECT id FROM cms_pages WHERE slug = ? AND blog_id = ? AND id != ?");

    while (true) {
        $stmt->execute($blogId === null ? [$slug, $excludeId] : [$slug, $blogId, $excludeId]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function normalizePageNavigationOrder(PDO $pdo): void
{
    $pages = $pdo->query(
        "SELECT id, nav_order, title
         FROM cms_pages
         WHERE blog_id IS NULL AND deleted_at IS NULL
         ORDER BY nav_order, title, id"
    )->fetchAll();

    if ($pages === []) {
        return;
    }

    $update = $pdo->prepare("UPDATE cms_pages SET nav_order = ? WHERE id = ?");
    $position = 1;
    foreach ($pages as $page) {
        if ((int)($page['nav_order'] ?? 0) !== $position) {
            $update->execute([$position, (int)$page['id']]);
        }
        $position++;
    }
}

function nextPageNavigationOrder(PDO $pdo): int
{
    normalizePageNavigationOrder($pdo);

    $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(nav_order), 0) FROM cms_pages WHERE blog_id IS NULL AND deleted_at IS NULL")->fetchColumn();
    return $maxOrder + 1;
}

function movePageNavigationOrder(PDO $pdo, int $pageId, string $direction): bool
{
    if (!in_array($direction, ['up', 'down'], true)) {
        return false;
    }

    normalizePageNavigationOrder($pdo);

    $pages = $pdo->query(
        "SELECT id
         FROM cms_pages
         WHERE blog_id IS NULL AND deleted_at IS NULL
         ORDER BY nav_order, title, id"
    )->fetchAll();

    $orderedIds = array_map(static fn (array $row): int => (int)$row['id'], $pages);
    $currentIndex = array_search($pageId, $orderedIds, true);
    if ($currentIndex === false) {
        return false;
    }

    $swapIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;
    if (!isset($orderedIds[$swapIndex])) {
        return false;
    }

    [$orderedIds[$currentIndex], $orderedIds[$swapIndex]] = [$orderedIds[$swapIndex], $orderedIds[$currentIndex]];

    $update = $pdo->prepare("UPDATE cms_pages SET nav_order = ? WHERE id = ?");
    foreach ($orderedIds as $index => $orderedId) {
        $update->execute([$index + 1, $orderedId]);
    }

    return true;
}

function normalizeBlogPageNavigationOrder(PDO $pdo, int $blogId): void
{
    if ($blogId <= 0) {
        return;
    }

    $stmt = $pdo->prepare(
        "SELECT id, blog_nav_order, title
         FROM cms_pages
         WHERE blog_id = ? AND deleted_at IS NULL
         ORDER BY blog_nav_order, title, id"
    );
    $stmt->execute([$blogId]);
    $pages = $stmt->fetchAll();
    $items = [];
    foreach ($pages as $page) {
        $items[] = [
            'type' => 'page',
            'id' => (int)$page['id'],
            'order' => (int)($page['blog_nav_order'] ?? 0),
            'title' => (string)$page['title'],
        ];
    }
    foreach (loadNavigationLinks($pdo, $blogId, false) as $link) {
        $items[] = [
            'type' => 'link',
            'id' => (int)$link['id'],
            'order' => (int)($link['nav_order'] ?? 0),
            'title' => (string)$link['title'],
        ];
    }

    if ($items === []) {
        return;
    }

    usort($items, static function (array $left, array $right): int {
        $leftOrder = (int)$left['order'];
        $rightOrder = (int)$right['order'];
        if ($leftOrder !== $rightOrder) {
            return $leftOrder <=> $rightOrder;
        }

        return strcasecmp((string)$left['title'], (string)$right['title']);
    });

    $updatePage = $pdo->prepare("UPDATE cms_pages SET blog_nav_order = ? WHERE id = ?");
    $updateLink = null;
    $position = 1;
    foreach ($items as $item) {
        if ((int)$item['order'] !== $position) {
            if ($item['type'] === 'page') {
                $updatePage->execute([$position, (int)$item['id']]);
            } elseif ($item['type'] === 'link') {
                if (!$updateLink instanceof PDOStatement) {
                    $updateLink = $pdo->prepare("UPDATE cms_nav_links SET nav_order = ? WHERE id = ?");
                }
                $updateLink->execute([$position, (int)$item['id']]);
            }
        }
        $position++;
    }
}

function nextBlogPageNavigationOrder(PDO $pdo, int $blogId): int
{
    if ($blogId <= 0) {
        return 0;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT GREATEST(
                COALESCE((SELECT MAX(blog_nav_order) FROM cms_pages WHERE blog_id = ? AND deleted_at IS NULL), 0),
                COALESCE((SELECT MAX(nav_order) FROM cms_nav_links WHERE blog_id = ?), 0)
            )"
        );
        $stmt->execute([$blogId, $blogId]);
        return (int)$stmt->fetchColumn() + 1;
    } catch (\PDOException $e) {
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(blog_nav_order), 0) FROM cms_pages WHERE blog_id = ? AND deleted_at IS NULL");
        $stmt->execute([$blogId]);
        return (int)$stmt->fetchColumn() + 1;
    }
}

function navigationLinkUrl(string $target): string
{
    return storedRedirectTarget($target, '');
}

/**
 * @param array<string, mixed> $link
 */
function navigationLinkHref(array $link): string
{
    return navigationLinkUrl((string)($link['url'] ?? ''));
}

function newWindowLinkLabel(string $label, string $supplement = ''): string
{
    $parts = [];
    foreach ([$label, $supplement] as $part) {
        $part = trim($part);
        if ($part !== '') {
            $parts[] = $part;
        }
    }

    if ($parts === []) {
        $parts[] = 'Odkaz';
    }

    $parts[] = 'otevře se v novém okně';
    return implode(' – ', $parts);
}

function newWindowLinkSrOnlySuffix(): string
{
    return '<span class="sr-only"> – otevře se v novém okně</span>';
}

/**
 * @param array<string, mixed> $link
 */
function navigationLinkAnchorAttributes(array $link): string
{
    $href = navigationLinkHref($link);
    if ($href === '') {
        return '';
    }

    $attributes = ['href="' . h($href) . '"'];
    $opensInNewWindow = (int)($link['target_blank'] ?? 0) === 1;
    if ($opensInNewWindow) {
        $attributes[] = 'target="_blank"';
        $attributes[] = 'rel="noopener noreferrer"';
    }

    return implode(' ', $attributes);
}

/**
 * @param array<string, mixed> $link
 */
function navigationLinkAccessibleSuffix(array $link): string
{
    $altText = trim((string)($link['alt_text'] ?? ''));
    $opensInNewWindow = (int)($link['target_blank'] ?? 0) === 1;

    $suffix = '';
    if ($altText !== '') {
        $suffix .= '<span class="sr-only"> – ' . h($altText) . '</span>';
    }
    if ($opensInNewWindow) {
        $suffix .= newWindowLinkSrOnlySuffix();
    }

    return $suffix;
}

/**
 * @return list<array<string, mixed>>
 */
function loadNavigationLinks(PDO $pdo, ?int $blogId = null, bool $publicOnly = false): array
{
    try {
        if ($blogId === null) {
            $sql = "SELECT id, blog_id, title, url, alt_text, target_blank, is_active, nav_order
                    FROM cms_nav_links
                    WHERE blog_id IS NULL";
            $params = [];
        } else {
            $sql = "SELECT id, blog_id, title, url, alt_text, target_blank, is_active, nav_order
                    FROM cms_nav_links
                    WHERE blog_id = ?";
            $params = [$blogId];
        }
        if ($publicOnly) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY nav_order, title, id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $links = $stmt->fetchAll();
    } catch (\PDOException $e) {
        return [];
    }

    return array_values(array_filter(
        $links,
        static fn (array $link): bool => !$publicOnly || navigationLinkHref($link) !== ''
    ));
}

function nextNavigationLinkOrder(PDO $pdo, ?int $blogId = null): int
{
    if ($blogId === null) {
        try {
            $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(nav_order), 0) FROM cms_nav_links WHERE blog_id IS NULL")->fetchColumn();
            return $maxOrder + 1;
        } catch (\PDOException $e) {
            return 1;
        }
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT GREATEST(
                COALESCE((SELECT MAX(blog_nav_order) FROM cms_pages WHERE blog_id = ? AND deleted_at IS NULL), 0),
                COALESCE((SELECT MAX(nav_order) FROM cms_nav_links WHERE blog_id = ?), 0)
            )"
        );
        $stmt->execute([$blogId, $blogId]);
        return (int)$stmt->fetchColumn() + 1;
    } catch (\PDOException $e) {
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(blog_nav_order), 0) FROM cms_pages WHERE blog_id = ? AND deleted_at IS NULL");
        $stmt->execute([$blogId]);
        return (int)$stmt->fetchColumn() + 1;
    }
}

function normalizeNavigationLinkOrder(PDO $pdo, ?int $blogId = null): void
{
    $links = loadNavigationLinks($pdo, $blogId, false);
    if ($links === []) {
        return;
    }

    $update = $pdo->prepare("UPDATE cms_nav_links SET nav_order = ? WHERE id = ?");
    foreach ($links as $index => $link) {
        $position = $index + 1;
        if ((int)($link['nav_order'] ?? 0) !== $position) {
            $update->execute([$position, (int)$link['id']]);
        }
    }
}

function uniqueEventSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = eventSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'udalost';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_events WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniqueEventTypeSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = eventTypeSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'typ-akce';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_event_types WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function seedDefaultEventTypes(PDO $pdo): void
{
    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO cms_event_types
         (legacy_key, title, slug, description, is_active, sort_order, created_at, updated_at)
         VALUES (?, ?, ?, ?, 1, ?, NOW(), NOW())"
    );

    foreach (defaultEventTypeRows() as $row) {
        $stmt->execute([
            $row['legacy_key'],
            $row['title'],
            $row['slug'],
            $row['description'],
            $row['sort_order'],
        ]);
    }
}

function uniquePlaceSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = placeSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'misto';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_places WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniqueDownloadSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = downloadSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'soubor';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_downloads WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniqueDownloadCategorySlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = downloadCategorySlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'kategorie';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_dl_categories WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniqueFaqCategorySlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = faqCategorySlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'kategorie';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_faq_categories WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniqueDownloadSeriesSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = downloadSeriesSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'serie';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_download_series WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniqueFoodCardSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = foodCardSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'listek';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_food_cards WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

/**
 * @return array<string, string>
 */
function reservationBookingStatusLabels(): array
{
    return [
        'pending' => 'Čeká na schválení',
        'confirmed' => 'Potvrzená',
        'cancelled' => 'Zrušená',
        'rejected' => 'Zamítnutá',
        'completed' => 'Dokončená',
        'no_show' => 'Nedostavil se',
    ];
}

/**
 * @return array<string, string>
 */
function reservationBookingStatusColors(): array
{
    return [
        'pending' => '#8a4b00',
        'confirmed' => '#1b5e20',
        'cancelled' => '#666666',
        'rejected' => '#b71c1c',
        'completed' => '#005fcc',
        'no_show' => '#6d0000',
    ];
}

function uniqueBoardSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = boardSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'dokument';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_board WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniqueBoardCategorySlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = boardCategorySlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'kategorie';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_board_categories WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniqueGalleryAlbumSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = galleryAlbumSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'album';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_gallery_albums WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniqueGalleryPhotoSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = galleryPhotoSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'fotografie';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_gallery_photos WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniquePodcastShowSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = podcastShowSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'podcast';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_podcast_shows WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniquePodcastEpisodeSlug(PDO $pdo, int $showId, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = podcastEpisodeSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'epizoda';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_podcasts WHERE show_id = ? AND slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$showId, $slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniqueFaqSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = faqSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'otazka';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_faqs WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniquePollSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = pollSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'anketa';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_polls WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniqueNewsSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = newsSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'novinka';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_news WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniqueAuthorSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = authorSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'autor';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_users WHERE author_slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

/**
 * @param array<string, mixed> $author
 */
function authorRoleValue(array $author): string
{
    return trim((string)($author['author_role'] ?? $author['role'] ?? ''));
}

/**
 * @param array<string, mixed> $author
 */
function authorPublicSlugValue(array $author): string
{
    return authorSlug((string)($author['author_slug'] ?? $author['slug'] ?? ''));
}

/**
 * @param array<string, mixed> $author
 */
function authorPublicEnabled(array $author): bool
{
    return (int)($author['author_public_enabled'] ?? 0) === 1
        && authorRoleValue($author) !== 'public'
        && authorPublicSlugValue($author) !== '';
}

/**
 * @param array<string, mixed> $author
 */
function authorDisplayName(array $author): string
{
    $preferred = trim((string)($author['author_name'] ?? ''));
    if ($preferred !== '') {
        return $preferred;
    }

    $nickname = trim((string)($author['nickname'] ?? ''));
    if ($nickname !== '') {
        return $nickname;
    }

    $fullName = trim(
        trim((string)($author['first_name'] ?? '')) . ' ' . trim((string)($author['last_name'] ?? ''))
    );
    if ($fullName !== '') {
        return $fullName;
    }

    return trim((string)($author['email'] ?? ''));
}

function normalizeAuthorWebsite(string $value): string
{
    return normalizeHttpExternalUrl($value);
}

/**
 * @param array<string, mixed> $author
 */
function authorPublicRequestPath(array $author): string
{
    if (!authorPublicEnabled($author)) {
        return '';
    }

    return '/author/' . rawurlencode(authorPublicSlugValue($author));
}

function authorIndexRequestPath(): string
{
    return '/authors/';
}

/**
 * @param array<string, mixed> $author
 */
function authorPublicPath(array $author): string
{
    $path = authorPublicRequestPath($author);
    return $path !== '' ? BASE_URL . $path : '';
}

function authorIndexPath(): string
{
    return BASE_URL . authorIndexRequestPath();
}

/**
 * @param array<string, mixed> $author
 */
function authorPublicUrl(array $author): string
{
    $path = authorPublicRequestPath($author);
    return $path !== '' ? siteUrl($path) : '';
}

function authorIndexUrl(): string
{
    return siteUrl(authorIndexRequestPath());
}

/**
 * @param array<string, mixed> $author
 */
function authorAvatarUrl(array $author): string
{
    $avatarFile = trim((string)($author['author_avatar'] ?? ''));
    if ($avatarFile === '') {
        return '';
    }

    return BASE_URL . '/uploads/authors/' . rawurlencode($avatarFile);
}

/**
 * @param array<string, mixed> $author
 * @return array<string, mixed>
 */
function hydrateAuthorPresentation(array $author): array
{
    $author['author_display_name'] = authorDisplayName($author);
    $author['author_public_path'] = authorPublicPath($author);
    $author['author_public_url'] = authorPublicUrl($author);
    $author['author_avatar_url'] = authorAvatarUrl($author);
    $author['author_website_url'] = normalizeAuthorWebsite((string)($author['author_website'] ?? ''));
    return $author;
}

/**
 * @param array<string, mixed> $news
 * @return array<string, mixed>
 */
function hydrateNewsPresentation(array $news): array
{
    $news['title'] = newsTitleCandidate((string)($news['title'] ?? ''), (string)($news['content'] ?? ''));
    $news['slug'] = newsSlug((string)($news['slug'] ?? ''));
    $news['excerpt'] = newsExcerpt((string)($news['content'] ?? ''));
    $news['excerpt'] = str_replace('â€¦', '…', $news['excerpt']);
    $news['meta_title'] = trim((string)($news['meta_title'] ?? ''));
    $news['meta_description'] = trim((string)($news['meta_description'] ?? ''));
    $news['public_path'] = newsPublicPath($news);
    $news['public_url'] = newsPublicUrl($news);

    if (array_key_exists('author_public_enabled', $news) || array_key_exists('author_slug', $news) || array_key_exists('author_name', $news)) {
        $news = hydrateAuthorPresentation($news);
    }

    return $news;
}

/**
 * @param array<string, mixed> $show
 * @return array<string, mixed>
 */
function hydratePodcastShowPresentation(array $show): array
{
    $show['slug'] = podcastShowSlug((string)($show['slug'] ?? ''));
    $show['website_url'] = normalizePodcastWebsiteUrl((string)($show['website_url'] ?? ''));
    $show['subtitle'] = trim((string)($show['subtitle'] ?? ''));
    $show['owner_name'] = trim((string)($show['owner_name'] ?? ''));
    $show['owner_email'] = normalizePodcastOwnerEmail((string)($show['owner_email'] ?? ''));
    $show['explicit_mode'] = normalizePodcastExplicitMode((string)($show['explicit_mode'] ?? 'no'));
    $show['show_type'] = normalizePodcastShowType((string)($show['show_type'] ?? 'episodic'));
    $show['feed_complete'] = !empty($show['feed_complete']) ? 1 : 0;
    $show['feed_episode_limit'] = normalizePodcastFeedEpisodeLimit($show['feed_episode_limit'] ?? 100);
    $show['status'] = trim((string)($show['status'] ?? 'published'));
    $show['is_published'] = (int)($show['is_published'] ?? 1);
    $show['cover_url'] = podcastCoverUrl($show);
    $show['public_path'] = podcastShowPublicPath($show);
    $show['public_url'] = podcastShowPublicUrl($show);
    $show['description_plain'] = normalizePlainText((string)($show['description'] ?? ''));
    $show['feed_subtitle'] = podcastFeedSubtitle((string)($show['subtitle'] !== '' ? $show['subtitle'] : $show['description_plain']));
    $show['feed_summary'] = podcastFeedSummary((string)$show['description_plain']);
    $show['is_public'] = podcastShowIsPublic($show);
    return $show;
}

/**
 * @param array<string, mixed> $episode
 * @return array<string, mixed>
 */
function hydratePodcastEpisodePresentation(array $episode): array
{
    $episode['slug'] = podcastEpisodeSlug((string)($episode['slug'] ?? ''));
    $episode['transcript'] = (string)($episode['transcript'] ?? '');
    $episode['transcript_plain'] = normalizePlainText($episode['transcript']);
    $episode['audio_url'] = normalizePodcastEpisodeAudioUrl((string)($episode['audio_url'] ?? ''));
    $episode['subtitle'] = trim((string)($episode['subtitle'] ?? ''));
    $episode['season_num'] = !empty($episode['season_num']) ? (int)$episode['season_num'] : null;
    $episode['episode_type'] = normalizePodcastEpisodeType((string)($episode['episode_type'] ?? 'full'));
    $episode['explicit_mode'] = normalizePodcastEpisodeExplicitMode((string)($episode['explicit_mode'] ?? 'inherit'));
    $episode['block_from_feed'] = !empty($episode['block_from_feed']) ? 1 : 0;
    $episode['status'] = trim((string)($episode['status'] ?? 'published'));
    $episode['excerpt'] = podcastEpisodeExcerpt($episode);
    $episode['public_path'] = podcastEpisodePublicPath($episode);
    $episode['public_url'] = podcastEpisodePublicUrl($episode);
    $episode['audio_src'] = podcastEpisodeAudioUrl($episode);
    $episode['image_url'] = podcastEpisodeImageUrl($episode);
    $fallbackShowCover = trim((string)($episode['show_cover_image'] ?? ''));
    $episode['display_image_url'] = $episode['image_url'] !== ''
        ? $episode['image_url']
        : ($fallbackShowCover !== '' ? podcastCoverUrl([
            'id' => (int)($episode['show_id'] ?? 0),
            'cover_image' => $fallbackShowCover,
        ]) : '');
    $displayDate = trim((string)($episode['publish_at'] ?? ''));
    if ($displayDate === '') {
        $displayDate = trim((string)($episode['created_at'] ?? ''));
    }
    $episode['display_date'] = $displayDate;
    $episode['feed_subtitle'] = podcastFeedSubtitle((string)($episode['subtitle'] !== '' ? $episode['subtitle'] : $episode['excerpt']));
    $episodeDescription = (string)($episode['description'] ?? '');
    $episode['feed_summary'] = podcastFeedSummary($episodeDescription !== '' ? $episodeDescription : $episode['transcript_plain']);
    $episode['is_scheduled'] = podcastEpisodeIsScheduled($episode);
    $episode['is_public'] = podcastEpisodeIsPublic($episode);
    return $episode;
}

/**
 * @param array<string, mixed> $faq
 * @return array<string, mixed>
 */
function hydrateFaqPresentation(array $faq): array
{
    $faq['question'] = trim((string)($faq['question'] ?? ''));
    $faq['slug'] = faqSlug((string)($faq['slug'] ?? ''));
    $faq['excerpt'] = faqExcerpt($faq);
    $faq['meta_title'] = trim((string)($faq['meta_title'] ?? ''));
    $faq['meta_description'] = trim((string)($faq['meta_description'] ?? ''));
    $faq['public_path'] = faqPublicPath($faq);
    $faq['public_url'] = faqPublicUrl($faq);
    $faq['status'] = (string)($faq['status'] ?? ((int)($faq['is_published'] ?? 1) === 1 ? 'published' : 'pending'));
    $faq['is_publicly_visible'] = $faq['status'] === 'published'
        && (int)($faq['is_published'] ?? 1) === 1
        && empty($faq['deleted_at']);
    return $faq;
}

/**
 * @param array<string, mixed> $poll
 * @return array<string, mixed>
 */
function hydratePollPresentation(array $poll): array
{
    $poll['question'] = trim((string)($poll['question'] ?? ''));
    $poll['slug'] = pollSlug((string)($poll['slug'] ?? ''));
    $poll['excerpt'] = pollExcerpt($poll);
    $poll['meta_title'] = trim((string)($poll['meta_title'] ?? ''));
    $poll['meta_description'] = trim((string)($poll['meta_description'] ?? ''));
    $poll['vote_mode'] = pollVoteMode((string)($poll['vote_mode'] ?? 'single'));
    $poll['vote_mode_label'] = pollVoteModeOptions()[$poll['vote_mode']] ?? 'Jedna možnost';
    $poll['max_choices'] = isset($poll['max_choices']) && $poll['max_choices'] !== ''
        ? max(1, (int)$poll['max_choices'])
        : null;
    $poll['results_visibility'] = pollResultsVisibility((string)($poll['results_visibility'] ?? 'after_vote'));
    $poll['results_visibility_label'] = pollResultsVisibilityOptions()[$poll['results_visibility']] ?? 'Po hlasování';
    $poll['public_path'] = pollPublicPath($poll);
    $poll['public_url'] = pollPublicUrl($poll);

    $status = (string)($poll['status'] ?? 'active');
    $nowTimestamp = time();
    $startAt = trim((string)($poll['start_date'] ?? ''));
    $endAt = trim((string)($poll['end_date'] ?? ''));
    $startTimestamp = $startAt !== '' ? strtotime($startAt) : false;
    $endTimestamp = $endAt !== '' ? strtotime($endAt) : false;

    if ($status === 'closed' || ($endTimestamp !== false && $endTimestamp <= $nowTimestamp)) {
        $poll['state'] = 'closed';
        $poll['state_label'] = 'Uzavřená';
    } elseif ($startTimestamp !== false && $startTimestamp > $nowTimestamp) {
        $poll['state'] = 'scheduled';
        $poll['state_label'] = 'Naplánovaná';
    } else {
        $poll['state'] = 'active';
        $poll['state_label'] = 'Aktivní';
    }

    return $poll;
}

/**
 * @param array<string, mixed> $album
 */
function galleryAlbumExcerpt(array $album, int $limit = 220): string
{
    $explicitExcerpt = normalizePlainText((string)($album['description'] ?? ''));
    if ($explicitExcerpt === '') {
        return '';
    }

    return mb_strimwidth($explicitExcerpt, 0, $limit, '...', 'UTF-8');
}

/**
 * @param array<string, mixed> $photo
 */
function galleryPhotoLabel(array $photo): string
{
    $title = trim((string)($photo['title'] ?? ''));
    if ($title !== '') {
        return $title;
    }

    $filename = pathinfo((string)($photo['filename'] ?? ''), PATHINFO_FILENAME);
    $filename = preg_replace('/[_-]+/u', ' ', $filename);
    $filename = trim((string)$filename);
    if ($filename !== '') {
        return $filename;
    }

    return 'Fotografie';
}

function normalizeGalleryLicenseUrl(string $value): string
{
    return normalizeHttpExternalUrl($value, false);
}

/**
 * @param array<string, mixed> $photo
 */
function galleryPhotoCaption(array $photo): string
{
    $caption = trim((string)($photo['caption'] ?? ''));
    if ($caption !== '') {
        return $caption;
    }

    return trim((string)($photo['title'] ?? ''));
}

/**
 * @param array<string, mixed> $photo
 */
function galleryPhotoAltText(array $photo): string
{
    $altText = trim((string)($photo['alt_text'] ?? ''));
    if ($altText !== '') {
        return $altText;
    }

    $caption = galleryPhotoCaption($photo);
    if ($caption !== '') {
        return $caption;
    }

    return galleryPhotoLabel($photo);
}

function galleryPhotoDateLabel(?string $date): string
{
    $value = trim((string)$date);
    if ($value === '') {
        return '';
    }

    $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$dateTime instanceof DateTimeImmutable) {
        return '';
    }

    static $months = [
        '', 'ledna', 'února', 'března', 'dubna', 'května', 'června',
        'července', 'srpna', 'září', 'října', 'listopadu', 'prosince',
    ];

    return $dateTime->format('j') . '. ' . $months[(int)$dateTime->format('n')] . ' ' . $dateTime->format('Y');
}

/**
 * @param array<string, mixed> $album
 * @return array<string, mixed>
 */
function hydrateGalleryAlbumPresentation(array $album): array
{
    $album['name'] = trim((string)($album['name'] ?? ''));
    if ($album['name'] === '') {
        $album['name'] = 'Album';
    }
    $album['slug'] = galleryAlbumSlug((string)($album['slug'] ?? ''));
    $album['excerpt'] = galleryAlbumExcerpt($album);
    $album['public_path'] = galleryAlbumPublicPath($album);
    $album['public_url'] = galleryAlbumPublicUrl($album);
    if (!isset($album['cover_url']) && !empty($album['id'])) {
        $album['cover_url'] = gallery_cover_url((int)$album['id']);
    }
    return $album;
}

/**
 * @param array<string, mixed> $photo
 * @return array<string, mixed>
 */
function hydrateGalleryPhotoPresentation(array $photo): array
{
    $photo['slug'] = galleryPhotoSlug((string)($photo['slug'] ?? ''));
    $photo['alt_text'] = trim((string)($photo['alt_text'] ?? ''));
    $photo['caption'] = trim((string)($photo['caption'] ?? ''));
    $photo['description'] = trim((string)($photo['description'] ?? ''));
    $photo['credit'] = trim((string)($photo['credit'] ?? ''));
    $photo['license_label'] = trim((string)($photo['license_label'] ?? ''));
    $photo['license_url'] = normalizeGalleryLicenseUrl((string)($photo['license_url'] ?? ''));
    $photo['taken_at'] = trim((string)($photo['taken_at'] ?? ''));
    $photo['location_label'] = trim((string)($photo['location_label'] ?? ''));
    $photo['label'] = galleryPhotoLabel($photo);
    $photo['caption_text'] = galleryPhotoCaption($photo);
    $photo['alt_text_resolved'] = galleryPhotoAltText($photo);
    $photo['taken_at_label'] = galleryPhotoDateLabel($photo['taken_at'] !== '' ? (string)$photo['taken_at'] : null);
    $photo['metadata_complete'] = $photo['alt_text'] !== '';
    $photo['public_path'] = galleryPhotoPublicPath($photo);
    $photo['public_url'] = galleryPhotoPublicUrl($photo);
    if (!isset($photo['image_url'])) {
        $photo['image_url'] = galleryPhotoMediaPath($photo, 'full');
    }
    if (!isset($photo['thumb_url'])) {
        $photo['thumb_url'] = galleryPhotoMediaPath($photo, 'thumb');
    }
    return $photo;
}

/**
 * @return array<string, mixed>|null
 */
function fetchPublicAuthorBySlug(PDO $pdo, string $slug): ?array
{
    $normalizedSlug = authorSlug($slug);
    if ($normalizedSlug === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, email, first_name, last_name, nickname, role,
                author_public_enabled, author_slug, author_bio, author_avatar, author_website
         FROM cms_users
         WHERE author_slug = ? AND author_public_enabled = 1 AND role != 'public'
         LIMIT 1"
    );
    $stmt->execute([$normalizedSlug]);
    $author = $stmt->fetch();

    return $author ? hydrateAuthorPresentation($author) : null;
}

/**
 * @return array<string, mixed>|null
 */
function fetchPublicAuthorById(PDO $pdo, int $userId): ?array
{
    if ($userId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, email, first_name, last_name, nickname, role,
                author_public_enabled, author_slug, author_bio, author_avatar, author_website
         FROM cms_users
         WHERE id = ? AND author_public_enabled = 1 AND role != 'public'
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $author = $stmt->fetch();

    return $author ? hydrateAuthorPresentation($author) : null;
}

/**
 * @return list<array<string, mixed>>
 */
function fetchPublicAuthors(PDO $pdo): array
{
    $articleCountSelect = isModuleEnabled('blog')
        ? "(SELECT COUNT(*)
             FROM cms_articles a
             WHERE a.author_id = u.id
               AND a.status = 'published'
               AND a.deleted_at IS NULL
               AND (a.publish_at IS NULL OR a.publish_at <= NOW()))"
        : '0';
    $latestArticleSelect = isModuleEnabled('blog')
        ? "(SELECT MAX(COALESCE(a.publish_at, a.created_at))
             FROM cms_articles a
             WHERE a.author_id = u.id
               AND a.status = 'published'
               AND a.deleted_at IS NULL
               AND (a.publish_at IS NULL OR a.publish_at <= NOW()))"
        : 'NULL';
    $newsCountSelect = isModuleEnabled('news')
        ? "(SELECT COUNT(*)
             FROM cms_news n
             WHERE n.author_id = u.id
               AND " . newsPublicVisibilitySql('n') . ")"
        : '0';
    $latestNewsSelect = isModuleEnabled('news')
        ? "(SELECT MAX(n.created_at)
             FROM cms_news n
             WHERE n.author_id = u.id
               AND " . newsPublicVisibilitySql('n') . ")"
        : 'NULL';

    $authors = $pdo->query(
        "SELECT u.id, u.email, u.first_name, u.last_name, u.nickname, u.role, u.is_superadmin,
                u.author_public_enabled, u.author_slug, u.author_bio, u.author_avatar, u.author_website,
                {$articleCountSelect} AS article_count,
                {$newsCountSelect} AS news_count,
                ({$articleCountSelect} + {$newsCountSelect}) AS content_count,
                {$latestArticleSelect} AS latest_article_at,
                {$latestNewsSelect} AS latest_news_at,
                GREATEST(
                    COALESCE({$latestArticleSelect}, '1000-01-01 00:00:00'),
                    COALESCE({$latestNewsSelect}, '1000-01-01 00:00:00')
                ) AS latest_content_at
         FROM cms_users u
         WHERE u.author_public_enabled = 1
           AND u.role != 'public'
         ORDER BY content_count DESC, latest_content_at DESC, u.is_superadmin DESC, u.id ASC"
    )->fetchAll();

    return array_map(
        static function (array $author): array {
            $author['article_count'] = (int)($author['article_count'] ?? 0);
            $author['news_count'] = (int)($author['news_count'] ?? 0);
            $author['content_count'] = (int)($author['content_count'] ?? 0);
            $author['articles_enabled'] = isModuleEnabled('blog');
            $author['news_enabled'] = isModuleEnabled('news');
            $author['content_summary'] = authorContentSummaryLabel($author);
            return hydrateAuthorPresentation($author);
        },
        $authors
    );
}

/**
 * @return array<string, mixed>|null
 */
function resolveHomeAuthor(PDO $pdo): ?array
{
    $selectedAuthorId = (int)getSetting('home_author_user_id', '0');
    if ($selectedAuthorId > 0) {
        return fetchPublicAuthorById($pdo, $selectedAuthorId);
    }

    $authors = $pdo->query(
        "SELECT id, email, first_name, last_name, nickname, role,
                author_public_enabled, author_slug, author_bio, author_avatar, author_website
         FROM cms_users
         WHERE author_public_enabled = 1 AND role != 'public'
         ORDER BY is_superadmin DESC, id ASC
         LIMIT 2"
    )->fetchAll();

    if (count($authors) !== 1) {
        return null;
    }

    return hydrateAuthorPresentation($authors[0]);
}

function articleCountLabel(int $count): string
{
    $count = max(0, $count);
    if ($count === 1) {
        return '1 článek';
    }

    $mod100 = $count % 100;
    $mod10 = $count % 10;
    if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
        return $count . ' články';
    }

    return $count . ' článků';
}

function newsCountLabel(int $count): string
{
    $count = max(0, $count);
    if ($count === 1) {
        return '1 novinka';
    }

    $mod100 = $count % 100;
    $mod10 = $count % 10;
    if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
        return $count . ' novinky';
    }

    return $count . ' novinek';
}

function normalizeAuthorContentType(string $value): string
{
    $type = slugify(trim($value));
    if ($type === '') {
        return 'vse';
    }

    return in_array($type, ['vse', 'clanky', 'novinky'], true) ? $type : 'vse';
}

/**
 * @param array<string, mixed> $counts
 */
function authorContentSummaryLabel(array $counts): string
{
    $parts = [];
    if ((bool)($counts['articles_enabled'] ?? true)) {
        $parts[] = articleCountLabel((int)($counts['article_count'] ?? $counts['articles'] ?? 0));
    }
    if ((bool)($counts['news_enabled'] ?? true)) {
        $parts[] = newsCountLabel((int)($counts['news_count'] ?? $counts['news'] ?? 0));
    }

    return $parts !== [] ? implode(', ', $parts) : 'Žádný veřejný obsah';
}

/**
 * @return array{article_count:int, news_count:int, content_count:int, articles_enabled:bool, news_enabled:bool}
 */
function fetchPublicAuthorContentCounts(PDO $pdo, int $authorId): array
{
    $counts = [
        'article_count' => 0,
        'news_count' => 0,
        'content_count' => 0,
        'articles_enabled' => isModuleEnabled('blog'),
        'news_enabled' => isModuleEnabled('news'),
    ];

    if ($authorId < 1) {
        return $counts;
    }

    if ($counts['articles_enabled']) {
        $articleCountStmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM cms_articles a
             WHERE a.author_id = ?
               AND a.status = 'published'
               AND a.deleted_at IS NULL
               AND (a.publish_at IS NULL OR a.publish_at <= NOW())"
        );
        $articleCountStmt->execute([$authorId]);
        $counts['article_count'] = (int)$articleCountStmt->fetchColumn();
    }

    if ($counts['news_enabled']) {
        $newsCountStmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM cms_news n
             WHERE n.author_id = ?
               AND " . newsPublicVisibilitySql('n')
        );
        $newsCountStmt->execute([$authorId]);
        $counts['news_count'] = (int)$newsCountStmt->fetchColumn();
    }

    $counts['content_count'] = $counts['article_count'] + $counts['news_count'];
    return $counts;
}

/**
 * @param array<string, mixed> $counts
 */
function authorContentCountForType(array $counts, string $contentType): int
{
    $contentType = normalizeAuthorContentType($contentType);
    if ($contentType === 'clanky') {
        return (bool)($counts['articles_enabled'] ?? true) ? (int)($counts['article_count'] ?? 0) : 0;
    }
    if ($contentType === 'novinky') {
        return (bool)($counts['news_enabled'] ?? true) ? (int)($counts['news_count'] ?? 0) : 0;
    }

    return (int)($counts['content_count'] ?? 0);
}

/**
 * @param array<string, mixed> $counts
 * @return list<array{type:string, label:string, count:int}>
 */
function authorContentFilterOptions(array $counts): array
{
    $options = [
        [
            'type' => 'vse',
            'label' => 'Vše',
            'count' => (int)($counts['content_count'] ?? 0),
        ],
    ];

    if ((bool)($counts['articles_enabled'] ?? true)) {
        $options[] = [
            'type' => 'clanky',
            'label' => 'Články',
            'count' => (int)($counts['article_count'] ?? 0),
        ];
    }

    if ((bool)($counts['news_enabled'] ?? true)) {
        $options[] = [
            'type' => 'novinky',
            'label' => 'Novinky',
            'count' => (int)($counts['news_count'] ?? 0),
        ];
    }

    return $options;
}

/**
 * @return list<array<string, mixed>>
 */
function fetchPublicAuthorContent(PDO $pdo, int $authorId, string $contentType, int $limit, int $offset): array
{
    $contentType = normalizeAuthorContentType($contentType);
    $limit = max(1, $limit);
    $offset = max(0, $offset);
    $queries = [];
    $params = [];

    if (($contentType === 'vse' || $contentType === 'clanky') && isModuleEnabled('blog')) {
        $queries[] = "SELECT 'article' AS content_type, a.id, a.title, a.slug, a.perex, a.content,
                             a.image_file, a.created_at, COALESCE(a.publish_at, a.created_at) AS sort_date,
                             a.view_count, a.category_id, c.name AS category, a.blog_id, b.slug AS blog_slug,
                             '' AS meta_description
                      FROM cms_articles a
                      LEFT JOIN cms_categories c ON c.id = a.category_id
                      LEFT JOIN cms_blogs b ON b.id = a.blog_id
                      WHERE a.author_id = ?
                        AND a.status = 'published'
                        AND a.deleted_at IS NULL
                        AND (a.publish_at IS NULL OR a.publish_at <= NOW())";
        $params[] = $authorId;
    }

    if (($contentType === 'vse' || $contentType === 'novinky') && isModuleEnabled('news')) {
        $queries[] = "SELECT 'news' AS content_type, n.id, n.title, n.slug, '' AS perex, n.content,
                             '' AS image_file, n.created_at, n.created_at AS sort_date,
                             0 AS view_count, NULL AS category_id, '' AS category, NULL AS blog_id, '' AS blog_slug,
                             n.meta_description
                      FROM cms_news n
                      WHERE n.author_id = ?
                        AND " . newsPublicVisibilitySql('n');
        $params[] = $authorId;
    }

    if ($queries === [] || $authorId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        implode(' UNION ALL ', $queries)
        . ' ORDER BY sort_date DESC, id DESC LIMIT ? OFFSET ?'
    );
    $stmt->execute(array_merge($params, [$limit, $offset]));

    return array_map(
        static function (array $item): array {
            $item['content_type'] = (string)($item['content_type'] ?? '');
            $item['type_label'] = $item['content_type'] === 'news' ? 'Novinka' : 'Článek';
            $item['display_date'] = (string)($item['sort_date'] ?? $item['created_at'] ?? '');

            if ($item['content_type'] === 'news') {
                $item['public_path'] = newsPublicPath($item);
                $item['excerpt'] = newsExcerpt((string)($item['content'] ?? ''));
                $item['reading_meta'] = '';
            } else {
                $item['public_path'] = articlePublicPath($item);
                $item['excerpt'] = trim((string)($item['perex'] ?? '')) !== ''
                    ? (string)$item['perex']
                    : articleExcerpt((string)($item['content'] ?? ''));
                $item['reading_meta'] = articleReadingMeta(
                    ((string)($item['perex'] ?? '')) . ((string)($item['content'] ?? '')),
                    (int)($item['view_count'] ?? 0)
                );
            }

            return $item;
        },
        $stmt->fetchAll()
    );
}

function deleteAuthorAvatarFile(string $filename): void
{
    $safeFilename = basename(trim($filename));
    if ($safeFilename === '') {
        return;
    }

    $path = dirname(__DIR__) . '/uploads/authors/' . $safeFilename;
    if (is_file($path)) {
        unlink($path);
    }
}

/**
 * @param array<string, mixed> $file
 * @return array<string, mixed>
 */
function storeUploadedAuthorAvatar(array $file, string $existingFilename = ''): array
{
    return storePresentationUploadedFile($file, $existingFilename, [
        'upload_error' => 'Avatar se nepodařilo nahrát.',
        'invalid_upload_error' => 'Avatar se nepodařilo zpracovat.',
        'allowed_mime_map' => presentationImageMimeMap(),
        'unsupported_type_error' => 'Avatar musí být ve formátu JPEG, PNG, GIF nebo WebP.',
        'directory' => dirname(__DIR__) . '/uploads/authors/',
        'mkdir_error' => 'Adresář pro avatary se nepodařilo vytvořit.',
        'move_error' => 'Avatar se nepodařilo uložit.',
        'prefix' => 'author_',
        'generate_webp' => true,
        'delete_callback' => 'deleteAuthorAvatarFile',
    ]);
}

// ─────────────────────────────── Formuláře ────────────────────────────────

function formSlug(string $input): string
{
    return slugify($input);
}

/**
 * @return array<string, array{label:string}>
 */
function formFieldTypeDefinitions(): array
{
    return [
        'text' => ['label' => 'Krátký text'],
        'email' => ['label' => 'E-mail'],
        'tel' => ['label' => 'Telefon'],
        'textarea' => ['label' => 'Delší text'],
        'select' => ['label' => 'Výběr'],
        'radio' => ['label' => 'Jedna volba'],
        'checkbox_group' => ['label' => 'Více voleb'],
        'checkbox' => ['label' => 'Zaškrtávací pole'],
        'consent' => ['label' => 'Souhlas'],
        'number' => ['label' => 'Číslo'],
        'date' => ['label' => 'Datum'],
        'url' => ['label' => 'Webová adresa'],
        'file' => ['label' => 'Soubor'],
        'hidden' => ['label' => 'Skryté pole'],
        'section' => ['label' => 'Sekce formuláře'],
    ];
}

/**
 * @return array<string, array{label:string, is_open:bool}>
 */
function formSubmissionStatusDefinitions(): array
{
    return [
        'new' => [
            'label' => 'Nové',
            'is_open' => true,
        ],
        'in_progress' => [
            'label' => 'Rozpracované',
            'is_open' => true,
        ],
        'resolved' => [
            'label' => 'Vyřešené',
            'is_open' => false,
        ],
        'closed' => [
            'label' => 'Uzavřené',
            'is_open' => false,
        ],
    ];
}

function normalizeFormSubmissionStatus(string $status): string
{
    $status = trim($status);
    $definitions = formSubmissionStatusDefinitions();
    return isset($definitions[$status]) ? $status : 'new';
}

function formSubmissionStatusLabel(string $status): string
{
    $definitions = formSubmissionStatusDefinitions();
    $normalized = normalizeFormSubmissionStatus($status);
    return $definitions[$normalized]['label'];
}

/**
 * @return list<string>
 */
function formSubmissionOpenStatuses(): array
{
    return array_keys(array_filter(
        formSubmissionStatusDefinitions(),
        static fn (array $definition): bool => !empty($definition['is_open'])
    ));
}

/**
 * @return array<string, int>
 */
function formSubmissionStatusCounts(PDO $pdo, int $formId): array
{
    $counts = array_fill_keys(array_keys(formSubmissionStatusDefinitions()), 0);
    $stmt = $pdo->prepare(
        "SELECT status, COUNT(*) AS total
         FROM cms_form_submissions
         WHERE form_id = ?
         GROUP BY status"
    );
    $stmt->execute([$formId]);

    foreach ($stmt->fetchAll() as $row) {
        $status = normalizeFormSubmissionStatus((string)($row['status'] ?? 'new'));
        $counts[$status] = (int)($row['total'] ?? 0);
    }

    return $counts;
}

/**
 * @return array<string, array{label:string}>
 */
function formSubmissionPriorityDefinitions(): array
{
    return [
        'low' => [
            'label' => 'Nízká',
        ],
        'medium' => [
            'label' => 'Střední',
        ],
        'high' => [
            'label' => 'Vysoká',
        ],
        'critical' => [
            'label' => 'Kritická',
        ],
    ];
}

function normalizeFormSubmissionPriority(string $priority): string
{
    $priority = trim($priority);
    $definitions = formSubmissionPriorityDefinitions();
    return isset($definitions[$priority]) ? $priority : 'medium';
}

function formSubmissionPriorityLabel(string $priority): string
{
    $definitions = formSubmissionPriorityDefinitions();
    $normalized = normalizeFormSubmissionPriority($priority);
    return $definitions[$normalized]['label'];
}

function formSubmissionPriorityFromText(string $value): string
{
    $normalized = mb_strtolower(trim($value), 'UTF-8');
    if ($normalized === '') {
        return 'medium';
    }

    foreach (['krit', 'critical', 'urgent', 'blok'] as $needle) {
        if (str_contains($normalized, $needle)) {
            return 'critical';
        }
    }
    foreach (['vysok', 'high'] as $needle) {
        if (str_contains($normalized, $needle)) {
            return 'high';
        }
    }
    foreach (['střed', 'stred', 'medium', 'normal'] as $needle) {
        if (str_contains($normalized, $needle)) {
            return 'medium';
        }
    }
    foreach (['nízk', 'nizk', 'low', 'minor'] as $needle) {
        if (str_contains($normalized, $needle)) {
            return 'low';
        }
    }

    return 'medium';
}

/**
 * @param array<string, array<string, mixed>> $fieldsByName
 * @param array<string, mixed> $submissionData
 */
function formSubmissionInferPriority(array $fieldsByName, array $submissionData): string
{
    $candidates = $fieldsByName !== []
        ? array_keys($fieldsByName)
        : array_keys($submissionData);

    foreach ($candidates as $fieldName) {
        $normalizedName = mb_strtolower(trim((string)$fieldName), 'UTF-8');
        if (
            !str_contains($normalizedName, 'priorita')
            && !str_contains($normalizedName, 'zavaz')
            && !str_contains($normalizedName, 'závaž')
            && !str_contains($normalizedName, 'nalehav')
            && !str_contains($normalizedName, 'naléhav')
        ) {
            continue;
        }

        $rawValue = $submissionData[$fieldName] ?? '';
        if (is_array($rawValue)) {
            $rawValue = implode(' ', array_map(static fn ($item): string => trim((string)$item), $rawValue));
        }

        $resolved = formSubmissionPriorityFromText((string)$rawValue);
        if ($resolved !== 'medium' || trim((string)$rawValue) !== '') {
            return $resolved;
        }
    }

    return 'medium';
}

/**
 * @return list<string>
 */
function formSubmissionLabelsFromString(string $value): array
{
    $parts = preg_split('/[,;\n\r]+/u', $value) ?: [];
    $labels = [];
    foreach ($parts as $part) {
        $label = trim((string)$part);
        if ($label === '') {
            continue;
        }
        $labels[mb_strtolower($label, 'UTF-8')] = $label;
    }

    return array_values($labels);
}

function formSubmissionNormalizeLabels(string $value): string
{
    return implode(', ', formSubmissionLabelsFromString($value));
}

/**
 * @return list<string>
 */
function formFieldNameVariants(string $fieldName): array
{
    $fieldName = trim($fieldName);
    if ($fieldName === '') {
        return [];
    }

    $variants = [$fieldName];
    $hyphenVariant = str_replace('_', '-', $fieldName);
    $underscoreVariant = str_replace('-', '_', $fieldName);
    foreach ([$hyphenVariant, $underscoreVariant] as $variant) {
        if (!in_array($variant, $variants, true)) {
            $variants[] = $variant;
        }
    }

    return $variants;
}

/**
 * @param array<string, mixed> $submissionData
 */
function formSubmissionValueByFieldName(array $submissionData, string $fieldName): mixed
{
    foreach (formFieldNameVariants($fieldName) as $candidateName) {
        if (array_key_exists($candidateName, $submissionData)) {
            return $submissionData[$candidateName];
        }
    }

    return '';
}

/**
 * @param array<string, array<string, mixed>> $fieldsByName
 * @return array<string, mixed>|null
 */
function formFieldDefinitionByName(array $fieldsByName, string $fieldName): ?array
{
    foreach (formFieldNameVariants($fieldName) as $candidateName) {
        if (isset($fieldsByName[$candidateName])) {
            return $fieldsByName[$candidateName];
        }
    }

    return null;
}

/**
 * @param array<string, mixed> $form
 * @param array<string, array<string, mixed>> $fieldsByName
 * @param array<string, mixed> $submissionData
 * @return array{email:string, field_name:string, field_label:string}|array{}
 */
function formSubmissionRecipient(array $form, array $fieldsByName, array $submissionData): array
{
    $emailField = trim((string)($form['submitter_email_field'] ?? ''));
    if ($emailField !== '') {
        $recipient = trim((string)formSubmissionValueByFieldName($submissionData, $emailField));
        if ($recipient !== '' && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $fieldDefinition = formFieldDefinitionByName($fieldsByName, $emailField);
            return [
                'email' => $recipient,
                'field_name' => $emailField,
                'field_label' => trim((string)($fieldDefinition['label'] ?? $emailField)),
            ];
        }
    }

    foreach ($fieldsByName as $fieldName => $field) {
        if (normalizeFormFieldType((string)($field['field_type'] ?? 'text')) !== 'email') {
            continue;
        }
        $recipient = trim((string)($submissionData[$fieldName] ?? ''));
        if ($recipient !== '' && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return [
                'email' => $recipient,
                'field_name' => $fieldName,
                'field_label' => trim((string)($field['label'] ?? $fieldName)),
            ];
        }
    }

    return [];
}

/**
 * @param array<string, mixed> $form
 */
function formSubmissionReferencePrefix(array $form): string
{
    $slug = formSlug((string)($form['slug'] ?? ''));
    $title = slugify((string)($form['title'] ?? ''));
    $source = $slug . ' ' . $title;
    foreach (['issue', 'bug', 'chyb', 'problem', 'report'] as $needle) {
        if (str_contains($source, $needle)) {
            return 'ISSUE';
        }
    }

    return 'FORM';
}

/**
 * @param array<string, mixed> $form
 */
function formSubmissionBuildReference(array $form, int $submissionId, string $createdAt = ''): string
{
    $year = date('Y');
    if ($createdAt !== '') {
        try {
            $year = (new DateTime($createdAt))->format('Y');
        } catch (Exception $e) {
            $year = date('Y');
        }
    }

    return formSubmissionReferencePrefix($form)
        . '-' . $year
        . '-' . str_pad((string)max(1, $submissionId), 4, '0', STR_PAD_LEFT);
}

/**
 * @param array<string, mixed> $form
 * @param array<string, mixed> $submission
 */
function formSubmissionReference(array $form, array $submission): string
{
    $storedReference = trim((string)($submission['reference_code'] ?? ''));
    if ($storedReference !== '') {
        return $storedReference;
    }

    return formSubmissionBuildReference(
        $form,
        (int)($submission['id'] ?? 0),
        (string)($submission['created_at'] ?? '')
    );
}

/**
 * @return list<array<string, mixed>>
 */
function formSubmissionAssignableUsers(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT id, email, first_name, last_name, nickname, role, is_superadmin
         FROM cms_users
         WHERE is_confirmed = 1 AND role <> 'public'
         ORDER BY is_superadmin DESC, role ASC, first_name ASC, last_name ASC, email ASC"
    );

    return $stmt ? $stmt->fetchAll() : [];
}

/**
 * @param array<string, mixed> $user
 */
function formSubmissionAssigneeDisplayName(array $user): string
{
    $displayName = trim((string)($user['nickname'] ?? ''));
    if ($displayName === '') {
        $displayName = trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''));
    }
    if ($displayName === '') {
        $displayName = trim((string)($user['email'] ?? ''));
    }

    $roleLabel = (int)($user['is_superadmin'] ?? 0) === 1
        ? 'Hlavní admin'
        : userRoleLabel((string)($user['role'] ?? 'collaborator'));

    return $displayName . ' · ' . $roleLabel;
}

/**
 * @param array<string, array<string, mixed>> $fieldsByName
 * @param array<string, mixed> $submissionData
 */
function formSubmissionSummary(array $fieldsByName, array $submissionData, int $maxParts = 2): string
{
    $parts = [];

    foreach ($fieldsByName as $fieldName => $field) {
        $fieldType = normalizeFormFieldType((string)($field['field_type'] ?? 'text'));
        if (!formFieldStoresSubmissionValue($field) || in_array($fieldType, ['hidden', 'consent'], true)) {
            continue;
        }

        $displayValue = trim(formSubmissionDisplayValueForField($field, $submissionData[$fieldName] ?? ''));
        if ($displayValue === '') {
            continue;
        }

        $parts[] = trim((string)($field['label'] ?? $fieldName)) . ': ' . $displayValue;
        if (count($parts) >= $maxParts) {
            break;
        }
    }

    return implode(' · ', $parts);
}

function formSubmissionHistoryCreate(PDO $pdo, int $submissionId, ?int $actorUserId, string $eventType, string $message): void
{
    $normalizedMessage = trim($message);
    if ($submissionId <= 0 || $normalizedMessage === '') {
        return;
    }

    $pdo->prepare(
        "INSERT INTO cms_form_submission_history (submission_id, actor_user_id, event_type, message)
         VALUES (?, ?, ?, ?)"
    )->execute([
        $submissionId,
        $actorUserId,
        trim($eventType) !== '' ? trim($eventType) : 'note',
        $normalizedMessage,
    ]);
}

/**
 * @return list<array<string, mixed>>
 */
function formSubmissionHistoryEntries(PDO $pdo, int $submissionId): array
{
    $stmt = $pdo->prepare(
        "SELECT h.*,
                u.email AS actor_email,
                u.first_name AS actor_first_name,
                u.last_name AS actor_last_name,
                u.nickname AS actor_nickname,
                u.role AS actor_role,
                u.is_superadmin AS actor_is_superadmin
         FROM cms_form_submission_history h
         LEFT JOIN cms_users u ON u.id = h.actor_user_id
         WHERE h.submission_id = ?
         ORDER BY h.created_at DESC, h.id DESC"
    );
    $stmt->execute([$submissionId]);
    return $stmt->fetchAll();
}

/**
 * @param array<string, mixed> $historyRow
 */
function formSubmissionHistoryActorLabel(array $historyRow): string
{
    if ((int)($historyRow['actor_user_id'] ?? 0) <= 0) {
        return 'Systém';
    }

    return formSubmissionAssigneeDisplayName([
        'email' => (string)($historyRow['actor_email'] ?? ''),
        'first_name' => (string)($historyRow['actor_first_name'] ?? ''),
        'last_name' => (string)($historyRow['actor_last_name'] ?? ''),
        'nickname' => (string)($historyRow['actor_nickname'] ?? ''),
        'role' => (string)($historyRow['actor_role'] ?? ''),
        'is_superadmin' => (int)($historyRow['actor_is_superadmin'] ?? 0),
    ]);
}

/**
 * @return array<string, array{label:string}>
 */
function formFieldLayoutWidthDefinitions(): array
{
    return [
        'full' => ['label' => 'Celá šířka'],
        'half' => ['label' => 'Polovina řádku'],
        'third' => ['label' => 'Třetina řádku'],
    ];
}

function normalizeFormFieldLayoutWidth(string $value): string
{
    $normalized = trim($value);
    return array_key_exists($normalized, formFieldLayoutWidthDefinitions()) ? $normalized : 'full';
}

function formFieldLayoutWidthLabel(string $value): string
{
    $normalized = normalizeFormFieldLayoutWidth($value);
    return formFieldLayoutWidthDefinitions()[$normalized]['label'] ?? 'Celá šířka';
}

/**
 * @return array<string, array{label:string, requires_value:bool}>
 */
function formConditionOperatorDefinitions(): array
{
    return [
        'filled' => ['label' => 'je vyplněno', 'requires_value' => false],
        'empty' => ['label' => 'je prázdné', 'requires_value' => false],
        'equals' => ['label' => 'má hodnotu', 'requires_value' => true],
        'not_equals' => ['label' => 'nemá hodnotu', 'requires_value' => true],
        'contains' => ['label' => 'obsahuje některou z hodnot', 'requires_value' => true],
        'not_contains' => ['label' => 'neobsahuje žádnou z hodnot', 'requires_value' => true],
    ];
}

/**
 * @return array<string, array{label:string}>
 */
function formSuccessBehaviorDefinitions(): array
{
    return [
        'message' => ['label' => 'Zobrazit potvrzení na stránce'],
        'redirect' => ['label' => 'Přesměrovat na jinou interní stránku'],
    ];
}

function normalizeFormSuccessBehavior(string $value, string $redirectUrl = ''): string
{
    $normalized = trim($value);
    if (array_key_exists($normalized, formSuccessBehaviorDefinitions())) {
        return $normalized;
    }

    return trim($redirectUrl) !== '' ? 'redirect' : 'message';
}

function normalizePublicFormUrlFieldValue(string $value): string
{
    return normalizeHttpExternalUrl($value, false);
}

function publicFormRequiredFieldErrorMessage(string $label, string $fieldType = 'text'): string
{
    $fieldLabel = trim($label) !== '' ? trim($label) : 'Toto pole';

    return match (normalizeFormFieldType($fieldType)) {
        'checkbox_group' => 'Vyberte alespoň jednu možnost v poli „' . $fieldLabel . '“.',
        'radio', 'select' => 'Vyberte možnost v poli „' . $fieldLabel . '“.',
        'checkbox', 'consent' => 'Zaškrtněte pole „' . $fieldLabel . '“, aby bylo možné formulář odeslat.',
        'file' => 'Nahrajte soubor v poli „' . $fieldLabel . '“. Řiďte se povoleným typem a velikostí uvedenou u pole.',
        default => 'Vyplňte pole „' . $fieldLabel . '“. Pokud si nejste jistí, použijte nápovědu u pole.',
    };
}

function publicFormEmailFieldErrorMessage(string $label): string
{
    $fieldLabel = trim($label) !== '' ? trim($label) : 'Toto pole';

    return 'Zadejte do pole „' . $fieldLabel . '“ úplnou e-mailovou adresu ve tvaru jmeno@example.cz.';
}

function publicFormUrlFieldErrorMessage(string $label): string
{
    $fieldLabel = trim($label) !== '' ? trim($label) : 'Toto pole';

    return 'Zadejte do pole „' . $fieldLabel . '“ úplnou adresu začínající http:// nebo https:// bez přihlašovacích údajů.';
}

function publicFormOptionFieldErrorMessage(string $label): string
{
    $fieldLabel = trim($label) !== '' ? trim($label) : 'Toto pole';

    return 'Vyberte v poli „' . $fieldLabel . '“ jen možnost nabídnutou formulářem.';
}

function publicFormUploadFieldErrorMessage(string $label, string $uploadError): string
{
    $fieldLabel = trim($label) !== '' ? trim($label) : 'Toto pole';
    $message = trim($uploadError) !== '' ? trim($uploadError) : 'Soubor se nepodařilo ověřit.';

    return 'Pole „' . $fieldLabel . '“: ' . $message . ' Zkontrolujte povolený typ a velikost souboru uvedenou u pole.';
}

function normalizeFormConditionOperator(string $value, string $fallbackExpected = ''): string
{
    $normalized = trim($value);
    if (array_key_exists($normalized, formConditionOperatorDefinitions())) {
        return $normalized;
    }

    return trim($fallbackExpected) === '' ? 'filled' : 'equals';
}

function formConditionOperatorRequiresValue(string $operator): bool
{
    $normalized = normalizeFormConditionOperator($operator);
    return (bool)(formConditionOperatorDefinitions()[$normalized]['requires_value'] ?? false);
}

function normalizeFormFieldType(string $type): string
{
    $normalized = trim($type);
    return array_key_exists($normalized, formFieldTypeDefinitions()) ? $normalized : 'text';
}

/**
 * @param array<string, mixed> $field
 */
function formFieldStoresSubmissionValue(array $field): bool
{
    return !in_array(normalizeFormFieldType((string)($field['field_type'] ?? 'text')), ['section'], true);
}

/**
 * @param array<string, mixed> $field
 */
function formFieldStartsNewRow(array $field): bool
{
    if ((int)($field['start_new_row'] ?? 0) !== 1) {
        return false;
    }

    return !in_array(normalizeFormFieldType((string)($field['field_type'] ?? 'text')), ['hidden', 'section'], true);
}

function formFieldTypeLabel(string $type): string
{
    $normalized = normalizeFormFieldType($type);
    return formFieldTypeDefinitions()[$normalized]['label'] ?? 'Krátký text';
}

function formFieldAutocompletePurpose(string $type, string $name = '', string $label = ''): string
{
    $normalizedType = normalizeFormFieldType($type);
    if ($normalizedType === 'email') {
        return 'email';
    }
    if ($normalizedType === 'tel') {
        return 'tel';
    }
    if ($normalizedType === 'url') {
        return 'url';
    }

    $slug = slugify(trim($name . ' ' . $label));
    if ($slug === '') {
        return '';
    }

    /** @param list<string> $needles */
    $contains = static function (string $value, array $needles): bool {
        $wrapped = '-' . $value . '-';
        foreach ($needles as $needle) {
            if (str_contains($wrapped, '-' . $needle . '-')) {
                return true;
            }
        }

        return false;
    };

    if ($contains($slug, ['username', 'user-name', 'uzivatelske-jmeno', 'prihlasovaci-jmeno'])) {
        return '';
    }

    if ($normalizedType === 'date') {
        if ($contains($slug, ['datum-narozeni', 'narozeni', 'birth-date', 'date-of-birth', 'birthday', 'bday'])) {
            return 'bday';
        }

        return '';
    }

    if ($normalizedType !== 'text') {
        return '';
    }

    if ($contains($slug, ['email', 'e-mail', 'mail', 'web', 'url', 'uri', 'www'])) {
        return '';
    }

    if ($contains($slug, ['pracovni-pozice', 'pozice', 'funkce', 'job-title', 'jobtitle', 'organization-title'])) {
        return 'organization-title';
    }

    if ($contains($slug, ['firma', 'firmy', 'spolecnost', 'organizace', 'company', 'organization', 'organisation'])) {
        return 'organization';
    }

    if ($contains($slug, ['psc', 'postal-code', 'postcode', 'zip', 'zip-code', 'postovni-smerovaci-cislo'])) {
        return 'postal-code';
    }

    if ($contains($slug, ['adresa-radek-1', 'prvni-radek-adresy', 'address-line-1', 'address-line1'])) {
        return 'address-line1';
    }

    if ($contains($slug, ['adresa-radek-2', 'druhy-radek-adresy', 'address-line-2', 'address-line2'])) {
        return 'address-line2';
    }

    if ($contains($slug, ['ulice-a-cislo', 'ulice', 'street-address', 'street', 'postal-address', 'dodaci-adresa', 'fakturacni-adresa'])) {
        return 'street-address';
    }

    if ($contains($slug, ['mesto', 'obec', 'locality', 'lokalita', 'city', 'address-level-2', 'address-level2'])) {
        return 'address-level2';
    }

    if ($contains($slug, ['kraj', 'region', 'state', 'province', 'address-level-1', 'address-level1'])) {
        return 'address-level1';
    }

    if ($contains($slug, ['stat', 'zeme', 'country', 'country-name'])) {
        return 'country-name';
    }

    if ($contains($slug, ['jmeno-a-prijmeni', 'cele-jmeno', 'vase-jmeno', 'kontaktni-osoba', 'contact-name', 'full-name', 'fullname', 'your-name'])) {
        return 'name';
    }

    if ($contains($slug, ['krestni-jmeno', 'given-name', 'first-name', 'firstname'])) {
        return 'given-name';
    }

    if ($contains($slug, ['prijmeni', 'family-name', 'last-name', 'lastname', 'surname'])) {
        return 'family-name';
    }

    if ($contains($slug, ['jmeno'])) {
        return 'name';
    }

    return '';
}

/**
 * @return list<string>
 */
function formFieldOptionsList(string $rawOptions): array
{
    $items = [];
    foreach (explode('|', $rawOptions) as $option) {
        $option = trim($option);
        if ($option !== '') {
            $items[] = $option;
        }
    }
    return $items;
}

/**
 * @return array<string, array{label:string, description:string, form:array<string, mixed>, fields:list<array<string, mixed>>}>
 */
function formPresetDefinitions(): array
{
    return [
        'issue_report' => [
            'label' => 'Nahlášení chyby',
            'description' => 'Připraví formulář pro hlášení chyb a problémů bez nutnosti používat GitHub.',
            'form' => [
                'title' => 'Nahlášení chyby',
                'slug' => 'nahlaseni-chyby',
                'description' => 'Popište problém co nejkonkrétněji. Pomůže nám stručný název, závažnost, prostředí, kroky k reprodukci i případný screenshot nebo log.',
                'success_message' => 'Děkujeme, hlášení bylo úspěšně odesláno.',
                'submit_label' => 'Odeslat hlášení',
                'notification_subject' => 'Nové hlášení chyby',
                'redirect_url' => '',
                'success_behavior' => 'message',
                'success_primary_label' => '',
                'success_primary_url' => '',
                'success_secondary_label' => '',
                'success_secondary_url' => '',
                'is_active' => 1,
                'use_honeypot' => 1,
                'submitter_confirmation_enabled' => 1,
                'submitter_email_field' => 'email_pro_odpoved',
                'submitter_confirmation_subject' => 'Potvrzení přijetí hlášení',
                'submitter_confirmation_message' => "Děkujeme za odeslání formuláře „{{form_title}}“.\n\nPotvrdili jsme přijetí hlášení „{{field:strucny_nazev_problemu}}“ se závažností {{field:zavaznost}}.\n\nPokud bude potřeba něco doplnit, ozveme se na tuto adresu.\n\n— {{site_name}}",
            ],
            'fields' => [
                [
                    'field_type' => 'hidden',
                    'label' => 'Zdroj hlášení',
                    'name' => 'zdroj_hlaseni',
                    'default_value' => 'web-form',
                    'placeholder' => '',
                    'help_text' => '',
                    'options' => '',
                    'accept_types' => '',
                    'max_file_size_mb' => 10,
                    'layout_width' => 'full',
                    'is_required' => 0,
                    'sort_order' => 0,
                ],
                [
                    'field_type' => 'section',
                    'label' => 'Zařazení problému',
                    'name' => 'zarazeni_problemu',
                    'default_value' => '',
                    'placeholder' => '',
                    'help_text' => 'Pomozte nám rychle poznat, jak závažný problém je a kde se projevil.',
                    'options' => '',
                    'accept_types' => '',
                    'max_file_size_mb' => 10,
                    'layout_width' => 'full',
                    'is_required' => 0,
                    'sort_order' => 5,
                ],
                [
                    'field_type' => 'text',
                    'label' => 'Stručný název problému',
                    'name' => 'strucny_nazev_problemu',
                    'default_value' => '',
                    'placeholder' => 'Například Nelze uložit profil',
                    'help_text' => 'Jedna krátká věta, podle které problém rychle poznáme.',
                    'options' => '',
                    'accept_types' => '',
                    'max_file_size_mb' => 10,
                    'layout_width' => 'full',
                    'start_new_row' => 1,
                    'is_required' => 1,
                    'sort_order' => 10,
                ],
                [
                    'field_type' => 'radio',
                    'label' => 'Závažnost',
                    'name' => 'zavaznost',
                    'default_value' => '',
                    'placeholder' => '',
                    'help_text' => 'Vyberte, jak moc problém blokuje práci.',
                    'options' => 'Nízká|Střední|Vysoká|Kritická',
                    'accept_types' => '',
                    'max_file_size_mb' => 10,
                    'layout_width' => 'half',
                    'is_required' => 1,
                    'sort_order' => 20,
                ],
                [
                    'field_type' => 'checkbox_group',
                    'label' => 'Kde se problém projevil',
                    'name' => 'kde_se_problem_projevil',
                    'default_value' => '',
                    'placeholder' => '',
                    'help_text' => 'Můžete označit více oblastí, kterých se problém týká.',
                    'options' => 'Administrace|Veřejný web|Formuláře|Rezervace|Blogy|Multiblog',
                    'accept_types' => '',
                    'max_file_size_mb' => 10,
                    'layout_width' => 'half',
                    'is_required' => 0,
                    'sort_order' => 30,
                ],
                [
                    'field_type' => 'url',
                    'label' => 'Adresa stránky',
                    'name' => 'adresa_stranky',
                    'default_value' => '',
                    'placeholder' => 'https://example.com/problemova-stranka',
                    'help_text' => 'Volitelné. Vložte adresu stránky, kde se problém projevil.',
                    'options' => '',
                    'accept_types' => '',
                    'max_file_size_mb' => 10,
                    'layout_width' => 'full',
                    'is_required' => 0,
                    'sort_order' => 40,
                ],
                [
                    'field_type' => 'text',
                    'label' => 'Prohlížeč a zařízení',
                    'name' => 'prohlizec_a_zarizeni',
                    'default_value' => '',
                    'placeholder' => 'Například Firefox 125 na Windows 11',
                    'help_text' => 'Pomůže nám zjistit, jestli se problém týká konkrétního prostředí.',
                    'options' => '',
                    'accept_types' => '',
                    'max_file_size_mb' => 10,
                    'layout_width' => 'half',
                    'is_required' => 0,
                    'sort_order' => 50,
                ],
                [
                    'field_type' => 'text',
                    'label' => 'Verze aplikace nebo modulu',
                    'name' => 'verze_aplikace',
                    'default_value' => '',
                    'placeholder' => 'Například 3.0.0-beta.6 nebo release 2026-03-28',
                    'help_text' => 'Volitelné. Hodí se hlavně při hlášení chyby po aktualizaci.',
                    'options' => '',
                    'accept_types' => '',
                    'max_file_size_mb' => 10,
                    'layout_width' => 'half',
                    'is_required' => 0,
                    'sort_order' => 60,
                ],
                [
                    'field_type' => 'section',
                    'label' => 'Popis chyby',
                    'name' => 'popis_chyby',
                    'default_value' => '',
                    'placeholder' => '',
                    'help_text' => 'Čím konkrétněji problém popíšete, tím rychleji ho půjde ověřit a opravit.',
                    'options' => '',
                    'accept_types' => '',
                    'max_file_size_mb' => 10,
                    'layout_width' => 'full',
                    'is_required' => 0,
                    'sort_order' => 65,
                ],
                [
                    'field_type' => 'textarea',
                    'label' => 'Jak problém vyvolat',
                    'name' => 'jak_problem_vyvolat',
                    'default_value' => '',
                    'placeholder' => 'Popište jednotlivé kroky od otevření stránky po vznik chyby.',
                    'help_text' => 'Ideálně krok po kroku, aby šlo problém znovu nasimulovat.',
                    'options' => '',
                    'accept_types' => '',
                    'max_file_size_mb' => 10,
                    'layout_width' => 'full',
                    'start_new_row' => 1,
                    'is_required' => 1,
                    'sort_order' => 70,
                ],
                [
                    'field_type' => 'textarea',
                    'label' => 'Co jste očekávali',
                    'name' => 'co_jste_ocekavali',
                    'default_value' => '',
                    'placeholder' => 'Jak se měla aplikace nebo web zachovat správně?',
                    'help_text' => '',
                    'options' => '',
                    'accept_types' => '',
                    'max_file_size_mb' => 10,
                    'layout_width' => 'half',
                    'is_required' => 0,
                    'sort_order' => 80,
                ],
                [
                    'field_type' => 'textarea',
                    'label' => 'Co se stalo místo toho',
                    'name' => 'co_se_stalo',
                    'default_value' => '',
                    'placeholder' => 'Popište chybu, hlášku nebo nečekané chování.',
                    'help_text' => '',
                    'options' => '',
                    'accept_types' => '',
                    'max_file_size_mb' => 10,
                    'layout_width' => 'half',
                    'is_required' => 1,
                    'sort_order' => 90,
                ],
                [
                    'field_type' => 'textarea',
                    'label' => 'Dopad na práci',
                    'name' => 'dopad_na_praci',
                    'default_value' => '',
                    'placeholder' => 'Popište, co je teď blokované nebo co nejde dokončit.',
                    'help_text' => 'Zobrazí se jen u vysoké nebo kritické závažnosti.',
                    'options' => '',
                    'accept_types' => '',
                    'max_file_size_mb' => 10,
                    'layout_width' => 'full',
                    'start_new_row' => 1,
                    'show_if_field' => 'zavaznost',
                    'show_if_operator' => 'contains',
                    'show_if_value' => 'Vysoká|Kritická',
                    'is_required' => 0,
                    'sort_order' => 100,
                ],
                [
                    'field_type' => 'textarea',
                    'label' => 'Dočasné obejití',
                    'name' => 'docasne_obejiti',
                    'default_value' => '',
                    'placeholder' => 'Pokud jste našli náhradní postup, popište ho.',
                    'help_text' => 'Volitelné. Hodí se hlavně tehdy, když chyba neblokuje práci úplně.',
                    'options' => '',
                    'accept_types' => '',
                    'max_file_size_mb' => 10,
                    'layout_width' => 'full',
                    'show_if_field' => 'zavaznost',
                    'show_if_operator' => 'contains',
                    'show_if_value' => 'Nízká|Střední|Vysoká|Kritická',
                    'is_required' => 0,
                    'sort_order' => 110,
                ],
                [
                    'field_type' => 'section',
                    'label' => 'Přílohy a kontakt',
                    'name' => 'prilohy_a_kontakt',
                    'default_value' => '',
                    'placeholder' => '',
                    'help_text' => 'Sem patří vše, co nám může pomoct při dohledání a ověření problému.',
                    'options' => '',
                    'accept_types' => '',
                    'max_file_size_mb' => 10,
                    'layout_width' => 'full',
                    'is_required' => 0,
                    'sort_order' => 115,
                ],
                [
                    'field_type' => 'email',
                    'label' => 'E-mail pro případnou odpověď',
                    'name' => 'email_pro_odpoved',
                    'default_value' => '',
                    'placeholder' => 'vas@email.cz',
                    'help_text' => 'Volitelné. Vyplňte ho, jen pokud chcete poslat doplňující dotaz nebo vyřešení.',
                    'options' => '',
                    'accept_types' => '',
                    'max_file_size_mb' => 10,
                    'layout_width' => 'half',
                    'start_new_row' => 1,
                    'is_required' => 0,
                    'sort_order' => 120,
                ],
                [
                    'field_type' => 'file',
                    'label' => 'Příloha',
                    'name' => 'priloha',
                    'default_value' => '',
                    'placeholder' => '',
                    'help_text' => 'Volitelné. Můžete přiložit screenshot, PDF nebo textový log.',
                    'options' => '',
                    'accept_types' => '.png,.jpg,.jpeg,.webp,.txt,.log,.pdf',
                    'max_file_size_mb' => 10,
                    'allow_multiple' => 1,
                    'layout_width' => 'half',
                    'is_required' => 0,
                    'sort_order' => 130,
                ],
                [
                    'field_type' => 'consent',
                    'label' => 'Souhlasím se zpracováním údajů z tohoto formuláře pro vyřízení hlášení.',
                    'name' => 'souhlas_se_zpracovanim',
                    'default_value' => '',
                    'placeholder' => '',
                    'help_text' => 'Povinné potvrzení pro vyřízení nahlášeného problému.',
                    'options' => '',
                    'accept_types' => '',
                    'max_file_size_mb' => 10,
                    'layout_width' => 'full',
                    'is_required' => 1,
                    'sort_order' => 140,
                ],
            ],
        ],
        'feature_request' => [
            'label' => 'Návrh nové funkce',
            'description' => 'Připraví formulář pro sběr nápadů, zlepšení a nových funkcí od uživatelů nebo editorů.',
            'form' => [
                'title' => 'Návrh nové funkce',
                'slug' => 'navrh-nove-funkce',
                'description' => 'Popište, co by nová funkce měla řešit, komu pomůže a jak by se podle vás měla chovat v praxi.',
                'success_message' => 'Děkujeme, návrh nové funkce byl odeslán.',
                'submit_label' => 'Odeslat návrh',
                'notification_subject' => 'Nový návrh funkce',
                'redirect_url' => '',
                'success_behavior' => 'message',
                'success_primary_label' => '',
                'success_primary_url' => '',
                'success_secondary_label' => '',
                'success_secondary_url' => '',
                'is_active' => 1,
                'use_honeypot' => 1,
                'submitter_confirmation_enabled' => 1,
                'submitter_email_field' => 'email_pro_odpoved',
                'submitter_confirmation_subject' => 'Potvrzení přijetí návrhu nové funkce',
                'submitter_confirmation_message' => "Děkujeme za odeslání formuláře „{{form_title}}“.\n\nPotvrdili jsme přijetí návrhu „{{field:strucny_nazev_navrhu}}“.\n\nPokud bude potřeba něco doplnit, ozveme se na tuto adresu.\n\n— {{site_name}}",
            ],
            'fields' => [
                ['field_type' => 'hidden', 'label' => 'Zdroj návrhu', 'name' => 'zdroj_navrhu', 'default_value' => 'web-feature-request', 'placeholder' => '', 'help_text' => '', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'is_required' => 0, 'sort_order' => 0],
                ['field_type' => 'section', 'label' => 'O návrhu', 'name' => 'o_navrhu', 'default_value' => '', 'placeholder' => '', 'help_text' => 'Nejdřív stručně shrňte, co navrhujete a komu to pomůže.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'is_required' => 0, 'sort_order' => 5],
                ['field_type' => 'text', 'label' => 'Stručný název návrhu', 'name' => 'strucny_nazev_navrhu', 'default_value' => '', 'placeholder' => 'Například Přidat export odpovědí do JSON', 'help_text' => 'Jedna krátká věta, podle které návrh rychle poznáme.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'start_new_row' => 1, 'is_required' => 1, 'sort_order' => 10],
                ['field_type' => 'radio', 'label' => 'Typ návrhu', 'name' => 'typ_navrhu', 'default_value' => '', 'placeholder' => '', 'help_text' => 'Vyberte, jestli jde o novou funkci, zlepšení nebo úpravu rozhraní.', 'options' => 'Nová funkce|Zlepšení stávající funkce|Úprava rozhraní', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'half', 'is_required' => 1, 'sort_order' => 20],
                ['field_type' => 'checkbox_group', 'label' => 'Komu by to pomohlo', 'name' => 'komu_by_to_pomohlo', 'default_value' => '', 'placeholder' => '', 'help_text' => 'Můžete označit víc skupin, kterým by nová funkce pomohla.', 'options' => 'Správci webu|Autoři obsahu|Moderátoři|Návštěvníci webu|Vývojáři', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'half', 'is_required' => 0, 'sort_order' => 30],
                ['field_type' => 'radio', 'label' => 'Priorita návrhu', 'name' => 'priorita_navrhu', 'default_value' => '', 'placeholder' => '', 'help_text' => 'Pomůže nám odhadnout, jak moc je návrh důležitý.', 'options' => 'Nízká|Střední|Vysoká', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'half', 'start_new_row' => 1, 'is_required' => 0, 'sort_order' => 40],
                ['field_type' => 'email', 'label' => 'E-mail pro odpověď', 'name' => 'email_pro_odpoved', 'default_value' => '', 'placeholder' => 'vas@email.cz', 'help_text' => 'Volitelné. Pokud vyplníte kontakt, můžeme se k návrhu vrátit s doplňující otázkou.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'half', 'is_required' => 0, 'sort_order' => 50],
                ['field_type' => 'section', 'label' => 'Popis a dopad', 'name' => 'popis_a_dopad', 'default_value' => '', 'placeholder' => '', 'help_text' => 'Tady popište, co má nová funkce řešit a jak si ji představujete v běžném použití.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'is_required' => 0, 'sort_order' => 55],
                ['field_type' => 'textarea', 'label' => 'Jaký problém to řeší', 'name' => 'jaky_problem_to_resi', 'default_value' => '', 'placeholder' => 'Popište, co dnes nejde, je pomalé nebo zbytečně složité.', 'help_text' => 'Zaměřte se na konkrétní problém nebo situaci z praxe.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'start_new_row' => 1, 'is_required' => 1, 'sort_order' => 60],
                ['field_type' => 'textarea', 'label' => 'Jak by se to mělo chovat', 'name' => 'jak_by_se_to_melo_chovat', 'default_value' => '', 'placeholder' => 'Popište ideální chování nebo podobu nové funkce.', 'help_text' => '', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'half', 'is_required' => 1, 'sort_order' => 70],
                ['field_type' => 'textarea', 'label' => 'Příklad použití', 'name' => 'priklad_pouziti', 'default_value' => '', 'placeholder' => 'Na jaké konkrétní situaci nebo workflow by se nová funkce hodila?', 'help_text' => 'Volitelné. Jeden krátký scénář pomůže víc než obecný popis.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'half', 'is_required' => 0, 'sort_order' => 80],
                ['field_type' => 'file', 'label' => 'Příloha', 'name' => 'priloha', 'default_value' => '', 'placeholder' => '', 'help_text' => 'Volitelné. Můžete přiložit skicu, screenshot nebo dokument s návrhem.', 'options' => '', 'accept_types' => '.png,.jpg,.jpeg,.webp,.pdf,.txt,.doc,.docx', 'max_file_size_mb' => 10, 'allow_multiple' => 1, 'layout_width' => 'half', 'start_new_row' => 1, 'is_required' => 0, 'sort_order' => 90],
                ['field_type' => 'consent', 'label' => 'Souhlasím se zpracováním údajů z tohoto formuláře pro vyřízení návrhu.', 'name' => 'souhlas_se_zpracovanim', 'default_value' => '', 'placeholder' => '', 'help_text' => 'Povinné potvrzení pro zpracování návrhu.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'is_required' => 1, 'sort_order' => 100],
            ],
        ],
        'support_request' => [
            'label' => 'Žádost o podporu',
            'description' => 'Připraví formulář pro podporu, dotazy k používání a řešení blokujících situací.',
            'form' => [
                'title' => 'Žádost o podporu',
                'slug' => 'zadost-o-podporu',
                'description' => 'Popište, s čím potřebujete pomoct, kde se problém projevil a co už jste zkusili.',
                'success_message' => 'Děkujeme, žádost o podporu byla odeslána.',
                'submit_label' => 'Odeslat žádost',
                'notification_subject' => 'Nová žádost o podporu',
                'redirect_url' => '',
                'success_behavior' => 'message',
                'success_primary_label' => '',
                'success_primary_url' => '',
                'success_secondary_label' => '',
                'success_secondary_url' => '',
                'is_active' => 1,
                'use_honeypot' => 1,
                'submitter_confirmation_enabled' => 1,
                'submitter_email_field' => 'email_odesilatele',
                'submitter_confirmation_subject' => 'Potvrzení přijetí žádosti o podporu',
                'submitter_confirmation_message' => "Děkujeme za odeslání formuláře „{{form_title}}“.\n\nPotvrdili jsme přijetí žádosti „{{field:tema_pozadavku}}“.\n\nKdyž bude potřeba něco doplnit, ozveme se na tuto adresu.\n\n— {{site_name}}",
            ],
            'fields' => [
                ['field_type' => 'hidden', 'label' => 'Zdroj podpory', 'name' => 'zdroj_podpory', 'default_value' => 'web-support-request', 'placeholder' => '', 'help_text' => '', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'is_required' => 0, 'sort_order' => 0],
                ['field_type' => 'section', 'label' => 'Základ žádosti', 'name' => 'zaklad_zadosti', 'default_value' => '', 'placeholder' => '', 'help_text' => 'Začněte krátkým tématem a kontaktem, na který se můžeme ozvat.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'is_required' => 0, 'sort_order' => 5],
                ['field_type' => 'text', 'label' => 'Téma požadavku', 'name' => 'tema_pozadavku', 'default_value' => '', 'placeholder' => 'Například Nejde nastavit domovská stránka', 'help_text' => 'Jedna krátká věta, co potřebujete vyřešit.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'start_new_row' => 1, 'is_required' => 1, 'sort_order' => 10],
                ['field_type' => 'email', 'label' => 'E-mail odesílatele', 'name' => 'email_odesilatele', 'default_value' => '', 'placeholder' => 'vas@email.cz', 'help_text' => 'Na tuto adresu můžeme poslat doplňující otázky i řešení.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'half', 'is_required' => 1, 'sort_order' => 20],
                ['field_type' => 'radio', 'label' => 'Jak moc to spěchá', 'name' => 'nalehavost', 'default_value' => '', 'placeholder' => '', 'help_text' => 'Pomůže nám správně seřadit příchozí žádosti.', 'options' => 'Nízká|Střední|Vysoká', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'half', 'is_required' => 0, 'sort_order' => 30],
                ['field_type' => 'checkbox_group', 'label' => 'Čeho se žádost týká', 'name' => 'ceho_se_zadost_tyka', 'default_value' => '', 'placeholder' => '', 'help_text' => 'Můžete označit víc oblastí, pokud se dotaz týká více částí systému.', 'options' => 'Administrace|Veřejný web|Formuláře|Rezervace|Blogy|Šablony|Import a export', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'half', 'start_new_row' => 1, 'is_required' => 0, 'sort_order' => 40],
                ['field_type' => 'url', 'label' => 'Adresa stránky nebo místa v systému', 'name' => 'adresa_mista', 'default_value' => '', 'placeholder' => 'https://example.com/admin/...', 'help_text' => 'Volitelné. Vložte úplnou webovou adresu začínající na http:// nebo https://, aby šlo rychleji najít, čeho se problém týká.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'half', 'is_required' => 0, 'sort_order' => 50],
                ['field_type' => 'section', 'label' => 'Popis a co už jste zkusili', 'name' => 'popis_a_co_uz_jste_zkusili', 'default_value' => '', 'placeholder' => '', 'help_text' => 'Čím konkrétněji žádost popíšete, tím rychleji se v ní zorientujeme.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'is_required' => 0, 'sort_order' => 55],
                ['field_type' => 'textarea', 'label' => 'Co potřebujete vyřešit', 'name' => 'co_potrebujete_vyresit', 'default_value' => '', 'placeholder' => 'Popište, s čím potřebujete pomoct nebo co nejde dokončit.', 'help_text' => '', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'start_new_row' => 1, 'is_required' => 1, 'sort_order' => 60],
                ['field_type' => 'textarea', 'label' => 'Co jste už zkusili', 'name' => 'co_jste_uz_zkusili', 'default_value' => '', 'placeholder' => 'Například změna nastavení, odhlášení, jiný prohlížeč nebo znovunačtení stránky.', 'help_text' => 'Volitelné. Pomůže nám to nepokládat stejné první otázky znovu.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'half', 'is_required' => 0, 'sort_order' => 70],
                ['field_type' => 'file', 'label' => 'Příloha', 'name' => 'priloha', 'default_value' => '', 'placeholder' => '', 'help_text' => 'Volitelné. Screenshot, PDF nebo log často pomůže víc než dlouhý popis.', 'options' => '', 'accept_types' => '.png,.jpg,.jpeg,.webp,.pdf,.txt,.log', 'max_file_size_mb' => 10, 'allow_multiple' => 1, 'layout_width' => 'half', 'is_required' => 0, 'sort_order' => 80],
                ['field_type' => 'consent', 'label' => 'Souhlasím se zpracováním údajů z tohoto formuláře pro vyřízení žádosti o podporu.', 'name' => 'souhlas_se_zpracovanim', 'default_value' => '', 'placeholder' => '', 'help_text' => 'Povinné potvrzení pro zpracování žádosti.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'is_required' => 1, 'sort_order' => 90],
            ],
        ],
        'contact_basic' => [
            'label' => 'Obecný kontaktní formulář',
            'description' => 'Připraví jednoduchý formulář pro běžný kontakt, dotazy a nezávazné zprávy.',
            'form' => [
                'title' => 'Kontaktní formulář',
                'slug' => 'kontaktni-formular',
                'description' => 'Napište nám zprávu. Pokud chcete odpověď, nezapomeňte uvést kontakt.',
                'success_message' => 'Děkujeme, zpráva byla odeslána.',
                'submit_label' => 'Odeslat zprávu',
                'notification_subject' => 'Nová zpráva z kontaktního formuláře',
                'redirect_url' => '',
                'success_behavior' => 'message',
                'success_primary_label' => '',
                'success_primary_url' => '',
                'success_secondary_label' => '',
                'success_secondary_url' => '',
                'is_active' => 1,
                'use_honeypot' => 1,
                'submitter_confirmation_enabled' => 1,
                'submitter_email_field' => 'email',
                'submitter_confirmation_subject' => 'Potvrzení přijetí zprávy',
                'submitter_confirmation_message' => "Děkujeme za odeslání formuláře „{{form_title}}“.\n\nVaše zpráva „{{field:tema_zpravy}}“ byla úspěšně přijata.\n\n— {{site_name}}",
            ],
            'fields' => [
                ['field_type' => 'section', 'label' => 'Kontakt na vás', 'name' => 'kontakt_na_vas', 'default_value' => '', 'placeholder' => '', 'help_text' => 'Vyplňte alespoň jméno a e-mail, pokud chcete odpověď.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'is_required' => 0, 'sort_order' => 5],
                ['field_type' => 'text', 'label' => 'Jméno', 'name' => 'jmeno', 'default_value' => '', 'placeholder' => 'Vaše jméno', 'help_text' => '', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'half', 'start_new_row' => 1, 'is_required' => 1, 'sort_order' => 10],
                ['field_type' => 'email', 'label' => 'E-mail', 'name' => 'email', 'default_value' => '', 'placeholder' => 'vas@email.cz', 'help_text' => 'Na tuto adresu můžeme odpovědět.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'half', 'is_required' => 1, 'sort_order' => 20],
                ['field_type' => 'tel', 'label' => 'Telefon', 'name' => 'telefon', 'default_value' => '', 'placeholder' => '+420 123 456 789', 'help_text' => 'Volitelné. Hodí se, pokud chcete, abychom zavolali zpět.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'half', 'start_new_row' => 1, 'is_required' => 0, 'sort_order' => 30],
                ['field_type' => 'radio', 'label' => 'Důvod kontaktu', 'name' => 'duvod_kontaktu', 'default_value' => '', 'placeholder' => '', 'help_text' => 'Vyberte, čeho se zpráva týká.', 'options' => 'Dotaz|Zpětná vazba|Spolupráce|Jiné', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'half', 'is_required' => 0, 'sort_order' => 40],
                ['field_type' => 'section', 'label' => 'Vaše zpráva', 'name' => 'vase_zprava', 'default_value' => '', 'placeholder' => '', 'help_text' => 'Teď už stačí napsat, co potřebujete nebo co nám chcete sdělit.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'is_required' => 0, 'sort_order' => 45],
                ['field_type' => 'text', 'label' => 'Téma zprávy', 'name' => 'tema_zpravy', 'default_value' => '', 'placeholder' => 'Krátký předmět zprávy', 'help_text' => '', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'start_new_row' => 1, 'is_required' => 1, 'sort_order' => 50],
                ['field_type' => 'textarea', 'label' => 'Zpráva', 'name' => 'zprava', 'default_value' => '', 'placeholder' => 'Napište svou zprávu.', 'help_text' => '', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'is_required' => 1, 'sort_order' => 60],
                ['field_type' => 'consent', 'label' => 'Souhlasím se zpracováním údajů z tohoto formuláře pro vyřízení zprávy.', 'name' => 'souhlas_se_zpracovanim', 'default_value' => '', 'placeholder' => '', 'help_text' => 'Povinné potvrzení pro zpracování zprávy.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'is_required' => 1, 'sort_order' => 70],
            ],
        ],
        'content_report' => [
            'label' => 'Nahlášení problému s obsahem',
            'description' => 'Připraví formulář pro hlášení chyb v článcích, stránkách, novinkách, galeriích nebo jiném obsahu.',
            'form' => [
                'title' => 'Nahlášení problému s obsahem',
                'slug' => 'nahlaseni-problemu-s-obsahem',
                'description' => 'Pomozte nám udržet obsah přesný a aktuální. Napište, co je špatně a kde se to nachází.',
                'success_message' => 'Děkujeme, nahlášení problému s obsahem bylo odesláno.',
                'submit_label' => 'Odeslat hlášení',
                'notification_subject' => 'Nové hlášení problému s obsahem',
                'redirect_url' => '',
                'success_behavior' => 'message',
                'success_primary_label' => '',
                'success_primary_url' => '',
                'success_secondary_label' => '',
                'success_secondary_url' => '',
                'is_active' => 1,
                'use_honeypot' => 1,
                'submitter_confirmation_enabled' => 1,
                'submitter_email_field' => 'email_pro_odpoved',
                'submitter_confirmation_subject' => 'Potvrzení přijetí hlášení o obsahu',
                'submitter_confirmation_message' => "Děkujeme za odeslání formuláře „{{form_title}}“.\n\nPotvrdili jsme přijetí hlášení „{{field:strucne_shrnuti}}“.\n\n— {{site_name}}",
            ],
            'fields' => [
                ['field_type' => 'hidden', 'label' => 'Zdroj hlášení obsahu', 'name' => 'zdroj_hlaseni_obsahu', 'default_value' => 'web-content-report', 'placeholder' => '', 'help_text' => '', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'is_required' => 0, 'sort_order' => 0],
                ['field_type' => 'section', 'label' => 'Kde je problém', 'name' => 'kde_je_problem', 'default_value' => '', 'placeholder' => '', 'help_text' => 'Nejdřív nám pomozte rychle najít stránku a typ obsahu, kterého se hlášení týká.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'is_required' => 0, 'sort_order' => 5],
                ['field_type' => 'url', 'label' => 'Adresa stránky s problémem', 'name' => 'adresa_stranky_s_problemem', 'default_value' => '', 'placeholder' => 'https://example.com/clanek-nebo-stranka', 'help_text' => 'Ideálně vložte přesnou adresu stránky, na které je problém vidět.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'start_new_row' => 1, 'is_required' => 1, 'sort_order' => 10],
                ['field_type' => 'radio', 'label' => 'Čeho se problém týká', 'name' => 'ceho_se_problem_tyka', 'default_value' => '', 'placeholder' => '', 'help_text' => 'Vyberte typ obsahu, který je podle vás potřeba opravit.', 'options' => 'Článek|Statická stránka|Novinka|Událost|Fotogalerie|Místo|Soubor ke stažení|Jiné', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'half', 'is_required' => 1, 'sort_order' => 20],
                ['field_type' => 'checkbox_group', 'label' => 'Druh problému', 'name' => 'druh_problemu', 'default_value' => '', 'placeholder' => '', 'help_text' => 'Můžete označit víc typů problému, pokud se jich na stránce objevuje více.', 'options' => 'Neplatná informace|Překlep nebo jazyková chyba|Nefunkční odkaz|Chybějící obrázek nebo příloha|Nevhodný obsah|Jiné', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'half', 'is_required' => 0, 'sort_order' => 30],
                ['field_type' => 'section', 'label' => 'Co je potřeba opravit', 'name' => 'co_je_potreba_opravit', 'default_value' => '', 'placeholder' => '', 'help_text' => 'Teď popište, co je na obsahu špatně a jak jste na to narazili.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'is_required' => 0, 'sort_order' => 35],
                ['field_type' => 'text', 'label' => 'Stručné shrnutí', 'name' => 'strucne_shrnuti', 'default_value' => '', 'placeholder' => 'Například Na stránce je neplatný odkaz na PDF', 'help_text' => 'Jedna krátká věta, podle které problém rychle poznáme.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'start_new_row' => 1, 'is_required' => 1, 'sort_order' => 40],
                ['field_type' => 'textarea', 'label' => 'Podrobnosti', 'name' => 'podrobnosti', 'default_value' => '', 'placeholder' => 'Popište, co je špatně, co by mělo být jinak a případně kde jste na problém narazili.', 'help_text' => '', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'is_required' => 1, 'sort_order' => 50],
                ['field_type' => 'email', 'label' => 'E-mail pro odpověď', 'name' => 'email_pro_odpoved', 'default_value' => '', 'placeholder' => 'vas@email.cz', 'help_text' => 'Volitelné. Pokud chcete vědět, jak bylo hlášení vyřešeno, nechte na sebe kontakt.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'half', 'start_new_row' => 1, 'is_required' => 0, 'sort_order' => 60],
                ['field_type' => 'file', 'label' => 'Příloha', 'name' => 'priloha', 'default_value' => '', 'placeholder' => '', 'help_text' => 'Volitelné. Screenshot nebo dokument pomůže rychleji ověřit nahlášený problém.', 'options' => '', 'accept_types' => '.png,.jpg,.jpeg,.webp,.pdf,.txt', 'max_file_size_mb' => 10, 'allow_multiple' => 1, 'layout_width' => 'half', 'is_required' => 0, 'sort_order' => 70],
                ['field_type' => 'consent', 'label' => 'Souhlasím se zpracováním údajů z tohoto formuláře pro vyřízení hlášení o obsahu.', 'name' => 'souhlas_se_zpracovanim', 'default_value' => '', 'placeholder' => '', 'help_text' => 'Povinné potvrzení pro zpracování hlášení.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'is_required' => 1, 'sort_order' => 80],
            ],
        ],
    ];
}

/**
 * @return array{label:string, description:string, form:array<string, mixed>, fields:list<array<string, mixed>>}|null
 */
function formPresetDefinition(string $key): ?array
{
    $definitions = formPresetDefinitions();
    $definition = $definitions[$key] ?? null;
    if ($definition === null) {
        return null;
    }

    $defaultUploadLimit = koraDefaultUploadMaxSizeMb();
    foreach ($definition['fields'] as &$field) {
        if (normalizeFormFieldType((string)($field['field_type'] ?? 'text')) !== 'file') {
            continue;
        }

        if ((int)($field['max_file_size_mb'] ?? 0) === 10) {
            $field['max_file_size_mb'] = $defaultUploadLimit;
        }
    }
    unset($field);

    return $definition;
}

/**
 * @param list<array<string, mixed>> $fields
 * @return array<string, string>
 */
function formEmailFieldOptions(array $fields): array
{
    $options = [];
    foreach ($fields as $field) {
        $fieldType = normalizeFormFieldType((string)($field['field_type'] ?? 'text'));
        if ($fieldType !== 'email') {
            continue;
        }

        $name = trim((string)($field['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $label = trim((string)($field['label'] ?? ''));
        $options[$name] = $label !== '' ? $label : $name;
    }

    return $options;
}

/**
 * @param array<string, mixed> $field
 */
function formFieldAllowsMultipleFiles(array $field): bool
{
    return normalizeFormFieldType((string)($field['field_type'] ?? 'text')) === 'file'
        && (int)($field['allow_multiple'] ?? 0) === 1;
}

/**
 * @param array<string, mixed> $field
 * @param array<string, mixed> $submissionData
 */
function formFieldConditionMatches(array $field, array $submissionData): bool
{
    $controller = trim((string)($field['show_if_field'] ?? ''));
    if ($controller === '') {
        return true;
    }

    $expected = trim((string)($field['show_if_value'] ?? ''));
    $operator = normalizeFormConditionOperator((string)($field['show_if_operator'] ?? ''), $expected);
    $actual = $submissionData[$controller] ?? '';

    if (is_array($actual)) {
        $actualValues = array_values(array_filter(array_map(static fn ($item): string => trim((string)$item), $actual), static fn (string $item): bool => $item !== ''));
        $expectedValues = formFieldOptionsList(str_replace(',', '|', $expected));

        return match ($operator) {
            'filled' => $actualValues !== [],
            'empty' => $actualValues === [],
            'equals' => in_array($expected, $actualValues, true),
            'not_equals' => !in_array($expected, $actualValues, true),
            'contains' => array_intersect($expectedValues, $actualValues) !== [],
            'not_contains' => array_intersect($expectedValues, $actualValues) === [],
            default => in_array($expected, $actualValues, true),
        };
    }

    $actualValue = trim((string)$actual);
    $expectedValues = formFieldOptionsList(str_replace(',', '|', $expected));

    return match ($operator) {
        'filled' => $actualValue !== '',
        'empty' => $actualValue === '',
        'equals' => $actualValue === $expected,
        'not_equals' => $actualValue !== $expected,
        'contains' => in_array($actualValue, $expectedValues, true),
        'not_contains' => !in_array($actualValue, $expectedValues, true),
        default => $actualValue === $expected,
    };
}

function defaultFormSubmitterConfirmationSubjectTemplate(): string
{
    return 'Potvrzení odeslání formuláře „{{form_title}}“ – {{site_name}}';
}

function defaultFormSubmitterConfirmationMessageTemplate(): string
{
    return "Děkujeme za odeslání formuláře „{{form_title}}“.\n\nVaše zpráva byla úspěšně přijata.\n\n— {{site_name}}";
}

/**
 * @param array<string, mixed> $form
 * @param array<string, array<string, mixed>> $fieldsByName
 * @param array<string, mixed> $submissionData
 * @param array<string, string> $extraPlaceholders
 * @return array<string, string>
 */
function formTemplatePlaceholderMap(array $form, array $fieldsByName, array $submissionData, array $extraPlaceholders = []): array
{
    $siteName = getSetting('site_name', 'Kora CMS');
    $map = [
        '{{site_name}}' => $siteName,
        '{{form_title}}' => trim((string)($form['title'] ?? '')),
        '{{success_message}}' => trim((string)($form['success_message'] ?? '')),
        '{{submission_date}}' => date('j. n. Y H:i'),
    ];

    foreach ($fieldsByName as $name => $field) {
        $displayValue = formSubmissionDisplayValueForField($field, formSubmissionValueByFieldName($submissionData, $name));
        foreach (formFieldNameVariants($name) as $candidateName) {
            $map['{{field:' . $candidateName . '}}'] = $displayValue;
        }
    }

    return array_merge($map, $extraPlaceholders);
}

/**
 * @param array<string, string> $placeholderMap
 */
function formRenderTemplate(string $template, array $placeholderMap): string
{
    return strtr($template, $placeholderMap);
}

/**
 * @param array<string, mixed> $field
 */
function formPreviewSampleValueForField(array $field): mixed
{
    $fieldType = normalizeFormFieldType((string)($field['field_type'] ?? 'text'));
    $defaultValue = trim((string)($field['default_value'] ?? ''));
    $label = trim((string)($field['label'] ?? ''));
    $options = formFieldOptionsList((string)($field['options'] ?? ''));

    if (!formFieldStoresSubmissionValue($field)) {
        return '';
    }

    if ($defaultValue !== '' && !in_array($fieldType, ['checkbox_group', 'checkbox', 'consent', 'file'], true)) {
        return $defaultValue;
    }

    return match ($fieldType) {
        'hidden' => $defaultValue !== '' ? $defaultValue : 'ukazkova-hodnota',
        'email' => $defaultValue !== '' ? $defaultValue : 'tester@example.com',
        'tel' => $defaultValue !== '' ? $defaultValue : '+420 123 456 789',
        'url' => $defaultValue !== '' ? $defaultValue : 'https://example.com/problemova-stranka',
        'number' => $defaultValue !== '' ? $defaultValue : '42',
        'date' => $defaultValue !== '' ? $defaultValue : date('Y-m-d'),
        'textarea' => $defaultValue !== '' ? $defaultValue : ($label !== '' ? 'Ukázková odpověď pro pole „' . $label . '“.' : 'Ukázková odpověď.'),
        'select', 'radio' => $options[0] ?? $defaultValue,
        'checkbox_group' => $options === [] ? [] : array_slice($options, 0, min(2, count($options))),
        'checkbox', 'consent' => '1',
        'file' => formFieldAllowsMultipleFiles($field)
            ? [['original_name' => 'ukazka-priloha.png']]
            : ['original_name' => 'ukazka-priloha.png'],
        default => $defaultValue !== '' ? $defaultValue : ($label !== '' ? 'Ukázka pro ' . mb_strtolower($label, 'UTF-8') : 'Ukázková hodnota'),
    };
}

/**
 * @param list<array<string, mixed>> $fields
 * @return array<string, mixed>
 */
function formPreviewSubmissionData(array $fields): array
{
    $previewData = [];
    foreach ($fields as $field) {
        if (!formFieldStoresSubmissionValue($field)) {
            continue;
        }

        $name = trim((string)($field['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $previewData[$name] = formPreviewSampleValueForField($field);
    }

    return $previewData;
}

/**
 * @param array<string, mixed> $form
 * @param list<array<string, mixed>> $fields
 * @return array{subject:string, message:string, placeholder_map:array<string, string>}
 */
function formSubmitterConfirmationPreview(array $form, array $fields, string $subjectTemplate = '', string $messageTemplate = ''): array
{
    $fieldsByName = [];
    foreach ($fields as $field) {
        if (!formFieldStoresSubmissionValue($field)) {
            continue;
        }

        $name = trim((string)($field['name'] ?? ''));
        if ($name !== '') {
            $fieldsByName[$name] = $field;
        }
    }

    $previewData = formPreviewSubmissionData($fields);
    $placeholderMap = formTemplatePlaceholderMap($form, $fieldsByName, $previewData, [
        '{{submission_reference}}' => 'FORM-' . date('Y') . '-0001',
    ]);

    $normalizedSubjectTemplate = trim($subjectTemplate);
    if ($normalizedSubjectTemplate === '') {
        $normalizedSubjectTemplate = defaultFormSubmitterConfirmationSubjectTemplate();
    }

    $normalizedMessageTemplate = trim($messageTemplate);
    if ($normalizedMessageTemplate === '') {
        $normalizedMessageTemplate = defaultFormSubmitterConfirmationMessageTemplate();
    }

    return [
        'subject' => formRenderTemplate($normalizedSubjectTemplate, $placeholderMap),
        'message' => formRenderTemplate($normalizedMessageTemplate, $placeholderMap),
        'placeholder_map' => $placeholderMap,
    ];
}

function formUploadDirectory(): string
{
    return rtrim(koraStoragePath('forms'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
}

function formUploadLegacyDirectory(): string
{
    return dirname(__DIR__) . '/uploads/forms/';
}

function formUploadStoredName(string $storedName): string
{
    $storedName = trim($storedName);
    if ($storedName === '' || preg_match('#[\\\\/]#', $storedName)) {
        return '';
    }

    return $storedName;
}

function formUploadFilePath(string $storedName): string
{
    $normalizedName = formUploadStoredName($storedName);
    if ($normalizedName === '') {
        return '';
    }

    $privatePath = formUploadDirectory() . $normalizedName;
    if (is_file($privatePath)) {
        return $privatePath;
    }

    $legacyPath = formUploadLegacyDirectory() . $normalizedName;
    if (is_file($legacyPath)) {
        return $legacyPath;
    }

    return $privatePath;
}

function formUploadPublicPath(string $storedName): string
{
    return '';
}

function formDeleteUploadedFile(string $storedName): void
{
    $path = formUploadFilePath($storedName);
    if ($path === '') {
        return;
    }

    if (is_file($path)) {
        @unlink($path);
    }
}

/**
 * @param array<string, mixed> $item
 */
function formSubmissionStoredFileName(array $item): string
{
    $storedName = formUploadStoredName((string)($item['stored_name'] ?? ''));
    if ($storedName !== '') {
        return $storedName;
    }

    $legacyUrl = trim((string)($item['url'] ?? ''));
    if ($legacyUrl === '') {
        return '';
    }

    $legacyPath = parse_url($legacyUrl, PHP_URL_PATH);
    if (!is_string($legacyPath) || !str_contains($legacyPath, '/uploads/forms/')) {
        return '';
    }

    return formUploadStoredName(rawurldecode((string)basename($legacyPath)));
}

/**
 * @return list<array<string, mixed>>
 */
function formSubmissionFileItems(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }

    if (array_keys($value) === range(0, count($value) - 1)) {
        return array_values(array_filter($value, static fn (mixed $item): bool => is_array($item)));
    }

    return [$value];
}

function formSubmissionFileDownloadPath(int $submissionId, string $fieldName, int $index = 0): string
{
    return appendUrlQuery('/admin/form_submission_file.php', [
        'id' => $submissionId,
        'field' => trim($fieldName),
        'index' => max(0, $index),
    ]);
}

function formSubmissionDisplayValue(mixed $value): string
{
    if (is_array($value)) {
        $isAssoc = array_keys($value) !== range(0, count($value) - 1);
        if ($isAssoc) {
            if (isset($value['original_name'])) {
                return trim((string)$value['original_name']);
            }
            $parts = [];
            foreach ($value as $item) {
                if (is_scalar($item) || $item === null) {
                    $parts[] = (string)$item;
                }
            }
            return implode(', ', array_filter($parts, static fn (string $item): bool => $item !== ''));
        }

        $parts = [];
        foreach ($value as $item) {
            $rendered = formSubmissionDisplayValue($item);
            if ($rendered !== '') {
                $parts[] = $rendered;
            }
        }
        return implode(', ', $parts);
    }

    return trim((string)$value);
}

/**
 * @param array<string, mixed> $field
 */
function formSubmissionDisplayValueForField(array $field, mixed $value): string
{
    $fieldType = normalizeFormFieldType((string)($field['field_type'] ?? 'text'));

    if (!formFieldStoresSubmissionValue($field)) {
        return '';
    }

    if ($fieldType === 'checkbox_group') {
        return formSubmissionDisplayValue(is_array($value) ? $value : [$value]);
    }

    if (in_array($fieldType, ['checkbox', 'consent'], true)) {
        return trim((string)$value) === '1' ? 'Ano' : '';
    }

    return formSubmissionDisplayValue($value);
}

/**
 * @return list<string>
 */
function formCollectUploadedFilesFromSubmissionData(mixed $value): array
{
    $files = [];

    if (is_array($value)) {
        $isAssoc = array_keys($value) !== range(0, count($value) - 1);
        if ($isAssoc) {
            $storedName = formSubmissionStoredFileName($value);
            if ($storedName !== '') {
                $files[] = $storedName;
            }
        }

        foreach ($value as $item) {
            foreach (formCollectUploadedFilesFromSubmissionData($item) as $storedName) {
                $files[] = $storedName;
            }
        }
    }

    return array_values(array_unique($files));
}

function formDeleteUploadedFilesFromSubmissionData(mixed $value): void
{
    foreach (formCollectUploadedFilesFromSubmissionData($value) as $storedName) {
        formDeleteUploadedFile($storedName);
    }
}

function uniqueFormSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = formSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'formular';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_forms WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

/**
 * @param array<string, mixed> $form
 */
function formPublicRequestPath(array $form): string
{
    $slug = formSlug((string)($form['slug'] ?? ''));
    if ($slug !== '') {
        return '/forms/' . rawurlencode($slug);
    }
    return '/forms/index.php?id=' . (int)($form['id'] ?? 0);
}

/**
 * @param array<string, mixed> $form
 * @param array<string, mixed> $query
 */
function formPublicPath(array $form, array $query = []): string
{
    return BASE_URL . appendUrlQuery(formPublicRequestPath($form), $query);
}

/**
 * @param array<string, mixed> $form
 * @return list<array{label:string, url:string, variant:string}>
 */
function formResolveSuccessActions(array $form): array
{
    $resolved = [];
    $actionDefinitions = [
        [
            'label_key' => 'success_primary_label',
            'url_key' => 'success_primary_url',
            'fallback_label' => 'Pokračovat',
            'variant' => 'primary',
        ],
        [
            'label_key' => 'success_secondary_label',
            'url_key' => 'success_secondary_url',
            'fallback_label' => 'Další krok',
            'variant' => 'secondary',
        ],
    ];

    foreach ($actionDefinitions as $definition) {
        $target = internalRedirectTarget((string)($form[$definition['url_key']] ?? ''), '');
        if ($target === '') {
            continue;
        }

        $label = trim((string)($form[$definition['label_key']] ?? ''));
        if ($label === '') {
            $label = $definition['fallback_label'];
        }

        $resolved[] = [
            'label' => $label,
            'url' => $target,
            'variant' => $definition['variant'],
        ];
    }

    return $resolved;
}

/**
 * @param array<string, mixed> $form
 * @param array<string, mixed> $query
 */
function formPublicUrl(array $form, array $query = []): string
{
    return siteUrl(appendUrlQuery(formPublicRequestPath($form), $query));
}

// ──────────────────────── Série článků blogu ─────────────────────────────

function blogSeriesSlug(string $value): string
{
    return articleSlug($value);
}

function uniqueBlogSeriesSlug(PDO $pdo, string $slug, int $blogId, ?int $excludeId = null): string
{
    $base = blogSeriesSlug($slug);
    if ($base === '') {
        $base = 'serie';
    }

    $candidate = $base;
    $suffix = 2;
    while (true) {
        $params = [$blogId, $candidate];
        $sql = "SELECT id FROM cms_blog_series WHERE blog_id = ? AND slug = ?";
        if ($excludeId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $excludeId;
        }
        $sql .= " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetch()) {
            return $candidate;
        }

        $candidate = $base . '-' . $suffix;
        $suffix++;
    }
}

/**
 * @param array<int, mixed> $ids
 * @return list<int>
 */
function normalizeBlogSeriesIds(array $ids): array
{
    $normalized = [];
    foreach ($ids as $rawId) {
        $seriesId = (int)$rawId;
        if ($seriesId <= 0 || in_array($seriesId, $normalized, true)) {
            continue;
        }
        $normalized[] = $seriesId;
    }

    return $normalized;
}

/**
 * @return list<int>
 */
function loadArticleSeriesIds(PDO $pdo, int $articleId): array
{
    if ($articleId <= 0) {
        return [];
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT series_id
             FROM cms_blog_series_items
             WHERE article_id = ?
             ORDER BY series_id ASC"
        );
        $stmt->execute([$articleId]);
        return normalizeBlogSeriesIds($stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (\PDOException $e) {
        return [];
    }
}

/**
 * @return list<array{id:int,title:string,slug:string,is_active:int,article_count:int}>
 */
function blogSeriesOptions(PDO $pdo, int $blogId): array
{
    if ($blogId <= 0) {
        return [];
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT s.id, s.title, s.slug, s.is_active,
                    COUNT(si.article_id) AS article_count
             FROM cms_blog_series s
             LEFT JOIN cms_blog_series_items si ON si.series_id = s.id
             WHERE s.blog_id = ?
             GROUP BY s.id, s.title, s.slug, s.is_active, s.sort_order
             ORDER BY s.sort_order ASC, s.title ASC, s.id ASC"
        );
        $stmt->execute([$blogId]);
        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $rows[] = [
                'id' => (int)$row['id'],
                'title' => (string)$row['title'],
                'slug' => (string)$row['slug'],
                'is_active' => (int)($row['is_active'] ?? 1),
                'article_count' => (int)($row['article_count'] ?? 0),
            ];
        }
        return $rows;
    } catch (\PDOException $e) {
        return [];
    }
}

/**
 * @param list<int> $seriesIds
 * @return list<int>
 */
function validateBlogSeriesIds(PDO $pdo, int $blogId, array $seriesIds): array
{
    $seriesIds = normalizeBlogSeriesIds($seriesIds);
    if ($blogId <= 0 || $seriesIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($seriesIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT id
         FROM cms_blog_series
         WHERE blog_id = ? AND id IN ({$placeholders})"
    );
    $stmt->execute(array_merge([$blogId], $seriesIds));
    $validIds = array_values(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));

    return array_values(array_filter(
        $seriesIds,
        static fn (int $seriesId): bool => in_array($seriesId, $validIds, true)
    ));
}

/**
 * @param list<int> $seriesIds
 */
function saveArticleSeriesMemberships(PDO $pdo, int $articleId, int $blogId, array $seriesIds): void
{
    $pdo->prepare("DELETE FROM cms_blog_series_items WHERE article_id = ?")->execute([$articleId]);
    $validSeriesIds = validateBlogSeriesIds($pdo, $blogId, $seriesIds);
    if ($validSeriesIds === []) {
        return;
    }

    $maxOrderStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM cms_blog_series_items WHERE series_id = ?");
    $insert = $pdo->prepare(
        "INSERT IGNORE INTO cms_blog_series_items (series_id, article_id, sort_order)
         VALUES (?, ?, ?)"
    );
    foreach ($validSeriesIds as $seriesId) {
        $maxOrderStmt->execute([$seriesId]);
        $nextOrder = (int)$maxOrderStmt->fetchColumn() + 1;
        $insert->execute([$seriesId, $articleId, $nextOrder]);
    }
}

/**
 * @return list<array<string, mixed>>
 */
function publicBlogSeries(PDO $pdo, int $blogId, int $limit = 0): array
{
    if ($blogId <= 0) {
        return [];
    }

    $limit = max(0, $limit);
    $limitSql = $limit > 0 ? ' LIMIT ' . $limit : '';
    try {
        $stmt = $pdo->prepare(
            "SELECT s.id, s.blog_id, s.title, s.slug, s.description, s.sort_order,
                    b.slug AS blog_slug,
                    COUNT(a.id) AS article_count
             FROM cms_blog_series s
             INNER JOIN cms_blogs b ON b.id = s.blog_id
             INNER JOIN cms_blog_series_items si ON si.series_id = s.id
             INNER JOIN cms_articles a ON a.id = si.article_id
             WHERE s.blog_id = ?
               AND s.is_active = 1
               AND a.blog_id = s.blog_id
               AND a.deleted_at IS NULL
               AND a.status = 'published'
               AND (a.publish_at IS NULL OR a.publish_at <= NOW())
             GROUP BY s.id, s.blog_id, s.title, s.slug, s.description, s.sort_order, b.slug
             ORDER BY s.sort_order ASC, s.title ASC, s.id ASC{$limitSql}"
        );
        $stmt->execute([$blogId]);
        return $stmt->fetchAll() ?: [];
    } catch (\PDOException $e) {
        return [];
    }
}

/**
 * @return array{series:array<string,mixed>,articles:list<array<string,mixed>>}|null
 */
function publicBlogSeriesDetail(PDO $pdo, int $blogId, string $seriesSlug): ?array
{
    $seriesSlug = blogSeriesSlug($seriesSlug);
    if ($blogId <= 0 || $seriesSlug === '') {
        return null;
    }

    try {
        $seriesStmt = $pdo->prepare(
            "SELECT s.*, b.slug AS blog_slug
             FROM cms_blog_series s
             INNER JOIN cms_blogs b ON b.id = s.blog_id
             WHERE s.blog_id = ?
               AND s.slug = ?
               AND s.is_active = 1
             LIMIT 1"
        );
        $seriesStmt->execute([$blogId, $seriesSlug]);
        $series = $seriesStmt->fetch() ?: null;
        if (!$series) {
            return null;
        }

        $articlesStmt = $pdo->prepare(
            "SELECT a.id, a.title, a.slug, a.perex, a.content, a.image_file, a.blog_id,
                    a.created_at, a.publish_at, a.view_count,
                    b.slug AS blog_slug,
                    COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),'')) AS author_name,
                    u.author_public_enabled, u.author_slug, u.role AS author_role
             FROM cms_blog_series_items si
             INNER JOIN cms_articles a ON a.id = si.article_id
             LEFT JOIN cms_blogs b ON b.id = a.blog_id
             LEFT JOIN cms_users u ON u.id = a.author_id
             WHERE si.series_id = ?
               AND a.blog_id = ?
               AND a.deleted_at IS NULL
               AND a.status = 'published'
               AND (a.publish_at IS NULL OR a.publish_at <= NOW())
             ORDER BY si.sort_order ASC, a.created_at ASC, a.id ASC"
        );
        $articlesStmt->execute([(int)$series['id'], $blogId]);
        $articles = array_map(
            static fn (array $article): array => hydrateAuthorPresentation($article),
            $articlesStmt->fetchAll() ?: []
        );
        if ($articles === []) {
            return null;
        }

        return [
            'series' => $series,
            'articles' => $articles,
        ];
    } catch (\PDOException $e) {
        return null;
    }
}

/**
 * @param array<string, mixed> $article
 * @return list<array<string, mixed>>
 */
function articleSeriesNavigation(PDO $pdo, array $article): array
{
    $articleId = (int)($article['id'] ?? 0);
    $blogId = (int)($article['blog_id'] ?? 0);
    if ($articleId <= 0 || $blogId <= 0) {
        return [];
    }

    try {
        $seriesStmt = $pdo->prepare(
            "SELECT s.id, s.blog_id, s.title, s.slug, s.description, b.slug AS blog_slug
             FROM cms_blog_series_items current_item
             INNER JOIN cms_blog_series s ON s.id = current_item.series_id
             INNER JOIN cms_blogs b ON b.id = s.blog_id
             WHERE current_item.article_id = ?
               AND s.blog_id = ?
               AND s.is_active = 1
             ORDER BY s.sort_order ASC, s.title ASC, s.id ASC"
        );
        $seriesStmt->execute([$articleId, $blogId]);
        $seriesRows = $seriesStmt->fetchAll() ?: [];
        if ($seriesRows === []) {
            return [];
        }

        $itemsStmt = $pdo->prepare(
            "SELECT a.id, a.title, a.slug, a.blog_id, b.slug AS blog_slug, si.sort_order
             FROM cms_blog_series_items si
             INNER JOIN cms_articles a ON a.id = si.article_id
             LEFT JOIN cms_blogs b ON b.id = a.blog_id
             WHERE si.series_id = ?
               AND a.blog_id = ?
               AND a.deleted_at IS NULL
               AND a.status = 'published'
               AND (a.publish_at IS NULL OR a.publish_at <= NOW())
             ORDER BY si.sort_order ASC, a.created_at ASC, a.id ASC"
        );

        $result = [];
        foreach ($seriesRows as $seriesRow) {
            $itemsStmt->execute([(int)$seriesRow['id'], $blogId]);
            $items = $itemsStmt->fetchAll() ?: [];
            if ($items === []) {
                continue;
            }

            $currentIndex = null;
            foreach ($items as $index => $item) {
                if ((int)$item['id'] === $articleId) {
                    $currentIndex = $index;
                    break;
                }
            }
            if ($currentIndex === null) {
                continue;
            }

            $seriesRow['items'] = $items;
            $seriesRow['current_index'] = $currentIndex;
            $seriesRow['previous_article'] = $items[$currentIndex - 1] ?? null;
            $seriesRow['next_article'] = $items[$currentIndex + 1] ?? null;
            $result[] = $seriesRow;
        }

        return $result;
    } catch (\PDOException $e) {
        return [];
    }
}

// ──────────────────────── Související články ─────────────────────────────

/**
 * @return list<int>
 */
function loadArticleRelatedIds(PDO $pdo, int $articleId): array
{
    if ($articleId <= 0) {
        return [];
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT related_article_id
             FROM cms_article_related
             WHERE article_id = ?
             ORDER BY sort_order ASC, related_article_id ASC"
        );
        $stmt->execute([$articleId]);
        return normalizeRelatedArticleIds($stmt->fetchAll(PDO::FETCH_COLUMN), $articleId);
    } catch (\PDOException $e) {
        return [];
    }
}

/**
 * @param array<int, mixed> $ids
 * @return list<int>
 */
function normalizeRelatedArticleIds(array $ids, int $excludeArticleId = 0): array
{
    $normalized = [];
    foreach ($ids as $rawId) {
        $relatedId = (int)$rawId;
        if ($relatedId <= 0 || $relatedId === $excludeArticleId || in_array($relatedId, $normalized, true)) {
            continue;
        }
        $normalized[] = $relatedId;
    }

    return $normalized;
}

/**
 * @return list<array{id:int,title:string,display_date:string}>
 */
function relatedArticleOptions(PDO $pdo, int $blogId, int $excludeArticleId = 0): array
{
    if ($blogId <= 0) {
        return [];
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT id, title, COALESCE(publish_at, created_at) AS display_date
             FROM cms_articles
             WHERE blog_id = ?
               AND id <> ?
               AND deleted_at IS NULL
               AND status = 'published'
               AND (publish_at IS NULL OR publish_at <= NOW())
             ORDER BY COALESCE(publish_at, created_at) DESC, id DESC"
        );
        $stmt->execute([$blogId, max(0, $excludeArticleId)]);
        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $rows[] = [
                'id' => (int)$row['id'],
                'title' => (string)$row['title'],
                'display_date' => (string)($row['display_date'] ?? ''),
            ];
        }
        return $rows;
    } catch (\PDOException $e) {
        return [];
    }
}

/**
 * @param list<int> $relatedArticleIds
 */
function saveArticleRelatedArticles(PDO $pdo, int $articleId, array $relatedArticleIds): void
{
    $pdo->prepare("DELETE FROM cms_article_related WHERE article_id = ?")->execute([$articleId]);
    $normalizedRelatedIds = normalizeRelatedArticleIds($relatedArticleIds, $articleId);
    if ($normalizedRelatedIds === []) {
        return;
    }

    $insert = $pdo->prepare(
        "INSERT IGNORE INTO cms_article_related (article_id, related_article_id, sort_order)
         VALUES (?, ?, ?)"
    );
    foreach ($normalizedRelatedIds as $sortOrder => $relatedArticleId) {
        $insert->execute([$articleId, $relatedArticleId, $sortOrder + 1]);
    }
}

/**
 * @param array<string, mixed> $article
 * @return list<array<string, mixed>>
 */
function manualRelatedArticles(PDO $pdo, array $article, int $limit): array
{
    $articleId = (int)($article['id'] ?? 0);
    $blogId = (int)($article['blog_id'] ?? 1);
    if ($articleId <= 0 || $blogId <= 0 || $limit <= 0) {
        return [];
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT a.id, a.title, a.slug, a.perex, a.image_file, a.blog_id,
                    a.created_at, a.category_id,
                    b.slug AS blog_slug,
                    100 AS relevance_score
             FROM cms_article_related ar
             INNER JOIN cms_articles a ON a.id = ar.related_article_id
             LEFT JOIN cms_blogs b ON b.id = a.blog_id
             WHERE ar.article_id = ?
               AND a.blog_id = ?
               AND a.id <> ?
               AND a.deleted_at IS NULL
               AND a.status = 'published'
               AND (a.publish_at IS NULL OR a.publish_at <= NOW())
             ORDER BY ar.sort_order ASC, ar.related_article_id ASC
             LIMIT ?"
        );
        $stmt->execute([$articleId, $blogId, $articleId, $limit]);
        return $stmt->fetchAll() ?: [];
    } catch (\PDOException $e) {
        return [];
    }
}

/**
 * Vrátí související články z téhož blogu. Ruční výběr autora má přednost,
 * zbytek se doplní automaticky podle stejné kategorie, štítků a novosti.
 *
 * @param array<string, mixed> $article
 * @return list<array<string, mixed>>
 */
function relatedArticles(PDO $pdo, array $article, int $limit = 3): array
{
    $limit = max(0, min(10, $limit));
    $articleId = (int)($article['id'] ?? 0);
    $blogId = (int)($article['blog_id'] ?? 1);
    $categoryId = $article['category_id'] ?? null;

    if ($articleId <= 0 || $limit <= 0) {
        return [];
    }

    $results = manualRelatedArticles($pdo, $article, $limit);
    if (count($results) >= $limit) {
        return array_slice($results, 0, $limit);
    }

    // Načtení ID štítků aktuálního článku
    $tagIds = [];
    try {
        $tagStmt = $pdo->prepare("SELECT tag_id FROM cms_article_tags WHERE article_id = ?");
        $tagStmt->execute([$articleId]);
        $tagIds = $tagStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (\PDOException $e) {
        // Tabulka nemusí existovat
    }

    // Skládáme query s bodováním
    $scoreParts = [];
    $params = [];

    // Bonus za stejnou kategorii
    if ($categoryId !== null) {
        $scoreParts[] = "(CASE WHEN a.category_id = ? THEN 2 ELSE 0 END)";
        $params[] = (int)$categoryId;
    }

    // Bonus za sdílené štítky
    if ($tagIds !== []) {
        $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
        $scoreParts[] = "(SELECT COUNT(*) FROM cms_article_tags at2 WHERE at2.article_id = a.id AND at2.tag_id IN ({$placeholders}))";
        foreach ($tagIds as $tagId) {
            $params[] = (int)$tagId;
        }
    }

    $scoreExpr = $scoreParts !== [] ? implode(' + ', $scoreParts) : '0';
    $excludeIds = array_values(array_unique(array_merge(
        [$articleId],
        array_map(static fn (array $row): int => (int)$row['id'], $results)
    )));
    $excludePlaceholders = implode(',', array_fill(0, count($excludeIds), '?'));

    $params[] = $blogId;
    foreach ($excludeIds as $excludeId) {
        $params[] = $excludeId;
    }
    $params[] = $limit - count($results);

    try {
        $stmt = $pdo->prepare(
            "SELECT a.id, a.title, a.slug, a.perex, a.image_file, a.blog_id,
                    a.created_at, a.category_id,
                    b.slug AS blog_slug,
                    ({$scoreExpr}) AS relevance_score
             FROM cms_articles a
             LEFT JOIN cms_blogs b ON b.id = a.blog_id
             WHERE a.blog_id = ?
               AND a.id NOT IN ({$excludePlaceholders})
               AND a.deleted_at IS NULL
               AND a.status = 'published'
               AND (a.publish_at IS NULL OR a.publish_at <= NOW())
             HAVING relevance_score > 0
             ORDER BY relevance_score DESC, a.created_at DESC
             LIMIT ?"
        );
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $results[] = $row;
        }
    } catch (\PDOException $e) {
        // Automatický výběr je jen fallback. Ruční související články ponecháme.
    }

    // Pokud nemáme dostatek výsledků s relevancí, doplníme nejnovější z blogu
    if (count($results) < $limit) {
        $existingIds = array_map(static fn (array $row): int => (int)$row['id'], $results);
        $existingIds[] = $articleId;
        $excludePlaceholders = implode(',', array_fill(0, count($existingIds), '?'));
        $fillParams = $existingIds;
        $fillParams[] = $blogId;
        $fillParams[] = $limit - count($results);

        try {
            $fillStmt = $pdo->prepare(
                "SELECT a.id, a.title, a.slug, a.perex, a.image_file, a.blog_id,
                        a.created_at, a.category_id,
                        b.slug AS blog_slug,
                        0 AS relevance_score
                 FROM cms_articles a
                 LEFT JOIN cms_blogs b ON b.id = a.blog_id
                 WHERE a.id NOT IN ({$excludePlaceholders})
                   AND a.blog_id = ?
                   AND a.deleted_at IS NULL
                   AND a.status = 'published'
                   AND (a.publish_at IS NULL OR a.publish_at <= NOW())
                 ORDER BY a.created_at DESC
                 LIMIT ?"
            );
            $fillStmt->execute($fillParams);
            foreach ($fillStmt->fetchAll() as $fill) {
                $results[] = $fill;
            }
        } catch (\PDOException $e) {
            // Ignorovat – vracíme co máme
        }
    }

    return $results;
}

// ─────────────────── Hierarchické kategorie blogu ────────────────────────

/**
 * Vrátí pole ID: zadaná kategorie + všechny její přímé potomky.
 */
/**
 * @return list<int>
 */
function categoryWithChildrenIds(PDO $pdo, int $categoryId): array
{
    $ids = [$categoryId];
    try {
        $stmt = $pdo->prepare("SELECT id FROM cms_categories WHERE parent_id = ?");
        $stmt->execute([$categoryId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $childId) {
            $ids[] = (int)$childId;
        }
    } catch (\PDOException $e) {
        // V případě chyby vrátíme alespoň rodičovskou kategorii
    }
    return array_unique($ids);
}

// ─────────────────────────────── Galerie ──────────────────────────────────
