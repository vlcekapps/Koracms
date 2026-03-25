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

$q = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$allowedStatusFilters = ['all', 'published', 'pending', 'scheduled'];
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = 'all';
}

$whereParts = ['p.show_id = ?'];
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

$episodesStmt = $pdo->prepare(
    "SELECT p.*, s.slug AS show_slug, s.title AS show_title
     FROM cms_podcasts p
     INNER JOIN cms_podcast_shows s ON s.id = p.show_id
     {$whereSql}
     ORDER BY COALESCE(p.episode_num, 0) DESC, COALESCE(p.publish_at, p.created_at) DESC, p.id DESC"
);
$episodesStmt->execute($params);
$episodes = array_map(
    static fn(array $episode): array => hydratePodcastEpisodePresentation($episode),
    $episodesStmt->fetchAll()
);

$canApprovePodcast = currentUserHasCapability('content_approve_shared');

adminHeader('Podcast: ' . (string)$show['title']);
?>
<p>
  <a href="podcast_shows.php"><span aria-hidden="true">&larr;</span> Všechny podcasty</a>
  &nbsp;|&nbsp;
  <a href="podcast_show_form.php?id=<?= (int)$show['id'] ?>">Upravit podcast</a>
  &nbsp;|&nbsp;
  <a href="<?= h((string)$show['public_path']) ?>" target="_blank" rel="noopener noreferrer">Veřejná stránka</a>
  &nbsp;|&nbsp;
  <a href="<?= h(BASE_URL . '/podcast/feed.php?slug=' . rawurlencode((string)$show['slug'])) ?>" target="_blank" rel="noopener noreferrer">RSS feed</a>
</p>

<p><a href="podcast_form.php?show_id=<?= (int)$showId ?>" class="btn">+ Přidat epizodu</a></p>

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
  <button type="submit" class="btn">Filtrovat</button>
  <?php if ($q !== '' || $statusFilter !== 'all'): ?>
    <a href="podcast.php?show_id=<?= (int)$showId ?>" class="btn">Zrušit</a>
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
  <table>
    <caption>Epizody podcastu</caption>
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
          <a href="podcast_form.php?id=<?= (int)$episode['id'] ?>&amp;show_id=<?= (int)$showId ?>" class="btn">Upravit</a>
          <?php if ((string)$episode['status'] === 'published' && empty($episode['is_scheduled'])): ?>
            <a href="<?= h((string)$episode['public_path']) ?>" target="_blank" rel="noopener noreferrer">Veřejná stránka</a>
          <?php endif; ?>
          <?php if ((string)$episode['status'] === 'pending' && $canApprovePodcast): ?>
            <form action="approve.php" method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="podcasts">
              <input type="hidden" name="id" value="<?= (int)$episode['id'] ?>">
              <input type="hidden" name="redirect" value="<?= h(BASE_URL) ?>/admin/podcast.php?show_id=<?= (int)$showId ?>">
              <button type="submit" class="btn btn-success">Schválit</button>
            </form>
          <?php endif; ?>
          <form action="podcast_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$episode['id'] ?>">
            <input type="hidden" name="show_id" value="<?= (int)$showId ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat epizodu?')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>


<?php adminFooter(); ?>
