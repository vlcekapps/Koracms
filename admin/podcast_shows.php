<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu podcastů nemáte potřebné oprávnění.');

$pdo = db_connect();
$q = trim($_GET['q'] ?? '');
$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$allowedStatusFilters = ['all', 'published', 'hidden', 'pending'];
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = 'all';
}
$whereParts = [];
$params = [];

if ($q !== '') {
    $whereParts[] = '(s.title LIKE ? OR s.description LIKE ? OR s.author LIKE ? OR s.category LIKE ?)';
    for ($i = 0; $i < 4; $i++) {
        $params[] = '%' . $q . '%';
    }
}

if ($statusFilter === 'pending') {
    $whereParts[] = "COALESCE(s.status, 'published') = 'pending'";
} elseif ($statusFilter === 'published') {
    $whereParts[] = "COALESCE(s.status, 'published') = 'published' AND COALESCE(s.is_published, 1) = 1";
} elseif ($statusFilter === 'hidden') {
    $whereParts[] = "COALESCE(s.status, 'published') = 'published' AND COALESCE(s.is_published, 1) = 0";
}

$whereSql = $whereParts !== [] ? 'WHERE ' . implode(' AND ', $whereParts) : '';
$perPage = 20;
$pagination = paginate(
    $pdo,
    "SELECT COUNT(*) FROM cms_podcast_shows s {$whereSql}",
    $params,
    $perPage
);
['totalPages' => $pages, 'page' => $page, 'offset' => $offset, 'total' => $totalShows] = $pagination;

$stmt = $pdo->prepare(
    "SELECT s.*,
            COUNT(e.id) AS episode_count,
            SUM(CASE WHEN COALESCE(e.status,'published') = 'pending' THEN 1 ELSE 0 END) AS pending_episode_count,
            SUM(CASE WHEN COALESCE(e.status,'published') = 'published' AND (e.publish_at IS NULL OR e.publish_at <= NOW()) THEN 1 ELSE 0 END) AS published_episode_count
     FROM cms_podcast_shows s
     LEFT JOIN cms_podcasts e ON e.show_id = s.id
     {$whereSql}
     GROUP BY s.id
     ORDER BY s.updated_at DESC, s.title ASC
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$shows = array_map(
    static fn(array $show): array => hydratePodcastShowPresentation($show),
    $stmt->fetchAll()
);
$canApprovePodcast = currentUserHasCapability('content_approve_shared');
$currentListUrl = BASE_URL . '/admin/podcast_shows.php';
$currentQuery = [];
if ($q !== '') {
    $currentQuery['q'] = $q;
}
if ($statusFilter !== 'all') {
    $currentQuery['status'] = $statusFilter;
}
if ($page > 1) {
    $currentQuery['strana'] = $page;
}
if ($currentQuery !== []) {
    $currentListUrl .= '?' . http_build_query($currentQuery);
}
$pagerBase = BASE_URL . '/admin/podcast_shows.php';
$pagerQuery = [];
if ($q !== '') {
    $pagerQuery['q'] = $q;
}
if ($statusFilter !== 'all') {
    $pagerQuery['status'] = $statusFilter;
}
$pagerBase .= $pagerQuery !== [] ? '?' . http_build_query($pagerQuery) . '&' : '?';
$pagerHtml = renderPager($page, $pages, $pagerBase, 'Stránkování podcastů v administraci');

adminHeader('Podcasty');
?>
<p><a href="podcast_show_form.php?redirect=<?= rawurlencode($currentListUrl) ?>" class="btn">+ Přidat podcast</a></p>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
  <div>
    <label for="q" class="visually-hidden">Hledat v podcastech</label>
    <input type="search" id="q" name="q" placeholder="Hledat v podcastech…"
           value="<?= h($q) ?>" style="width:300px">
  </div>
  <div>
    <label for="status">Stav</label>
    <select id="status" name="status">
      <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>>Vše</option>
      <option value="published"<?= $statusFilter === 'published' ? ' selected' : '' ?>>Veřejné</option>
      <option value="hidden"<?= $statusFilter === 'hidden' ? ' selected' : '' ?>>Skryté</option>
      <option value="pending"<?= $statusFilter === 'pending' ? ' selected' : '' ?>>Čekající</option>
    </select>
  </div>
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($q !== '' || $statusFilter !== 'all'): ?>
    <a href="podcast_shows.php" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<?php if (empty($shows)): ?>
  <p>
    <?php if ($q !== '' || $statusFilter !== 'all'): ?>
      Pro zvolený filtr tu teď nejsou žádné podcasty.
    <?php else: ?>
      Zatím tu nejsou žádné podcasty. <a href="podcast_show_form.php">Přidat první podcast</a>.
    <?php endif; ?>
  </p>
<?php else: ?>
  <p class="meta-row meta-row--tight"><?= $totalShows ?> podcastů</p>
  <table>
    <caption>Přehled podcastů</caption>
    <thead>
      <tr>
        <th scope="col">Název</th>
        <th scope="col">Autor a kategorie</th>
        <th scope="col">Epizody</th>
        <th scope="col">Stav</th>
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
        <td>
          <?php if ((string)$show['status'] === 'pending'): ?>
            <strong class="status-badge status-badge--pending">Čeká na schválení</strong>
          <?php elseif (!empty($show['is_public'])): ?>
            <strong>Veřejný</strong>
          <?php else: ?>
            <strong>Skrytý</strong>
          <?php endif; ?>
        </td>
        <td class="actions">
          <a href="podcast_show_form.php?id=<?= (int)$show['id'] ?>&amp;redirect=<?= rawurlencode($currentListUrl) ?>" class="btn">Upravit</a>
          <a href="podcast.php?show_id=<?= (int)$show['id'] ?>" class="btn">Spravovat epizody</a>
          <?php if (!empty($show['is_public'])): ?>
            <a href="<?= h((string)$show['public_path']) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu</a>
          <?php endif; ?>
          <a href="<?= h(BASE_URL . '/podcast/feed.php?slug=' . rawurlencode((string)$show['slug'])) ?>" target="_blank" rel="noopener noreferrer">RSS feed</a>
          <?php if ((string)$show['status'] === 'pending' && $canApprovePodcast): ?>
            <form action="approve.php" method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="podcast_shows">
              <input type="hidden" name="id" value="<?= (int)$show['id'] ?>">
              <input type="hidden" name="redirect" value="<?= h($currentListUrl) ?>">
              <button type="submit" class="btn btn-success">Schválit</button>
            </form>
          <?php endif; ?>
          <form action="podcast_show_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$show['id'] ?>">
            <input type="hidden" name="redirect" value="<?= h($currentListUrl) ?>">
            <button type="submit" class="btn btn-danger"
                    data-confirm="Smazat podcast včetně všech epizod?">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($pagerHtml !== ''): ?>
    <div style="margin-top:1rem">
      <?= $pagerHtml ?>
    </div>
  <?php endif; ?>
<?php endif; ?>


<?php adminFooter(); ?>
