<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
autoCompleteBookings();
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
    'cms_polls'          => 'Ankety',
    'cms_faqs'           => 'FAQ otázky',
    'cms_board'          => 'Úřední deska',
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
    'board'     => ['table' => 'cms_board',     'label' => 'Dokumentů','url' => 'board.php'],
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

$resUpcoming = 0;
$resPending  = 0;
if (isModuleEnabled('reservations')) {
    try {
        $resUpcoming = (int)$pdo->query(
            "SELECT COUNT(*) FROM cms_res_bookings
             WHERE status IN ('pending','confirmed')
               AND booking_date >= CURDATE()
               AND booking_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
        )->fetchColumn();
    } catch (\PDOException $e) {}
    try {
        $resPending = (int)$pdo->query(
            "SELECT COUNT(*) FROM cms_res_bookings WHERE status = 'pending'"
        )->fetchColumn();
    } catch (\PDOException $e) {}
}

adminHeader('Přehled');

// ── Statistiky návštěvnosti ─────────────────────────────────────────────────
if (isModuleEnabled('statistics') && getSetting('visitor_tracking_enabled', '0') === '1'):
    statsCleanup();
    $vs = getVisitorStats();
    $fmt = fn(int $n) => number_format($n, 0, ',', "\u{00a0}");

    // Posledních 7 dní – data pro graf
    $days7 = [];
    try {
        $rows = $pdo->query(
            "SELECT DATE(created_at) AS d, COUNT(*) AS views
             FROM cms_page_views
             WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
             GROUP BY DATE(created_at)
             ORDER BY d"
        )->fetchAll();
        foreach ($rows as $r) $days7[$r['d']] = (int)$r['views'];
    } catch (\PDOException $e) {}

    // Doplnit chybějící dny
    $chartData = [];
    $maxViews  = 1;
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $v    = $days7[$date] ?? 0;
        $chartData[] = ['date' => $date, 'label' => date('j.n.', strtotime($date)), 'views' => $v];
        if ($v > $maxViews) $maxViews = $v;
    }
?>
<section aria-labelledby="stat-heading" style="margin-bottom:1.5rem">
  <h2 id="stat-heading">Návštěvnost</h2>
  <div style="display:flex;gap:1.5rem;flex-wrap:wrap;margin-bottom:1rem" role="list" aria-label="Souhrn návštěvnosti">
    <div role="listitem" style="background:#f0f7ff;padding:.75rem 1.25rem;border-radius:4px;min-width:110px;text-align:center">
      <div style="font-size:1.5rem;font-weight:bold"><?= $fmt($vs['online']) ?></div>
      <div style="font-size:.85rem;color:#555">Online</div>
    </div>
    <div role="listitem" style="background:#f0fff0;padding:.75rem 1.25rem;border-radius:4px;min-width:110px;text-align:center">
      <div style="font-size:1.5rem;font-weight:bold"><?= $fmt($vs['today']) ?></div>
      <div style="font-size:.85rem;color:#555">Dnes</div>
    </div>
    <div role="listitem" style="background:#fffff0;padding:.75rem 1.25rem;border-radius:4px;min-width:110px;text-align:center">
      <div style="font-size:1.5rem;font-weight:bold"><?= $fmt($vs['month']) ?></div>
      <div style="font-size:.85rem;color:#555">Tento měsíc</div>
    </div>
    <div role="listitem" style="background:#fff0f0;padding:.75rem 1.25rem;border-radius:4px;min-width:110px;text-align:center">
      <div style="font-size:1.5rem;font-weight:bold"><?= $fmt($vs['total']) ?></div>
      <div style="font-size:.85rem;color:#555">Celkem</div>
    </div>
  </div>

  <?php if (!empty($chartData)): ?>
  <figure style="margin:0">
    <figcaption class="sr-only">Návštěvnost za posledních 7 dní</figcaption>
    <div style="display:flex;align-items:flex-end;gap:4px;height:100px" aria-hidden="true">
      <?php foreach ($chartData as $d): ?>
        <div style="flex:1;background:#005fcc;min-height:2px;height:<?= round($d['views'] / $maxViews * 100) ?>%"
             title="<?= h($d['label']) ?>: <?= $d['views'] ?> zobrazení"></div>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;gap:4px" aria-hidden="true">
      <?php foreach ($chartData as $d): ?>
        <span style="flex:1;text-align:center;font-size:.7rem;color:#666"><?= h($d['label']) ?></span>
      <?php endforeach; ?>
    </div>
    <table class="sr-only">
      <caption>Návštěvnost za posledních 7 dní</caption>
      <thead><tr><th scope="col">Den</th><th scope="col">Zobrazení</th></tr></thead>
      <tbody>
      <?php foreach ($chartData as $d): ?>
        <tr><td><?= h($d['label']) ?></td><td><?= $d['views'] ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </figure>
  <?php endif; ?>

  <?php
    // Nejčtenější články (top 5)
    if (isModuleEnabled('blog')):
        $topArticles = [];
        try {
            $topArticles = $pdo->query(
                "SELECT title, view_count FROM cms_articles
                 WHERE status = 'published' AND view_count > 0
                 ORDER BY view_count DESC LIMIT 5"
            )->fetchAll();
        } catch (\PDOException $e) {}
        if (!empty($topArticles)):
  ?>
  <h3>Nejčtenější články</h3>
  <ol>
    <?php foreach ($topArticles as $a): ?>
      <li><?= h($a['title']) ?> <small>(<?= (int)$a['view_count'] ?> zobrazení)</small></li>
    <?php endforeach; ?>
  </ol>
  <?php endif; endif; ?>

  <p><a href="statistics.php">Podrobné statistiky <span aria-hidden="true">→</span></a></p>
</section>
<?php endif; ?>

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

<?php if (isModuleEnabled('reservations')): ?>
<h2>Rezervace</h2>
<ul>
  <li>Nadcházejících rezervací (7 dní): <strong><?= $resUpcoming ?></strong></li>
  <li>Čekajících na schválení: <strong><?= $resPending ?></strong></li>
</ul>
<p><a href="res_bookings.php">Správa rezervací <span aria-hidden="true">→</span></a></p>
<?php endif; ?>

<h2>Povolené moduly</h2>
<ul>
  <?php foreach (['blog' => 'Blog', 'news' => 'Novinky', 'chat' => 'Chat', 'contact' => 'Kontakt', 'events' => 'Události', 'podcast' => 'Podcast', 'places' => 'Zajímavá místa', 'food' => 'Jídelní lístek', 'gallery' => 'Galerie', 'newsletter' => 'Newsletter', 'downloads' => 'Ke stažení', 'polls' => 'Ankety', 'faq' => 'FAQ', 'board' => 'Úřední deska', 'reservations' => 'Rezervace', 'statistics' => 'Statistiky'] as $k => $label): ?>
    <li><?= h($label) ?>: <strong><?= isModuleEnabled($k) ? 'zapnuto' : 'vypnuto' ?></strong></li>
  <?php endforeach; ?>
</ul>
<p><a href="settings.php">Změnit nastavení <span aria-hidden="true">→</span></a></p>
<?php adminFooter(); ?>
