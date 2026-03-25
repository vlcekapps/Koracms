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

adminHeader('Blog – správa článků');
?>

<p>
  <a href="blog_form.php" class="btn">+ Přidat článek</a>
  <?php if ($canManageTaxonomies): ?>
    <a href="blog_cats.php" style="margin-left:1rem">Kategorie</a>
    <a href="blog_tags.php" style="margin-left:1rem">Tagy</a>
  <?php endif; ?>
</p>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem">
  <label for="q" class="visually-hidden">Hledat</label>
  <input type="search" id="q" name="q" placeholder="Hledat v článcích…"
         value="<?= h($q) ?>" style="width:300px">
  <button type="submit" class="btn">Hledat</button>
  <?php if ($q !== ''): ?>
    <a href="blog.php" class="btn">Zrušit</a>
  <?php endif; ?>
</form>

<?php if (empty($articles)): ?>
  <p>Žádné články<?= $q !== '' ? ' odpovídající hledání.' : '.' ?></p>
<?php else: ?>
<form method="post" action="blog_bulk.php" id="bulk-form">
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <table>
    <caption>Články</caption>
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
  <div style="margin-top:.75rem">
    <button type="submit" name="action" value="delete" class="btn btn-danger"
            onclick="return confirm('Smazat vybrané články?')">Smazat vybrané</button>
  </div>
</form>
<?php endif; ?>


<script>
document.getElementById('check-all')?.addEventListener('change', function () {
    document.querySelectorAll('#bulk-form input[name="ids[]"]')
        .forEach((checkbox) => checkbox.checked = this.checked);
});
</script>

<?php adminFooter(); ?>
