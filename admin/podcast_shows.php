<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();

$shows = $pdo->query(
    "SELECT s.*, COUNT(e.id) AS episode_count
     FROM cms_podcast_shows s
     LEFT JOIN cms_podcasts e ON e.show_id = s.id
     GROUP BY s.id
     ORDER BY s.title ASC"
)->fetchAll();

adminHeader('Podcasty');
?>
<p><a href="podcast_show_form.php" class="btn">+ Přidat podcast</a></p>

<?php if (empty($shows)): ?>
  <p>Žádné podcasty. <a href="podcast_show_form.php">Přidejte první podcast.</a></p>
<?php else: ?>
  <table>
    <thead>
      <tr>
        <th scope="col">Název</th>
        <th scope="col">Autor</th>
        <th scope="col">Epizody</th>
        <th scope="col">RSS feed</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($shows as $show): ?>
      <tr>
        <td><strong><?= h($show['title']) ?></strong></td>
        <td><?= h($show['author'] ?: '–') ?></td>
        <td>
          <a href="podcast.php?show_id=<?= (int)$show['id'] ?>">
            <?= (int)$show['episode_count'] ?> epizod<?= $show['episode_count'] === '1' ? 'a' : ($show['episode_count'] >= 2 && $show['episode_count'] <= 4 ? 'y' : '') ?>
          </a>
        </td>
        <td>
          <a href="<?= h(BASE_URL) ?>/podcast/feed.php?slug=<?= h($show['slug']) ?>"
             target="_blank" aria-label="RSS feed – <?= h($show['title']) ?>">RSS</a>
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
