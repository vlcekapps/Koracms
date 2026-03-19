<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
$counts = [];
foreach ([
    'cms_articles'       => 'Články',
    'cms_news'           => 'Novinky',
    'cms_events'         => 'Události',
    'cms_podcast_shows'  => 'Podcasty',
    'cms_podcasts'       => 'Epizody podcastu',
    'cms_places'         => 'Zajímavá místa',
    'cms_pages'          => 'Statické stránky',
    'cms_downloads'      => 'Soubory ke stažení',
    'cms_food_cards'     => 'Jídelní / nápoj. lístky',
    'cms_gallery_albums' => 'Galerie – alba',
    'cms_gallery_photos' => 'Galerie – fotografie',
    'cms_chat'           => 'Chat zprávy',
    'cms_contact'        => 'Kontaktní zprávy',
] as $tbl => $label) {
    try {
        $counts[$label] = (int)$pdo->query("SELECT COUNT(*) FROM {$tbl}")->fetchColumn();
    } catch (\PDOException $e) {
        $counts[$label] = '–';
    }
}

$pendingComments = 0;
try {
    $pendingComments = (int)$pdo->query("SELECT COUNT(*) FROM cms_comments WHERE is_approved = 0")->fetchColumn();
} catch (\PDOException $e) {}

$pendingContent = [];
$pendingModules = [
    'articles' => ['table' => 'cms_articles', 'label' => 'Článků', 'url' => 'blog.php'],
    'news'     => ['table' => 'cms_news',     'label' => 'Novinek', 'url' => 'news.php'],
    'podcasts' => ['table' => 'cms_podcasts', 'label' => 'Epizod', 'url' => 'podcast.php'],
    'events'   => ['table' => 'cms_events',   'label' => 'Událostí', 'url' => 'events.php'],
    'places'    => ['table' => 'cms_places',    'label' => 'Míst',    'url' => 'places.php'],
    'pages'     => ['table' => 'cms_pages',    'label' => 'Stránek', 'url' => 'pages.php'],
    'downloads' => ['table' => 'cms_downloads','label' => 'Souborů', 'url' => 'downloads.php'],
    'food'      => ['table' => 'cms_food_cards','label' => 'Lístků',  'url' => 'food.php'],
];
foreach ($pendingModules as $key => $cfg) {
    try {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM {$cfg['table']} WHERE status='pending'")->fetchColumn();
        if ($n > 0) $pendingContent[$key] = ['count' => $n, 'label' => $cfg['label'], 'url' => $cfg['url']];
    } catch (\PDOException $e) {}
}

$pages = [];
try {
    $pages = $pdo->query("SELECT title, slug, is_published FROM cms_pages ORDER BY nav_order, title")->fetchAll();
} catch (\PDOException $e) {}

$upcomingEvents = [];
try {
    $upcomingEvents = $pdo->query(
        "SELECT title, event_date, location FROM cms_events
         WHERE is_published = 1 AND event_date >= NOW()
         ORDER BY event_date ASC LIMIT 5"
    )->fetchAll();
} catch (\PDOException $e) {}

$subscriberCount = 0;
try {
    $subscriberCount = (int)$pdo->query("SELECT COUNT(*) FROM cms_subscribers WHERE confirmed = 1")->fetchColumn();
} catch (\PDOException $e) {}

adminHeader('Přehled');
?>
<table>
  <caption>Počty záznamů</caption>
  <thead><tr><th scope="col">Modul</th><th scope="col">Počet</th></tr></thead>
  <tbody>
  <?php foreach ($counts as $label => $count): ?>
    <tr><td><?= h($label) ?></td><td><?= h((string)$count) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php if ($pendingComments > 0): ?>
  <p class="error">
    <strong><?= $pendingComments ?> komentář<?= $pendingComments === 1 ? '' : ($pendingComments < 5 ? 'e' : 'ů') ?> čeká na schválení.</strong>
    <a href="<?= BASE_URL ?>/admin/comments.php?filter=pending">Zobrazit <span aria-hidden="true">→</span></a>
  </p>
<?php endif; ?>

<?php if (!empty($pendingContent)): ?>
  <div style="background:#fffbe6;border:1px solid #e6c800;padding:.75rem 1rem;margin-bottom:1rem">
    <strong>Obsah čekající na schválení:</strong>
    <ul style="margin:.4rem 0 0;padding-left:1.2rem">
      <?php foreach ($pendingContent as $cfg): ?>
        <li>
          <?= $cfg['count'] ?> <?= h($cfg['label']) ?>
          <span aria-hidden="true">→</span> <a href="<?= BASE_URL ?>/admin/<?= h($cfg['url']) ?>"><?= h($cfg['url'] === 'blog.php' ? 'Blog' : ucfirst(str_replace('.php','', $cfg['url']))) ?></a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<h2>Statické stránky</h2>
<?php if (empty($pages)): ?>
  <p>Zatím žádné statické stránky. <a href="page_form.php">Vytvořit <span aria-hidden="true">→</span></a></p>
<?php else: ?>
  <ul>
    <?php foreach ($pages as $p): ?>
      <li>
        <a href="<?= BASE_URL ?>/page.php?slug=<?= rawurlencode($p['slug']) ?>"
           target="_blank" rel="noopener"><?= h($p['title']) ?></a>
        <?= $p['is_published'] ? '' : ' <em>(koncept)</em>' ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <p><a href="pages.php">Spravovat stránky <span aria-hidden="true">→</span></a></p>
<?php endif; ?>

<?php if (!empty($upcomingEvents)): ?>
<h2>Nadcházející akce</h2>
<ul>
  <?php foreach ($upcomingEvents as $e): ?>
    <li>
      <strong><?= h($e['title']) ?></strong>
      – <time datetime="<?= h(str_replace(' ', 'T', $e['event_date'])) ?>"><?= h($e['event_date']) ?></time>
      <?= $e['location'] ? '· ' . h($e['location']) : '' ?>
    </li>
  <?php endforeach; ?>
</ul>
<p><a href="events.php">Všechny události <span aria-hidden="true">→</span></a></p>
<?php endif; ?>

<?php if ($subscriberCount > 0): ?>
<p>
  Newsletter: <strong><?= $subscriberCount ?></strong> potvrzených odběratelů.
  <a href="newsletter.php">Správa <span aria-hidden="true">→</span></a>
</p>
<?php endif; ?>

<h2>Povolené moduly</h2>
<ul>
  <?php foreach (['blog' => 'Blog', 'news' => 'Novinky', 'chat' => 'Chat', 'contact' => 'Kontakt', 'events' => 'Události', 'podcast' => 'Podcast', 'places' => 'Zajímavá místa', 'food' => 'Jídelní lístek', 'gallery' => 'Galerie', 'newsletter' => 'Newsletter', 'downloads' => 'Ke stažení'] as $k => $label): ?>
    <li><?= h($label) ?>: <strong><?= isModuleEnabled($k) ? 'zapnuto' : 'vypnuto' ?></strong></li>
  <?php endforeach; ?>
</ul>
<p><a href="settings.php">Změnit nastavení <span aria-hidden="true">→</span></a></p>
<?php adminFooter(); ?>
