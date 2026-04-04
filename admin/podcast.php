<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu podcastů nemáte potřebné oprávnění.');

$pdo = db_connect();
$showId = inputInt('get', 'show_id');
if ($showId === null) {
    header('Location: podcast_shows.php');
    exit;
}

$showStmt = $pdo->prepare("SELECT * FROM cms_podcast_shows WHERE id = ?");
$showStmt->execute([$showId]);
$show = $showStmt->fetch() ?: null;
if (!$show) {
    header('Location: podcast_shows.php');
    exit;
}
$show = hydratePodcastShowPresentation($show);
$perPage = 20;

$q = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$allowedStatusFilters = ['all', 'published', 'pending', 'scheduled'];
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = 'all';
}

$whereParts = ['p.deleted_at IS NULL', 'p.show_id = ?'];
$params = [$showId];

if ($q !== '') {
    $whereParts[] = '(p.title LIKE ? OR p.slug LIKE ? OR p.description LIKE ? OR p.duration LIKE ?)';
    for ($i = 0; $i < 4; $i++) {
        $params[] = '%' . $q . '%';
    }
}

if ($statusFilter === 'pending') {
    $whereParts[] = "COALESCE(p.status,'published') = 'pending'";
} elseif ($statusFilter === 'published') {
    $whereParts[] = "COALESCE(p.status,'published') = 'published' AND (p.publish_at IS NULL OR p.publish_at <= NOW())";
} elseif ($statusFilter === 'scheduled') {
    $whereParts[] = "COALESCE(p.status,'published') = 'published' AND p.publish_at IS NOT NULL AND p.publish_at > NOW()";
}

$whereSql = 'WHERE ' . implode(' AND ', $whereParts);
$pagination = paginate(
    $pdo,
    "SELECT COUNT(*)
     FROM cms_podcasts p
     INNER JOIN cms_podcast_shows s ON s.id = p.show_id
     {$whereSql}",
    $params,
    $perPage
);
['totalPages' => $pages, 'page' => $page, 'offset' => $offset, 'total' => $totalEpisodes] = $pagination;

$episodesStmt = $pdo->prepare(
    "SELECT p.*, s.slug AS show_slug, s.title AS show_title
     FROM cms_podcasts p
     INNER JOIN cms_podcast_shows s ON s.id = p.show_id
     {$whereSql}
     ORDER BY COALESCE(p.episode_num, 0) DESC, COALESCE(p.publish_at, p.created_at) DESC, p.id DESC
     LIMIT ? OFFSET ?"
);
$episodesStmt->execute(array_merge($params, [$perPage, $offset]));
$episodes = array_map(
    static fn(array $episode): array => hydratePodcastEpisodePresentation($episode),
    $episodesStmt->fetchAll()
);

$canApprovePodcast = currentUserHasCapability('content_approve_shared');
$currentListUrl = BASE_URL . '/admin/podcast.php?show_id=' . (int)$showId;
$currentQuery = ['show_id' => $showId];
if ($q !== '') {
    $currentQuery['q'] = $q;
}
if ($statusFilter !== 'all') {
    $currentQuery['status'] = $statusFilter;
}
if ($page > 1) {
    $currentQuery['strana'] = $page;
}
$currentListUrl = BASE_URL . '/admin/podcast.php?' . http_build_query($currentQuery);
$pagerQuery = ['show_id' => $showId];
if ($q !== '') {
    $pagerQuery['q'] = $q;
}
if ($statusFilter !== 'all') {
    $pagerQuery['status'] = $statusFilter;
}
$pagerHtml = renderPager($page, $pages, BASE_URL . '/admin/podcast.php?' . http_build_query($pagerQuery) . '&', 'Stránkování epizod podcastu v administraci');

adminHeader('Epizody podcastu: ' . (string)$show['title']);
?>
<p>
  <a href="podcast_shows.php"><span aria-hidden="true">&larr;</span> Zpět na přehled podcastů</a>
  &nbsp;|&nbsp;
  <a href="podcast_show_form.php?id=<?= (int)$show['id'] ?>">Upravit podcast</a>
  &nbsp;|&nbsp;
  <a href="<?= h((string)$show['public_path']) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu</a>
  &nbsp;|&nbsp;
  <a href="<?= h(BASE_URL . '/podcast/feed.php?slug=' . rawurlencode((string)$show['slug'])) ?>" target="_blank" rel="noopener noreferrer">RSS feed</a>
</p>

<p><a href="podcast_form.php?show_id=<?= (int)$showId ?>&amp;redirect=<?= rawurlencode($currentListUrl) ?>" class="btn">+ Přidat epizodu</a></p>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
  <input type="hidden" name="show_id" value="<?= (int)$showId ?>">
  <div>
    <label for="q" class="visually-hidden">Hledat v epizodách</label>
    <input type="search" id="q" name="q" placeholder="Hledat v epizodách…"
           value="<?= h($q) ?>" style="width:300px">
  </div>
  <div>
    <label for="status">Stav</label>
    <select id="status" name="status">
      <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>>Vše</option>
      <option value="published"<?= $statusFilter === 'published' ? ' selected' : '' ?>>Publikované</option>
      <option value="scheduled"<?= $statusFilter === 'scheduled' ? ' selected' : '' ?>>Naplánované</option>
      <option value="pending"<?= $statusFilter === 'pending' ? ' selected' : '' ?>>Čekající</option>
    </select>
  </div>
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($q !== '' || $statusFilter !== 'all'): ?>
    <a href="podcast.php?show_id=<?= (int)$showId ?>" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<?php if (empty($episodes)): ?>
  <p>
    <?php if ($q !== '' || $statusFilter !== 'all'): ?>
      Pro zvolený filtr tu teď nejsou žádné epizody.
    <?php else: ?>
      Zatím tu v tomto podcastu nejsou žádné epizody.
      <a href="podcast_form.php?show_id=<?= (int)$showId ?>">Přidat první epizodu</a>.
    <?php endif; ?>
  </p>
<?php else: ?>
  <p class="meta-row meta-row--tight"><?= $totalEpisodes ?> epizod</p>
  <table>
    <caption>Přehled epizod podcastu</caption>
    <thead>
      <tr>
        <th scope="col">Epizoda</th>
        <th scope="col">Datum a délka</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($episodes as $episode): ?>
      <tr<?= (string)$episode['status'] === 'pending' ? ' class="table-row--pending"' : '' ?>>
        <td>
          <?php if (!empty($episode['episode_num'])): ?>
            <strong>Epizoda <?= (int)$episode['episode_num'] ?></strong><br>
          <?php endif; ?>
          <strong><?= h((string)$episode['title']) ?></strong><br>
          <small style="color:#555"><?= h((string)$episode['public_path']) ?></small>
          <?php if ($episode['excerpt'] !== ''): ?>
            <br><small style="color:#555"><?= h((string)$episode['excerpt']) ?></small>
          <?php endif; ?>
        </td>
        <td>
          <?php if ((string)$episode['display_date'] !== ''): ?>
            <?= h(formatCzechDate((string)$episode['display_date'])) ?>
          <?php else: ?>
            –
          <?php endif; ?>
          <?php if (trim((string)($episode['duration'] ?? '')) !== ''): ?>
            <br><small style="color:#555"><?= h((string)$episode['duration']) ?></small>
          <?php endif; ?>
        </td>
        <td>
          <?php if ((string)$episode['status'] === 'pending'): ?>
            <strong class="status-badge status-badge--pending">Čeká na schválení</strong>
          <?php elseif (!empty($episode['is_scheduled'])): ?>
            <strong>Naplánováno</strong>
          <?php else: ?>
            Publikováno
          <?php endif; ?>
        </td>
        <td class="actions">
          <a href="podcast_form.php?id=<?= (int)$episode['id'] ?>&amp;show_id=<?= (int)$showId ?>&amp;redirect=<?= rawurlencode($currentListUrl) ?>" class="btn">Upravit</a>
          <?php if ((string)$episode['status'] === 'published' && empty($episode['is_scheduled']) && !empty($show['is_public'])): ?>
            <a href="<?= h((string)$episode['public_path']) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu</a>
          <?php endif; ?>
          <?php if ((string)$episode['status'] === 'pending' && $canApprovePodcast): ?>
            <form action="approve.php" method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="podcasts">
              <input type="hidden" name="id" value="<?= (int)$episode['id'] ?>">
              <input type="hidden" name="redirect" value="<?= h($currentListUrl) ?>">
              <button type="submit" class="btn btn-success">Schválit</button>
            </form>
          <?php endif; ?>
          <form action="podcast_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$episode['id'] ?>">
            <input type="hidden" name="show_id" value="<?= (int)$showId ?>">
            <input type="hidden" name="redirect" value="<?= h($currentListUrl) ?>">
            <button type="submit" class="btn btn-danger"
                    data-confirm="Smazat epizodu?">Smazat</button>
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
