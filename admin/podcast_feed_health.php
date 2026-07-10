<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro kontrolu podcastů nemáte potřebné oprávnění.');
requireModuleEnabled('podcast');

$pdo = db_connect();
$showId = inputInt('get', 'show_id');
if ($showId === null || $showId < 1) {
    header('Location: ' . BASE_URL . '/admin/podcast_shows.php');
    exit;
}

$showStmt = $pdo->prepare("SELECT * FROM cms_podcast_shows WHERE id = ? AND deleted_at IS NULL LIMIT 1");
$showStmt->execute([$showId]);
$show = $showStmt->fetch();
if (!$show) {
    header('Location: ' . BASE_URL . '/admin/podcast_shows.php');
    exit;
}

$episodesStmt = $pdo->prepare(
    "SELECT * FROM cms_podcasts p
     WHERE p.show_id = ?
       AND COALESCE(p.block_from_feed, 0) = 0
       AND " . podcastEpisodePublicVisibilitySql('p') . "
     ORDER BY COALESCE(p.publish_at, p.created_at) DESC, p.id DESC"
);
$episodesStmt->execute([$showId]);
$episodes = $episodesStmt->fetchAll();
$issues = podcastFeedHealthIssues($show, $episodes);
$errorCount = count(array_filter($issues, static fn (array $issue): bool => $issue['severity'] === 'error'));
$warningCount = count($issues) - $errorCount;
$feedUrl = BASE_URL . '/podcast/feed.php?slug=' . rawurlencode((string)$show['slug']);

adminHeader('Kontrola RSS feedu: ' . (string)$show['title']);
?>
<p><a href="podcast.php?show_id=<?= (int)$showId ?>"><span aria-hidden="true">&larr;</span> Zpět na epizody podcastu</a></p>

<section aria-labelledby="podcast-feed-health-heading">
  <h2 id="podcast-feed-health-heading">Stav podcastového feedu</h2>
  <p>
    <a href="<?= h($feedUrl) ?>" target="_blank" rel="noopener noreferrer">Otevřít RSS feed<?= newWindowLinkSrOnlySuffix() ?></a>
  </p>

  <?php if ($issues === []): ?>
    <p class="success" role="status">RSS feed je připravený: nebyly nalezeny žádné známé chyby ani varování.</p>
  <?php else: ?>
    <p role="status"><?= $errorCount ?> chyb, <?= $warningCount ?> varování.</p>
    <div class="table-responsive">
      <table>
        <caption>Nálezy kontroly RSS feedu</caption>
        <thead><tr><th scope="col">Závažnost</th><th scope="col">Nález</th><th scope="col">Akce</th></tr></thead>
        <tbody>
        <?php foreach ($issues as $issue): ?>
          <tr>
            <td><strong><?= $issue['severity'] === 'error' ? 'Chyba' : 'Varování' ?></strong></td>
            <td><?= h($issue['message']) ?></td>
            <td>
              <?php if ($issue['episode_id'] > 0): ?>
                <a href="podcast_form.php?id=<?= (int)$issue['episode_id'] ?>&amp;show_id=<?= (int)$showId ?>">Upravit epizodu</a>
              <?php else: ?>
                <a href="podcast_show_form.php?id=<?= (int)$showId ?>">Upravit podcast</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php adminFooter(); ?>
