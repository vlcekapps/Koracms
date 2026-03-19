<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo    = db_connect();
$showId = inputInt('get', 'show_id');

// Pokud show_id chybí, přesměrovat na seznam podcastů
if ($showId === null) {
    header('Location: podcast_shows.php'); exit;
}

$showStmt = $pdo->prepare("SELECT * FROM cms_podcast_shows WHERE id = ?");
$showStmt->execute([$showId]);
$show = $showStmt->fetch();
if (!$show) { header('Location: podcast_shows.php'); exit; }

$episodes = $pdo->prepare(
    "SELECT id, title, episode_num, duration, publish_at, created_at,
            COALESCE(status,'published') AS status
     FROM cms_podcasts WHERE show_id = ?
     ORDER BY episode_num DESC, created_at DESC"
);
$episodes->execute([$showId]);
$episodes = $episodes->fetchAll();

adminHeader('Podcast: ' . h($show['title']));
?>
<p>
  <a href="podcast_shows.php"><span aria-hidden="true">←</span> Všechny podcasty</a>
  &nbsp;|&nbsp;
  <a href="podcast_show_form.php?id=<?= (int)$show['id'] ?>">Upravit podcast</a>
  &nbsp;|&nbsp;
  <a href="<?= h(BASE_URL) ?>/podcast/feed.php?slug=<?= h($show['slug']) ?>" target="_blank">RSS feed</a>
</p>

<p><a href="podcast_form.php?show_id=<?= (int)$showId ?>" class="btn">+ Přidat epizodu</a></p>

<?php if (empty($episodes)): ?>
  <p>Žádné epizody.</p>
<?php else: ?>
  <table>
    <thead>
      <tr>
        <th scope="col">Ep.</th>
        <th scope="col">Název</th>
        <th scope="col">Délka</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($episodes as $ep): ?>
      <tr>
        <td><?= $ep['episode_num'] ? (int)$ep['episode_num'] : '–' ?></td>
        <td><?= h($ep['title']) ?></td>
        <td><?= h($ep['duration'] ?: '–') ?></td>
        <td>
          <?php if ($ep['status'] === 'pending'): ?>
            <strong style="color:#c60">⏳ Čeká na schválení</strong>
          <?php elseif ($ep['publish_at'] && strtotime($ep['publish_at']) > time()): ?>
            <small>Naplánováno</small>
          <?php else: ?>
            Publikováno
          <?php endif; ?>
        </td>
        <td class="actions">
          <a href="podcast_form.php?id=<?= (int)$ep['id'] ?>&amp;show_id=<?= (int)$showId ?>" class="btn">Upravit</a>
          <?php if ($ep['status'] === 'pending' && isSuperAdmin()): ?>
            <form action="approve.php" method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="podcasts">
              <input type="hidden" name="id" value="<?= (int)$ep['id'] ?>">
              <input type="hidden" name="redirect" value="<?= h(BASE_URL) ?>/admin/podcast.php?show_id=<?= (int)$showId ?>">
              <button type="submit" class="btn" style="background:#060;color:#fff">Schválit</button>
            </form>
          <?php endif; ?>
          <form action="podcast_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$ep['id'] ?>">
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
