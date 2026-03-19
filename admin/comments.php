<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();

$filter = $_GET['filter'] ?? 'pending';
$q      = trim($_GET['q'] ?? '');

$where = match($filter) {
    'approved' => 'WHERE c.is_approved = 1',
    'all'      => 'WHERE 1',
    default    => 'WHERE c.is_approved = 0',
};

$params = [];
if ($q !== '') {
    $where  .= " AND (c.author_name LIKE ? OR c.content LIKE ?)";
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

$stmt = $pdo->prepare(
    "SELECT c.id, c.author_name, c.author_email, c.content, c.is_approved,
            c.created_at, a.title AS article_title, a.id AS article_id
     FROM cms_comments c
     LEFT JOIN cms_articles a ON a.id = c.article_id
     {$where}
     ORDER BY c.created_at DESC"
);
$stmt->execute($params);
$comments = $stmt->fetchAll();

$pendingCount = (int)$pdo->query(
    "SELECT COUNT(*) FROM cms_comments WHERE is_approved = 0"
)->fetchColumn();

adminHeader('Komentáře');
?>

<nav aria-label="Filtr komentářů" style="margin-bottom:1rem">
  <a href="?filter=pending"  <?= $filter === 'pending'  ? 'aria-current="page"' : '' ?>>
    Čekající (<?= $pendingCount ?>)
  </a>
  ·
  <a href="?filter=approved" <?= $filter === 'approved' ? 'aria-current="page"' : '' ?>>Schválené</a>
  ·
  <a href="?filter=all"      <?= $filter === 'all'      ? 'aria-current="page"' : '' ?>>Všechny</a>
</nav>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem">
  <input type="hidden" name="filter" value="<?= h($filter) ?>">
  <label for="q" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)">Hledat</label>
  <input type="search" id="q" name="q" placeholder="Hledat v komentářích…"
         value="<?= h($q) ?>" style="width:280px">
  <button type="submit" class="btn">Hledat</button>
  <?php if ($q !== ''): ?>
    <a href="?filter=<?= h($filter) ?>" class="btn">Zrušit</a>
  <?php endif; ?>
</form>

<?php if (empty($comments)): ?>
  <p>Žádné komentáře v této kategorii.</p>
<?php else: ?>
  <form method="post" action="comment_bulk.php" id="bulk-form">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="filter"     value="<?= h($filter) ?>">
    <table>
      <caption>Komentáře</caption>
      <thead>
        <tr>
          <th scope="col"><input type="checkbox" id="check-all" aria-label="Vybrat vše"></th>
          <th scope="col">Autor</th>
          <th scope="col">Článek</th>
          <th scope="col">Komentář</th>
          <th scope="col">Datum</th>
          <th scope="col">Stav</th>
          <th scope="col">Akce</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($comments as $c): ?>
          <tr>
            <td><input type="checkbox" name="ids[]" value="<?= (int)$c['id'] ?>" aria-label="Vybrat komentář"></td>
            <td>
              <?= h($c['author_name']) ?>
              <?php if ($c['author_email'] !== ''): ?>
                <br><small><?= h($c['author_email']) ?></small>
              <?php endif; ?>
            </td>
            <td>
              <a href="<?= BASE_URL ?>/blog/article.php?id=<?= (int)$c['article_id'] ?>">
                <?= h($c['article_title'] ?? '–') ?>
              </a>
            </td>
            <td><?= h(mb_substr($c['content'], 0, 120)) . (mb_strlen($c['content']) > 120 ? '…' : '') ?></td>
            <td><time datetime="<?= h(str_replace(' ', 'T', $c['created_at'])) ?>"><?= formatCzechDate($c['created_at']) ?></time></td>
            <td><?= $c['is_approved'] ? 'Schválený' : '<strong>Čekající</strong>' ?></td>
            <td class="actions">
              <?php if (!$c['is_approved']): ?>
                <form method="post" action="<?= BASE_URL ?>/admin/comment_approve.php" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                  <input type="hidden" name="id"         value="<?= (int)$c['id'] ?>">
                  <input type="hidden" name="filter"     value="<?= h($filter) ?>">
                  <button type="submit" class="btn">Schválit</button>
                </form>
              <?php endif; ?>
              <form method="post" action="<?= BASE_URL ?>/admin/comment_delete.php"
                    onsubmit="return confirm('Smazat tento komentář?')" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id"         value="<?= (int)$c['id'] ?>">
                <input type="hidden" name="filter"     value="<?= h($filter) ?>">
                <button type="submit" class="btn btn-danger">Smazat</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div style="margin-top:.75rem;display:flex;gap:.5rem">
      <?php if ($filter !== 'approved'): ?>
        <button type="submit" name="action" value="approve" class="btn">Schválit vybrané</button>
      <?php endif; ?>
      <button type="submit" name="action" value="delete" class="btn btn-danger"
              onclick="return confirm('Smazat vybrané komentáře?')">Smazat vybrané</button>
    </div>
  </form>
<?php endif; ?>

<script>
document.getElementById('check-all')?.addEventListener('change', function () {
    document.querySelectorAll('#bulk-form input[name="ids[]"]')
        .forEach(cb => cb.checked = this.checked);
});
</script>

<?php adminFooter(); ?>
