<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

const BLOG_TRANSFER_SESSION_KEY = 'blog_transfer_selection';

/**
 * @param array<int, array<string, mixed>> $articles
 * @return int[]
 */
function blogTransferArticleIds(array $articles): array
{
    return array_values(array_map(
        static fn(array $article): int => (int)($article['id'] ?? 0),
        $articles
    ));
}

function blogTransferNormalizeName(string $value): string
{
    return normalizeBlogTaxonomyName($value);
}

function blogTransferMappingFieldName(string $prefix, string $key): string
{
    return $prefix . '_map_' . substr(sha1($key), 0, 12);
}

/**
 * @param array<int, array<string, mixed>> $categories
 * @return array<int, array{id:int,name:string}>
 */
function blogTransferCategoryMapById(array $categories): array
{
    $map = [];
    foreach ($categories as $category) {
        $categoryId = (int)($category['id'] ?? 0);
        $categoryName = trim((string)($category['name'] ?? ''));
        if ($categoryId <= 0 || $categoryName === '') {
            continue;
        }

        $map[$categoryId] = [
            'id' => $categoryId,
            'name' => $categoryName,
        ];
    }

    return $map;
}

/**
 * @param array<int, array<string, mixed>> $tags
 * @return array<int, array{id:int,name:string,slug:string}>
 */
function blogTransferTagMapById(array $tags): array
{
    $map = [];
    foreach ($tags as $tag) {
        $tagId = (int)($tag['id'] ?? 0);
        $tagName = trim((string)($tag['name'] ?? ''));
        $tagSlug = trim((string)($tag['slug'] ?? ''));
        if ($tagId <= 0 || ($tagName === '' && $tagSlug === '')) {
            continue;
        }

        $map[$tagId] = [
            'id' => $tagId,
            'name' => $tagName,
            'slug' => $tagSlug,
        ];
    }

    return $map;
}

/**
 * @param int[] $articleIds
 * @return array<int, array<int, array{name:string,slug:string}>>
 */
function blogTransferLoadArticleTags(PDO $pdo, array $articleIds): array
{
    if ($articleIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($articleIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT at.article_id, t.name, t.slug
         FROM cms_article_tags at
         INNER JOIN cms_tags t ON t.id = at.tag_id
         WHERE at.article_id IN ({$placeholders})
         ORDER BY t.name ASC"
    );
    $stmt->execute($articleIds);

    $tagsByArticle = [];
    foreach ($stmt->fetchAll() as $row) {
        $articleId = (int)($row['article_id'] ?? 0);
        if ($articleId <= 0) {
            continue;
        }

        $tagsByArticle[$articleId][] = [
            'name' => (string)($row['name'] ?? ''),
            'slug' => (string)($row['slug'] ?? ''),
        ];
    }

    return $tagsByArticle;
}

/**
 * @param array<int, array{name:string,slug:string}> $tags
 */
function blogTransferTagSummary(array $tags): string
{
    if ($tags === []) {
        return 'Bez štítků';
    }

    $names = array_values(array_filter(array_map(
        static fn(array $tag): string => trim((string)($tag['name'] ?? '')),
        $tags
    )));

    return $names === [] ? 'Bez štítků' : implode(', ', $names);
}

/**
 * @param array<int, array<string, mixed>> $articles
 * @return array<int, array<string, mixed>>
 */
function blogTransferLoadCategories(PDO $pdo, int $blogId): array
{
    $stmt = $pdo->prepare("SELECT id, name FROM cms_categories WHERE blog_id = ? ORDER BY name ASC, id ASC");
    $stmt->execute([$blogId]);
    return $stmt->fetchAll() ?: [];
}

/**
 * @return array<int, array<string, mixed>>
 */
function blogTransferLoadTags(PDO $pdo, int $blogId): array
{
    $stmt = $pdo->prepare("SELECT id, name, slug FROM cms_tags WHERE blog_id = ? ORDER BY name ASC, id ASC");
    $stmt->execute([$blogId]);
    return $stmt->fetchAll() ?: [];
}

/**
 * @param array<int, array<string, mixed>> $categories
 * @return array<string, array{id:int,name:string}>
 */
function blogTransferCategoryMapByName(array $categories): array
{
    return blogCategoryLookupByNormalizedName($categories);
}

/**
 * @param array<int, array<string, mixed>> $tags
 * @return array{by_slug: array<string, array{id:int,name:string,slug:string}>, by_name: array<string, array{id:int,name:string,slug:string}>}
 */
function blogTransferTagLookupMaps(array $tags): array
{
    return blogTagLookupMaps($tags);
}

/**
 * @param array<int, array<string, mixed>> $articles
 * @param array<int, array<int, array{name:string,slug:string}>> $articleTags
 * @return array{resolved: array<string, array{id:int,name:string}>, missing: array<string, string>}
 */
function blogTransferResolveCategories(array $articles, array $targetCategoryMap): array
{
    $resolved = [];
    $missing = [];

    foreach ($articles as $article) {
        $categoryName = trim((string)($article['category_name'] ?? ''));
        if ($categoryName === '') {
            continue;
        }

        $normalized = blogTransferNormalizeName($categoryName);
        if (isset($targetCategoryMap[$normalized])) {
            $resolved[$normalized] = $targetCategoryMap[$normalized];
            continue;
        }

        $missing[$normalized] = $categoryName;
    }

    return ['resolved' => $resolved, 'missing' => $missing];
}

/**
 * @param array<int, array<int, array{name:string,slug:string}>> $articleTags
 * @return array{resolved: array<string, array{id:int,name:string,slug:string}>, missing: array<string, array{name:string,slug:string}>}
 */
function blogTransferResolveTags(array $articleTags, array $targetTagMaps): array
{
    $resolved = [];
    $missing = [];

    foreach ($articleTags as $tags) {
        foreach ($tags as $tag) {
            $name = trim((string)($tag['name'] ?? ''));
            $slug = trim((string)($tag['slug'] ?? ''));
            if ($name === '' && $slug === '') {
                continue;
            }

            $key = $slug !== '' ? 'slug:' . $slug : 'name:' . blogTransferNormalizeName($name);
            if (isset($resolved[$key]) || isset($missing[$key])) {
                continue;
            }

            if ($slug !== '' && isset($targetTagMaps['by_slug'][$slug])) {
                $resolved[$key] = $targetTagMaps['by_slug'][$slug];
                continue;
            }

            $normalizedName = blogTransferNormalizeName($name);
            if ($normalizedName !== '' && isset($targetTagMaps['by_name'][$normalizedName])) {
                $resolved[$key] = $targetTagMaps['by_name'][$normalizedName];
                continue;
            }

            $missing[$key] = ['name' => $name, 'slug' => $slug];
        }
    }

    return ['resolved' => $resolved, 'missing' => $missing];
}

$setFlash = static function (string $type, string $message): void {
    $_SESSION['blog_transfer_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
};

$selection = $_SESSION[BLOG_TRANSFER_SESSION_KEY] ?? null;
$returnUrl = internalRedirectTarget(trim((string)($selection['redirect'] ?? '')), BASE_URL . '/admin/blog.php');

if (isset($_GET['cancel'])) {
    unset($_SESSION[BLOG_TRANSFER_SESSION_KEY]);
    header('Location: ' . $returnUrl);
    exit;
}

if (!is_array($selection) || !isset($selection['ids']) || !is_array($selection['ids'])) {
    $setFlash('error', 'Přesouvané články už nejsou připravené. Vyberte je prosím znovu v přehledu článků.');
    header('Location: ' . $returnUrl);
    exit;
}

$pdo = db_connect();
$selectionIds = array_values(array_unique(array_filter(array_map('intval', $selection['ids']), static fn(int $id): bool => $id > 0)));
$writableBlogs = getWritableBlogsForUser();

if (count($writableBlogs) < 2) {
    unset($_SESSION[BLOG_TRANSFER_SESSION_KEY]);
    $setFlash('error', 'Přesun článků je dostupný jen tehdy, když máte přístup alespoň do dvou blogů.');
    header('Location: ' . $returnUrl);
    exit;
}

$articles = loadTransferableBlogArticles($pdo, $selectionIds);
if ($selectionIds === [] || count($articles) !== count($selectionIds)) {
    unset($_SESSION[BLOG_TRANSFER_SESSION_KEY]);
    $setFlash('error', 'Vybraný seznam článků se změnil nebo obsahuje položky, které nemůžete přesouvat.');
    header('Location: ' . $returnUrl);
    exit;
}

usort($articles, static function (array $leftArticle, array $rightArticle): int {
    return strcasecmp((string)($leftArticle['title'] ?? ''), (string)($rightArticle['title'] ?? ''));
});

$articleIds = blogTransferArticleIds($articles);
$articleTags = blogTransferLoadArticleTags($pdo, $articleIds);
$sourceBlogIds = array_values(array_unique(array_map(
    static fn(array $article): int => (int)($article['blog_id'] ?? 0),
    $articles
)));
$sourceBlogNames = array_values(array_unique(array_filter(array_map(
    static fn(array $article): string => trim((string)($article['blog_name'] ?? '')),
    $articles
))));

$targetBlogs = array_values(array_filter(
    $writableBlogs,
    static fn(array $blog): bool => !in_array((int)($blog['id'] ?? 0), $sourceBlogIds, true)
));
$targetBlogMap = [];
foreach ($targetBlogs as $targetBlogRow) {
    $targetBlogMap[(int)($targetBlogRow['id'] ?? 0)] = $targetBlogRow;
}

$selectedTargetBlogId = inputInt($_SERVER['REQUEST_METHOD'] === 'POST' ? 'post' : 'get', 'target_blog_id');
$targetBlog = ($selectedTargetBlogId !== null && isset($targetBlogMap[$selectedTargetBlogId]))
    ? $targetBlogMap[$selectedTargetBlogId]
    : null;
$canCreateTargetTaxonomies = $targetBlog ? canCurrentUserManageBlogTaxonomies((int)$targetBlog['id']) : false;

$targetCategories = [];
$targetTags = [];
$targetCategoryMap = [];
$targetCategoryMapById = [];
$targetTagMaps = ['by_slug' => [], 'by_name' => []];
$targetTagMapById = [];
$categoryResolution = ['resolved' => [], 'missing' => []];
$tagResolution = ['resolved' => [], 'missing' => []];
$error = '';
$fieldErrors = [];
$categoryMapSelections = [];
$tagMapSelections = [];
$manualCategoryAssignments = [];
$manualTagAssignments = [];

if ($selectedTargetBlogId !== null && $targetBlog === null && $targetBlogs !== []) {
    $error = 'Vybraný cílový blog nemůžete použít pro přesun článků.';
    $fieldErrors[] = 'target_blog_id';
}

if ($targetBlog !== null) {
    $targetCategories = blogTransferLoadCategories($pdo, (int)$targetBlog['id']);
    $targetTags = blogTransferLoadTags($pdo, (int)$targetBlog['id']);
    $targetCategoryMap = blogTransferCategoryMapByName($targetCategories);
    $targetCategoryMapById = blogTransferCategoryMapById($targetCategories);
    $targetTagMaps = blogTransferTagLookupMaps($targetTags);
    $targetTagMapById = blogTransferTagMapById($targetTags);
    $categoryResolution = blogTransferResolveCategories(
        $articles,
        $targetCategoryMap
    );
    $tagResolution = blogTransferResolveTags(
        $articleTags,
        $targetTagMaps
    );
}

$canMapExistingCategories = $canCreateTargetTaxonomies && $targetCategoryMapById !== [];
$canMapExistingTags = $canCreateTargetTaxonomies && $targetTagMapById !== [];
$categoryStrategy = 'drop';
$tagStrategy = 'drop';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    verifyCsrf();

    if ($targetBlogs === []) {
        $error = 'Pro tento výběr teď nemáte k dispozici žádný jiný cílový blog. Zúžte prosím výběr článků.';
    } elseif ($targetBlog === null) {
        $error = 'Vyberte prosím cílový blog, do kterého se mají články přesunout.';
        $fieldErrors[] = 'target_blog_id';
    } else {
        $submittedRedirect = internalRedirectTarget(trim((string)($_POST['redirect'] ?? '')), $returnUrl);
        $categoryStrategy = (string)($_POST['category_strategy'] ?? 'drop');
        $tagStrategy = (string)($_POST['tag_strategy'] ?? 'drop');
        $categoryMapSelections = is_array($_POST['category_map'] ?? null)
            ? array_map(static fn($value): string => trim((string)$value), (array)$_POST['category_map'])
            : [];
        $tagMapSelections = is_array($_POST['tag_map'] ?? null)
            ? array_map(static fn($value): string => trim((string)$value), (array)$_POST['tag_map'])
            : [];

        if (!in_array($categoryStrategy, ['drop', 'create', 'map_existing'], true)) {
            $categoryStrategy = 'drop';
        }
        if (!in_array($tagStrategy, ['drop', 'create', 'map_existing'], true)) {
            $tagStrategy = 'drop';
        }

        if ($categoryResolution['missing'] !== [] && $categoryStrategy === 'create' && !$canCreateTargetTaxonomies) {
            $error = 'Chybějící kategorie může v cílovém blogu vytvářet jen správce blogu.';
            $fieldErrors[] = 'category_strategy';
        } elseif ($tagResolution['missing'] !== [] && $tagStrategy === 'create' && !$canCreateTargetTaxonomies) {
            $error = 'Chybějící štítky může v cílovém blogu vytvářet jen správce blogu.';
            $fieldErrors[] = 'tag_strategy';
        } elseif ($categoryResolution['missing'] !== [] && $categoryStrategy === 'map_existing' && !$canCreateTargetTaxonomies) {
            $error = 'Ruční mapování kategorií je dostupné jen správci cílového blogu.';
            $fieldErrors[] = 'category_strategy';
        } elseif ($tagResolution['missing'] !== [] && $tagStrategy === 'map_existing' && !$canCreateTargetTaxonomies) {
            $error = 'Ruční mapování štítků je dostupné jen správci cílového blogu.';
            $fieldErrors[] = 'tag_strategy';
        } elseif ($categoryResolution['missing'] !== [] && $categoryStrategy === 'map_existing' && !$canMapExistingCategories) {
            $error = 'V cílovém blogu zatím není žádná kategorie, na kterou by šlo chybějící kategorie namapovat.';
            $fieldErrors[] = 'category_strategy';
        } elseif ($tagResolution['missing'] !== [] && $tagStrategy === 'map_existing' && !$canMapExistingTags) {
            $error = 'V cílovém blogu zatím není žádný štítek, na který by šlo chybějící štítky namapovat.';
            $fieldErrors[] = 'tag_strategy';
        } else {
            if ($categoryResolution['missing'] !== [] && $categoryStrategy === 'map_existing') {
                foreach ($categoryResolution['missing'] as $normalizedName => $categoryName) {
                    $fieldName = blogTransferMappingFieldName('category', $normalizedName);
                    $selectedCategoryValue = trim((string)($categoryMapSelections[$normalizedName] ?? ''));
                    if ($selectedCategoryValue === '') {
                        $fieldErrors[] = $fieldName;
                        continue;
                    }
                    if ($selectedCategoryValue === 'drop') {
                        $manualCategoryAssignments[$normalizedName] = null;
                        continue;
                    }

                    $selectedCategoryId = (int)$selectedCategoryValue;
                    if ($selectedCategoryId <= 0 || !isset($targetCategoryMapById[$selectedCategoryId])) {
                        $error = 'Vybraná cílová kategorie nepatří do cílového blogu.';
                        $fieldErrors[] = $fieldName;
                        break;
                    }

                    $manualCategoryAssignments[$normalizedName] = $targetCategoryMapById[$selectedCategoryId];
                }

                if ($error === '' && $fieldErrors !== []) {
                    $error = 'Vyberte prosím cílovou kategorii nebo možnost bez kategorie pro všechny chybějící kategorie.';
                }
            }

            if ($error === '' && $tagResolution['missing'] !== [] && $tagStrategy === 'map_existing') {
                foreach ($tagResolution['missing'] as $tagKey => $missingTag) {
                    $fieldName = blogTransferMappingFieldName('tag', $tagKey);
                    $selectedTagValue = trim((string)($tagMapSelections[$tagKey] ?? ''));
                    if ($selectedTagValue === '') {
                        $fieldErrors[] = $fieldName;
                        continue;
                    }
                    if ($selectedTagValue === 'drop') {
                        $manualTagAssignments[$tagKey] = null;
                        continue;
                    }

                    $selectedTagId = (int)$selectedTagValue;
                    if ($selectedTagId <= 0 || !isset($targetTagMapById[$selectedTagId])) {
                        $error = 'Vybraný cílový štítek nepatří do cílového blogu.';
                        $fieldErrors[] = $fieldName;
                        break;
                    }

                    $manualTagAssignments[$tagKey] = $targetTagMapById[$selectedTagId];
                }

                if ($error === '' && $fieldErrors !== []) {
                    $error = 'Vyberte prosím cílový štítek nebo možnost bez štítku pro všechny chybějící štítky.';
                }
            }
        }

        if ($error === '') {
            try {
                $pdo->beginTransaction();

                $targetCategories = blogTransferLoadCategories($pdo, (int)$targetBlog['id']);
                $targetCategoryMap = blogTransferCategoryMapByName($targetCategories);
                $targetCategoryMapById = blogTransferCategoryMapById($targetCategories);
                if ($categoryStrategy === 'create') {
                    $insertCategory = $pdo->prepare("INSERT INTO cms_categories (name, blog_id) VALUES (?, ?)");
                    foreach ($categoryResolution['missing'] as $normalizedName => $categoryName) {
                        if (isset($targetCategoryMap[$normalizedName])) {
                            continue;
                        }
                        $insertCategory->execute([$categoryName, (int)$targetBlog['id']]);
                        $targetCategoryMap[$normalizedName] = [
                            'id' => (int)$pdo->lastInsertId(),
                            'name' => $categoryName,
                        ];
                    }
                }
                if ($categoryStrategy === 'map_existing') {
                    foreach ($manualCategoryAssignments as $normalizedName => $mappedCategory) {
                        if ($mappedCategory === null) {
                            continue;
                        }

                        $mappedCategoryId = (int)($mappedCategory['id'] ?? 0);
                        if ($mappedCategoryId <= 0 || !isset($targetCategoryMapById[$mappedCategoryId])) {
                            throw new RuntimeException('Ruční mapování kategorií obsahuje neplatnou cílovou kategorii.');
                        }

                        $targetCategoryMap[$normalizedName] = $targetCategoryMapById[$mappedCategoryId];
                    }
                }

                $targetTags = blogTransferLoadTags($pdo, (int)$targetBlog['id']);
                $targetTagMaps = blogTransferTagLookupMaps($targetTags);
                $targetTagMapById = blogTransferTagMapById($targetTags);
                if ($tagStrategy === 'create') {
                    $insertTag = $pdo->prepare("INSERT INTO cms_tags (name, slug, blog_id) VALUES (?, ?, ?)");
                    foreach ($tagResolution['missing'] as $missingTag) {
                        $missingName = trim((string)($missingTag['name'] ?? ''));
                        $missingSlug = trim((string)($missingTag['slug'] ?? ''));
                        if ($missingName === '') {
                            continue;
                        }
                        if ($missingSlug !== '' && isset($targetTagMaps['by_slug'][$missingSlug])) {
                            continue;
                        }
                        $normalizedName = blogTransferNormalizeName($missingName);
                        if ($normalizedName !== '' && isset($targetTagMaps['by_name'][$normalizedName])) {
                            continue;
                        }
                        $insertTag->execute([$missingName, $missingSlug !== '' ? $missingSlug : slugify($missingName), (int)$targetBlog['id']]);
                    }
                    $targetTags = blogTransferLoadTags($pdo, (int)$targetBlog['id']);
                    $targetTagMaps = blogTransferTagLookupMaps($targetTags);
                    $targetTagMapById = blogTransferTagMapById($targetTags);
                }
                if ($tagStrategy === 'map_existing') {
                    foreach ($manualTagAssignments as $tagKey => $mappedTag) {
                        if ($mappedTag === null) {
                            continue;
                        }

                        $mappedTagId = (int)($mappedTag['id'] ?? 0);
                        if ($mappedTagId <= 0 || !isset($targetTagMapById[$mappedTagId])) {
                            throw new RuntimeException('Ruční mapování štítků obsahuje neplatný cílový štítek.');
                        }

                        $manualTagAssignments[$tagKey] = $targetTagMapById[$mappedTagId];
                    }
                }

                $articleIdPlaceholders = implode(',', array_fill(0, count($articleIds), '?'));
                $featuredParams = array_merge([(int)$targetBlog['id']], $articleIds);
                $featuredStmt = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM cms_articles
                     WHERE blog_id = ?
                       AND is_featured_in_blog = 1
                       AND deleted_at IS NULL
                       AND id NOT IN ({$articleIdPlaceholders})"
                );
                $featuredStmt->execute($featuredParams);
                $featuredTaken = (int)$featuredStmt->fetchColumn() > 0;

                $updateArticle = $pdo->prepare(
                    "UPDATE cms_articles
                     SET blog_id = ?, category_id = ?, is_featured_in_blog = ?, updated_at = NOW()
                     WHERE id = ?"
                );
                $deleteTags = $pdo->prepare("DELETE FROM cms_article_tags WHERE article_id = ?");
                $insertArticleTag = $pdo->prepare("INSERT IGNORE INTO cms_article_tags (article_id, tag_id) VALUES (?, ?)");

                foreach ($articles as $article) {
                    $articleId = (int)($article['id'] ?? 0);
                    $oldCategoryName = trim((string)($article['category_name'] ?? ''));
                    $oldTags = $articleTags[$articleId] ?? [];
                    $normalizedCategoryName = $oldCategoryName !== '' ? blogTransferNormalizeName($oldCategoryName) : '';

                    $newCategoryId = null;
                    $newCategoryLabel = 'Bez kategorie';
                    if ($normalizedCategoryName !== '' && isset($targetCategoryMap[$normalizedCategoryName])) {
                        $newCategoryId = (int)$targetCategoryMap[$normalizedCategoryName]['id'];
                        $newCategoryLabel = (string)$targetCategoryMap[$normalizedCategoryName]['name'];
                    } elseif ($normalizedCategoryName !== '' && $categoryStrategy === 'map_existing' && array_key_exists($normalizedCategoryName, $manualCategoryAssignments)) {
                        $mappedCategory = $manualCategoryAssignments[$normalizedCategoryName];
                        if (is_array($mappedCategory)) {
                            $newCategoryId = (int)($mappedCategory['id'] ?? 0);
                            $newCategoryLabel = (string)($mappedCategory['name'] ?? 'Bez kategorie');
                        }
                    }

                    $newTagIds = [];
                    $newTagNames = [];
                    foreach ($oldTags as $tag) {
                        $tagName = trim((string)($tag['name'] ?? ''));
                        $tagSlug = trim((string)($tag['slug'] ?? ''));
                        $tagKey = $tagSlug !== '' ? 'slug:' . $tagSlug : 'name:' . blogTransferNormalizeName($tagName);
                        $matchedTag = null;

                        if ($tagSlug !== '' && isset($targetTagMaps['by_slug'][$tagSlug])) {
                            $matchedTag = $targetTagMaps['by_slug'][$tagSlug];
                        } else {
                            $normalizedTagName = blogTransferNormalizeName($tagName);
                            if ($normalizedTagName !== '' && isset($targetTagMaps['by_name'][$normalizedTagName])) {
                                $matchedTag = $targetTagMaps['by_name'][$normalizedTagName];
                            }
                        }

                        if ($matchedTag === null && $tagStrategy === 'map_existing' && array_key_exists($tagKey, $manualTagAssignments)) {
                            $matchedTag = $manualTagAssignments[$tagKey];
                        }

                        if ($matchedTag === null) {
                            continue;
                        }

                        $matchedTagId = (int)($matchedTag['id'] ?? 0);
                        if ($matchedTagId <= 0 || in_array($matchedTagId, $newTagIds, true)) {
                            continue;
                        }

                        $newTagIds[] = $matchedTagId;
                        $newTagNames[] = (string)($matchedTag['name'] ?? '');
                    }

                    $newFeaturedInBlog = 0;
                    if ((int)($article['is_featured_in_blog'] ?? 0) === 1 && !$featuredTaken) {
                        $newFeaturedInBlog = 1;
                        $featuredTaken = true;
                    }

                    saveRevision($pdo, 'article', $articleId, [
                        'blog' => (string)($article['blog_name'] ?? ''),
                        'category' => $oldCategoryName !== '' ? $oldCategoryName : 'Bez kategorie',
                        'tags' => blogTransferTagSummary($oldTags),
                    ], [
                        'blog' => (string)($targetBlog['name'] ?? ''),
                        'category' => $newCategoryLabel,
                        'tags' => $newTagNames === [] ? 'Bez štítků' : implode(', ', $newTagNames),
                    ]);

                    $updateArticle->execute([
                        (int)$targetBlog['id'],
                        $newCategoryId,
                        $newFeaturedInBlog,
                        $articleId,
                    ]);

                    $deleteTags->execute([$articleId]);
                    foreach ($newTagIds as $newTagId) {
                        $insertArticleTag->execute([$articleId, $newTagId]);
                    }
                }

                $pdo->commit();
                unset($_SESSION[BLOG_TRANSFER_SESSION_KEY]);
                clearBlogCache();
                logAction(
                    'article_transfer',
                    'ids=' . implode(',', $articleIds) . ';target_blog_id=' . (int)$targetBlog['id']
                );

                $movedCount = count($articleIds);
                $setFlash(
                    'success',
                    $movedCount === 1
                        ? 'Vybraný článek byl přesunut do jiného blogu.'
                        : 'Vybrané články byly přesunuty do jiného blogu.'
                );
                header('Location: ' . $submittedRedirect);
                exit;
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('blog_transfer: ' . $e->getMessage());
                $error = 'Přesun článků se nepodařilo dokončit. Zkuste to prosím znovu.';
            }
        }
    }
}

$selectedArticleCount = count($articles);
$articleCountLabel = $selectedArticleCount === 1 ? 'článek' : (($selectedArticleCount >= 2 && $selectedArticleCount <= 4) ? 'články' : 'článků');
$missingCategoryNames = array_values($categoryResolution['missing']);
$missingTagNames = array_values(array_map(
    static fn(array $tag): string => (string)($tag['name'] ?? ''),
    $tagResolution['missing']
));

adminHeader('Přesun článků mezi blogy');
?>

<?php if ($error !== ''): ?>
  <p class="error" role="alert"><?= h($error) ?></p>
<?php endif; ?>

<p class="button-row button-row--start">
  <a href="<?= h($returnUrl) ?>"><span aria-hidden="true">←</span> Zpět na články</a>
  <?php if ($targetBlog !== null): ?>
    <a href="<?= h(blogIndexPath($targetBlog)) ?>" target="_blank" rel="noopener">Zobrazit cílový blog na webu</a>
  <?php endif; ?>
</p>

<form method="get" action="blog_transfer.php" style="margin-bottom:1rem" novalidate>
  <fieldset>
    <legend>Přesun článků</legend>
    <p class="field-help" id="transfer-summary-help">
      Připraveno je <?= $selectedArticleCount ?> <?= h($articleCountLabel) ?>.
      <?php if (count($sourceBlogNames) === 1): ?>
        Zdrojový blog: <strong><?= h($sourceBlogNames[0]) ?></strong>.
      <?php else: ?>
        Výběr obsahuje články z více blogů: <strong><?= h(implode(', ', $sourceBlogNames)) ?></strong>.
      <?php endif; ?>
    </p>

    <?php if ($targetBlogs === []): ?>
      <p class="error" role="alert">
        Pro tento výběr teď nemáte k dispozici žádný jiný cílový blog.
        Vyberte prosím články z menšího počtu blogů nebo zúžte výběr jen na jeden zdrojový blog.
      </p>
    <?php else: ?>
      <label for="target_blog_id">Cílový blog <span aria-hidden="true">*</span></label>
      <select
        id="target_blog_id"
        name="target_blog_id"
        required
        aria-required="true"
        <?= adminFieldAttributes('target_blog_id', $fieldErrors, [], ['transfer-summary-help', 'transfer-target-help']) ?>
      >
        <option value="">Vyberte cílový blog</option>
        <?php foreach ($targetBlogs as $targetBlogOption): ?>
          <option value="<?= (int)$targetBlogOption['id'] ?>"<?= $targetBlog && (int)$targetBlogOption['id'] === (int)$targetBlog['id'] ? ' selected' : '' ?>>
            <?= h((string)$targetBlogOption['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <small id="transfer-target-help" class="field-help">
        Nabízí se jen blogy, do kterých teď smíte zapisovat a které nejsou zdrojem vybraných článků.
      </small>
      <?php adminRenderFieldError('target_blog_id', $fieldErrors, [], 'Vyberte prosím cílový blog, do kterého se mají články přesunout.'); ?>
      <div class="button-row" style="margin-top:1rem">
        <button type="submit" class="btn">Načíst možnosti převodu</button>
      </div>
    <?php endif; ?>
  </fieldset>
</form>

<section aria-labelledby="transfer-articles-heading" style="margin-bottom:1rem">
  <h2 id="transfer-articles-heading">Vybrané články</h2>
  <table>
    <caption>Vybrané články pro přesun</caption>
    <thead>
      <tr>
        <th scope="col">Titulek</th>
        <th scope="col">Blog</th>
        <th scope="col">Kategorie</th>
        <th scope="col">Štítky</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($articles as $article): ?>
        <?php $articleId = (int)($article['id'] ?? 0); ?>
        <tr>
          <td><?= h((string)($article['title'] ?? '')) ?></td>
          <td><?= h((string)($article['blog_name'] ?? '')) ?></td>
          <td><?= h((string)($article['category_name'] ?? 'Bez kategorie')) ?></td>
          <td><?= h(blogTransferTagSummary($articleTags[$articleId] ?? [])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<?php if ($targetBlog !== null): ?>
  <form method="post" action="blog_transfer.php" novalidate>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="target_blog_id" value="<?= (int)$targetBlog['id'] ?>">
    <input type="hidden" name="redirect" value="<?= h($returnUrl) ?>">

    <fieldset style="margin-bottom:1rem">
      <legend>Kategorie v cílovém blogu</legend>
      <?php if ($missingCategoryNames === []): ?>
        <p class="field-help">Všechny použité kategorie už v cílovém blogu existují a při přesunu se automaticky namapují.</p>
        <input type="hidden" name="category_strategy" value="drop">
      <?php else: ?>
        <p id="transfer-category-help" class="field-help">
          V cílovém blogu teď chybí tyto kategorie: <strong><?= h(implode(', ', $missingCategoryNames)) ?></strong>.
        </p>
        <label>
          <input type="radio" name="category_strategy" value="drop"<?= $categoryStrategy === 'drop' ? ' checked' : '' ?>>
          Přesunout články bez chybějících kategorií
        </label>
        <?php if ($canCreateTargetTaxonomies): ?>
          <label>
            <input type="radio" name="category_strategy" value="create"<?= $categoryStrategy === 'create' ? ' checked' : '' ?>>
            Vytvořit chybějící kategorie v cílovém blogu
          </label>
        <?php endif; ?>
        <?php if ($canMapExistingCategories): ?>
          <label>
            <input type="radio" name="category_strategy" value="map_existing"<?= $categoryStrategy === 'map_existing' ? ' checked' : '' ?>>
            Namapovat chybějící kategorie na existující kategorie cílového blogu
          </label>
        <?php endif; ?>
        <?php if (!$canCreateTargetTaxonomies): ?>
          <p class="field-help">Nové kategorie může v cílovém blogu vytvářet jen správce blogu.</p>
        <?php endif; ?>
        <?php adminRenderFieldError('category_strategy', $fieldErrors, [], 'Zvolte prosím způsob, jak naložit s chybějícími kategoriemi.'); ?>
        <?php if ($categoryStrategy === 'map_existing' && $canMapExistingCategories): ?>
          <fieldset style="margin-top:1rem">
            <legend>Ruční mapování kategorií</legend>
            <p class="field-help" id="transfer-category-map-help">
              Pro každou chybějící zdrojovou kategorii vyberte odpovídající kategorii v cílovém blogu, nebo potvrďte přesun bez kategorie.
            </p>
            <?php foreach ($categoryResolution['missing'] as $normalizedName => $categoryName): ?>
              <?php
              $categoryFieldName = blogTransferMappingFieldName('category', $normalizedName);
              $categoryHelpId = $categoryFieldName . '-help';
              $selectedCategoryValue = (string)($categoryMapSelections[$normalizedName] ?? '');
              ?>
              <div style="margin-top:.85rem">
                <label for="<?= h($categoryFieldName) ?>">
                  Zdrojová kategorie: <strong><?= h($categoryName) ?></strong>
                </label>
                <select
                  id="<?= h($categoryFieldName) ?>"
                  name="category_map[<?= h($normalizedName) ?>]"
                  <?= adminFieldAttributes($categoryFieldName, $fieldErrors, [], ['transfer-category-map-help', $categoryHelpId]) ?>
                >
                  <option value="">Vyberte cílovou kategorii</option>
                  <option value="drop"<?= $selectedCategoryValue === 'drop' ? ' selected' : '' ?>>Bez kategorie</option>
                  <?php foreach ($targetCategories as $targetCategoryOption): ?>
                    <option value="<?= (int)$targetCategoryOption['id'] ?>"<?= $selectedCategoryValue === (string)(int)$targetCategoryOption['id'] ? ' selected' : '' ?>>
                      <?= h((string)($targetCategoryOption['name'] ?? '')) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <small id="<?= h($categoryHelpId) ?>" class="field-help">
                  Vyberte existující kategorii cílového blogu, kterou chcete použít místo zdrojové kategorie „<?= h($categoryName) ?>“.
                </small>
                <?php adminRenderFieldError($categoryFieldName, $fieldErrors, [], 'Vyberte cílovou kategorii nebo možnost bez kategorie.'); ?>
              </div>
            <?php endforeach; ?>
          </fieldset>
        <?php endif; ?>
      <?php endif; ?>
    </fieldset>

    <fieldset style="margin-bottom:1rem">
      <legend>Štítky v cílovém blogu</legend>
      <?php if ($missingTagNames === []): ?>
        <p class="field-help">Všechny použité štítky už v cílovém blogu existují a při přesunu se automaticky namapují.</p>
        <input type="hidden" name="tag_strategy" value="drop">
      <?php else: ?>
        <p id="transfer-tag-help" class="field-help">
          V cílovém blogu teď chybí tyto štítky: <strong><?= h(implode(', ', $missingTagNames)) ?></strong>.
        </p>
        <label>
          <input type="radio" name="tag_strategy" value="drop"<?= $tagStrategy === 'drop' ? ' checked' : '' ?>>
          Přesunout články bez chybějících štítků
        </label>
        <?php if ($canCreateTargetTaxonomies): ?>
          <label>
            <input type="radio" name="tag_strategy" value="create"<?= $tagStrategy === 'create' ? ' checked' : '' ?>>
            Vytvořit chybějící štítky v cílovém blogu
          </label>
        <?php endif; ?>
        <?php if ($canMapExistingTags): ?>
          <label>
            <input type="radio" name="tag_strategy" value="map_existing"<?= $tagStrategy === 'map_existing' ? ' checked' : '' ?>>
            Namapovat chybějící štítky na existující štítky cílového blogu
          </label>
        <?php endif; ?>
        <?php if (!$canCreateTargetTaxonomies): ?>
          <p class="field-help">Nové štítky může v cílovém blogu vytvářet jen správce blogu.</p>
        <?php endif; ?>
        <?php adminRenderFieldError('tag_strategy', $fieldErrors, [], 'Zvolte prosím způsob, jak naložit s chybějícími štítky.'); ?>
        <?php if ($tagStrategy === 'map_existing' && $canMapExistingTags): ?>
          <fieldset style="margin-top:1rem">
            <legend>Ruční mapování štítků</legend>
            <p class="field-help" id="transfer-tag-map-help">
              Pro každý chybějící zdrojový štítek vyberte odpovídající štítek v cílovém blogu, nebo potvrďte přesun bez štítku.
            </p>
            <?php foreach ($tagResolution['missing'] as $tagKey => $missingTag): ?>
              <?php
              $tagName = trim((string)($missingTag['name'] ?? ''));
              $tagLabel = $tagName !== '' ? $tagName : trim((string)($missingTag['slug'] ?? ''));
              $tagFieldName = blogTransferMappingFieldName('tag', $tagKey);
              $tagHelpId = $tagFieldName . '-help';
              $selectedTagValue = (string)($tagMapSelections[$tagKey] ?? '');
              ?>
              <div style="margin-top:.85rem">
                <label for="<?= h($tagFieldName) ?>">
                  Zdrojový štítek: <strong><?= h($tagLabel) ?></strong>
                </label>
                <select
                  id="<?= h($tagFieldName) ?>"
                  name="tag_map[<?= h($tagKey) ?>]"
                  <?= adminFieldAttributes($tagFieldName, $fieldErrors, [], ['transfer-tag-map-help', $tagHelpId]) ?>
                >
                  <option value="">Vyberte cílový štítek</option>
                  <option value="drop"<?= $selectedTagValue === 'drop' ? ' selected' : '' ?>>Bez štítku</option>
                  <?php foreach ($targetTags as $targetTagOption): ?>
                    <option value="<?= (int)$targetTagOption['id'] ?>"<?= $selectedTagValue === (string)(int)$targetTagOption['id'] ? ' selected' : '' ?>>
                      <?= h((string)($targetTagOption['name'] ?? '')) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <small id="<?= h($tagHelpId) ?>" class="field-help">
                  Vyberte existující štítek cílového blogu, který chcete použít místo zdrojového štítku „<?= h($tagLabel) ?>“.
                </small>
                <?php adminRenderFieldError($tagFieldName, $fieldErrors, [], 'Vyberte cílový štítek nebo možnost bez štítku.'); ?>
              </div>
            <?php endforeach; ?>
          </fieldset>
        <?php endif; ?>
      <?php endif; ?>
    </fieldset>

    <div class="button-row">
      <button type="submit" class="btn btn-success">Přesunout články</button>
      <a href="blog_transfer.php?cancel=1" class="btn">Zrušit</a>
    </div>
  </form>
<?php endif; ?>

<?php adminFooter(); ?>
