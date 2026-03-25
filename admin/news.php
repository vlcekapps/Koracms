<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
$q = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$allowedStatusFilters = ['all', 'pending', 'published'];
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = 'all';
}

$whereParts = [];
$params = [];

if (canManageOwnNewsOnly()) {
    $whereParts[] = 'n.author_id = ?';
    $params[] = currentUserId();
}

if ($q !== '') {
    $whereParts[] = '(n.title LIKE ? OR n.content LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

if ($statusFilter === 'pending') {
    $whereParts[] = "COALESCE(n.status,'published') = 'pending'";
} elseif ($statusFilter === 'published') {
    $whereParts[] = "COALESCE(n.status,'published') = 'published'";
}

$whereSql = $whereParts !== [] ? 'WHERE ' . implode(' AND ', $whereParts) : '';

$stmt = $pdo->prepare(
    "SELECT n.id, n.title, n.slug, n.content, n.created_at, COALESCE(n.status,'published') AS status,
            COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS author_name
     FROM cms_news n
     LEFT JOIN cms_users u ON u.id = n.author_id
     {$whereSql}
     ORDER BY n.created_at DESC"
);
$stmt->execute($params);
$items = array_map(
    static fn(array $item): array => hydrateNewsPresentation($item),
    $stmt->fetchAll()
);

$canApproveNews = currentUserHasCapability('news_approve');

adminHeader('Novinky');
?>
<p><a href="news_form.php" class="btn">+ Přidat novinku</a></p>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
  <div>
    <label for="q" class="visually-hidden">Hledat v novinkách</label>
    <input type="search" id="q" name="q" placeholder="Hledat v novinkách…"
           value="<?= h($q) ?>" style="width:300px">
  </div>
  <div>
    <label for="status">Stav</label>
    <select id="status" name="status">
      <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>>Vše</option>
      <option value="published"<?= $statusFilter === 'published' ? ' selected' : '' ?>>Publikované</option>
      <option value="pending"<?= $statusFilter === 'pending' ? ' selected' : '' ?>>Čekající</option>
    </select>
  </div>
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($q !== '' || $statusFilter !== 'all'): ?>
    <a href="news.php" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<?php if (empty($items)): ?>
  <p>
    <?php if ($q !== '' || $statusFilter !== 'all'): ?>
      Pro zvolený filtr tu teď nejsou žádné novinky.
    <?php else: ?>
      Zatím tu nejsou žádné novinky. <a href="news_form.php">Přidat první novinku</a>.
    <?php endif; ?>
  </p>
<?php else: ?>
  <table>
    <caption>Přehled novinek</caption>
    <thead>
      <tr>
        <th scope="col">Titulek</th>
        <th scope="col">Autor</th>
        <th scope="col">Datum</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $item): ?>
      <tr<?= $item['status'] === 'pending' ? ' class="table-row--pending"' : '' ?>>
        <td>
          <strong><?= h((string)$item['title']) ?></strong><br>
          <small style="color:#555">/news/<?= h((string)$item['slug']) ?></small>
          <?php if ($item['excerpt'] !== ''): ?>
            <br><small style="color:#555"><?= h((string)$item['excerpt']) ?></small>
          <?php endif; ?>
        </td>
        <td><?= $item['author_name'] ? h((string)$item['author_name']) : '<em>–</em>' ?></td>
        <td><?= h(formatCzechDate((string)$item['created_at'])) ?></td>
        <td>
          <?php if ($item['status'] === 'pending'): ?>
            <strong class="status-badge status-badge--pending">Čeká na schválení</strong>
          <?php else: ?>
            Publikováno
          <?php endif; ?>
        </td>
        <td class="actions">
          <a href="news_form.php?id=<?= (int)$item['id'] ?>">Upravit</a>
          <?php if ($item['status'] === 'published'): ?>
            <a href="<?= h(newsPublicPath($item)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu</a>
          <?php endif; ?>
          <?php if ($item['status'] === 'pending' && $canApproveNews): ?>
            <form action="approve.php" method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="news">
              <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
              <input type="hidden" name="redirect" value="<?= h(BASE_URL) ?>/admin/news.php">
              <button type="submit" class="btn btn-success">Schválit</button>
            </form>
          <?php endif; ?>
          <form action="news_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat novinku?')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
