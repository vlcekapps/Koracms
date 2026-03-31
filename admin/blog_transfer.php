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
    $normalized = preg_replace('/\s+/u', ' ', trim($value));
    if ($normalized === null) {
        $normalized = trim($value);
    }

    return function_exists('mb_strtolower')
        ? mb_strtolower($normalized, 'UTF-8')
        : strtolower($normalized);
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
    $map = [];
    foreach ($categories as $category) {
        $name = trim((string)($category['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $normalized = blogTransferNormalizeName($name);
        if (!isset($map[$normalized])) {
            $map[$normalized] = [
                'id' => (int)($category['id'] ?? 0),
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
function blogTransferTagLookupMaps(array $tags): array
{
    $bySlug = [];
    $byName = [];
    foreach ($tags as $tag) {
        $name = trim((string)($tag['name'] ?? ''));
        $slug = trim((string)($tag['slug'] ?? ''));
        $tagData = [
            'id' => (int)($tag['id'] ?? 0),
            'name' => $name,
            'slug' => $slug,
        ];

        if ($slug !== '' && !isset($bySlug[$slug])) {
            $bySlug[$slug] = $tagData;
        }

        if ($name !== '') {
            $normalized = blogTransferNormalizeName($name);
            if (!isset($byName[$normalized])) {
                $byName[$normalized] = $tagData;
            }
        }
    }

    return ['by_slug' => $bySlug, 'by_name' => $byName];
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

$categoryResolution = ['resolved' => [], 'missing' => []];
$tagResolution = ['resolved' => [], 'missing' => []];
$error = '';

if ($selectedTargetBlogId !== null && $targetBlog === null && $targetBlogs !== []) {
    $error = 'Vybraný cílový blog nemůžete použít pro přesun článků.';
}

if ($targetBlog !== null) {
    $categoryResolution = blogTransferResolveCategories(
        $articles,
        blogTransferCategoryMapByName(blogTransferLoadCategories($pdo, (int)$targetBlog['id']))
    );
    $tagResolution = blogTransferResolveTags(
        $articleTags,
        blogTransferTagLookupMaps(blogTransferLoadTags($pdo, (int)$targetBlog['id']))
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    verifyCsrf();

    if ($targetBlogs === []) {
        $error = 'Pro tento výběr teď nemáte k dispozici žádný jiný cílový blog. Zúžte prosím výběr článků.';
    } elseif ($targetBlog === null) {
        $error = 'Vyberte prosím cílový blog, do kterého se mají články přesunout.';
    } else {
        $submittedRedirect = internalRedirectTarget(trim((string)($_POST['redirect'] ?? '')), $returnUrl);
        $categoryStrategy = (string)($_POST['category_strategy'] ?? 'drop');
        $tagStrategy = (string)($_POST['tag_strategy'] ?? 'drop');

        if (!in_array($categoryStrategy, ['drop', 'create'], true)) {
            $categoryStrategy = 'drop';
        }
        if (!in_array($tagStrategy, ['drop', 'create'], true)) {
            $tagStrategy = 'drop';
        }

        if ($categoryResolution['missing'] !== [] && $categoryStrategy === 'create' && !$canCreateTargetTaxonomies) {
            $error = 'Chybějící kategorie může v cílovém blogu vytvářet jen správce blogu.';
        } elseif ($tagResolution['missing'] !== [] && $tagStrategy === 'create' && !$canCreateTargetTaxonomies) {
            $error = 'Chybějící štítky může v cílovém blogu vytvářet jen správce blogu.';
        } else {
            try {
                $pdo->beginTransaction();

                $targetCategoryMap = blogTransferCategoryMapByName(
                    blogTransferLoadCategories($pdo, (int)$targetBlog['id'])
                );
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

                $targetTagMaps = blogTransferTagLookupMaps(
                    blogTransferLoadTags($pdo, (int)$targetBlog['id'])
                );
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
                    $targetTagMaps = blogTransferTagLookupMaps(
                        blogTransferLoadTags($pdo, (int)$targetBlog['id'])
                    );
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
                    }

                    $newTagIds = [];
                    $newTagNames = [];
                    foreach ($oldTags as $tag) {
                        $tagName = trim((string)($tag['name'] ?? ''));
                        $tagSlug = trim((string)($tag['slug'] ?? ''));
                        $matchedTag = null;

                        if ($tagSlug !== '' && isset($targetTagMaps['by_slug'][$tagSlug])) {
                            $matchedTag = $targetTagMaps['by_slug'][$tagSlug];
                        } else {
                            $normalizedTagName = blogTransferNormalizeName($tagName);
                            if ($normalizedTagName !== '' && isset($targetTagMaps['by_name'][$normalizedTagName])) {
                                $matchedTag = $targetTagMaps['by_name'][$normalizedTagName];
                            }
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
        aria-describedby="transfer-summary-help transfer-target-help"
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
          <input type="radio" name="category_strategy" value="drop" checked>
          Přesunout články bez chybějících kategorií
        </label>
        <?php if ($canCreateTargetTaxonomies): ?>
          <label>
            <input type="radio" name="category_strategy" value="create">
            Vytvořit chybějící kategorie v cílovém blogu
          </label>
        <?php else: ?>
          <p class="field-help">Nové kategorie může v cílovém blogu vytvářet jen správce blogu.</p>
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
          <input type="radio" name="tag_strategy" value="drop" checked>
          Přesunout články bez chybějících štítků
        </label>
        <?php if ($canCreateTargetTaxonomies): ?>
          <label>
            <input type="radio" name="tag_strategy" value="create">
            Vytvořit chybějící štítky v cílovém blogu
          </label>
        <?php else: ?>
          <p class="field-help">Nové štítky může v cílovém blogu vytvářet jen správce blogu.</p>
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
