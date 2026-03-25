<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
$q = trim($_GET['q'] ?? '');
$params = [];
$whereParts = [];

if ($q !== '') {
    $whereParts[] = "(a.title LIKE ? OR a.perex LIKE ?)";
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

if (canManageOwnBlogOnly()) {
    $whereParts[] = 'a.author_id = ?';
    $params[] = currentUserId();
}

$whereSql = $whereParts !== [] ? 'WHERE ' . implode(' AND ', $whereParts) : '';

$stmt = $pdo->prepare(
    "SELECT a.id, a.title, a.slug, a.created_at, a.publish_at, a.preview_token,
            COALESCE(a.status,'published') AS status,
            c.name AS category,
            COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS author_name
     FROM cms_articles a
     LEFT JOIN cms_categories c ON c.id = a.category_id
     LEFT JOIN cms_users u ON u.id = a.author_id
     {$whereSql}
     ORDER BY a.created_at DESC"
);
$stmt->execute($params);
$articles = $stmt->fetchAll();

$canManageTaxonomies = currentUserHasCapability('blog_taxonomies_manage');
$canApproveBlog = currentUserHasCapability('blog_approve');
$currentRedirect = BASE_URL . '/admin/blog.php' . ($q !== '' ? '?q=' . urlencode($q) : '');

adminHeader('Blog');
?>

<p>
  <a href="blog_form.php" class="btn">+ Přidat článek</a>
  <?php if ($canManageTaxonomies): ?>
    <a href="blog_cats.php" style="margin-left:1rem">Kategorie blogu</a>
    <a href="blog_tags.php" style="margin-left:1rem">Štítky blogu</a>
  <?php endif; ?>
</p>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem">
  <label for="q" class="visually-hidden">Hledat</label>
  <input type="search" id="q" name="q" placeholder="Hledat v článcích…"
         value="<?= h($q) ?>" style="width:300px">
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($q !== ''): ?>
    <a href="blog.php" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<?php if (empty($articles)): ?>
  <p>
    <?php if ($q !== ''): ?>
      Pro zadané hledání tu teď nejsou žádné články.
    <?php else: ?>
      Zatím tu nejsou žádné články. <a href="blog_form.php">Přidat první článek</a>.
    <?php endif; ?>
  </p>
<?php else: ?>
<form method="post" action="blog_bulk.php" id="bulk-form">
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
  <fieldset style="margin:0 0 .85rem;border:1px solid #d6d6d6;border-radius:10px;padding:.85rem 1rem">
    <legend>Hromadné akce s vybranými články</legend>
    <p data-selection-status="blog" class="field-help" aria-live="polite" style="margin-top:0">Zatím není vybraný žádný článek.</p>
    <div class="button-row">
      <button type="submit" name="action" value="delete" class="btn btn-danger bulk-action-btn"
              disabled onclick="return confirm('Smazat vybrané články?')">Smazat vybrané</button>
    </div>
  </fieldset>
  <table>
    <caption>Přehled článků blogu</caption>
    <thead>
      <tr>
        <th scope="col"><input type="checkbox" id="check-all" aria-label="Vybrat vše"></th>
        <th scope="col">Titulek</th>
        <th scope="col">Autor</th>
        <th scope="col">Kategorie</th>
        <th scope="col">Datum</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($articles as $article): ?>
      <tr>
        <td><input type="checkbox" name="ids[]" value="<?= (int)$article['id'] ?>" aria-label="Vybrat článek <?= h($article['title']) ?>"></td>
        <td><?= h($article['title']) ?></td>
        <td><?= $article['author_name'] ? h($article['author_name']) : '<em>–</em>' ?></td>
        <td><?= h($article['category'] ?? '–') ?></td>
        <td><?= h((string)$article['created_at']) ?></td>
        <td>
          <?php if ($article['status'] === 'pending'): ?>
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
               target="_blank" class="btn" style="background:#555;color:#fff">Náhled</a>
          <?php endif; ?>
          <?php if ($article['status'] === 'pending' && $canApproveBlog): ?>
            <form action="approve.php" method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="articles">
              <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
              <input type="hidden" name="redirect" value="<?= h(BASE_URL) ?>/admin/blog.php">
              <button type="submit" class="btn btn-success">Schválit</button>
            </form>
          <?php endif; ?>
          <form action="blog_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat článek?')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div style="margin-top:.75rem;color:#555" aria-hidden="true">Po výběru článků můžete použít hromadnou akci nahoře.</div>
</form>
<?php endif; ?>


<script>
(() => {
    const checkAll = document.getElementById('check-all');
    const checkboxes = Array.from(document.querySelectorAll('#bulk-form input[name="ids[]"]'));
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

<?php adminFooter(); ?>
