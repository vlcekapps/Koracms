<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();

$q = trim($_GET['q'] ?? '');
$params = [];
$where  = '';
if ($q !== '') {
    $where    = "WHERE a.title LIKE ? OR a.perex LIKE ?";
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

$stmt = $pdo->prepare(
    "SELECT a.id, a.title, a.created_at, a.publish_at, a.preview_token,
            COALESCE(a.status,'published') AS status,
            c.name AS category,
            COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS author_name
     FROM cms_articles a
     LEFT JOIN cms_categories c ON c.id = a.category_id
     LEFT JOIN cms_users u ON u.id = a.author_id
     {$where}
     ORDER BY a.created_at DESC"
);
$stmt->execute($params);
$articles = $stmt->fetchAll();

adminHeader('Blog – správa článků');
?>

<p>
  <a href="blog_form.php" class="btn">+ Přidat článek</a>
  <a href="blog_cats.php"  style="margin-left:1rem">Kategorie</a>
  <a href="blog_tags.php"  style="margin-left:1rem">Tagy</a>
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
    <?php foreach ($articles as $a): ?>
      <tr>
        <td><input type="checkbox" name="ids[]" value="<?= (int)$a['id'] ?>" aria-label="Vybrat článek <?= h($a['title']) ?>"></td>
        <td><?= h($a['title']) ?></td>
        <td><?= $a['author_name'] ? h($a['author_name']) : '<em>–</em>' ?></td>
        <td><?= h($a['category'] ?? '–') ?></td>
        <td><?= h($a['created_at']) ?></td>
        <td>
          <?php if ($a['status'] === 'pending'): ?>
            <strong style="color:#c60">⏳ Čeká na schválení</strong>
          <?php elseif ($a['publish_at'] && strtotime($a['publish_at']) > time()): ?>
            <small>Naplánováno: <?= h($a['publish_at']) ?></small>
          <?php else: ?>
            Publikováno
          <?php endif; ?>
        </td>
        <td class="actions">
          <a href="blog_form.php?id=<?= (int)$a['id'] ?>" class="btn">Upravit</a>
          <?php if (!empty($a['preview_token'])): ?>
            <a href="<?= BASE_URL ?>/blog/article.php?id=<?= (int)$a['id'] ?>&preview=<?= h($a['preview_token']) ?>"
               target="_blank" class="btn" style="background:#555;color:#fff">Náhled</a>
          <?php endif; ?>
          <?php if ($a['status'] === 'pending' && isSuperAdmin()): ?>
            <form action="approve.php" method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="articles">
              <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
              <input type="hidden" name="redirect" value="<?= h(BASE_URL) ?>/admin/blog.php">
              <button type="submit" class="btn" style="background:#060;color:#fff">Schválit</button>
            </form>
          <?php endif; ?>
          <form action="blog_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
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

<style>.visually-hidden{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}</style>
<script>
document.getElementById('check-all').addEventListener('change', function () {
    document.querySelectorAll('#bulk-form input[name="ids[]"]')
        .forEach(cb => cb.checked = this.checked);
});
</script>

<?php adminFooter(); ?>
