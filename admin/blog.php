<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');
requireModuleEnabled('blog');

$pdo = db_connect();
$q = trim($_GET['q'] ?? '');
$cat = trim($_GET['cat'] ?? '');
$blogFilter = trim($_GET['blog'] ?? '');
$message = trim($_GET['msg'] ?? '');
$transferFlash = $_SESSION['blog_transfer_flash'] ?? null;
unset($_SESSION['blog_transfer_flash']);
$hasBlogs = hasAnyBlogs();
$allBlogs = $hasBlogs ? getAllBlogs() : [];
$availableBlogs = $hasBlogs
    ? (canManageOwnBlogOnly() ? getWritableBlogsForUser() : $allBlogs)
    : [];
$availableBlogIds = array_map(static fn (array $blog): int => (int)$blog['id'], $availableBlogs);
if ($blogFilter !== '' && ctype_digit($blogFilter) && !in_array((int)$blogFilter, $availableBlogIds, true)) {
    $blogFilter = '';
}
$multiBlog = count($availableBlogs) > 1;
$activeBlog = ($availableBlogs !== [] && $blogFilter !== '' && ctype_digit($blogFilter)) ? getBlogById((int)$blogFilter) : null;
$params = [];
$whereParts = ['a.deleted_at IS NULL'];

if ($q !== '') {
    $whereParts[] = "(a.title LIKE ? OR a.perex LIKE ?)";
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

if ($cat === 'none') {
    $whereParts[] = 'a.category_id IS NULL';
} elseif ($cat !== '' && ctype_digit($cat)) {
    $whereParts[] = 'a.category_id = ?';
    $params[] = (int)$cat;
}

if ($blogFilter !== '' && ctype_digit($blogFilter)) {
    $whereParts[] = 'a.blog_id = ?';
    $params[] = (int)$blogFilter;
}

if (canManageOwnBlogOnly()) {
    $whereParts[] = 'a.author_id = ?';
    $params[] = currentUserId();
    if (blogMembershipsEnabled()) {
        if ($availableBlogIds === []) {
            $whereParts[] = '1 = 0';
        } else {
            $whereParts[] = 'a.blog_id IN (' . implode(',', array_fill(0, count($availableBlogIds), '?')) . ')';
            foreach ($availableBlogIds as $availableBlogId) {
                $params[] = $availableBlogId;
            }
        }
    }
}

$whereSql = 'WHERE ' . implode(' AND ', $whereParts);

$categories = [];
if ($hasBlogs) {
    $catQuery = "SELECT id, name FROM cms_categories";
    $catParams = [];
    if ($blogFilter !== '' && ctype_digit($blogFilter)) {
        $catQuery .= " WHERE blog_id = ?";
        $catParams[] = (int)$blogFilter;
    } elseif (canManageOwnBlogOnly() && blogMembershipsEnabled()) {
        if ($availableBlogIds === []) {
            $catQuery .= " WHERE 1 = 0";
        } else {
            $catQuery .= " WHERE blog_id IN (" . implode(',', array_fill(0, count($availableBlogIds), '?')) . ")";
            foreach ($availableBlogIds as $availableBlogId) {
                $catParams[] = $availableBlogId;
            }
        }
    }
    $catQuery .= " ORDER BY name";
    $catStmt = $pdo->prepare($catQuery);
    $catStmt->execute($catParams);
    $categories = $catStmt->fetchAll();
}

$stmt = $pdo->prepare(
    "SELECT a.id, a.title, a.slug, a.created_at, a.publish_at, a.preview_token,
            COALESCE(a.status,'published') AS status, a.blog_id,
            c.name AS category,
            b.name AS blog_name, b.slug AS blog_slug,
            COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS author_name
     FROM cms_articles a
     LEFT JOIN cms_categories c ON c.id = a.category_id
     LEFT JOIN cms_users u ON u.id = a.author_id
     LEFT JOIN cms_blogs b ON b.id = a.blog_id
     {$whereSql}
     ORDER BY a.created_at DESC"
);
$stmt->execute($params);
$articles = $stmt->fetchAll();
$bulkDeleteErrorCode = is_array($transferFlash) ? trim((string)($transferFlash['code'] ?? '')) : '';
$bulkDeleteHasError = is_array($transferFlash)
    && ($transferFlash['type'] ?? '') === 'error'
    && in_array($bulkDeleteErrorCode, [
        'article_bulk_delete_selection_required',
        'article_bulk_delete_confirm_required',
        'article_bulk_delete_selection_invalid',
        'article_bulk_delete_failed',
    ], true);
$bulkDeleteConfirmError = $bulkDeleteErrorCode === 'article_bulk_delete_confirm_required';
$bulkDeleteErrorFields = $bulkDeleteConfirmError ? ['confirm_article_bulk_delete'] : [];
$bulkDeleteSelectedIds = [];
foreach ((array)(is_array($transferFlash) ? ($transferFlash['selected_ids'] ?? []) : []) as $selectedId) {
    $selectedId = (int)$selectedId;
    if ($selectedId > 0) {
        $bulkDeleteSelectedIds[] = $selectedId;
    }
}
$visibleArticleIds = array_map(static fn (array $article): int => (int)$article['id'], $articles);
$bulkDeleteSelectedIds = array_values(array_intersect(array_unique($bulkDeleteSelectedIds), $visibleArticleIds));
$bulkDeleteSelectedLookup = array_fill_keys($bulkDeleteSelectedIds, true);
$bulkDeleteFormErrorId = 'article-bulk-delete-form-error';
$bulkDeleteReviewId = 'article-bulk-delete-review';
$bulkDeleteFieldErrorId = 'confirm-article-bulk-delete-error';
$deleteError = trim((string)($_GET['delete_error'] ?? ''));
$deleteErrorArticleId = inputInt('get', 'delete_error_id');
$deleteErrorMessage = match ($deleteError) {
    'confirm_required' => 'Článek nejde přesunout do Koše bez potvrzení kontroly dopadu. U pole Potvrzení přesunu je konkrétní nápověda.',
    'invalid' => 'Článek už není dostupný nebo jej nemůžete přesunout do Koše. Vyberte článek ze svého aktuálního seznamu.',
    'failed' => 'Článek se nepodařilo přesunout do Koše. Data zůstala beze změny; zkontrolujte položku a zkuste akci znovu.',
    default => '',
};
$deleteSuccessMessage = trim((string)($_GET['deleted'] ?? '')) === '1'
    ? 'Článek byl přesunut do Koše. Lze jej obnovit ve správě Koše.'
    : '';

$canManageTaxonomies = $activeBlog
    ? canCurrentUserManageBlogTaxonomies((int)$activeBlog['id'])
    : canCurrentUserManageAnyBlogTaxonomies();
$canApproveBlog = currentUserHasCapability('blog_approve');
$canConvertContent = currentUserHasCapability('content_manage_shared');
$filterParams = [];
if ($q !== '') {
    $filterParams['q'] = $q;
}
if ($cat !== '') {
    $filterParams['cat'] = $cat;
}
if ($blogFilter !== '') {
    $filterParams['blog'] = $blogFilter;
}
$currentRedirect = BASE_URL . '/admin/blog.php' . ($filterParams !== [] ? '?' . http_build_query($filterParams) : '');
$newArticleTargetBlog = $activeBlog ?? ($availableBlogs[0] ?? null);
$newArticleUrl = 'blog_form.php' . ($newArticleTargetBlog ? '?blog_id=' . (int)$newArticleTargetBlog['id'] : '');
$blogTaxonomySuffix = $activeBlog ? '?blog_id=' . (int)$activeBlog['id'] : '';
$blogCaptionTitle = $activeBlog ? 'Články blogu – ' . (string)$activeBlog['name'] : 'Blogy';

adminHeader($blogCaptionTitle);
?>

<p class="button-row button-row--start">
  <?php if ($availableBlogs !== []): ?>
    <a href="<?= h($newArticleUrl) ?>" class="btn">+ Přidat článek</a>
  <?php endif; ?>
  <?php if ($canManageTaxonomies): ?>
    <?php if (currentUserHasCapability('blog_taxonomies_manage') || currentUserHasCapability('settings_manage')): ?>
      <a href="blogs.php">Správa blogů</a>
    <?php endif; ?>
    <?php if ($activeBlog): ?>
      <a href="blog_members.php?blog_id=<?= (int)$activeBlog['id'] ?>">Tým blogu</a>
    <?php endif; ?>
    <?php if ($hasBlogs): ?>
      <a href="blog_cats.php<?= h($blogTaxonomySuffix) ?>">Kategorie blogu</a>
      <a href="blog_tags.php<?= h($blogTaxonomySuffix) ?>">Štítky blogu</a>
    <?php endif; ?>
  <?php endif; ?>
  <?php if ($activeBlog): ?>
    <a href="blog_series.php?blog_id=<?= (int)$activeBlog['id'] ?>">Série článků</a>
    <a href="<?= h(blogIndexPath($activeBlog)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit blog na webu<?= newWindowLinkSrOnlySuffix() ?></a>
    <a href="<?= h(blogFeedPath($activeBlog)) ?>" target="_blank" rel="noopener noreferrer">RSS feed blogu<?= newWindowLinkSrOnlySuffix() ?></a>
  <?php endif; ?>
</p>

<?php if ($message === 'no_blog'): ?>
  <p role="status"><strong>Nejdřív vytvořte blog.</strong> Kategorie, štítky i články se spravují až uvnitř existujícího blogu.</p>
<?php endif; ?>

<?php if ($deleteSuccessMessage !== ''): ?><p class="success" role="status" aria-atomic="true"><?= h($deleteSuccessMessage) ?></p><?php endif; ?>
<?php if ($deleteErrorMessage !== ''): ?><p id="article-delete-form-error" class="error" role="alert" aria-atomic="true"><?= h($deleteErrorMessage) ?></p><?php endif; ?>

<?php if (is_array($transferFlash) && ($transferFlash['message'] ?? '') !== ''): ?>
  <p<?= $bulkDeleteHasError ? ' id="' . h($bulkDeleteFormErrorId) . '"' : '' ?>
     class="<?= ($transferFlash['type'] ?? '') === 'error' ? 'error' : 'success' ?>"
     role="<?= ($transferFlash['type'] ?? '') === 'error' ? 'alert' : 'status' ?>" aria-atomic="true">
    <?= h((string)$transferFlash['message']) ?>
  </p>
<?php endif; ?>

<?php if ($message === 'no_blog_access'): ?>
  <p role="status"><strong>Zatím nemáte přiřazený žádný blog.</strong> Jakmile vás správce přidá do týmu některého blogu, půjde v něm vytvářet a upravovat články.</p>
<?php endif; ?>

<?php if (!$hasBlogs): ?>
  <p>
    <?php if ($canManageTaxonomies): ?>
      Zatím tu není vytvořený žádný blog. <a href="blogs.php">Vytvořit první blog</a>.
    <?php else: ?>
      Zatím tu není vytvořený žádný blog. Jakmile správce založí první blog, půjde do něj přidávat články.
    <?php endif; ?>
  </p>
<?php elseif ($availableBlogs === []): ?>
  <p>
    Zatím nemáte přiřazený žádný blog. Jakmile vás správce přidá do týmu některého blogu, uvidíte tady své články i dostupné blogy.
  </p>
<?php elseif ($activeBlog): ?>
  <p class="field-help">
    Právě spravujete články blogu <strong><?= h((string)$activeBlog['name']) ?></strong>.
    Kategorie, štítky i nový článek se teď vztahují k tomuto blogu.
  </p>
<?php endif; ?>

<?php if ($availableBlogs !== []): ?>
<form method="get" class="button-row admin-stack-sm">
  <label for="q" class="visually-hidden">Hledat</label>
  <input type="search" id="q" name="q" placeholder="Hledat v článcích…"
         value="<?= h($q) ?>" class="admin-search-input">
  <label for="cat" class="visually-hidden">Kategorie</label>
  <select id="cat" name="cat" class="admin-select-md">
    <option value="">Všechny kategorie</option>
    <option value="none"<?= $cat === 'none' ? ' selected' : '' ?>>Bez kategorie</option>
    <?php foreach ($categories as $category): ?>
      <option value="<?= (int)$category['id'] ?>"<?= $cat === (string)$category['id'] ? ' selected' : '' ?>><?= h($category['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <?php if ($multiBlog): ?>
    <label for="blog" class="visually-hidden">Blog</label>
    <select id="blog" name="blog" class="admin-select-sm">
      <option value="">Všechny blogy</option>
      <?php foreach ($availableBlogs as $blog): ?>
        <option value="<?= (int)$blog['id'] ?>"<?= $blogFilter === (string)$blog['id'] ? ' selected' : '' ?>><?= h((string)$blog['name']) ?></option>
      <?php endforeach; ?>
    </select>
  <?php endif; ?>
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($q !== '' || $cat !== '' || $blogFilter !== ''): ?>
    <a href="blog.php" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>
<?php endif; ?>

<?php if ($availableBlogs !== [] && empty($articles)): ?>
  <p>
    <?php if ($q !== '' || $cat !== '' || $blogFilter !== ''): ?>
      <?php if ($activeBlog && $q === '' && $cat === ''): ?>
        V blogu <?= h((string)$activeBlog['name']) ?> zatím nejsou žádné články.
      <?php else: ?>
        Pro zadaný filtr tu teď nejsou žádné články.
      <?php endif; ?>
    <?php else: ?>
      Zatím tu nejsou žádné články. <a href="<?= h($newArticleUrl) ?>">Přidat první článek</a>.
    <?php endif; ?>
  </p>
<?php elseif ($hasBlogs): ?>
<form method="post" action="blog_bulk.php" id="bulk-form"<?= $bulkDeleteHasError ? ' aria-describedby="' . h($bulkDeleteFormErrorId) . '"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
  <fieldset class="admin-fieldset-card">
    <legend>Hromadné akce s vybranými články</legend>
    <p data-selection-status="blog" class="field-help field-help--flush" aria-live="polite">Zatím není vybraný žádný článek.</p>
    <p id="<?= h($bulkDeleteReviewId) ?>" class="field-help field-help--flush">
      Přesun do Koše skryje vybrané články z webu, ale zachová jejich obrázky, komentáře, štítky, série, související články, revize i redirecty. Články lze v Koši obnovit; trvale se odstraní až samostatně potvrzeným vysypáním.
    </p>
    <label for="confirm-article-bulk-delete" class="admin-checkbox-label">
      <input type="checkbox" id="confirm-article-bulk-delete" name="confirm_article_bulk_delete" value="1"
             required aria-required="true"<?= adminFieldAttributes('confirm_article_bulk_delete', $bulkDeleteErrorFields, [], [$bulkDeleteReviewId], $bulkDeleteFieldErrorId) ?>>
      Zkontroloval(a) jsem vybrané články a chci je přesunout do Koše.
    </label>
    <?php adminRenderFieldError(
        'confirm_article_bulk_delete',
        $bulkDeleteErrorFields,
        [],
        'Před přesunem článků do Koše potvrďte, že jste zkontrolovali výběr a zachování souvisejících dat.',
        $bulkDeleteFieldErrorId
    ); ?>
    <div class="button-row">
      <?php if ($multiBlog): ?>
        <button type="submit" name="action" value="move" class="btn bulk-action-btn" disabled formnovalidate>Přesunout do jiného blogu</button>
      <?php endif; ?>
      <button type="submit" name="action" value="set_draft" class="btn bulk-action-btn" disabled formnovalidate>Nastavit jako koncept</button>
      <button type="submit" name="action" value="set_pending" class="btn bulk-action-btn" disabled formnovalidate>Nastavit čeká na schválení</button>
      <?php if (currentUserHasCapability('blog_approve')): ?>
        <button type="submit" name="action" value="set_published" class="btn bulk-action-btn" disabled formnovalidate>Nastavit jako publikováno</button>
      <?php endif; ?>
      <button type="submit" name="action" value="delete" class="btn btn-danger bulk-action-btn" disabled>Přesunout vybrané do Koše</button>
    </div>
  </fieldset>
</form>
<table>
    <caption>Přehled článků blogu</caption>
    <thead>
      <tr>
        <th scope="col"><label for="check-all" class="sr-only">Vybrat vše</label><input type="checkbox" id="check-all"></th>
        <th scope="col">Titulek</th>
        <?php if ($multiBlog): ?><th scope="col">Blog</th><?php endif; ?>
        <th scope="col">Autor</th>
        <th scope="col">Kategorie</th>
        <th scope="col">Datum</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($articles as $article): ?>
      <?php
        $articleId = (int)$article['id'];
        $deleteConfirmField = 'confirm_article_delete_' . $articleId;
        $deleteConfirmId = 'confirm-article-delete-' . $articleId;
        $deleteReviewId = 'article-delete-review-' . $articleId;
        $deleteFieldErrorId = 'confirm-article-delete-' . $articleId . '-error';
        $deleteHasError = $deleteError === 'confirm_required' && $deleteErrorArticleId === $articleId;
        $deleteErrorFields = $deleteHasError ? [$deleteConfirmField] : [];
        ?>
      <tr>
        <td><label for="article-select-<?= $articleId ?>" class="sr-only">Vybrat článek <?= h($article['title']) ?></label><input type="checkbox" id="article-select-<?= $articleId ?>" name="ids[]" value="<?= $articleId ?>" form="bulk-form"<?= isset($bulkDeleteSelectedLookup[$articleId]) ? ' checked' : '' ?>></td>
        <td><?= h($article['title']) ?></td>
        <?php if ($multiBlog): ?><td><?= h($article['blog_name'] ?? '–') ?></td><?php endif; ?>
        <td><?= $article['author_name'] ? h($article['author_name']) : '<em>–</em>' ?></td>
        <td><?= h($article['category'] ?? '–') ?></td>
        <td><?= h((string)$article['created_at']) ?></td>
        <td>
          <?php if ($article['status'] === 'draft'): ?>
            <span class="status-badge status-badge--draft">✎ Koncept</span>
          <?php elseif ($article['status'] === 'pending'): ?>
            <strong class="status-badge status-badge--pending">⟳ Čeká na schválení</strong>
          <?php elseif ($article['publish_at'] && strtotime((string)$article['publish_at']) > time()): ?>
            <small>Naplánováno: <?= h((string)$article['publish_at']) ?></small>
          <?php else: ?>
            Publikováno
          <?php endif; ?>
        </td>
        <td class="actions">
          <a href="blog_form.php?id=<?= (int)$article['id'] ?>" class="btn">Upravit</a>
          <?php if (!empty($article['preview_token'])): ?>
            <a href="<?= h(articlePreviewPath($article)) ?>"
               target="_blank" rel="noopener noreferrer" class="btn btn-muted">Náhled<?= newWindowLinkSrOnlySuffix() ?></a>
          <?php endif; ?>
          <?php if ($article['status'] === 'pending' && $canApproveBlog): ?>
            <form action="approve.php" method="post">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="articles">
              <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
              <input type="hidden" name="redirect" value="<?= h(BASE_URL) ?>/admin/blog.php">
              <button type="submit" class="btn btn-success">Schválit</button>
            </form>
          <?php endif; ?>
          <?php if ($canConvertContent): ?>
            <form action="convert_content.php" method="post" class="admin-inline-form">
              <fieldset class="admin-inline-fieldset">
                <legend class="sr-only">Kontrola převodu článku <?= h((string)$article['title']) ?> na stránku</legend>
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="direction" value="article_to_page">
                <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
                <input type="hidden" name="stage" value="review">
                <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
                <button type="submit" class="btn"><span aria-hidden="true">→</span> Převést na stránku</button>
              </fieldset>
            </form>
          <?php endif; ?>
          <form action="blog_clone.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
            <button type="submit" class="btn"
                    data-confirm="Vytvořit kopii článku?">Duplikovat</button>
          </form>
          <form id="article-delete-form-<?= $articleId ?>" action="blog_delete.php" method="post" class="admin-inline-form" novalidate<?= $deleteHasError ? ' aria-describedby="article-delete-form-error"' : '' ?>>
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= $articleId ?>">
            <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
            <fieldset class="admin-inline-fieldset">
              <legend class="sr-only">Přesun článku <?= h((string)$article['title']) ?> do Koše</legend>
              <p id="<?= h($deleteReviewId) ?>" class="field-help field-help--flush">
                Přesun skryje článek z webu, ale zachová jeho obrázek, komentáře, štítky, série, související články, revize i redirecty pro případné obnovení.
              </p>
              <label for="<?= h($deleteConfirmId) ?>" class="admin-checkbox-label">
                <input type="checkbox" id="<?= h($deleteConfirmId) ?>" name="<?= h($deleteConfirmField) ?>" value="1"
                       required aria-required="true"<?= adminFieldAttributes($deleteConfirmField, $deleteErrorFields, [], [$deleteReviewId], $deleteFieldErrorId) ?>>
                Potvrzuji přesun tohoto článku do Koše.
              </label>
              <?php adminRenderFieldError($deleteConfirmField, $deleteErrorFields, [], 'Před přesunem článku do Koše potvrďte, že jste zkontrolovali zachování obrázku, komentářů, štítků, sérií, souvisejících článků, revizí a redirectů.', $deleteFieldErrorId); ?>
              <button type="submit" class="btn btn-danger"
                      data-confirm="Přesunout článek <?= h((string)$article['title']) ?> do Koše?">Přesunout do Koše</button>
            </fieldset>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="table-note" aria-hidden="true">
    Po výběru článků můžete nahoře použít hromadnou akci<?= $multiBlog ? ' včetně přesunu do jiného blogu' : '' ?>.
  </div>
<?php endif; ?>

<?php if ($hasBlogs): ?>
<script nonce="<?= cspNonce() ?>">
(() => {
    const checkAll = document.getElementById('check-all');
    const checkboxes = Array.from(document.querySelectorAll('input[form="bulk-form"][name="ids[]"]'));
    const actionButtons = Array.from(document.querySelectorAll('#bulk-form .bulk-action-btn'));
    const status = document.querySelector('[data-selection-status="blog"]');

    const updateBulkUi = () => {
        const selectedCount = checkboxes.filter((checkbox) => checkbox.checked).length;
        if (status) {
            status.textContent = selectedCount === 0
                ? 'Zatím není vybraný žádný článek.'
                : (selectedCount === 1
                    ? 'Vybraný je 1 článek.'
                    : 'Vybrané jsou ' + selectedCount + ' články.');
        }
        actionButtons.forEach((button) => {
            button.disabled = selectedCount === 0;
        });
        if (checkAll) {
            checkAll.checked = selectedCount > 0 && selectedCount === checkboxes.length;
            checkAll.indeterminate = selectedCount > 0 && selectedCount < checkboxes.length;
        }
    };

    checkAll?.addEventListener('change', function () {
        checkboxes.forEach((checkbox) => checkbox.checked = this.checked);
        updateBulkUi();
    });

    checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', updateBulkUi);
    });

    updateBulkUi();
})();
</script>
<?php endif; ?>

<?php adminFooter(); ?>
