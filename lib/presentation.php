<?php
// Prezentační funkce – slugy, URL, excerpty, hydratace, autoři – extrahováno z db.php

// ──────────────────────── Multiblog helper funkce ────────────────────────────

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

function getBlogById(int $id): ?array
{
    foreach (getAllBlogs() as $blog) {
        if ((int)$blog['id'] === $id) {
            return $blog;
        }
    }
    return null;
}

function getBlogBySlug(string $slug): ?array
{
    foreach (getAllBlogs() as $blog) {
        if ((string)$blog['slug'] === $slug) {
            return $blog;
        }
    }
    return null;
}

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

function getUserBlogMembershipMap(?int $userId = null): array
{
    $map = [];
    foreach (getUserBlogMemberships($userId) as $membership) {
        $map[(int)($membership['blog_id'] ?? 0)] = (string)($membership['member_role'] ?? 'author');
    }

    return $map;
}

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
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
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

function articleBlogSlug(array $article): string
{
    if (!empty($article['blog_slug'])) {
        return (string)$article['blog_slug'];
    }
    $blogId = (int)($article['blog_id'] ?? 1);
    $blog = getBlogById($blogId);
    return $blog ? (string)$blog['slug'] : 'blog';
}

function blogIndexPath(array $blog): string
{
    $slug = (string)($blog['slug'] ?? 'blog');
    if ($slug === 'blog') {
        return BASE_URL . '/blog/index.php';
    }
    return BASE_URL . '/' . rawurlencode($slug) . '/';
}

function blogIndexUrl(array $blog): string
{
    $path = (string)($blog['slug'] ?? 'blog');
    if ($path === 'blog') {
        return siteUrl('/blog/index.php');
    }
    return siteUrl('/' . rawurlencode($path) . '/');
}

function blogFeedPath(array $blog): string
{
    $slug = (string)($blog['slug'] ?? 'blog');
    return BASE_URL . '/feed.php?blog=' . rawurlencode($slug);
}

function blogFeedUrl(array $blog): string
{
    $slug = (string)($blog['slug'] ?? 'blog');
    return siteUrl('/feed.php?blog=' . rawurlencode($slug));
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
        error_log('presentation: nelze ulozit redirect stareho blog slug: ' . $e->getMessage());
    }
}

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
    if (trim($datetime) === '') return '';
    static $months = [
        '', 'ledna', 'února', 'března', 'dubna', 'května', 'června',
        'července', 'srpna', 'září', 'října', 'listopadu', 'prosince',
    ];
    try { $dt = new \DateTime($datetime); } catch (\Exception $e) { return h($datetime); }
    return $dt->format('j') . '. ' . $months[(int)$dt->format('n')]
         . ' ' . $dt->format('Y, H:i');
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
    return readingTime($text) . ' min čtení, přečteno ' . max(0, $viewCount) . ' krát';
}

// ─────────────────────────────── Statické stránky ────────────────────────

/**
 * Převede text na URL slug (podporuje českou diakritiku).
 */
function slugify(string $text): string
{
    $map = [
        'á'=>'a','č'=>'c','ď'=>'d','é'=>'e','ě'=>'e','í'=>'i','ň'=>'n',
        'ó'=>'o','ř'=>'r','š'=>'s','ť'=>'t','ú'=>'u','ů'=>'u','ý'=>'y','ž'=>'z',
        'Á'=>'a','Č'=>'c','Ď'=>'d','É'=>'e','Ě'=>'e','Í'=>'i','Ň'=>'n',
        'Ó'=>'o','Ř'=>'r','Š'=>'s','Ť'=>'t','Ú'=>'u','Ů'=>'u','Ý'=>'y','Ž'=>'z',
        // slovenština
        'ľ'=>'l','Ľ'=>'l','ŕ'=>'r','Ŕ'=>'r','ĺ'=>'l','Ĺ'=>'l','ô'=>'o','Ô'=>'o',
        // polština
        'ą'=>'a','Ą'=>'a','ć'=>'c','Ć'=>'c','ę'=>'e','Ę'=>'e','ł'=>'l','Ł'=>'l',
        'ń'=>'n','Ń'=>'n','ś'=>'s','Ś'=>'s','ź'=>'z','Ź'=>'z','ż'=>'z','Ż'=>'z',
        // němčina
        'ä'=>'ae','Ä'=>'ae','ö'=>'oe','Ö'=>'oe','ü'=>'ue','Ü'=>'ue','ß'=>'ss',
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

function faqSlug(string $value): string
{
    return slugify(trim($value));
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

function newsPublicVisibilitySql(string $alias = ''): string
{
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';

    return "{$prefix}deleted_at IS NULL"
        . " AND COALESCE({$prefix}status, 'published') = 'published'"
        . " AND ({$prefix}publish_at IS NULL OR {$prefix}publish_at <= NOW())"
        . " AND ({$prefix}unpublish_at IS NULL OR {$prefix}unpublish_at > NOW())";
}

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

function podcastShowIsPublic(array $show): bool
{
    return trim((string)($show['status'] ?? 'published')) === 'published'
        && (int)($show['is_published'] ?? 1) === 1;
}

function podcastEpisodeIsScheduled(array $episode): bool
{
    $publishAt = trim((string)($episode['publish_at'] ?? ''));
    if ($publishAt === '') {
        return false;
    }

    return databaseDateTimeIsInFuture($publishAt);
}

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

function podcastEpisodeRevisionSnapshot(array $episode): array
{
    return [
        'title' => trim((string)($episode['title'] ?? '')),
        'slug' => podcastEpisodeSlug((string)($episode['slug'] ?? '')),
        'description' => (string)($episode['description'] ?? ''),
        'audio_url' => normalizePodcastEpisodeAudioUrl((string)($episode['audio_url'] ?? '')),
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

function pollExcerpt(array $poll, int $limit = 220): string
{
    $descriptionExcerpt = normalizePlainText((string)($poll['description'] ?? ''));
    if ($descriptionExcerpt === '') {
        return '';
    }

    return mb_strimwidth($descriptionExcerpt, 0, $limit, '...', 'UTF-8');
}

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
        'status' => trim((string)($poll['status'] ?? 'active')),
        'start_date' => trim((string)($poll['start_date'] ?? '')),
        'end_date' => trim((string)($poll['end_date'] ?? '')),
        'meta_title' => trim((string)($poll['meta_title'] ?? '')),
        'meta_description' => trim((string)($poll['meta_description'] ?? '')),
        'options' => implode("\n", $normalizedOptions),
    ];
}

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

    return "COALESCE({$prefix}status, 'published') = 'published'"
        . " AND COALESCE({$prefix}is_published, 1) = 1";
}

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
        ], static fn($value): bool => $value !== '') : null,
        'geo' => !empty($place['has_coordinates']) ? [
            '@type' => 'GeoCoordinates',
            'latitude' => (string)($place['latitude'] ?? ''),
            'longitude' => (string)($place['longitude'] ?? ''),
        ] : null,
    ], static fn($value): bool => $value !== '' && $value !== null);

    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
}

function downloadTypeLabel(string $type): string
{
    $definitions = downloadTypeDefinitions();
    return $definitions[normalizeDownloadType($type)]['label'];
}

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

function podcastEpisodeExcerpt(array $episode, int $limit = 220): string
{
    $descriptionExcerpt = normalizePlainText((string)($episode['description'] ?? ''));
    if ($descriptionExcerpt === '') {
        return '';
    }

    return mb_strimwidth($descriptionExcerpt, 0, $limit, '...', 'UTF-8');
}

function downloadImageUrl(array $download): string
{
    $filename = trim((string)($download['image_file'] ?? ''));
    if ($filename === '') {
        return '';
    }

    return BASE_URL . '/uploads/downloads/images/' . rawurlencode($filename);
}

function podcastCoverUrl(array $show): string
{
    $filename = trim((string)($show['cover_image'] ?? ''));
    $showId = isset($show['id']) ? (int)$show['id'] : 0;
    if ($filename === '' || $showId < 1) {
        return '';
    }

    return BASE_URL . '/podcast/cover.php?id=' . $showId;
}

function podcastEpisodeImageUrl(array $episode): string
{
    $filename = trim((string)($episode['image_file'] ?? ''));
    $episodeId = isset($episode['id']) ? (int)$episode['id'] : 0;
    if ($filename === '' || $episodeId < 1) {
        return '';
    }

    return BASE_URL . '/podcast/image.php?id=' . $episodeId;
}

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
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (!preg_match('#^https?://#i', $value)) {
        $value = 'https://' . ltrim($value, '/');
    }

    $validated = filter_var($value, FILTER_VALIDATE_URL);
    if (!is_string($validated) || !preg_match('#^https?://#i', $validated)) {
        return '';
    }

    return $validated;
}

function normalizePodcastEpisodeAudioUrl(string $value): string
{
    return normalizePodcastWebsiteUrl($value);
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

function podcastFeedManagingEditor(array $show): string
{
    $ownerEmail = normalizePodcastOwnerEmail((string)($show['owner_email'] ?? ''));
    if ($ownerEmail !== '') {
        $ownerName = trim((string)($show['owner_name'] ?? ''));
        return $ownerName !== '' ? $ownerEmail . ' (' . $ownerName . ')' : $ownerEmail;
    }

    return trim((string)($show['author'] ?? ''));
}

function podcastEpisodeEnclosureLength(array $episode): int
{
    $filename = trim((string)($episode['audio_file'] ?? ''));
    if ($filename === '') {
        return 0;
    }

    $path = podcastAudioFilePath($filename);
    if (!is_file($path)) {
        return 0;
    }

    $size = filesize($path);
    return $size === false ? 0 : (int)$size;
}

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

    return '  <script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . PHP_EOL;
}

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
        $hours = isset($matches[1]) && $matches[1] !== '' ? (int)$matches[1] : 0;
        $minutes = (int)$matches[2];
        $seconds = (int)$matches[3];
        $data['duration'] = sprintf('PT%dH%dM%dS', $hours, $minutes, $seconds);
    }

    return '  <script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . PHP_EOL;
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
            error_log('presentation: nelze smazat soubor ' . $path);
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
            error_log('presentation: nelze smazat soubor ' . $path);
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
            error_log('presentation: nelze smazat soubor ' . $path);
        }
    }
}

/**
 * @return array{filename:string,uploaded:bool,error:string}
 */
function uploadPodcastCoverImage(array $file, string $existingFilename = ''): array
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if (($file['name'] ?? '') === '' || $uploadError === UPLOAD_ERR_NO_FILE) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => '',
        ];
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek coveru se nepodařilo nahrát.',
        ];
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek coveru se nepodařilo zpracovat.',
        ];
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    $mimeType = (string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
    if (!isset($allowedTypes[$mimeType])) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Cover musí být ve formátu JPG nebo PNG.',
        ];
    }

    $imageInfo = @getimagesize($tmpPath);
    if (!is_array($imageInfo) || empty($imageInfo[0]) || empty($imageInfo[1])) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Cover obrázek se nepodařilo zkontrolovat.',
        ];
    }

    $width = (int)$imageInfo[0];
    $height = (int)$imageInfo[1];
    if ($width !== $height || $width < 1024 || $width > 3000) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Cover musí být čtvercový JPG nebo PNG v rozmezí 1024×1024 až 3000×3000 px.',
        ];
    }

    $directory = dirname(__DIR__) . '/uploads/podcasts/covers/';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Adresář pro cover obrázky se nepodařilo vytvořit.',
        ];
    }

    $filename = uniqid('podcast_cover_', true) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($tmpPath, $directory . $filename)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Cover obrázek se nepodařilo uložit.',
        ];
    }
    generateWebp($directory . $filename);

    if ($existingFilename !== '' && $existingFilename !== $filename) {
        deletePodcastCoverFile($existingFilename);
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
function uploadPodcastEpisodeImage(array $file, string $existingFilename = ''): array
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if (($file['name'] ?? '') === '' || $uploadError === UPLOAD_ERR_NO_FILE) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => '',
        ];
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek epizody se nepodařilo nahrát.',
        ];
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek epizody se nepodařilo zpracovat.',
        ];
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    $mimeType = (string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
    if (!isset($allowedTypes[$mimeType])) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek epizody musí být ve formátu JPG nebo PNG.',
        ];
    }

    $imageInfo = @getimagesize($tmpPath);
    if (!is_array($imageInfo) || empty($imageInfo[0]) || empty($imageInfo[1])) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek epizody se nepodařilo zkontrolovat.',
        ];
    }

    $width = (int)$imageInfo[0];
    $height = (int)$imageInfo[1];
    if ($width !== $height || $width < 1024 || $width > 3000) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek epizody musí být čtvercový JPG nebo PNG v rozmezí 1024×1024 až 3000×3000 px.',
        ];
    }

    $directory = dirname(__DIR__) . '/uploads/podcasts/images/';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Adresář pro obrázky epizod se nepodařilo vytvořit.',
        ];
    }

    $filename = uniqid('podcast_episode_image_', true) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($tmpPath, $directory . $filename)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek epizody se nepodařilo uložit.',
        ];
    }
    generateWebp($directory . $filename);

    if ($existingFilename !== '' && $existingFilename !== $filename) {
        deletePodcastEpisodeImageFile($existingFilename);
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
function uploadPodcastAudioFile(array $file, string $existingFilename = ''): array
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if (($file['name'] ?? '') === '' || $uploadError === UPLOAD_ERR_NO_FILE) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => '',
        ];
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Audio soubor se nepodařilo nahrát.',
        ];
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Audio soubor se nepodařilo zpracovat.',
        ];
    }

    $allowedTypes = [
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/ogg' => 'ogg',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
        'audio/aac' => 'aac',
    ];

    $mimeType = (string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
    if (!isset($allowedTypes[$mimeType])) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Audio musí být ve formátu MP3, OGG, WAV, M4A nebo AAC.',
        ];
    }

    $directory = dirname(__DIR__) . '/uploads/podcasts/';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Adresář pro podcastová audia se nepodařilo vytvořit.',
        ];
    }

    $filename = uniqid('podcast_episode_', true) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($tmpPath, $directory . $filename)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Audio soubor se nepodařilo uložit.',
        ];
    }

    if ($existingFilename !== '' && $existingFilename !== $filename) {
        deletePodcastAudioFile($existingFilename);
    }

    return [
        'filename' => $filename,
        'uploaded' => true,
        'error' => '',
    ];
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
            error_log('presentation: nelze smazat soubor ' . $path);
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
            error_log('presentation: nelze smazat soubor ' . $path);
        }
    }
}

/**
 * @return array{filename:string,uploaded:bool,error:string}
 */
function uploadDownloadImage(array $file, string $existingFilename = ''): array
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if (($file['name'] ?? '') === '' || $uploadError === UPLOAD_ERR_NO_FILE) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => '',
        ];
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek se nepodařilo nahrát.',
        ];
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek se nepodařilo zpracovat.',
        ];
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    $mimeType = (string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
    if (!isset($allowedTypes[$mimeType])) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek musí být ve formátu JPEG, PNG, GIF nebo WebP.',
        ];
    }

    $directory = dirname(__DIR__) . '/uploads/downloads/images/';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Adresář pro obrázky ke stažení se nepodařilo vytvořit.',
        ];
    }

    $filename = uniqid('download_image_', true) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($tmpPath, $directory . $filename)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek se nepodařilo uložit.',
        ];
    }
    generateWebp($directory . $filename);

    if ($existingFilename !== '' && $existingFilename !== $filename) {
        deleteDownloadImageFile($existingFilename);
    }

    return [
        'filename' => $filename,
        'uploaded' => true,
        'error' => '',
    ];
}

function normalizeDownloadExternalUrl(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (!preg_match('#^https?://#i', $value)) {
        $value = 'https://' . ltrim($value, '/');
    }

    $validated = filter_var($value, FILTER_VALIDATE_URL);
    if (!is_string($validated) || !preg_match('#^https?://#i', $validated)) {
        return '';
    }

    return $validated;
}

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
        'external_url' => normalizeDownloadExternalUrl((string)($download['external_url'] ?? '')),
        'is_featured' => (string)((int)($download['is_featured'] ?? 0)),
        'is_published' => (string)((int)($download['is_published'] ?? 1)),
    ];
}

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
    $download['project_url'] = normalizeDownloadExternalUrl((string)($download['project_url'] ?? ''));
    $download['release_date'] = trim((string)($download['release_date'] ?? ''));
    $download['requirements'] = trim((string)($download['requirements'] ?? ''));
    $download['checksum_sha256'] = normalizeDownloadChecksum((string)($download['checksum_sha256'] ?? ''));
    $download['series_key'] = normalizeDownloadSeriesKey((string)($download['series_key'] ?? ''));
    $download['is_featured'] = (int)($download['is_featured'] ?? 0) === 1 ? 1 : 0;
    $download['download_count'] = max(0, (int)($download['download_count'] ?? 0));
    $download['release_date_label'] = $download['release_date'] !== ''
        ? formatCzechDate((string)$download['release_date'])
        : '';
    $download['download_count_label'] = $download['download_count'] === 1
        ? 'Staženo 1×'
        : 'Staženo ' . $download['download_count'] . '×';
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
        'is_current' => (string)(int)($card['is_current'] ?? 0),
        'is_published' => (string)(int)($card['is_published'] ?? 0),
        'status' => (string)($card['status'] ?? 'published'),
    ];
}

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

    return '<script type="application/ld+json">' . json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) . '</script>';
}

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
    $card['is_publicly_visible'] = ((string)($card['status'] ?? 'published') === 'published')
        && (int)($card['is_published'] ?? 1) === 1;
    $card['is_temporally_active'] = (string)($card['state_key'] ?? 'current') === 'current';

    return $card;
}

function blogLogoUrl(array $blog): string
{
    $filename = trim((string)($blog['logo_file'] ?? ''));
    if ($filename === '') {
        return '';
    }

    return BASE_URL . '/uploads/blogs/' . rawurlencode($filename);
}

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
            error_log('presentation: nelze smazat soubor ' . $path);
        }
    }
}

/**
 * @return array{filename:string,uploaded:bool,error:string}
 */
function uploadBlogLogo(array $file, string $existingFilename = ''): array
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if (($file['name'] ?? '') === '' || $uploadError === UPLOAD_ERR_NO_FILE) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => '',
        ];
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Logo blogu se nepodařilo nahrát.',
        ];
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Logo blogu se nepodařilo zpracovat.',
        ];
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    $mimeType = (string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
    if (!isset($allowedTypes[$mimeType])) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Logo blogu musí být ve formátu JPEG, PNG, GIF nebo WebP.',
        ];
    }

    $directory = dirname(__DIR__) . '/uploads/blogs/';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Adresář pro loga blogů se nepodařilo vytvořit.',
        ];
    }

    $filename = uniqid('blog_logo_', true) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($tmpPath, $directory . $filename)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Logo blogu se nepodařilo uložit.',
        ];
    }
    generateWebp($directory . $filename);

    if ($existingFilename !== '' && $existingFilename !== $filename) {
        deleteBlogLogoFile($existingFilename);
    }

    return [
        'filename' => $filename,
        'uploaded' => true,
        'error' => '',
    ];
}

function placeImageUrl(array $place): string
{
    $requestPath = placeImageRequestPath($place);
    if ($requestPath === '') {
        return '';
    }

    return BASE_URL . $requestPath;
}

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
            error_log('presentation: nelze smazat soubor ' . $path);
        }
    }
}

/**
 * @return array{filename:string,uploaded:bool,error:string}
 */
function uploadPlaceImage(array $file, string $existingFilename = ''): array
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if (($file['name'] ?? '') === '' || $uploadError === UPLOAD_ERR_NO_FILE) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => '',
        ];
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek se nepodařilo nahrát.',
        ];
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek se nepodařilo zpracovat.',
        ];
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    $mimeType = (string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
    if (!isset($allowedTypes[$mimeType])) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek musí být ve formátu JPEG, PNG, GIF nebo WebP.',
        ];
    }

    $directory = dirname(__DIR__) . '/uploads/places/';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Adresář pro obrázky míst se nepodařilo vytvořit.',
        ];
    }

    $filename = uniqid('place_image_', true) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($tmpPath, $directory . $filename)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek se nepodařilo uložit.',
        ];
    }
    generateWebp($directory . $filename);

    if ($existingFilename !== '' && $existingFilename !== $filename) {
        deletePlaceImageFile($existingFilename);
    }

    return [
        'filename' => $filename,
        'uploaded' => true,
        'error' => '',
    ];
}

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
        error_log('presentation: nelze smazat soubor ' . $path);
    }
}

/**
 * @return array{filename:string,uploaded:bool,error:string}
 */
function uploadEventImage(array $file, string $existingFilename = ''): array
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if (($file['name'] ?? '') === '' || $uploadError === UPLOAD_ERR_NO_FILE) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => '',
        ];
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek akce se nepodařilo nahrát.',
        ];
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek akce se nepodařilo zpracovat.',
        ];
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    $mimeType = (string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
    if (!isset($allowedTypes[$mimeType])) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek akce musí být ve formátu JPEG, PNG, GIF nebo WebP.',
        ];
    }

    $directory = dirname(__DIR__) . '/uploads/events/images/';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Adresář pro obrázky akcí se nepodařilo vytvořit.',
        ];
    }

    $filename = uniqid('event_image_', true) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($tmpPath, $directory . $filename)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek akce se nepodařilo uložit.',
        ];
    }
    generateWebp($directory . $filename);

    if ($existingFilename !== '' && $existingFilename !== $filename) {
        deleteEventImageFile($existingFilename);
    }

    return [
        'filename' => $filename,
        'uploaded' => true,
        'error' => '',
    ];
}

function normalizePlaceUrl(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (!preg_match('#^https?://#i', $value)) {
        $value = 'https://' . ltrim($value, '/');
    }

    $validated = filter_var($value, FILTER_VALIDATE_URL);
    if (!is_string($validated) || !preg_match('#^https?://#i', $validated)) {
        return '';
    }

    return $validated;
}

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
        ], static fn(string $value): bool => $value !== ''))
    );

    $latitude = trim((string)($place['latitude'] ?? ''));
    $longitude = trim((string)($place['longitude'] ?? ''));
    $place['latitude'] = $latitude;
    $place['longitude'] = $longitude;
    $place['has_coordinates'] = $latitude !== '' && $longitude !== '';
    $place['map_url'] = $place['has_coordinates']
        ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($latitude . ',' . $longitude)
        : '';
    $place['is_publicly_visible'] = ((string)($place['status'] ?? 'published') === 'published')
        && (int)($place['is_published'] ?? 1) === 1;
    $place['public_path'] = placePublicPath($place);
    $place['public_url'] = placePublicUrl($place);

    return $place;
}

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
            error_log('presentation: nelze smazat soubor ' . $path);
        }
    }
}

/**
 * @return array{filename:string,uploaded:bool,error:string}
 */
function uploadBoardImage(array $file, string $existingFilename = ''): array
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if (($file['name'] ?? '') === '' || $uploadError === UPLOAD_ERR_NO_FILE) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => '',
        ];
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek se nepodařilo nahrát.',
        ];
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek se nepodařilo zpracovat.',
        ];
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    $mimeType = (string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
    if (!isset($allowedTypes[$mimeType])) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek musí být ve formátu JPEG, PNG, GIF nebo WebP.',
        ];
    }

    $directory = dirname(__DIR__) . '/uploads/board/images/';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Adresář pro obrázky vývěsky se nepodařilo vytvořit.',
        ];
    }

    $filename = uniqid('board_image_', true) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($tmpPath, $directory . $filename)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek se nepodařilo uložit.',
        ];
    }
    generateWebp($directory . $filename);

    if ($existingFilename !== '' && $existingFilename !== $filename) {
        deleteBoardImageFile($existingFilename);
    }

    return [
        'filename' => $filename,
        'uploaded' => true,
        'error' => '',
    ];
}

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

function appendUrlQuery(string $path, array $params): string
{
    $query = http_build_query(array_filter(
        $params,
        static fn($value): bool => $value !== null && $value !== ''
    ));

    if ($query === '') {
        return $path;
    }

    return $path . (str_contains($path, '?') ? '&' : '?') . $query;
}

function articlePublicRequestPath(array $article): string
{
    $slug = articleSlug((string)($article['slug'] ?? ''));
    $blogSlug = articleBlogSlug($article);
    if ($slug !== '') {
        return '/' . rawurlencode($blogSlug) . '/' . rawurlencode($slug);
    }

    return '/blog/article.php?id=' . (int)($article['id'] ?? 0);
}

function articlePublicPath(array $article, array $query = []): string
{
    return BASE_URL . appendUrlQuery(articlePublicRequestPath($article), $query);
}

function articlePublicUrl(array $article, array $query = []): string
{
    return siteUrl(appendUrlQuery(articlePublicRequestPath($article), $query));
}

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

function pagePublicPath(array $page, array $query = []): string
{
    return BASE_URL . appendUrlQuery(pagePublicRequestPath($page), $query);
}

function pagePublicUrl(array $page, array $query = []): string
{
    return siteUrl(appendUrlQuery(pagePublicRequestPath($page), $query));
}

function articlePreviewPath(array $article): string
{
    $previewToken = trim((string)($article['preview_token'] ?? ''));
    return articlePublicPath($article, $previewToken !== '' ? ['preview' => $previewToken] : []);
}

function newsPublicRequestPath(array $news): string
{
    $slug = newsSlug((string)($news['slug'] ?? ''));
    if ($slug !== '') {
        return '/news/' . rawurlencode($slug);
    }

    return '/news/article.php?id=' . (int)($news['id'] ?? 0);
}

function podcastShowPublicRequestPath(array $show): string
{
    $slug = podcastShowSlug((string)($show['slug'] ?? ''));
    if ($slug !== '') {
        return '/podcast/' . rawurlencode($slug);
    }

    return '/podcast/index.php';
}

function podcastShowPublicPath(array $show, array $query = []): string
{
    return BASE_URL . appendUrlQuery(podcastShowPublicRequestPath($show), $query);
}

function podcastShowPublicUrl(array $show, array $query = []): string
{
    return siteUrl(appendUrlQuery(podcastShowPublicRequestPath($show), $query));
}

function podcastEpisodePublicRequestPath(array $episode): string
{
    $showSlug = podcastShowSlug((string)($episode['show_slug'] ?? ''));
    $episodeSlug = podcastEpisodeSlug((string)($episode['slug'] ?? ''));
    if ($showSlug !== '' && $episodeSlug !== '') {
        return '/podcast/' . rawurlencode($showSlug) . '/' . rawurlencode($episodeSlug);
    }

    return '/podcast/episode.php?id=' . (int)($episode['id'] ?? 0);
}

function podcastEpisodePublicPath(array $episode, array $query = []): string
{
    return BASE_URL . appendUrlQuery(podcastEpisodePublicRequestPath($episode), $query);
}

function podcastEpisodePublicUrl(array $episode, array $query = []): string
{
    return siteUrl(appendUrlQuery(podcastEpisodePublicRequestPath($episode), $query));
}

function faqPublicRequestPath(array $faq): string
{
    $slug = faqSlug((string)($faq['slug'] ?? ''));
    if ($slug !== '') {
        return '/faq/' . rawurlencode($slug);
    }

    return '/faq/item.php?id=' . (int)($faq['id'] ?? 0);
}

function faqPublicPath(array $faq, array $query = []): string
{
    return BASE_URL . appendUrlQuery(faqPublicRequestPath($faq), $query);
}

function faqPublicUrl(array $faq, array $query = []): string
{
    return siteUrl(appendUrlQuery(faqPublicRequestPath($faq), $query));
}

function pollPublicRequestPath(array $poll): string
{
    $slug = pollSlug((string)($poll['slug'] ?? ''));
    if ($slug !== '') {
        return '/polls/' . rawurlencode($slug);
    }

    return '/polls/index.php?id=' . (int)($poll['id'] ?? 0);
}

function pollPublicPath(array $poll, array $query = []): string
{
    return BASE_URL . appendUrlQuery(pollPublicRequestPath($poll), $query);
}

function pollPublicUrl(array $poll, array $query = []): string
{
    return siteUrl(appendUrlQuery(pollPublicRequestPath($poll), $query));
}

function foodCardPublicRequestPath(array $card): string
{
    $slug = foodCardSlug((string)($card['slug'] ?? ''));
    if ($slug !== '') {
        return '/food/card/' . rawurlencode($slug);
    }

    return '/food/card.php?id=' . (int)($card['id'] ?? 0);
}

function foodCardPublicPath(array $card, array $query = []): string
{
    return BASE_URL . appendUrlQuery(foodCardPublicRequestPath($card), $query);
}

function foodCardPublicUrl(array $card, array $query = []): string
{
    return siteUrl(appendUrlQuery(foodCardPublicRequestPath($card), $query));
}

function reservationResourcePublicRequestPath(array $resource): string
{
    $slug = reservationResourceSlug((string)($resource['slug'] ?? ''));
    if ($slug !== '') {
        return '/reservations/resource.php?slug=' . rawurlencode($slug);
    }

    return '/reservations/index.php';
}

function reservationResourcePublicPath(array $resource, array $query = []): string
{
    return BASE_URL . appendUrlQuery(reservationResourcePublicRequestPath($resource), $query);
}

function reservationResourcePublicUrl(array $resource, array $query = []): string
{
    return siteUrl(appendUrlQuery(reservationResourcePublicRequestPath($resource), $query));
}

function galleryAlbumPublicRequestPath(array $album): string
{
    $slug = galleryAlbumSlug((string)($album['slug'] ?? ''));
    if ($slug !== '') {
        return '/gallery/album/' . rawurlencode($slug);
    }

    return '/gallery/album.php?id=' . (int)($album['id'] ?? 0);
}

function galleryAlbumPublicPath(array $album, array $query = []): string
{
    return BASE_URL . appendUrlQuery(galleryAlbumPublicRequestPath($album), $query);
}

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

function galleryPhotoPublicRequestPath(array $photo): string
{
    $slug = galleryPhotoSlug((string)($photo['slug'] ?? ''));
    if ($slug !== '') {
        return '/gallery/photo/' . rawurlencode($slug);
    }

    return '/gallery/photo.php?id=' . (int)($photo['id'] ?? 0);
}

function galleryPhotoPublicPath(array $photo, array $query = []): string
{
    return BASE_URL . appendUrlQuery(galleryPhotoPublicRequestPath($photo), $query);
}

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

function galleryPhotoMediaRequestPath(array $photo, string $size = 'full'): string
{
    $photoId = (int)($photo['id'] ?? 0);
    if ($photoId <= 0) {
        return '';
    }

    $normalizedSize = $size === 'thumb' ? 'thumb' : 'full';
    return '/gallery/image.php?id=' . $photoId . '&size=' . $normalizedSize;
}

function galleryPhotoMediaPath(array $photo, string $size = 'full'): string
{
    $requestPath = galleryPhotoMediaRequestPath($photo, $size);
    return $requestPath !== '' ? BASE_URL . $requestPath : '';
}

function galleryPhotoMediaUrl(array $photo, string $size = 'full'): string
{
    $requestPath = galleryPhotoMediaRequestPath($photo, $size);
    return $requestPath !== '' ? siteUrl($requestPath) : '';
}

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
        'is_published' => (string)((int)($album['is_published'] ?? 1)),
        'status' => (string)($album['status'] ?? 'published'),
    ];
}

function galleryPhotoRevisionSnapshot(array $photo, string $albumName = ''): array
{
    return [
        'title' => trim((string)($photo['title'] ?? '')),
        'slug' => galleryPhotoSlug((string)($photo['slug'] ?? '')),
        'album' => trim($albumName),
        'sort_order' => (string)((int)($photo['sort_order'] ?? 0)),
        'is_published' => (string)((int)($photo['is_published'] ?? 1)),
        'status' => (string)($photo['status'] ?? 'published'),
    ];
}

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
            'caption' => trim((string)($photo['title'] ?? '')),
        ], static fn($value): bool => $value !== '');
    }

    $data = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'ImageGallery',
        'name' => trim((string)($album['name'] ?? 'Album')),
        'description' => trim((string)($album['excerpt'] ?? galleryAlbumExcerpt($album, 500))),
        'url' => galleryAlbumPublicUrl($album),
        'image' => trim((string)($album['cover_url'] ?? '')),
        'associatedMedia' => $items !== [] ? $items : null,
    ], static fn($value): bool => $value !== '' && $value !== null);

    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
}

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
        'caption' => trim((string)($photo['title'] ?? '')),
        'contentUrl' => $imageUrl,
        'thumbnailUrl' => trim((string)($photo['thumb_url'] ?? galleryPhotoMediaUrl($photo, 'thumb'))),
        'url' => galleryPhotoPublicUrl($photo),
        'isPartOf' => !empty($album) ? array_filter([
            '@type' => 'ImageGallery',
            'name' => trim((string)($album['name'] ?? 'Galerie')),
            'url' => galleryAlbumPublicUrl($album),
        ], static fn($value): bool => $value !== '') : null,
    ], static fn($value): bool => $value !== '' && $value !== null);

    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
}

function newsPublicPath(array $news, array $query = []): string
{
    return BASE_URL . appendUrlQuery(newsPublicRequestPath($news), $query);
}

function newsPreviewPath(array $news): string
{
    $previewToken = trim((string)($news['preview_token'] ?? ''));
    return newsPublicPath($news, $previewToken !== '' ? ['preview' => $previewToken] : []);
}

function pagePreviewPath(array $page): string
{
    $previewToken = trim((string)($page['preview_token'] ?? ''));
    return pagePublicPath($page, $previewToken !== '' ? ['preview' => $previewToken] : []);
}

function eventPreviewPath(array $event): string
{
    $previewToken = trim((string)($event['preview_token'] ?? ''));
    return eventPublicPath($event, $previewToken !== '' ? ['preview' => $previewToken] : []);
}

function boardPreviewPath(array $item): string
{
    $previewToken = trim((string)($item['preview_token'] ?? ''));
    return boardPublicPath($item, $previewToken !== '' ? ['preview' => $previewToken] : []);
}

function newsPublicUrl(array $news, array $query = []): string
{
    return siteUrl(appendUrlQuery(newsPublicRequestPath($news), $query));
}

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
        ], static fn($value): bool => $value !== '');
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
        ], static fn($value): bool => $value !== ''),
    ], static fn($value): bool => $value !== '' && $value !== null);

    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
}

function downloadPublicRequestPath(array $download): string
{
    $slug = downloadSlug((string)($download['slug'] ?? ''));
    if ($slug !== '') {
        return '/downloads/' . rawurlencode($slug);
    }

    return '/downloads/item.php?id=' . (int)($download['id'] ?? 0);
}

function downloadPublicPath(array $download, array $query = []): string
{
    return BASE_URL . appendUrlQuery(downloadPublicRequestPath($download), $query);
}

function downloadPublicUrl(array $download, array $query = []): string
{
    return siteUrl(appendUrlQuery(downloadPublicRequestPath($download), $query));
}

function boardPublicRequestPath(array $document): string
{
    $slug = boardSlug((string)($document['slug'] ?? ''));
    if ($slug !== '') {
        return '/board/' . rawurlencode($slug);
    }

    return '/board/document.php?id=' . (int)($document['id'] ?? 0);
}

function boardPublicPath(array $document, array $query = []): string
{
    return BASE_URL . appendUrlQuery(boardPublicRequestPath($document), $query);
}

function boardPublicUrl(array $document, array $query = []): string
{
    return siteUrl(appendUrlQuery(boardPublicRequestPath($document), $query));
}

function boardPublicVisibilitySql(string $alias = ''): string
{
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';

    return "{$prefix}deleted_at IS NULL AND {$prefix}status = 'published' AND {$prefix}is_published = 1 AND {$prefix}posted_date <= CURDATE()";
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

function upsertPathRedirect(PDO $pdo, string $oldPath, string $newPath, int $statusCode = 301): void
{
    $oldPath = trim($oldPath);
    $newPath = trim($newPath);
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
        error_log('presentation: nelze ulozit redirect cesty: ' . $e->getMessage());
    }
}

function eventPublicRequestPath(array $event): string
{
    $slug = eventSlug((string)($event['slug'] ?? ''));
    if ($slug !== '') {
        return '/events/' . rawurlencode($slug);
    }

    return '/events/event.php?id=' . (int)($event['id'] ?? 0);
}

function placePublicRequestPath(array $place): string
{
    $slug = placeSlug((string)($place['slug'] ?? ''));
    if ($slug !== '') {
        return '/places/' . rawurlencode($slug);
    }

    return '/places/place.php?id=' . (int)($place['id'] ?? 0);
}

function placePublicPath(array $place, array $query = []): string
{
    return BASE_URL . appendUrlQuery(placePublicRequestPath($place), $query);
}

function placePublicUrl(array $place, array $query = []): string
{
    return siteUrl(appendUrlQuery(placePublicRequestPath($place), $query));
}

function eventPublicPath(array $event, array $query = []): string
{
    return BASE_URL . appendUrlQuery(eventPublicRequestPath($event), $query);
}

function eventPublicUrl(array $event, array $query = []): string
{
    return siteUrl(appendUrlQuery(eventPublicRequestPath($event), $query));
}

function eventIcsRequestPath(array $event): string
{
    $slug = eventSlug((string)($event['slug'] ?? ''));
    if ($slug !== '') {
        return '/events/' . rawurlencode($slug) . '.ics';
    }

    return '/events/ics.php?id=' . (int)($event['id'] ?? 0);
}

function eventIcsPath(array $event, array $query = []): string
{
    return BASE_URL . appendUrlQuery(eventIcsRequestPath($event), $query);
}

function eventIcsUrl(array $event, array $query = []): string
{
    return siteUrl(appendUrlQuery(eventIcsRequestPath($event), $query));
}

function eventRevisionSnapshot(array $event): array
{
    return [
        'title' => trim((string)($event['title'] ?? '')),
        'slug' => eventSlug((string)($event['slug'] ?? '')),
        'event_kind' => normalizeEventKind((string)($event['event_kind'] ?? 'general')),
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

function hydrateEventPresentation(array $event): array
{
    $event['slug'] = eventSlug((string)($event['slug'] ?? ''));
    $event['event_kind'] = normalizeEventKind((string)($event['event_kind'] ?? 'general'));
    $event['event_kind_label'] = (string)(eventKindDefinitions()[$event['event_kind']]['label'] ?? 'Akce');
    $event['event_kind_help'] = eventKindHelp((string)$event['event_kind']);
    $event['excerpt_plain'] = eventExcerpt($event);
    $event['image_url'] = eventImageUrl($event);
    $event['location'] = trim((string)($event['location'] ?? ''));
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
    $event['event_status_key'] = eventCurrentStatus($event);
    $event['event_status_label'] = match ($event['event_status_key']) {
        'ongoing' => 'Právě probíhá',
        'past' => 'Proběhlo',
        default => 'Připravujeme',
    };

    return $event;
}

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
        . " AND ({$prefix}unpublish_at IS NULL OR {$prefix}unpublish_at > NOW())";
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

    $location = trim((string)($event['location'] ?? ''));
    if ($location !== '') {
        $data['location'] = [
            '@type' => 'Place',
            'name' => $location,
        ];
    }

    $organizerName = trim((string)($event['organizer_name'] ?? ''));
    $organizerEmail = trim((string)($event['organizer_email'] ?? ''));
    if ($organizerName !== '' || $organizerEmail !== '') {
        $data['organizer'] = array_filter([
            '@type' => 'Organization',
            'name' => $organizerName,
            'email' => $organizerEmail !== '' ? 'mailto:' . $organizerEmail : '',
        ], static fn($value): bool => $value !== '');
    }

    $registrationUrl = trim((string)($event['registration_url'] ?? ''));
    if ($registrationUrl !== '') {
        $data['offers'] = [
            '@type' => 'Offer',
            'url' => $registrationUrl,
            'availability' => 'https://schema.org/InStock',
        ];
    }

    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
}

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

    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
}

function eventIcsFilename(array $event): string
{
    $slug = eventSlug((string)($event['slug'] ?? ''));
    if ($slug === '') {
        $slug = 'udalost-' . (int)($event['id'] ?? 0);
    }

    return $slug . '.ics';
}

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

    $location = trim((string)($event['location'] ?? ''));
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

function uniquePageSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = pageSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'stranka';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_pages WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
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

    $orderedIds = array_map(static fn(array $row): int => (int)$row['id'], $pages);
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

    if ($pages === []) {
        return;
    }

    $update = $pdo->prepare("UPDATE cms_pages SET blog_nav_order = ? WHERE id = ?");
    $position = 1;
    foreach ($pages as $page) {
        if ((int)($page['blog_nav_order'] ?? 0) !== $position) {
            $update->execute([$position, (int)$page['id']]);
        }
        $position++;
    }
}

function nextBlogPageNavigationOrder(PDO $pdo, int $blogId): int
{
    if ($blogId <= 0) {
        return 0;
    }

    normalizeBlogPageNavigationOrder($pdo, $blogId);

    $stmt = $pdo->prepare("SELECT COALESCE(MAX(blog_nav_order), 0) FROM cms_pages WHERE blog_id = ? AND deleted_at IS NULL");
    $stmt->execute([$blogId]);
    return (int)$stmt->fetchColumn() + 1;
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

function authorRoleValue(array $author): string
{
    return trim((string)($author['author_role'] ?? $author['role'] ?? ''));
}

function authorPublicSlugValue(array $author): string
{
    return authorSlug((string)($author['author_slug'] ?? $author['slug'] ?? ''));
}

function authorPublicEnabled(array $author): bool
{
    return (int)($author['author_public_enabled'] ?? 0) === 1
        && authorRoleValue($author) !== 'public'
        && authorPublicSlugValue($author) !== '';
}

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
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (!preg_match('#^https?://#i', $value)) {
        $value = 'https://' . ltrim($value, '/');
    }

    $validated = filter_var($value, FILTER_VALIDATE_URL);
    if (!is_string($validated) || !preg_match('#^https?://#i', $validated)) {
        return '';
    }

    return $validated;
}

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

function authorPublicPath(array $author): string
{
    $path = authorPublicRequestPath($author);
    return $path !== '' ? BASE_URL . $path : '';
}

function authorIndexPath(): string
{
    return BASE_URL . authorIndexRequestPath();
}

function authorPublicUrl(array $author): string
{
    $path = authorPublicRequestPath($author);
    return $path !== '' ? siteUrl($path) : '';
}

function authorIndexUrl(): string
{
    return siteUrl(authorIndexRequestPath());
}

function authorAvatarUrl(array $author): string
{
    $avatarFile = trim((string)($author['author_avatar'] ?? ''));
    if ($avatarFile === '') {
        return '';
    }

    return BASE_URL . '/uploads/authors/' . rawurlencode($avatarFile);
}

function hydrateAuthorPresentation(array $author): array
{
    $author['author_display_name'] = authorDisplayName($author);
    $author['author_public_path'] = authorPublicPath($author);
    $author['author_public_url'] = authorPublicUrl($author);
    $author['author_avatar_url'] = authorAvatarUrl($author);
    $author['author_website_url'] = normalizeAuthorWebsite((string)($author['author_website'] ?? ''));
    return $author;
}

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
    $show['feed_summary'] = podcastFeedSummary((string)($show['description_plain'] ?? ''));
    $show['is_public'] = podcastShowIsPublic($show);
    return $show;
}

function hydratePodcastEpisodePresentation(array $episode): array
{
    $episode['slug'] = podcastEpisodeSlug((string)($episode['slug'] ?? ''));
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
    $episode['feed_summary'] = podcastFeedSummary((string)($episode['description'] ?? ''));
    $episode['is_scheduled'] = podcastEpisodeIsScheduled($episode);
    $episode['is_public'] = podcastEpisodeIsPublic($episode);
    return $episode;
}

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

function hydratePollPresentation(array $poll): array
{
    $poll['question'] = trim((string)($poll['question'] ?? ''));
    $poll['slug'] = pollSlug((string)($poll['slug'] ?? ''));
    $poll['excerpt'] = pollExcerpt($poll);
    $poll['meta_title'] = trim((string)($poll['meta_title'] ?? ''));
    $poll['meta_description'] = trim((string)($poll['meta_description'] ?? ''));
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

function galleryAlbumExcerpt(array $album, int $limit = 220): string
{
    $explicitExcerpt = normalizePlainText((string)($album['description'] ?? ''));
    if ($explicitExcerpt === '') {
        return '';
    }

    return mb_strimwidth($explicitExcerpt, 0, $limit, '...', 'UTF-8');
}

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

function hydrateGalleryPhotoPresentation(array $photo): array
{
    $photo['slug'] = galleryPhotoSlug((string)($photo['slug'] ?? ''));
    $photo['label'] = galleryPhotoLabel($photo);
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

function fetchPublicAuthors(PDO $pdo): array
{
    $authors = $pdo->query(
        "SELECT u.id, u.email, u.first_name, u.last_name, u.nickname, u.role, u.is_superadmin,
                u.author_public_enabled, u.author_slug, u.author_bio, u.author_avatar, u.author_website,
                COUNT(a.id) AS article_count,
                MAX(COALESCE(a.publish_at, a.created_at)) AS latest_article_at
         FROM cms_users u
         LEFT JOIN cms_articles a
           ON a.author_id = u.id
          AND a.status = 'published'
          AND (a.publish_at IS NULL OR a.publish_at <= NOW())
         WHERE u.author_public_enabled = 1
           AND u.role != 'public'
         GROUP BY u.id, u.email, u.first_name, u.last_name, u.nickname, u.role, u.is_superadmin,
                  u.author_public_enabled, u.author_slug, u.author_bio, u.author_avatar, u.author_website
         ORDER BY COUNT(a.id) DESC, latest_article_at DESC, u.is_superadmin DESC, u.id ASC"
    )->fetchAll();

    return array_map(
        static function (array $author): array {
            $author['article_count'] = (int)($author['article_count'] ?? 0);
            return hydrateAuthorPresentation($author);
        },
        $authors
    );
}

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

function storeUploadedAuthorAvatar(array $file, string $existingFilename = ''): array
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError === UPLOAD_ERR_NO_FILE || trim((string)($file['name'] ?? '')) === '') {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => '',
        ];
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Avatar se nepodařilo nahrát.',
        ];
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Avatar se nepodařilo zpracovat.',
        ];
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    $mimeType = (string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
    if (!isset($allowedTypes[$mimeType])) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Avatar musí být ve formátu JPEG, PNG, GIF nebo WebP.',
        ];
    }

    $directory = dirname(__DIR__) . '/uploads/authors/';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Adresář pro avatary se nepodařilo vytvořit.',
        ];
    }

    $filename = uniqid('author_', true) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($tmpPath, $directory . $filename)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Avatar se nepodařilo uložit.',
        ];
    }
    generateWebp($directory . $filename);

    if ($existingFilename !== '' && $existingFilename !== $filename) {
        deleteAuthorAvatarFile($existingFilename);
    }

    return [
        'filename' => $filename,
        'uploaded' => true,
        'error' => '',
    ];
}

// ─────────────────────────────── Formuláře ────────────────────────────────

function formSlug(string $input): string
{
    return slugify($input);
}

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

function formSubmissionOpenStatuses(): array
{
    return array_keys(array_filter(
        formSubmissionStatusDefinitions(),
        static fn(array $definition): bool => !empty($definition['is_open'])
    ));
}

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
            $rawValue = implode(' ', array_map(static fn($item): string => trim((string)$item), $rawValue));
        }

        $resolved = formSubmissionPriorityFromText((string)$rawValue);
        if ($resolved !== 'medium' || trim((string)$rawValue) !== '') {
            return $resolved;
        }
    }

    return 'medium';
}

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
        if ($variant !== '' && !in_array($variant, $variants, true)) {
            $variants[] = $variant;
        }
    }

    return $variants;
}

function formSubmissionValueByFieldName(array $submissionData, string $fieldName): mixed
{
    foreach (formFieldNameVariants($fieldName) as $candidateName) {
        if (array_key_exists($candidateName, $submissionData)) {
            return $submissionData[$candidateName];
        }
    }

    return '';
}

function formFieldDefinitionByName(array $fieldsByName, string $fieldName): ?array
{
    foreach (formFieldNameVariants($fieldName) as $candidateName) {
        if (isset($fieldsByName[$candidateName]) && is_array($fieldsByName[$candidateName])) {
            return $fieldsByName[$candidateName];
        }
    }

    return null;
}

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

function formFieldStoresSubmissionValue(array $field): bool
{
    return !in_array(normalizeFormFieldType((string)($field['field_type'] ?? 'text')), ['section'], true);
}

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
                ['field_type' => 'url', 'label' => 'Adresa stránky nebo místa v systému', 'name' => 'adresa_mista', 'default_value' => '', 'placeholder' => 'https://example.com/admin/... nebo /blog/index.php', 'help_text' => 'Volitelné. Pomůže nám rychleji najít, čeho se problém týká.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'half', 'is_required' => 0, 'sort_order' => 50],
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

function formPresetDefinition(string $key): ?array
{
    $definitions = formPresetDefinitions();
    return $definitions[$key] ?? null;
}

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

function formFieldAllowsMultipleFiles(array $field): bool
{
    return normalizeFormFieldType((string)($field['field_type'] ?? 'text')) === 'file'
        && (int)($field['allow_multiple'] ?? 0) === 1;
}

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
        $actualValues = array_values(array_filter(array_map(static fn($item): string => trim((string)$item), $actual), static fn(string $item): bool => $item !== ''));
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

function formRenderTemplate(string $template, array $placeholderMap): string
{
    return strtr($template, $placeholderMap);
}

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

function formSubmissionFileItems(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }

    if (array_keys($value) === range(0, count($value) - 1)) {
        return array_values(array_filter($value, static fn(mixed $item): bool => is_array($item)));
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
            return implode(', ', array_filter($parts, static fn(string $item): bool => $item !== ''));
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

function formPublicRequestPath(array $form): string
{
    $slug = formSlug((string)($form['slug'] ?? ''));
    if ($slug !== '') {
        return '/forms/' . rawurlencode($slug);
    }
    return '/forms/index.php?id=' . (int)($form['id'] ?? 0);
}

function formPublicPath(array $form, array $query = []): string
{
    return BASE_URL . appendUrlQuery(formPublicRequestPath($form), $query);
}

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

function formPublicUrl(array $form, array $query = []): string
{
    return siteUrl(appendUrlQuery(formPublicRequestPath($form), $query));
}

// ──────────────────────── Související články ─────────────────────────────

/**
 * Vrátí související články z téhož blogu – prioritně se stejnou kategorií
 * nebo sdílenými štítky. Výsledky se řadí podle počtu společných štítků a data.
 */
function relatedArticles(PDO $pdo, array $article, int $limit = 3): array
{
    $articleId = (int)($article['id'] ?? 0);
    $blogId = (int)($article['blog_id'] ?? 1);
    $categoryId = $article['category_id'] ?? null;

    if ($articleId <= 0) {
        return [];
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

    $params[] = $blogId;
    $params[] = $articleId;
    $params[] = $limit;

    try {
        $stmt = $pdo->prepare(
            "SELECT a.id, a.title, a.slug, a.perex, a.image_file, a.blog_id,
                    a.created_at, a.category_id,
                    b.slug AS blog_slug,
                    ({$scoreExpr}) AS relevance_score
             FROM cms_articles a
             LEFT JOIN cms_blogs b ON b.id = a.blog_id
             WHERE a.blog_id = ?
               AND a.id != ?
               AND a.deleted_at IS NULL
               AND a.status = 'published'
               AND (a.publish_at IS NULL OR a.publish_at <= NOW())
             HAVING relevance_score > 0
             ORDER BY relevance_score DESC, a.created_at DESC
             LIMIT ?"
        );
        $stmt->execute($params);
        $results = $stmt->fetchAll();
    } catch (\PDOException $e) {
        $results = [];
    }

    // Pokud nemáme dostatek výsledků s relevancí, doplníme nejnovější z blogu
    if (count($results) < $limit) {
        $existingIds = array_map(static fn(array $row): int => (int)$row['id'], $results);
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
