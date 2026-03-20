<?php
require_once __DIR__ . '/../db.php';
header('Cache-Control: no-cache, no-store, must-revalidate');
checkMaintenanceMode();

if (!isModuleEnabled('reservations')) {
    header('Location: ' . BASE_URL . '/index.php'); exit;
}

$pdo      = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

$slotModeLabels = [
    'slots'    => 'Pevné časy',
    'range'    => 'Volný rozsah',
    'duration' => 'Pevná délka',
];

// Load active resources grouped by category
$resources = $pdo->query(
    "SELECT r.*, c.name AS category_name, c.sort_order AS cat_sort
     FROM cms_res_resources r
     LEFT JOIN cms_res_categories c ON c.id = r.category_id
     WHERE r.is_active = 1
     ORDER BY c.sort_order IS NULL, c.sort_order, c.name, r.name"
)->fetchAll();

// Group by category
$grouped = [];
foreach ($resources as $r) {
    $catName = $r['category_name'] ?? null;
    $key = $catName !== null ? $catName : '__uncategorized__';
    $grouped[$key][] = $r;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<?= faviconTag() ?>
<?= seoMeta(['title' => 'Rezervace – ' . $siteName, 'url' => BASE_URL . '/reservations/index.php']) ?>
  <title>Rezervace – <?= h($siteName) ?></title>
  <style>
    .skip-link { position: absolute; left: -9999px; }
    .skip-link:focus { left: 1rem; top: 1rem; z-index: 9999;
      background: #fff; padding: .5rem 1rem; border: 2px solid #000; }
    .res-card { border: 1px solid #ddd; border-radius: 6px; padding: 1rem 1.2rem; margin-bottom: 1rem; }
    .res-card h3 { margin: 0 0 .3rem; }
    .res-meta { font-size: .85rem; color: #666; margin-bottom: .5rem; }
  </style>
</head>
<body>
<?= adminBar() ?>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<header>
  <h1><?= h($siteName) ?></h1>
  <?= siteNav('reservations') ?>
</header>

<main id="obsah">
  <h2>Rezervace</h2>

  <?php if (empty($resources)): ?>
    <p>Momentálně nejsou k dispozici žádné rezervovatelné prostory.</p>
  <?php else: ?>

    <?php foreach ($grouped as $catName => $items): ?>
      <?php if ($catName !== '__uncategorized__'): ?>
        <section aria-labelledby="cat-<?= h(slugify($catName)) ?>">
          <h3 id="cat-<?= h(slugify($catName)) ?>"><?= h($catName) ?></h3>
      <?php else: ?>
        <section aria-label="Ostatní">
      <?php endif; ?>

      <?php foreach ($items as $r):
          $excerpt = mb_strlen($r['description'] ?? '') > 200
              ? mb_substr($r['description'], 0, 200, 'UTF-8') . '...'
              : ($r['description'] ?? '');
          $modeLabel = $slotModeLabels[$r['slot_mode']] ?? h($r['slot_mode']);
      ?>
        <article class="res-card">
          <h3><a href="<?= h(BASE_URL) ?>/reservations/resource.php?slug=<?= rawurlencode($r['slug']) ?>"><?= h($r['name']) ?></a></h3>
          <?php if ($excerpt !== ''): ?>
            <p><?= h($excerpt) ?></p>
          <?php endif; ?>
          <?php
          $locStmt = $pdo->prepare(
              "SELECT l.name FROM cms_res_locations l
               JOIN cms_res_resource_locations rl ON rl.location_id = l.id
               WHERE rl.resource_id = ? ORDER BY l.name"
          );
          $locStmt->execute([(int)$r['id']]);
          $resLocs = $locStmt->fetchAll(PDO::FETCH_COLUMN);
          ?>
          <p class="res-meta">
            <?php if (!empty($resLocs)): ?>
              Místo: <?= h(implode(', ', $resLocs)) ?> &middot;
            <?php endif; ?>
            Typ: <?= $modeLabel ?>
          </p>
        </article>
      <?php endforeach; ?>

      </section>
    <?php endforeach; ?>

  <?php endif; ?>
</main>

<?= siteFooter() ?>
</body>
</html>
