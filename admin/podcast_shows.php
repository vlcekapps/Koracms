<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu podcastů nemáte potřebné oprávnění.');

$pdo = db_connect();
$q = trim($_GET['q'] ?? '');
$whereParts = [];
$params = [];

if ($q !== '') {
    $whereParts[] = '(s.title LIKE ? OR s.description LIKE ? OR s.author LIKE ? OR s.category LIKE ?)';
    for ($i = 0; $i < 4; $i++) {
        $params[] = '%' . $q . '%';
    }
}

$whereSql = $whereParts !== [] ? 'WHERE ' . implode(' AND ', $whereParts) : '';

$stmt = $pdo->prepare(
    "SELECT s.*,
            COUNT(e.id) AS episode_count,
            SUM(CASE WHEN COALESCE(e.status,'published') = 'pending' THEN 1 ELSE 0 END) AS pending_episode_count,
            SUM(CASE WHEN COALESCE(e.status,'published') = 'published' AND (e.publish_at IS NULL OR e.publish_at <= NOW()) THEN 1 ELSE 0 END) AS published_episode_count
     FROM cms_podcast_shows s
     LEFT JOIN cms_podcasts e ON e.show_id = s.id
     {$whereSql}
     GROUP BY s.id
     ORDER BY s.updated_at DESC, s.title ASC"
);
$stmt->execute($params);
$shows = array_map(
    static fn(array $show): array => hydratePodcastShowPresentation($show),
    $stmt->fetchAll()
);

adminHeader('Podcasty');
?>
<p><a href="podcast_show_form.php" class="btn">+ Přidat podcast</a></p>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
  <div>
    <label for="q" class="visually-hidden">Hledat v podcastech</label>
    <input type="search" id="q" name="q" placeholder="Hledat v podcastech…"
           value="<?= h($q) ?>" style="width:300px">
  </div>
  <button type="submit" class="btn">Filtrovat</button>
  <?php if ($q !== ''): ?>
    <a href="podcast_shows.php" class="btn">Zrušit</a>
  <?php endif; ?>
</form>

<?php if (empty($shows)): ?>
  <p>Žádné podcasty<?= $q !== '' ? ' pro zadaný filtr.' : '. ' ?><a href="podcast_show_form.php">Přidejte první podcast.</a></p>
<?php else: ?>
  <table>
    <caption>Podcasty</caption>
    <thead>
      <tr>
        <th scope="col">Název</th>
        <th scope="col">Autor a kategorie</th>
        <th scope="col">Epizody</th>
        <th scope="col">Veřejně</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($shows as $show): ?>
      <tr>
        <td>
          <strong><?= h((string)$show['title']) ?></strong><br>
          <small style="color:#555"><?= h((string)$show['public_path']) ?></small>
          <?php if ($show['description_plain'] !== ''): ?>
            <br><small style="color:#555"><?= h(mb_strimwidth((string)$show['description_plain'], 0, 120, '…', 'UTF-8')) ?></small>
          <?php endif; ?>
        </td>
        <td>
          <?= h(trim((string)($show['author'] ?? '')) !== '' ? (string)$show['author'] : '–') ?>
          <?php if (trim((string)($show['category'] ?? '')) !== ''): ?>
            <br><small style="color:#555"><?= h((string)$show['category']) ?></small>
          <?php endif; ?>
        </td>
        <td>
          <a href="podcast.php?show_id=<?= (int)$show['id'] ?>">
            <?= (int)$show['episode_count'] ?> epizod
          </a>
          <?php if ((int)($show['pending_episode_count'] ?? 0) > 0): ?>
            <br><small class="status-badge status-badge--pending"><?= (int)$show['pending_episode_count'] ?> čeká na schválení</small>
          <?php endif; ?>
        </td>
        <td class="actions">
          <a href="<?= h((string)$show['public_path']) ?>" target="_blank" rel="noopener noreferrer">Veřejná stránka</a>
          <a href="<?= h(BASE_URL . '/podcast/feed.php?slug=' . rawurlencode((string)$show['slug'])) ?>" target="_blank" rel="noopener noreferrer">RSS feed</a>
        </td>
        <td class="actions">
          <a href="podcast_show_form.php?id=<?= (int)$show['id'] ?>" class="btn">Upravit</a>
          <a href="podcast.php?show_id=<?= (int)$show['id'] ?>" class="btn">Epizody</a>
          <form action="podcast_show_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$show['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat podcast včetně všech epizod?')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>


<?php adminFooter(); ?>
