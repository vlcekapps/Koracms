<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/content_reference_picker.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
$id = inputInt('get', 'id');
$article = null;

if ($id === null && !hasAnyBlogs()) {
    header('Location: ' . BASE_URL . '/admin/blog.php?msg=no_blog');
    exit;
}

if ($id !== null) {
    if (canManageOwnBlogOnly()) {
        $stmt = $pdo->prepare("SELECT * FROM cms_articles WHERE id = ? AND author_id = ?");
        $stmt->execute([$id, currentUserId()]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM cms_articles WHERE id = ?");
        $stmt->execute([$id]);
    }
    $article = $stmt->fetch();
    if (!$article) {
        header('Location: blog.php');
        exit;
    }

    if (canManageOwnBlogOnly() && !canCurrentUserWriteToBlog((int)($article['blog_id'] ?? 0))) {
        header('Location: ' . BASE_URL . '/admin/blog.php?msg=no_blog_access');
        exit;
    }
}

// Content locking – pokus o získání zámku při editaci existujícího článku
$contentLockWarning = null;
if ($article) {
    $contentLockWarning = acquireContentLock('article', $id);
}

$allBlogs = canManageOwnBlogOnly() ? getWritableBlogsForUser() : getAllBlogs();
if ($allBlogs === []) {
    header('Location: ' . BASE_URL . '/admin/blog.php?msg=no_blog_access');
    exit;
}

$accessibleBlogIds = array_map(static fn (array $blogEntry): int => (int)$blogEntry['id'], $allBlogs);
$defaultAccessibleBlog = $allBlogs[0];
$sourceArticleBlogId = (int)($article['blog_id'] ?? 0);
$requestedBlogId = (int)($_GET['blog_id'] ?? $article['blog_id'] ?? $defaultAccessibleBlog['id']);
if (!in_array($requestedBlogId, $accessibleBlogIds, true)) {
    $requestedBlogId = (int)$defaultAccessibleBlog['id'];
}

$currentBlogId = $requestedBlogId;
$currentBlog = getBlogById($currentBlogId) ?? $defaultAccessibleBlog;
$currentBlogId = (int)($currentBlog['id'] ?? $currentBlogId);
$articleListUrl = BASE_URL . '/admin/blog.php' . (isMultiBlog() ? '?blog=' . $currentBlogId : '');
$blogCategoriesUrl = BASE_URL . '/admin/blog_cats.php?blog_id=' . $currentBlogId;
$blogTagsUrl = BASE_URL . '/admin/blog_tags.php?blog_id=' . $currentBlogId;
$blogPublicUrl = $currentBlog ? blogIndexPath($currentBlog) : '';
$blogFeedUrl = $currentBlog ? blogFeedPath($currentBlog) : '';

$catStmt = $pdo->prepare("SELECT id, name, parent_id FROM cms_categories WHERE blog_id = ? ORDER BY name");
$catStmt->execute([$currentBlogId]);
$categories = $catStmt->fetchAll();

// Sestavíme strom kategorií pro hierarchické zobrazení v selectu
$blogFormCatTree = [];
$blogFormCatById = [];
foreach ($categories as $cat) {
    $blogFormCatById[(int)$cat['id']] = $cat;
}
foreach ($categories as $cat) {
    $pid = $cat['parent_id'] !== null ? (int)$cat['parent_id'] : 0;
    $blogFormCatTree[$pid][] = $cat;
}

/**
 * @param array<int, list<array{id:int|string,name:string,parent_id:int|string|null}>> $tree
 */
function renderBlogFormCategoryOptions(array $tree, int $parentId = 0, int $depth = 0, int $selectedId = 0): string
{
    $out = '';
    foreach ($tree[$parentId] ?? [] as $cat) {
        $cid = (int)$cat['id'];
        $prefix = $depth > 0 ? str_repeat('  ', $depth) . '-- ' : '';
        $selected = $cid === $selectedId ? ' selected' : '';
        $out .= '<option value="' . $cid . '"' . $selected . '>' . h($prefix . $cat['name']) . '</option>';
        $out .= renderBlogFormCategoryOptions($tree, $cid, $depth + 1, $selectedId);
    }
    return $out;
}

$allTags = [];
$articleTagIds = [];
$relatedArticleIds = $id !== null ? loadArticleRelatedIds($pdo, $id) : [];
$relatedArticleOptions = relatedArticleOptions($pdo, $currentBlogId, $id ?? 0);
$articleSeriesIds = $id !== null ? loadArticleSeriesIds($pdo, $id) : [];
$blogSeriesOptions = blogSeriesOptions($pdo, $currentBlogId);
$sourceCategoryName = '';
$sourceTagDetails = [];
$noCategoryLabel = '– bez kategorie –';
try {
    $tagStmt2 = $pdo->prepare("SELECT id, name, slug FROM cms_tags WHERE blog_id = ? ORDER BY name");
    $tagStmt2->execute([$currentBlogId]);
    $allTags = $tagStmt2->fetchAll();
    if ($id !== null) {
        if ((int)($article['category_id'] ?? 0) > 0) {
            $sourceCategoryStmt = $pdo->prepare("SELECT name FROM cms_categories WHERE id = ? LIMIT 1");
            $sourceCategoryStmt->execute([(int)$article['category_id']]);
            $sourceCategoryName = trim((string)$sourceCategoryStmt->fetchColumn());
        }

        $sourceTagDetails = loadArticleTagDetails($pdo, $id);
        $articleTagIds = array_values(array_map(
            static fn (array $tag): int => (int)$tag['id'],
            $sourceTagDetails
        ));
    }
} catch (\PDOException $e) {
    koraLog('warning', 'admin article form taxonomy query failed', [
        'section' => 'current_tags',
        'article_id' => $id,
        'blog_id' => $currentBlogId,
        'exception' => $e,
    ]);
}

$blogFormOptions = [];
if (isMultiBlog()) {
    foreach ($allBlogs as $blogEntry) {
        $blogFormOptions[(int)$blogEntry['id']] = [
            'categories' => [],
            'tags' => [],
            'related_articles' => [],
            'series' => [],
            'comments_default' => (int)($blogEntry['comments_default'] ?? 1),
            'can_manage_taxonomies' => canCurrentUserManageBlogTaxonomies((int)($blogEntry['id'] ?? 0)),
        ];
    }

    try {
        $allCategoriesStmt = $pdo->query("SELECT id, blog_id, name, parent_id FROM cms_categories ORDER BY blog_id, name");
        foreach ($allCategoriesStmt->fetchAll() as $categoryRow) {
            $blogId = (int)($categoryRow['blog_id'] ?? 0);
            if (!isset($blogFormOptions[$blogId])) {
                continue;
            }
            $blogFormOptions[$blogId]['categories'][] = [
                'id' => (int)$categoryRow['id'],
                'name' => (string)$categoryRow['name'],
                'parent_id' => $categoryRow['parent_id'] !== null ? (int)$categoryRow['parent_id'] : null,
            ];
        }
    } catch (\PDOException $e) {
        koraLog('warning', 'admin article form taxonomy query failed', [
            'section' => 'all_categories',
            'article_id' => $id,
            'blog_id' => $currentBlogId,
            'exception' => $e,
        ]);
    }

    try {
        $allTagsStmt = $pdo->query("SELECT id, blog_id, name, slug FROM cms_tags ORDER BY blog_id, name");
        foreach ($allTagsStmt->fetchAll() as $tagRow) {
            $blogId = (int)($tagRow['blog_id'] ?? 0);
            if (!isset($blogFormOptions[$blogId])) {
                continue;
            }
            $blogFormOptions[$blogId]['tags'][] = [
                'id' => (int)$tagRow['id'],
                'name' => (string)$tagRow['name'],
                'slug' => (string)($tagRow['slug'] ?? ''),
            ];
        }
    } catch (\PDOException $e) {
        koraLog('warning', 'admin article form taxonomy query failed', [
            'section' => 'all_tags',
            'article_id' => $id,
            'blog_id' => $currentBlogId,
            'exception' => $e,
        ]);
    }

    try {
        $allSeriesStmt = $pdo->query(
            "SELECT s.id, s.blog_id, s.title, s.slug, s.is_active, COUNT(si.article_id) AS article_count
             FROM cms_blog_series s
             LEFT JOIN cms_blog_series_items si ON si.series_id = s.id
             GROUP BY s.id, s.blog_id, s.title, s.slug, s.is_active, s.sort_order
             ORDER BY s.blog_id ASC, s.sort_order ASC, s.title ASC, s.id ASC"
        );
        foreach ($allSeriesStmt->fetchAll() as $seriesRow) {
            $blogId = (int)($seriesRow['blog_id'] ?? 0);
            if (!isset($blogFormOptions[$blogId])) {
                continue;
            }
            $blogFormOptions[$blogId]['series'][] = [
                'id' => (int)$seriesRow['id'],
                'title' => (string)$seriesRow['title'],
                'slug' => (string)($seriesRow['slug'] ?? ''),
                'is_active' => (int)($seriesRow['is_active'] ?? 1),
                'article_count' => (int)($seriesRow['article_count'] ?? 0),
            ];
        }
    } catch (\PDOException $e) {
        koraLog('warning', 'admin article form taxonomy query failed', [
            'section' => 'series',
            'article_id' => $id,
            'blog_id' => $currentBlogId,
            'exception' => $e,
        ]);
    }

    try {
        $excludeArticleId = $id ?? 0;
        $allRelatedStmt = $pdo->prepare(
            "SELECT id, blog_id, title, COALESCE(publish_at, created_at) AS display_date
             FROM cms_articles
             WHERE id <> ?
               AND deleted_at IS NULL
               AND status = 'published'
               AND (publish_at IS NULL OR publish_at <= NOW())
             ORDER BY blog_id ASC, COALESCE(publish_at, created_at) DESC, id DESC"
        );
        $allRelatedStmt->execute([$excludeArticleId]);
        foreach ($allRelatedStmt->fetchAll() as $relatedRow) {
            $blogId = (int)($relatedRow['blog_id'] ?? 0);
            if (!isset($blogFormOptions[$blogId])) {
                continue;
            }
            $blogFormOptions[$blogId]['related_articles'][] = [
                'id' => (int)$relatedRow['id'],
                'title' => (string)$relatedRow['title'],
                'display_date' => (string)($relatedRow['display_date'] ?? ''),
            ];
        }
    } catch (\PDOException $e) {
        koraLog('warning', 'admin article form taxonomy query failed', [
            'section' => 'related_articles',
            'article_id' => $id,
            'blog_id' => $currentBlogId,
            'exception' => $e,
        ]);
    }
}

$articleIsMovingToSelectedBlog = $id !== null
    && $sourceArticleBlogId > 0
    && $currentBlogId > 0
    && $sourceArticleBlogId !== $currentBlogId;
$storedBlog = $sourceArticleBlogId > 0 ? (getBlogById($sourceArticleBlogId) ?? null) : null;
$selectedBlog = $currentBlog;
$initialMoveTaxonomyState = [
    'matched_category_id' => null,
    'matched_tag_ids' => [],
    'missing_category_name' => '',
    'missing_tags' => [],
];
$initialCanCreateTargetTaxonomies = false;
$canCreateTaxonomiesAnywhere = canCurrentUserManageAnyBlogTaxonomies();
if ($articleIsMovingToSelectedBlog) {
    $selectedBlogOptions = $blogFormOptions[$currentBlogId] ?? [
        'categories' => [],
        'tags' => [],
        'can_manage_taxonomies' => false,
    ];
    $initialMoveTaxonomyState = resolveArticleMoveTaxonomyState(
        $sourceCategoryName,
        $sourceTagDetails,
        $selectedBlogOptions['categories'],
        $selectedBlogOptions['tags']
    );
    $initialCanCreateTargetTaxonomies = (bool)$selectedBlogOptions['can_manage_taxonomies'];
}

$initialCategorySelectId = $articleIsMovingToSelectedBlog
    ? (int)$initialMoveTaxonomyState['matched_category_id']
    : (int)($article['category_id'] ?? 0);
if ($initialCategorySelectId <= 0) {
    $initialCategorySelectId = 0;
}
$initialTagIds = $articleIsMovingToSelectedBlog
    ? array_values(array_map('intval', $initialMoveTaxonomyState['matched_tag_ids']))
    : $articleTagIds;
$initialMissingCategoryName = trim((string)$initialMoveTaxonomyState['missing_category_name']);
$initialMissingTagNames = array_values(array_filter(array_map(
    static fn (array $tag): string => trim((string)$tag['name']),
    $initialMoveTaxonomyState['missing_tags']
)));

$defaultCommentsEnabled = $article
    ? (int)($article['comments_enabled'] ?? 1)
    : (int)($currentBlog['comments_default'] ?? 1);

$useWysiwyg = getSetting('content_editor', 'html') === 'wysiwyg';
$err = trim($_GET['err'] ?? '');
$fieldErrorMap = [
    'required' => ['title', 'content'],
    'slug' => ['slug'],
    'publish_at' => ['publish_at'],
    'unpublish_at' => ['unpublish_at'],
    'publish_range' => ['publish_at', 'unpublish_at'],
    'category_target' => ['category_id'],
    'tags_target' => ['tags'],
    'related_articles_target' => ['related_article_ids'],
    'series_target' => ['series_ids'],
    'missing_category_action' => ['missing_category_action'],
    'missing_tags_action' => ['missing_tags_action'],
    'image_upload' => ['image'],
];
$blogPublishAtErrorMessage = 'Plánované publikování musí být platné datum a čas. Vyberte hodnotu v poli datum a čas nebo pole nechte prázdné pro okamžité zveřejnění.';
$blogUnpublishAtErrorMessage = 'Plánované zrušení publikace musí být platné datum a čas. Vyberte hodnotu v poli datum a čas nebo pole nechte prázdné.';
$blogPublishRangeErrorMessage = 'Plánované zrušení publikace musí být později než plánované publikování. Upravte jeden z časů nebo zrušení publikace nechte prázdné.';
$fieldErrorMessages = [
    'title' => 'Vyplňte prosím název článku.',
    'content' => 'Vyplňte prosím obsah článku.',
    'slug' => 'Slug článku je povinný a musí být unikátní.',
    'publish_at' => $blogPublishAtErrorMessage,
    'unpublish_at' => $blogUnpublishAtErrorMessage,
    'publish_range' => $blogPublishRangeErrorMessage,
    'category_target' => 'Vybraná kategorie nepatří do cílového blogu.',
    'tags_target' => 'Vybrané štítky nepatří do cílového blogu.',
    'related_articles_target' => 'Vybrané související články nepatří do cílového blogu.',
    'series_target' => 'Vybraná série článků nepatří do cílového blogu.',
    'missing_category_action' => 'Chybějící kategorii v cílovém blogu může vytvořit jen správce taxonomií tohoto blogu.',
    'missing_tags_action' => 'Chybějící štítky v cílovém blogu může vytvořit jen správce taxonomií tohoto blogu.',
    'image_upload' => 'Náhledový obrázek se nepodařilo uložit. Použijte JPEG, PNG, GIF nebo WebP.',
];
$publishAtInput = '';
if (!empty($article['publish_at'])) {
    $publishAtInput = date('Y-m-d\TH:i', strtotime((string)$article['publish_at']));
}

$pageTitle = $article ? 'Upravit článek' : 'Přidat článek';
if (isMultiBlog() && $currentBlog) {
    $pageTitle .= ' – ' . (string)$currentBlog['name'];
}
adminHeader($pageTitle);
?>

<?php if ($contentLockWarning !== null): ?>
  <div role="alert" class="admin-warning-box">
    <strong>Upozornění:</strong>
    Tento článek právě upravuje <?= h((string)$contentLockWarning['locked_by']) ?>
    (od <?= h(date('H:i', strtotime((string)$contentLockWarning['locked_at']))) ?>).
    Vaše změny mohou přepsat jejich práci.
  </div>
<?php endif; ?>

<p class="button-row button-row--start">
  <a href="<?= h($articleListUrl) ?>"><span aria-hidden="true">←</span> Zpět na články</a>
  <?php if (isMultiBlog() && $currentBlog && canCurrentUserManageBlogTaxonomies($currentBlogId)): ?>
    <a id="blog-link-categories" href="<?= h($blogCategoriesUrl) ?>">Kategorie blogu</a>
    <a id="blog-link-tags" href="<?= h($blogTagsUrl) ?>">Štítky blogu</a>
  <?php endif; ?>
  <?php if ($currentBlog): ?>
    <a id="blog-link-series" href="<?= BASE_URL ?>/admin/blog_series.php?blog_id=<?= $currentBlogId ?>">Série článků</a>
  <?php endif; ?>
  <?php if (isMultiBlog() && $currentBlog): ?>
    <a id="blog-link-public" href="<?= h($blogPublicUrl) ?>" target="_blank" rel="noopener noreferrer">Zobrazit blog na webu<?= newWindowLinkSrOnlySuffix() ?></a>
    <a id="blog-link-feed" href="<?= h($blogFeedUrl) ?>" target="_blank" rel="noopener noreferrer">RSS feed blogu<?= newWindowLinkSrOnlySuffix() ?></a>
  <?php endif; ?>
</p>

<?php if (isMultiBlog() && $currentBlog): ?>
  <p class="field-help" id="blog-form-context">
    <span id="blog-saved-context">
      Uložený blog článku:
      <strong id="blog-saved-context-name"><?= h((string)($storedBlog['name'] ?? $currentBlog['name'])) ?></strong>.
    </span>
    <span id="blog-target-context"<?= $articleIsMovingToSelectedBlog ? '' : ' hidden' ?>>
      Po uložení bude článek přesunut do blogu
      <strong id="blog-target-context-name"><?= h((string)($selectedBlog['name'] ?? $currentBlog['name'])) ?></strong>.
    </span>
    <span id="blog-form-context-note">Dostupné kategorie, štítky a odkazy nahoře se přepínají podle právě vybraného cílového blogu.</span>
  </p>
<?php endif; ?>

<?php if ($article): ?>
  <p>
    <a href="revisions.php?type=article&amp;id=<?= (int)$article['id'] ?>">Historie revizí</a>
    ·
    <form action="convert_content.php" method="post" class="admin-inline-form">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="direction" value="article_to_page">
      <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
      <button type="submit" class="btn"
              data-confirm="Převést článek na statickou stránku? Článek bude smazán a nahrazen stránkou.">Převést na stránku</button>
    </form>
  </p>
<?php endif; ?>

<?php if ($err === 'slug'): ?>
  <p role="alert" class="error" id="form-error">Slug článku je povinný a musí být unikátní.</p>
<?php elseif ($err === 'required'): ?>
  <p role="alert" class="error" id="form-error">Vyplňte prosím název článku i jeho obsah.</p>
<?php elseif ($err === 'publish_at'): ?>
  <p role="alert" class="error" id="form-error"><?= h($fieldErrorMessages['publish_at']) ?></p>
<?php elseif ($err === 'unpublish_at'): ?>
  <p role="alert" class="error" id="form-error"><?= h($fieldErrorMessages['unpublish_at']) ?></p>
<?php elseif ($err === 'publish_range'): ?>
  <p role="alert" class="error" id="form-error"><?= h($fieldErrorMessages['publish_range']) ?></p>
<?php elseif ($err === 'category_target'): ?>
  <p role="alert" class="error" id="form-error">Vybraná kategorie nepatří do cílového blogu.</p>
<?php elseif ($err === 'tags_target'): ?>
  <p role="alert" class="error" id="form-error">Vybrané štítky nepatří do cílového blogu.</p>
<?php elseif ($err === 'related_articles_target'): ?>
  <p role="alert" class="error" id="form-error">Vybrané související články nepatří do cílového blogu.</p>
<?php elseif ($err === 'series_target'): ?>
  <p role="alert" class="error" id="form-error">Vybraná série článků nepatří do cílového blogu.</p>
<?php elseif ($err === 'missing_category_action'): ?>
  <p role="alert" class="error" id="form-error">Chybějící kategorii v cílovém blogu může vytvořit jen správce taxonomií tohoto blogu.</p>
<?php elseif ($err === 'missing_tags_action'): ?>
  <p role="alert" class="error" id="form-error">Chybějící štítky v cílovém blogu může vytvořit jen správce taxonomií tohoto blogu.</p>
<?php elseif ($err === 'image_upload'): ?>
  <p role="alert" class="error" id="form-error">Náhledový obrázek se nepodařilo uložit. Použijte JPEG, PNG, GIF nebo WebP.</p>
<?php endif; ?>

<form method="post" action="blog_save.php" enctype="multipart/form-data" novalidate<?= $err !== '' ? ' aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="redirect" id="blog-redirect" value="<?= h($articleListUrl) ?>">
  <?php if ($article): ?>
    <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
  <?php endif; ?>

  <?php if (isMultiBlog()): ?>
    <label for="blog_id">Blog</label>
    <select id="blog_id" name="blog_id" aria-describedby="blog-id-help blog-form-context">
      <?php foreach ($allBlogs as $b): ?>
        <option value="<?= (int)$b['id'] ?>"<?= (int)$b['id'] === $currentBlogId ? ' selected' : '' ?>><?= h((string)$b['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <small id="blog-id-help" class="field-help">Po změně blogu se kategorie a štítky upraví rovnou ve formuláři bez obnovení stránky.</small>
  <?php else: ?>
    <input type="hidden" name="blog_id" value="<?= $currentBlogId ?>">
  <?php endif; ?>
  <input type="hidden" name="category_selection_mode" id="category-selection-mode" value="<?= $articleIsMovingToSelectedBlog ? 'auto' : 'manual' ?>">
  <input type="hidden" name="tag_selection_mode" id="tag-selection-mode" value="<?= $articleIsMovingToSelectedBlog ? 'auto' : 'manual' ?>">

  <?php if ($article && !empty($article['author_id'])): ?>
    <?php
    try {
        $authorStmt = $pdo->prepare("SELECT first_name, last_name, nickname, email FROM cms_users WHERE id = ?");
        $authorStmt->execute([(int)$article['author_id']]);
        $authorRow = $authorStmt->fetch();
        if ($authorRow) {
            $authorName = $authorRow['nickname'] !== '' ? $authorRow['nickname'] : trim($authorRow['first_name'] . ' ' . $authorRow['last_name']);
            if ($authorName === '') {
                $authorName = $authorRow['email'];
            }
        } else {
            $authorName = '–';
        }
    } catch (\PDOException $e) {
        $authorName = '–';
    }
?>
    <p class="admin-description admin-description--muted admin-stack-sm">
      Autor: <strong><?= h($authorName) ?></strong>
    </p>
  <?php elseif (!$article): ?>
    <p class="admin-description admin-description--muted admin-stack-sm">
      Autor: <strong><?= h(currentUserDisplayName()) ?></strong>
    </p>
  <?php endif; ?>

  <fieldset>
    <legend>Základní údaje článku</legend>

    <label for="title">Titulek <span aria-hidden="true">*</span></label>
    <input type="text" id="title" name="title" required aria-required="true" maxlength="255"
           <?= adminFieldAttributes('title', $err, $fieldErrorMap) ?>
           value="<?= h($article['title'] ?? '') ?>">
    <?php adminRenderFieldError('title', $err, $fieldErrorMap, $fieldErrorMessages['title']); ?>

    <label for="slug">Slug (URL článku) <span aria-hidden="true">*</span></label>
    <input type="text" id="slug" name="slug" required aria-required="true" maxlength="255" pattern="[a-z0-9\-]+"
           <?= adminFieldAttributes('slug', $err, $fieldErrorMap, ['blog-slug-help']) ?>
           value="<?= h($article['slug'] ?? '') ?>">
    <small id="blog-slug-help" class="field-help">Adresa se vyplní automaticky, dokud ji neupravíte ručně. Použijte malá písmena, číslice a pomlčky.</small>
    <?php adminRenderFieldError('slug', $err, $fieldErrorMap, $fieldErrorMessages['slug']); ?>

    <label for="category_id">Kategorie</label>
    <select id="category_id" name="category_id"
            <?= adminFieldAttributes('category_id', $err, $fieldErrorMap, isMultiBlog() ? ['blog-category-help', 'blog-taxonomy-transfer-help'] : [], 'blog-category-error') ?>>
      <option value="">– bez kategorie –</option>
      <?= renderBlogFormCategoryOptions($blogFormCatTree, 0, 0, $initialCategorySelectId) ?>
    </select>
    <?php if (isMultiBlog()): ?>
      <small id="blog-category-help" class="field-help">Nabídka kategorií odpovídá právě vybranému blogu.</small>
      <small id="blog-taxonomy-transfer-help" class="field-help">Při změně blogu editor předvyplní stejně pojmenovanou kategorii a odpovídající štítky, pokud v cílovém blogu existují. Tady nahoře vždy vybíráte už existující taxonomie cílového blogu.</small>
    <?php endif; ?>
    <?php adminRenderFieldError('category_id', $err, $fieldErrorMap, $fieldErrorMessages['category_target']); ?>
    <?php if (isMultiBlog() && $article): ?>
      <div id="blog-missing-category-group" class="blog-form-missing-taxonomy"<?= ($articleIsMovingToSelectedBlog && $initialMissingCategoryName !== '') ? '' : ' hidden' ?>>
        <p id="blog-missing-category-description" class="field-help">
          <?php if ($articleIsMovingToSelectedBlog && $initialMissingCategoryName !== ''): ?>
            Původní kategorie článku „<?= h($initialMissingCategoryName) ?>“ v cílovém blogu neexistuje.
          <?php endif; ?>
        </p>
        <div>
          <input type="radio" id="missing-category-action-drop" name="missing_category_action" value="drop"
                 <?= adminFieldAttributes('missing_category_action', $err, $fieldErrorMap, ['blog-missing-category-description', 'blog-missing-category-help']) ?>
                 <?= $err === 'missing_category_action' && $initialCanCreateTargetTaxonomies ? '' : ' checked' ?>>
          <label for="missing-category-action-drop" class="admin-checkbox-label">Bez kategorie</label>
        </div>
        <?php if ($canCreateTaxonomiesAnywhere): ?>
          <div id="blog-missing-category-create-option"<?= $initialCanCreateTargetTaxonomies ? '' : ' hidden' ?>>
            <input type="radio" id="missing-category-action-create" name="missing_category_action" value="create"
                   <?= adminFieldAttributes('missing_category_action', $err, $fieldErrorMap, ['blog-missing-category-description', 'blog-missing-category-help']) ?>
                   <?= $err === 'missing_category_action' && $initialCanCreateTargetTaxonomies ? ' checked' : '' ?>>
            <label for="missing-category-action-create" class="admin-checkbox-label">Vytvořit chybějící kategorii v cílovém blogu</label>
          </div>
        <?php endif; ?>
        <small id="blog-missing-category-help" class="field-help">Tato volba řeší jen původní kategorii článku, která v cílovém blogu zatím neexistuje.</small>
        <?php adminRenderFieldError('missing_category_action', $err, $fieldErrorMap, $fieldErrorMessages['missing_category_action']); ?>
      </div>
    <?php endif; ?>
  </fieldset>

  <fieldset id="article-tags-fieldset" class="blog-form-fieldset"<?= empty($allTags) ? ' hidden' : '' ?><?= isMultiBlog() ? ' aria-describedby="blog-tags-help blog-taxonomy-transfer-help' . ($err === 'tags_target' ? ' blog-tags-error' : '') . '"' : ($err === 'tags_target' ? ' aria-describedby="blog-tags-error"' : '') ?>>
    <legend>Štítky článku</legend>
    <div id="article-tags-options">
      <?php foreach ($allTags as $tag): ?>
        <label class="blog-form-tag-label">
          <input type="checkbox" name="tags[]" value="<?= (int)$tag['id'] ?>"
                 <?= in_array((int)$tag['id'], $initialTagIds, true) ? 'checked' : '' ?>>
          <?= h($tag['name']) ?>
        </label>
      <?php endforeach; ?>
    </div>
    <?php if (isMultiBlog()): ?>
      <small id="blog-tags-help" class="field-help">Dostupné štítky se mění podle vybraného blogu.</small>
    <?php endif; ?>
    <?php if ($err === 'tags_target'): ?>
      <small id="blog-tags-error" class="field-help field-error"><?= h($fieldErrorMessages['tags_target']) ?></small>
    <?php endif; ?>
    <?php if (isMultiBlog() && $article): ?>
      <div id="blog-missing-tags-group" class="blog-form-missing-taxonomy"<?= ($articleIsMovingToSelectedBlog && $initialMissingTagNames !== []) ? '' : ' hidden' ?>>
        <p id="blog-missing-tags-description" class="field-help">
          <?php if ($articleIsMovingToSelectedBlog && $initialMissingTagNames !== []): ?>
            V cílovém blogu chybí původní štítky: <?= h(implode(', ', $initialMissingTagNames)) ?>.
          <?php endif; ?>
        </p>
        <div>
          <input type="radio" id="missing-tags-action-drop" name="missing_tags_action" value="drop"
                 <?= adminFieldAttributes('missing_tags_action', $err, $fieldErrorMap, ['blog-missing-tags-description', 'blog-missing-tags-help']) ?>
                 <?= $err === 'missing_tags_action' && $initialCanCreateTargetTaxonomies ? '' : ' checked' ?>>
          <label for="missing-tags-action-drop" class="admin-checkbox-label">Bez chybějících štítků</label>
        </div>
        <?php if ($canCreateTaxonomiesAnywhere): ?>
          <div id="blog-missing-tags-create-option"<?= $initialCanCreateTargetTaxonomies ? '' : ' hidden' ?>>
            <input type="radio" id="missing-tags-action-create" name="missing_tags_action" value="create"
                   <?= adminFieldAttributes('missing_tags_action', $err, $fieldErrorMap, ['blog-missing-tags-description', 'blog-missing-tags-help']) ?>
                   <?= $err === 'missing_tags_action' && $initialCanCreateTargetTaxonomies ? ' checked' : '' ?>>
            <label for="missing-tags-action-create" class="admin-checkbox-label">Vytvořit chybějící štítky v cílovém blogu</label>
          </div>
        <?php endif; ?>
        <small id="blog-missing-tags-help" class="field-help">Tato volba se týká jen původních štítků, které v cílovém blogu zatím neexistují.</small>
        <?php adminRenderFieldError('missing_tags_action', $err, $fieldErrorMap, $fieldErrorMessages['missing_tags_action']); ?>
      </div>
    <?php endif; ?>
  </fieldset>

  <fieldset id="article-series-fieldset" class="blog-form-fieldset" aria-describedby="blog-series-help blog-series-empty<?= $err === 'series_target' ? ' blog-series-error' : '' ?>">
    <legend>Série článků</legend>
    <label for="series_ids">Zařazení do série</label>
    <select id="series_ids" name="series_ids[]" multiple size="<?= min(8, max(3, count($blogSeriesOptions))) ?>"
            <?= $blogSeriesOptions === [] ? 'disabled ' : '' ?>
            <?= adminFieldAttributes('series_ids', $err, $fieldErrorMap, ['blog-series-help', 'blog-series-empty'], 'blog-series-error') ?>>
      <?php foreach ($blogSeriesOptions as $seriesOption): ?>
        <?php
          $seriesLabel = (string)$seriesOption['title'];
          if ((int)$seriesOption['is_active'] !== 1) {
              $seriesLabel .= ' - skrytá';
          }
          ?>
        <option value="<?= (int)$seriesOption['id'] ?>"<?= in_array((int)$seriesOption['id'], $articleSeriesIds, true) ? ' selected' : '' ?>>
          <?= h($seriesLabel) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <small id="blog-series-help" class="field-help">Vyberte série stejného blogu, do kterých článek patří. Pořadí článků v sérii upravíte na stránce <a id="blog-series-manage-link" href="<?= BASE_URL ?>/admin/blog_series.php?blog_id=<?= $currentBlogId ?>">Série článků</a>.</small>
    <small id="blog-series-empty" class="field-help"<?= $blogSeriesOptions === [] ? '' : ' hidden' ?>>V tomto blogu zatím nejsou vytvořené žádné série článků.</small>
    <?php adminRenderFieldError('series_ids', $err, $fieldErrorMap, $fieldErrorMessages['series_target'], 'blog-series-error'); ?>
  </fieldset>

  <fieldset id="article-related-fieldset" class="blog-form-fieldset" aria-describedby="blog-related-help blog-related-empty<?= $err === 'related_articles_target' ? ' blog-related-error' : '' ?>">
    <legend>Související články</legend>
    <label for="related_article_ids">Ruční výběr souvisejících článků</label>
    <select id="related_article_ids" name="related_article_ids[]" multiple size="<?= min(8, max(3, count($relatedArticleOptions))) ?>"
            <?= $relatedArticleOptions === [] ? 'disabled ' : '' ?>
            <?= adminFieldAttributes('related_article_ids', $err, $fieldErrorMap, ['blog-related-help', 'blog-related-empty'], 'blog-related-error') ?>>
      <?php foreach ($relatedArticleOptions as $relatedOption): ?>
        <?php
          $relatedDate = trim($relatedOption['display_date']);
          $relatedLabel = (string)$relatedOption['title'];
          if ($relatedDate !== '') {
              $relatedTimestamp = strtotime($relatedDate);
              if ($relatedTimestamp !== false) {
                  $relatedLabel .= ' - ' . date('j. n. Y', $relatedTimestamp);
              }
          }
          ?>
        <option value="<?= (int)$relatedOption['id'] ?>"<?= in_array((int)$relatedOption['id'], $relatedArticleIds, true) ? ' selected' : '' ?>>
          <?= h($relatedLabel) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <small id="blog-related-help" class="field-help">Vyberte publikované články ze stejného blogu, které se mají na veřejném detailu ukázat v bloku Související články. Pokud nic nevyberete, Kora CMS použije automatické doporučení podle kategorie, štítků a novosti.</small>
    <small id="blog-related-empty" class="field-help"<?= $relatedArticleOptions === [] ? '' : ' hidden' ?>>V tomto blogu zatím není jiný publikovaný článek, který by šel ručně doporučit.</small>
    <?php adminRenderFieldError('related_article_ids', $err, $fieldErrorMap, $fieldErrorMessages['related_articles_target'], 'blog-related-error'); ?>
  </fieldset>

  <fieldset>
    <legend>Text článku</legend>

    <label for="perex">Perex (krátký úvod)</label>
    <textarea id="perex" name="perex" rows="3"><?= h($article['perex'] ?? '') ?></textarea>

    <label for="content">Text článku <span aria-hidden="true">*</span></label>
    <textarea id="content" name="content" rows="15" required aria-required="true"
              <?= !$useWysiwyg
                ? adminFieldAttributes('content', $err, $fieldErrorMap, ['blog-content-help'])
                : adminFieldAttributes('content', $err, $fieldErrorMap) ?>><?= h($article['content'] ?? '') ?></textarea>
    <?php if (!$useWysiwyg): ?><small id="blog-content-help" class="field-help"><?= adminHtmlSnippetSupportMarkup() ?></small><?php endif; ?>
    <?php adminRenderFieldError('content', $err, $fieldErrorMap, $fieldErrorMessages['content']); ?>
    <?php if (!$useWysiwyg): ?>
      <?php renderAdminContentReferencePicker('content'); ?>
    <?php endif; ?>

    <label for="image">Náhledový obrázek</label>
    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp"
           <?= adminFieldAttributes('image', $err, $fieldErrorMap, [!empty($article['image_file']) ? 'blog-image-current' : 'blog-image-help'], 'blog-image-error') ?>>
    <?php if (!empty($article['image_file'])): ?>
      <small id="blog-image-current" class="field-help">Aktuální obrázek: <a href="<?= BASE_URL ?>/uploads/articles/<?= rawurlencode((string)$article['image_file']) ?>"
             target="_blank" rel="noopener noreferrer"><?= h((string)$article['image_file']) ?><?= newWindowLinkSrOnlySuffix() ?></a>.</small>
    <?php else: ?>
      <small id="blog-image-help" class="field-help">Volitelné. Hodí se pro úvodní náhled článku.</small>
    <?php endif; ?>
    <?php adminRenderFieldError('image', $err, $fieldErrorMap, $fieldErrorMessages['image_upload'], 'blog-image-error'); ?>
    <?php if (!empty($article['image_file'])): ?>
      <label class="blog-form-checkbox-row">
        <input type="checkbox" name="image_delete" value="1"> Smazat stávající obrázek
      </label>
    <?php endif; ?>

    <label for="publish_at">Plánované publikování</label>
    <input type="datetime-local" id="publish_at" name="publish_at"
           <?= adminFieldAttributes('publish_at', $err, $fieldErrorMap, ['blog-publish-at-help'], 'blog-publish-at-error') ?>
           class="admin-input-auto" value="<?= h($publishAtInput) ?>">
    <small id="blog-publish-at-help" class="field-help">Nechte prázdné, pokud se má článek zveřejnit hned.</small>
    <?php if (adminFieldHasError('publish_at', $err, $fieldErrorMap)): ?>
      <small id="blog-publish-at-error" class="field-help field-error">
        <?= h($err === 'publish_range' ? $fieldErrorMessages['publish_range'] : $fieldErrorMessages['publish_at']) ?>
      </small>
    <?php endif; ?>

    <label for="unpublish_at">Plánované zrušení publikace</label>
    <input type="datetime-local" id="unpublish_at" name="unpublish_at"
           <?= adminFieldAttributes('unpublish_at', $err, $fieldErrorMap, ['blog-unpublish-at-help'], 'blog-unpublish-at-error') ?>
           class="admin-input-auto" value="<?= h(!empty($article['unpublish_at']) ? date('Y-m-d\TH:i', strtotime((string)$article['unpublish_at'])) : '') ?>">
    <small id="blog-unpublish-at-help" class="field-help">Volitelné. Článek se v zadaný čas automaticky skryje z veřejného webu.</small>
    <?php if (adminFieldHasError('unpublish_at', $err, $fieldErrorMap)): ?>
      <small id="blog-unpublish-at-error" class="field-help field-error">
        <?= h($err === 'publish_range' ? $fieldErrorMessages['publish_range'] : $fieldErrorMessages['unpublish_at']) ?>
      </small>
    <?php endif; ?>
  </fieldset>

  <fieldset class="blog-form-fieldset">
    <legend>Komentáře</legend>
    <div>
      <input type="checkbox" id="comments_enabled" name="comments_enabled" value="1" aria-describedby="blog-comments-help"
             <?= $defaultCommentsEnabled === 1 ? 'checked' : '' ?>>
      <label for="comments_enabled" class="admin-checkbox-label">
        Povolit komentáře u tohoto článku
      </label>
    </div>
    <small id="blog-comments-help" class="field-help">Globální pravidla moderace nastavíte v základním nastavení webu.</small>
  </fieldset>

  <fieldset class="blog-form-fieldset">
    <legend>Zvýraznění v blogu</legend>
    <div>
      <input type="checkbox" id="is_featured_in_blog" name="is_featured_in_blog" value="1" aria-describedby="blog-featured-help"
             <?= (int)($article['is_featured_in_blog'] ?? 0) === 1 ? 'checked' : '' ?>>
      <label for="is_featured_in_blog" class="admin-checkbox-label">
        Zvýraznit jako doporučený článek tohoto blogu
      </label>
    </div>
    <small id="blog-featured-help" class="field-help">Na hlavním indexu blogu se bez aktivních filtrů zobrazí jako doporučený článek jen jedna položka. Pokud tuto volbu zapnete zde, předchozí doporučený článek stejného blogu se automaticky vypne.</small>
  </fieldset>

  <fieldset class="blog-form-fieldset blog-form-fieldset--seo">
    <legend>Vyhledávače a sdílení</legend>
    <small id="blog-seo-help" class="field-help field-help--flush">Nepovinné. Ponechte prázdné pro automatické hodnoty.</small>
    <label for="meta_title">Meta titulek</label>
    <input type="text" id="meta_title" name="meta_title" maxlength="160" aria-describedby="blog-seo-help"
           value="<?= h($article['meta_title'] ?? '') ?>">

    <label for="meta_description">Meta popis</label>
    <textarea id="meta_description" name="meta_description" rows="2" aria-describedby="blog-seo-help"
              class="admin-textarea-compact"><?= h($article['meta_description'] ?? '') ?></textarea>
  </fieldset>

  <fieldset class="blog-form-fieldset">
    <legend>Interní poznámka</legend>
    <label for="admin_note" class="visually-hidden">Interní poznámka</label>
    <textarea id="admin_note" name="admin_note" rows="2" aria-describedby="admin-note-help"
              class="admin-textarea-compact"><?= h($article['admin_note'] ?? '') ?></textarea>
    <small id="admin-note-help" class="field-help">Viditelná jen v administraci. Na veřejném webu se nezobrazuje.</small>
  </fieldset>

  <fieldset class="blog-form-fieldset">
    <legend>Stav článku</legend>
    <label for="article_status" class="visually-hidden">Stav článku</label>
    <select id="article_status" name="article_status" aria-describedby="article-status-help">
      <option value="draft"<?= ($article['status'] ?? ($article ? '' : 'draft')) === 'draft' ? ' selected' : '' ?>>Koncept</option>
      <?php if (currentUserHasCapability('blog_approve')): ?>
        <option value="published"<?= ($article['status'] ?? '') === 'published' ? ' selected' : '' ?>>Publikováno</option>
      <?php endif; ?>
      <option value="pending"<?= ($article['status'] ?? '') === 'pending' ? ' selected' : '' ?>>Čeká na schválení</option>
    </select>
    <small id="article-status-help" class="field-help">Koncept je viditelný jen v administraci. Čeká na schválení upozorní správce.</small>
  </fieldset>

  <div class="button-row blog-form-actions">
    <button type="submit"><?= $article ? 'Uložit změny' : 'Přidat článek' ?></button>
    <a href="<?= h($articleListUrl) ?>">Zrušit</a>
    <?php if ($article && !empty($article['preview_token'])): ?>
      <a href="<?= h(articlePreviewPath($article)) ?>" target="_blank" rel="noopener noreferrer">Náhled<?= newWindowLinkSrOnlySuffix() ?></a>
    <?php elseif ($article): ?>
      <small class="blog-form-preview-note">(Uložte pro aktivaci odkazu „Náhled“)</small>
    <?php endif; ?>
  </div>
</form>

<script nonce="<?= cspNonce() ?>">
(function () {
    const titleInput = document.getElementById('title');
    const slugInput = document.getElementById('slug');
    const blogSelect = document.getElementById('blog_id');
    const redirectInput = document.getElementById('blog-redirect');
    const targetContext = document.getElementById('blog-target-context');
    const targetContextName = document.getElementById('blog-target-context-name');
    const categoryLink = document.getElementById('blog-link-categories');
    const tagLink = document.getElementById('blog-link-tags');
    const seriesLink = document.getElementById('blog-link-series');
    const seriesManageLink = document.getElementById('blog-series-manage-link');
    const publicLink = document.getElementById('blog-link-public');
    const feedLink = document.getElementById('blog-link-feed');
    const categorySelect = document.getElementById('category_id');
    const tagsFieldset = document.getElementById('article-tags-fieldset');
    const tagsContainer = document.getElementById('article-tags-options');
    const missingCategoryGroup = document.getElementById('blog-missing-category-group');
    const missingCategoryDescription = document.getElementById('blog-missing-category-description');
    const missingCategoryCreateOption = document.getElementById('blog-missing-category-create-option');
    const missingTagsGroup = document.getElementById('blog-missing-tags-group');
    const missingTagsDescription = document.getElementById('blog-missing-tags-description');
    const missingTagsCreateOption = document.getElementById('blog-missing-tags-create-option');
    const missingCategoryActionInputs = Array.from(document.querySelectorAll('input[name="missing_category_action"]'));
    const missingTagsActionInputs = Array.from(document.querySelectorAll('input[name="missing_tags_action"]'));
    const categorySelectionModeInput = document.getElementById('category-selection-mode');
    const tagSelectionModeInput = document.getElementById('tag-selection-mode');
    const seriesSelect = document.getElementById('series_ids');
    const seriesEmpty = document.getElementById('blog-series-empty');
    const relatedSelect = document.getElementById('related_article_ids');
    const relatedEmpty = document.getElementById('blog-related-empty');
    const commentsCheckbox = document.getElementById('comments_enabled');
    const noCategoryLabel = <?= json_encode($noCategoryLabel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const blogOptions = <?= json_encode($blogFormOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const sourceArticleTaxonomy = <?= json_encode([
    'categoryName' => $sourceCategoryName,
    'tags' => array_map(
        static function (array $tag): array {
            return [
                'name' => (string)$tag['name'],
                'slug' => (string)$tag['slug'],
            ];
        },
        $sourceTagDetails
    ),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const blogMeta = <?= json_encode(array_map(static function (array $blogEntry): array {
        return [
            'id' => (int)$blogEntry['id'],
            'name' => (string)$blogEntry['name'],
            'publicUrl' => blogIndexPath($blogEntry),
            'feedUrl' => blogFeedPath($blogEntry),
        ];
    }, $allBlogs), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const blogMetaById = Object.fromEntries(blogMeta.map((blogEntry) => [String(blogEntry.id), blogEntry]));
    const isNewArticle = <?= $article ? 'false' : 'true' ?>;
    const sourceBlogId = <?= (int)$sourceArticleBlogId ?>;
    let slugManual = <?= $article && !empty($article['slug']) ? 'true' : 'false' ?>;
    let commentsTouched = <?= $article ? 'true' : 'false' ?>;
    const rememberedSelections = {
        '<?= (int)($sourceArticleBlogId > 0 ? $sourceArticleBlogId : $currentBlogId) ?>': {
            categoryId: '<?= (int)($article['category_id'] ?? 0) ?>' !== '0' ? '<?= (int)($article['category_id'] ?? 0) ?>' : '',
            tags: <?= json_encode(array_map('intval', $articleTagIds)) ?>,
            categoryMode: 'manual',
            tagsMode: 'manual',
            series: <?= json_encode(array_map('intval', $articleSeriesIds)) ?>,
            relatedArticles: <?= json_encode(array_map('intval', $relatedArticleIds)) ?>,
            missingCategoryAction: 'drop',
            missingTagsAction: 'drop',
        }
    };

    const slugify = (value) => value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    const normalizeTaxonomyName = (value) => String(value || '')
        .trim()
        .replace(/\s+/g, ' ')
        .toLocaleLowerCase('cs-CZ');

    const resolveAutoSelections = (blogId) => {
        const fallback = {
            categoryId: '',
            tags: [],
            categoryMode: 'auto',
            tagsMode: 'auto',
            series: [],
            relatedArticles: [],
            missingCategoryAction: 'drop',
            missingTagsAction: 'drop',
        };
        const targetBlogOptions = blogOptions[blogId] || { categories: [], tags: [] };

        const normalizedSourceCategory = normalizeTaxonomyName(sourceArticleTaxonomy.categoryName || '');
        if (normalizedSourceCategory !== '') {
            const matchingCategory = (targetBlogOptions.categories || []).find((category) => (
                normalizeTaxonomyName(category.name) === normalizedSourceCategory
            ));
            if (matchingCategory) {
                fallback.categoryId = String(matchingCategory.id);
            }
        }

        const resolvedTagIds = [];
        (sourceArticleTaxonomy.tags || []).forEach((sourceTag) => {
            const sourceSlug = String(sourceTag.slug || '').trim();
            const normalizedSourceName = normalizeTaxonomyName(sourceTag.name || '');
            const matchingTag = (targetBlogOptions.tags || []).find((tag) => (
                (sourceSlug !== '' && String(tag.slug || '').trim() === sourceSlug)
                || (normalizedSourceName !== '' && normalizeTaxonomyName(tag.name) === normalizedSourceName)
            ));
            if (matchingTag) {
                const tagId = Number(matchingTag.id);
                if (!resolvedTagIds.includes(tagId)) {
                    resolvedTagIds.push(tagId);
                }
            }
        });
        fallback.tags = resolvedTagIds;

        return fallback;
    };

    const escapeHtml = (value) => String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const relatedArticleLabel = (article) => {
        const title = String(article.title || '');
        const dateValue = String(article.display_date || '').trim();
        if (dateValue === '') {
            return title;
        }

        const date = new Date(dateValue.replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) {
            return title;
        }

        return title + ' - ' + date.toLocaleDateString('cs-CZ');
    };

    const seriesLabel = (series) => {
        const title = String(series.title || '');
        return Number(series.is_active || 0) === 1 ? title : title + ' - skrytá';
    };

    const setRadioValue = (inputs, value, fallbackValue = 'drop') => {
        const normalizedValue = String(value || fallbackValue);
        const resolvedValue = inputs.some((input) => input.value === normalizedValue) ? normalizedValue : fallbackValue;
        inputs.forEach((input) => {
            input.checked = input.value === resolvedValue;
        });
    };

    const selectedRadioValue = (inputs, fallbackValue = 'drop') => {
        const checkedInput = inputs.find((input) => input.checked);
        return checkedInput ? checkedInput.value : fallbackValue;
    };

    const updateMissingTaxonomyChoices = (blogId, state) => {
        if ((!missingCategoryGroup && !missingTagsGroup) || isNewArticle || sourceBlogId <= 0) {
            return;
        }

        const targetBlogOptions = blogOptions[blogId] || { categories: [], tags: [], can_manage_taxonomies: false };
        const canCreateTaxonomies = !!targetBlogOptions.can_manage_taxonomies;
        const sourceBlogChanged = String(blogId) !== String(sourceBlogId);

        const sourceCategoryName = String(sourceArticleTaxonomy.categoryName || '').trim();
        const categoryIsMissing = sourceBlogChanged
            && sourceCategoryName !== ''
            && (state.categoryMode || 'auto') !== 'manual'
            && String(state.categoryId || '') === '';

        const missingTagNames = [];
        if (sourceBlogChanged && (state.tagsMode || 'auto') !== 'manual') {
            (sourceArticleTaxonomy.tags || []).forEach((sourceTag) => {
                const sourceSlug = String(sourceTag.slug || '').trim();
                const normalizedSourceName = normalizeTaxonomyName(sourceTag.name || '');
                const matchingTag = (targetBlogOptions.tags || []).find((tag) => (
                    (sourceSlug !== '' && String(tag.slug || '').trim() === sourceSlug)
                    || (normalizedSourceName !== '' && normalizeTaxonomyName(tag.name) === normalizedSourceName)
                ));
                if (!matchingTag && String(sourceTag.name || '').trim() !== '') {
                    missingTagNames.push(String(sourceTag.name).trim());
                }
            });
        }

        if (missingCategoryGroup) {
            missingCategoryGroup.hidden = !categoryIsMissing;
        }
        if (missingCategoryDescription) {
            missingCategoryDescription.textContent = categoryIsMissing
                ? 'Původní kategorie článku „' + sourceCategoryName + '“ v cílovém blogu neexistuje.'
                : '';
        }
        if (missingCategoryCreateOption) {
            missingCategoryCreateOption.hidden = !categoryIsMissing || !canCreateTaxonomies;
        }
        if (missingCategoryActionInputs.length > 0) {
            setRadioValue(missingCategoryActionInputs, categoryIsMissing ? state.missingCategoryAction : 'drop');
        }

        const uniqueMissingTagNames = Array.from(new Set(missingTagNames));
        const tagsAreMissing = uniqueMissingTagNames.length > 0;
        if (missingTagsGroup) {
            missingTagsGroup.hidden = !tagsAreMissing;
        }
        if (missingTagsDescription) {
            missingTagsDescription.textContent = tagsAreMissing
                ? 'V cílovém blogu chybí původní štítky: ' + uniqueMissingTagNames.join(', ') + '.'
                : '';
        }
        if (missingTagsCreateOption) {
            missingTagsCreateOption.hidden = !tagsAreMissing || !canCreateTaxonomies;
        }
        if (missingTagsActionInputs.length > 0) {
            setRadioValue(missingTagsActionInputs, tagsAreMissing ? state.missingTagsAction : 'drop');
        }

        if (targetContext) {
            targetContext.hidden = !sourceBlogChanged;
        }
    };

    if (categorySelect && categorySelect.options.length > 0 && categorySelect.options[0].value === '') {
        categorySelect.options[0].textContent = noCategoryLabel;
    }

    const rememberCurrentSelections = () => {
        if (!blogSelect || !categorySelect || !tagsContainer) {
            return;
        }

        rememberedSelections[blogSelect.value] = {
            categoryId: categorySelect.value,
            tags: Array.from(tagsContainer.querySelectorAll('input[name="tags[]"]:checked')).map((input) => Number(input.value)),
            categoryMode: categorySelectionModeInput ? categorySelectionModeInput.value : 'manual',
            tagsMode: tagSelectionModeInput ? tagSelectionModeInput.value : 'manual',
            series: seriesSelect
                ? Array.from(seriesSelect.selectedOptions).map((option) => Number(option.value))
                : [],
            relatedArticles: relatedSelect
                ? Array.from(relatedSelect.selectedOptions).map((option) => Number(option.value))
                : [],
            missingCategoryAction: selectedRadioValue(missingCategoryActionInputs),
            missingTagsAction: selectedRadioValue(missingTagsActionInputs),
        };
    };

    const renderBlogOptions = (blogId) => {
        if (!categorySelect || !tagsContainer || !tagsFieldset || !blogOptions[blogId]) {
            return;
        }

        const state = rememberedSelections[blogId] || resolveAutoSelections(blogId);
        const selectedTags = new Set((state.tags || []).map((value) => Number(value)));
        const selectedSeries = new Set((state.series || []).map((value) => Number(value)));
        const selectedRelatedArticles = new Set((state.relatedArticles || []).map((value) => Number(value)));
        const categoryMarkup = [];
        categoryMarkup.push('<option value="">' + noCategoryLabel + '</option>');

        const buildCatTree = (cats) => {
            const tree = {};
            (cats || []).forEach((c) => {
                const pid = c.parent_id != null ? String(c.parent_id) : '0';
                if (!tree[pid]) tree[pid] = [];
                tree[pid].push(c);
            });
            return tree;
        };
        const renderCatOptions = (tree, parentId, depth) => {
            (tree[String(parentId)] || []).forEach((category) => {
                const prefix = depth > 0 ? '\u00A0\u00A0'.repeat(depth) + '-- ' : '';
                const selected = String(category.id) === String(state.categoryId) ? ' selected' : '';
                categoryMarkup.push('<option value="' + String(category.id) + '"' + selected + '>' + prefix + String(category.name) + '</option>');
                renderCatOptions(tree, category.id, depth + 1);
            });
        };
        const catTree = buildCatTree(blogOptions[blogId].categories);
        renderCatOptions(catTree, 0, 0);
        categorySelect.innerHTML = categoryMarkup.join('');
        if (categorySelectionModeInput) {
            categorySelectionModeInput.value = state.categoryMode || 'manual';
        }

        const selectedBlog = blogMetaById[String(blogId)] || null;
        if (targetContextName && selectedBlog) {
            targetContextName.textContent = selectedBlog.name;
        }
        if (targetContext) {
            targetContext.hidden = String(blogId) === String(sourceBlogId);
        }
        if (redirectInput) {
            redirectInput.value = '<?= h(BASE_URL . '/admin/blog.php') ?>' + '?blog=' + encodeURIComponent(String(blogId));
        }
        if (categoryLink) {
            categoryLink.href = '<?= h(BASE_URL . '/admin/blog_cats.php?blog_id=') ?>' + encodeURIComponent(String(blogId));
        }
        if (tagLink) {
            tagLink.href = '<?= h(BASE_URL . '/admin/blog_tags.php?blog_id=') ?>' + encodeURIComponent(String(blogId));
        }
        if (seriesLink) {
            seriesLink.href = '<?= h(BASE_URL . '/admin/blog_series.php?blog_id=') ?>' + encodeURIComponent(String(blogId));
        }
        if (seriesManageLink) {
            seriesManageLink.href = '<?= h(BASE_URL . '/admin/blog_series.php?blog_id=') ?>' + encodeURIComponent(String(blogId));
        }
        if (publicLink && selectedBlog) {
            publicLink.href = selectedBlog.publicUrl;
        }
        if (feedLink && selectedBlog) {
            feedLink.href = selectedBlog.feedUrl;
        }
        if (isNewArticle && commentsCheckbox && !commentsTouched) {
            commentsCheckbox.checked = (blogOptions[blogId].comments_default || 0) === 1;
        }

        if (seriesSelect) {
            const seriesRows = blogOptions[blogId].series || [];
            seriesSelect.disabled = seriesRows.length === 0;
            seriesSelect.size = String(Math.min(8, Math.max(3, seriesRows.length)));
            seriesSelect.innerHTML = seriesRows.map((series) => {
                const selected = selectedSeries.has(Number(series.id)) ? ' selected' : '';
                return '<option value="' + String(series.id) + '"' + selected + '>'
                    + escapeHtml(seriesLabel(series))
                    + '</option>';
            }).join('');
            if (seriesEmpty) {
                seriesEmpty.hidden = seriesRows.length > 0;
            }
        }

        if (relatedSelect) {
            const relatedArticles = blogOptions[blogId].related_articles || [];
            relatedSelect.disabled = relatedArticles.length === 0;
            relatedSelect.size = String(Math.min(8, Math.max(3, relatedArticles.length)));
            relatedSelect.innerHTML = relatedArticles.map((relatedArticle) => {
                const selected = selectedRelatedArticles.has(Number(relatedArticle.id)) ? ' selected' : '';
                return '<option value="' + String(relatedArticle.id) + '"' + selected + '>'
                    + escapeHtml(relatedArticleLabel(relatedArticle))
                    + '</option>';
            }).join('');
            if (relatedEmpty) {
                relatedEmpty.hidden = relatedArticles.length > 0;
            }
        }

        const tags = blogOptions[blogId].tags || [];
        if (tagSelectionModeInput) {
            tagSelectionModeInput.value = state.tagsMode || 'manual';
        }
        if (tags.length === 0) {
            tagsContainer.innerHTML = '';
            tagsFieldset.hidden = true;
            updateMissingTaxonomyChoices(blogId, state);
            return;
        }

        tagsFieldset.hidden = false;
        tagsContainer.innerHTML = tags.map((tag) => {
            const checked = selectedTags.has(Number(tag.id)) ? ' checked' : '';
            return '<label class="blog-form-tag-label">'
                + '<input type="checkbox" name="tags[]" value="' + String(tag.id) + '"' + checked + '>'
                + ' ' + String(tag.name)
                + '</label>';
        }).join('');
        updateMissingTaxonomyChoices(blogId, state);
    };

    slugInput?.addEventListener('input', function () {
        slugManual = this.value.trim() !== '';
    });

    titleInput?.addEventListener('input', function () {
        if (slugManual || !slugInput) {
            return;
        }
        slugInput.value = slugify(this.value);
    });

    if (blogSelect) {
        blogSelect.addEventListener('change', function () {
            rememberCurrentSelections();
            renderBlogOptions(this.value);
        });
        renderBlogOptions(blogSelect.value);
    }

    commentsCheckbox?.addEventListener('change', function () {
        commentsTouched = true;
    });
    categorySelect?.addEventListener('change', function () {
        if (categorySelectionModeInput) {
            categorySelectionModeInput.value = 'manual';
        }
        rememberCurrentSelections();
        updateMissingTaxonomyChoices(blogSelect ? blogSelect.value : String(sourceBlogId), rememberedSelections[blogSelect ? blogSelect.value : String(sourceBlogId)] || {});
    });
    tagsContainer?.addEventListener('change', function () {
        if (tagSelectionModeInput) {
            tagSelectionModeInput.value = 'manual';
        }
        rememberCurrentSelections();
        updateMissingTaxonomyChoices(blogSelect ? blogSelect.value : String(sourceBlogId), rememberedSelections[blogSelect ? blogSelect.value : String(sourceBlogId)] || {});
    });
    missingCategoryActionInputs.forEach((input) => {
        input.addEventListener('change', rememberCurrentSelections);
    });
    missingTagsActionInputs.forEach((input) => {
        input.addEventListener('change', rememberCurrentSelections);
    });
    relatedSelect?.addEventListener('change', rememberCurrentSelections);
    seriesSelect?.addEventListener('change', rememberCurrentSelections);
})();
</script>

<?php if ($useWysiwyg): ?>
<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
<script nonce="<?= cspNonce() ?>" src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script nonce="<?= cspNonce() ?>">
(function () {
    const textarea = document.getElementById('content');
    const wrapper = document.createElement('div');
    wrapper.className = 'blog-form-wysiwyg-wrapper';
    textarea.parentNode.insertBefore(wrapper, textarea);
    textarea.hidden = true;

    const quill = new Quill(wrapper, {
        theme: 'snow',
        modules: { toolbar: [
            [{ header: [2, 3, 4, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['blockquote', 'code-block'],
            ['link', 'image'],
            ['clean']
        ] }
    });

    quill.root.innerHTML = textarea.value;

    textarea.closest('form')?.addEventListener('submit', function () {
        textarea.value = quill.root.innerHTML;
    });
})();
</script>
<?php endif; ?>

<?php if ($article && $id !== null): ?>
<?php adminRenderContentLockRefreshScript('article', $id); ?>
<?php endif; ?>

<?php adminFooter(); ?>
