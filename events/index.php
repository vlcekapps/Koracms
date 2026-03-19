<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('events')) {
    header('Location: ' . BASE_URL . '/index.php'); exit;
}

$pdo      = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$perPage  = max(1, (int)getSetting('events_per_page', '10'));

// --- Upcoming events ---
$upPage  = max(1, (int)($_GET['up_page'] ?? 1));
$upTotal = (int)$pdo->query(
    "SELECT COUNT(*) FROM cms_events WHERE status='published' AND is_published=1 AND event_date>=NOW()"
)->fetchColumn();
$upPages  = max(1, (int)ceil($upTotal / $perPage));
$upPage   = min($upPage, $upPages);
$upOffset = ($upPage - 1) * $perPage;

$stmtUp = $pdo->prepare(
    "SELECT * FROM cms_events
     WHERE status='published' AND is_published=1 AND event_date>=NOW()
     ORDER BY event_date ASC LIMIT :lim OFFSET :off"
);
$stmtUp->bindValue(':lim',  $perPage,  PDO::PARAM_INT);
$stmtUp->bindValue(':off',  $upOffset, PDO::PARAM_INT);
$stmtUp->execute();
$upcoming = $stmtUp->fetchAll();

// --- Past events ---
$pastPage  = max(1, (int)($_GET['past_page'] ?? 1));
$pastTotal = (int)$pdo->query(
    "SELECT COUNT(*) FROM cms_events WHERE status='published' AND is_published=1 AND event_date<NOW()"
)->fetchColumn();
$pastPages  = max(1, (int)ceil($pastTotal / $perPage));
$pastPage   = min($pastPage, $pastPages);
$pastOffset = ($pastPage - 1) * $perPage;

$stmtPast = $pdo->prepare(
    "SELECT * FROM cms_events
     WHERE status='published' AND is_published=1 AND event_date<NOW()
     ORDER BY event_date DESC LIMIT :lim OFFSET :off"
);
$stmtPast->bindValue(':lim',  $perPage,    PDO::PARAM_INT);
$stmtPast->bindValue(':off',  $pastOffset, PDO::PARAM_INT);
$stmtPast->execute();
$past = $stmtPast->fetchAll();

function eventsPagerUrl(string $param, int $page, string $otherParam): string {
    $otherVal = (int)($_GET[$otherParam] ?? 1);
    $q = [];
    if ($page     > 1) $q[$param]      = $page;
    if ($otherVal > 1) $q[$otherParam] = $otherVal;
    return 'index.php' . ($q ? '?' . http_build_query($q) : '');
}

function eventsPager(int $current, int $total, string $param, string $otherParam, string $anchor): string {
    if ($total <= 1) return '';
    $html = '<nav class="pager" aria-label="Stránkování" style="margin-top:.75rem">';
    if ($current > 1) {
        $html .= '<a href="' . h(eventsPagerUrl($param, $current - 1, $otherParam)) . '#' . $anchor . '">‹ Předchozí</a> ';
    }
    $html .= '<span aria-current="page">' . $current . '&nbsp;/&nbsp;' . $total . '</span>';
    if ($current < $total) {
        $html .= ' <a href="' . h(eventsPagerUrl($param, $current + 1, $otherParam)) . '#' . $anchor . '">Další ›</a>';
    }
    $html .= '</nav>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<?= faviconTag() ?>
<?= seoMeta(['title' => 'Akce – ' . $siteName, 'url' => BASE_URL . '/events/index.php']) ?>
  <title>Akce – <?= h($siteName) ?></title>
  <style>
    .events-wrap { overflow-x: auto; }
    .events-table { width: 100%; border-collapse: collapse; }
    .events-table caption { text-align: left; font-size: 1.1rem; font-weight: bold; padding: .4rem 0 .6rem; }
    .events-table th,
    .events-table td { padding: .45rem .6rem; border: 1px solid #ddd; vertical-align: top; }
    .events-table thead th { background: #f4f4f4; white-space: nowrap; }
    .events-table tbody tr:nth-child(odd) { background: #fafafa; }
    .events-table tbody tr.desc-row { background: #fff; }
    .events-table tbody tr.desc-row td { border-top: none; padding-top: 0; color: #333; }
    .events-table details summary {
      cursor: pointer;
      color: #06c;
      font-size: .9rem;
      padding: .2rem 0;
      list-style: none; /* Firefox */
    }
    .events-table details summary::-webkit-details-marker { display: none; }
    .events-table details summary::before { content: '▶ ' / ''; font-size: .75rem; }
    .events-table details[open] summary::before { content: '▼ ' / ''; }
    .events-table details .desc-content { margin-top: .4rem; }
    .events-table .col-date { white-space: nowrap; }
    .events-table .col-loc  { white-space: nowrap; }
    .events-past tbody tr { color: #555; }
  </style>
</head>
<body>
<?= adminBar() ?>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<header>
  <h1><?= h($siteName) ?></h1>
  <?= siteNav('events') ?>
</header>

<main id="obsah">
  <h2>Akce a události</h2>

  <?php if ($upTotal === 0 && $pastTotal === 0): ?>
    <p>Zatím žádné akce.</p>
  <?php else: ?>

    <?php if ($upTotal > 0): ?>
    <section aria-labelledby="nadpis-upcoming">
      <h3 id="nadpis-upcoming">Připravované akce</h3>
      <div class="events-wrap">
        <table class="events-table" aria-labelledby="nadpis-upcoming">
          <thead>
            <tr>
              <th scope="col">Název</th>
              <th scope="col" class="col-date">Začátek</th>
              <th scope="col" class="col-date">Konec</th>
              <th scope="col" class="col-loc">Místo</th>
            </tr>
          </thead>
          <?php foreach ($upcoming as $e): ?>
          <tbody>
            <tr>
              <td><?= h($e['title']) ?></td>
              <td class="col-date">
                <time datetime="<?= h(str_replace(' ', 'T', $e['event_date'])) ?>">
                  <?= formatCzechDate($e['event_date']) ?>
                </time>
              </td>
              <td class="col-date">
                <?php if ($e['event_end']): ?>
                  <time datetime="<?= h(str_replace(' ', 'T', $e['event_end'])) ?>">
                    <?= formatCzechDate($e['event_end']) ?>
                  </time>
                <?php else: ?>
                  <span aria-label="bez data konce">–</span>
                <?php endif; ?>
              </td>
              <td class="col-loc"><?= $e['location'] !== '' ? h($e['location']) : '<span aria-label="místo neuvedeno">–</span>' ?></td>
            </tr>
            <?php if (!empty($e['description'])): ?>
            <tr class="desc-row">
              <td colspan="4">
                <details>
                  <summary>Popis akce</summary>
                  <div class="desc-content"><?= renderContent($e['description']) ?></div>
                </details>
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
          <?php endforeach; ?>
        </table>
      </div>
      <?= eventsPager($upPage, $upPages, 'up_page', 'past_page', 'nadpis-upcoming') ?>
    </section>
    <?php endif; ?>

    <?php if ($pastTotal > 0): ?>
    <section aria-labelledby="nadpis-past" style="margin-top:2rem">
      <h3 id="nadpis-past">Proběhlé akce</h3>
      <div class="events-wrap">
        <table class="events-table events-past" aria-labelledby="nadpis-past">
          <thead>
            <tr>
              <th scope="col">Název</th>
              <th scope="col" class="col-date">Začátek</th>
              <th scope="col" class="col-date">Konec</th>
              <th scope="col" class="col-loc">Místo</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($past as $e): ?>
            <tr>
              <td><?= h($e['title']) ?></td>
              <td class="col-date">
                <time datetime="<?= h(str_replace(' ', 'T', $e['event_date'])) ?>">
                  <?= formatCzechDate($e['event_date']) ?>
                </time>
              </td>
              <td class="col-date">
                <?php if ($e['event_end']): ?>
                  <time datetime="<?= h(str_replace(' ', 'T', $e['event_end'])) ?>">
                    <?= formatCzechDate($e['event_end']) ?>
                  </time>
                <?php else: ?>
                  <span aria-label="bez data konce">–</span>
                <?php endif; ?>
              </td>
              <td class="col-loc"><?= $e['location'] !== '' ? h($e['location']) : '<span aria-label="místo neuvedeno">–</span>' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?= eventsPager($pastPage, $pastPages, 'past_page', 'up_page', 'nadpis-past') ?>
    </section>
    <?php endif; ?>

  <?php endif; ?>
</main>

<?= siteFooter() ?>
</body>
</html>
