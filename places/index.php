<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('places')) {
    header('Location: ' . BASE_URL . '/index.php'); exit;
}

$pdo      = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

$places = $pdo->query(
    "SELECT * FROM cms_places WHERE status = 'published' AND is_published = 1 ORDER BY sort_order, name"
)->fetchAll();

// Seskupit podle kategorie
$grouped = [];
foreach ($places as $p) {
    $grouped[$p['category'] ?: 'Ostatní'][] = $p;
}
ksort($grouped);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<?= faviconTag() ?>
<?= seoMeta(['title' => 'Zajímavá místa – ' . $siteName, 'url' => BASE_URL . '/places/index.php']) ?>
  <title>Zajímavá místa – <?= h($siteName) ?></title>
<?= publicA11yStyleTag() ?>
</head>
<body>
<?= adminBar() ?>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<header>
  <h1><?= h($siteName) ?></h1>
  <?= siteNav('places') ?>
</header>

<main id="obsah">
  <h2>Zajímavá místa</h2>

  <?php if (empty($places)): ?>
    <p>Zatím žádná místa.</p>
  <?php else: ?>
    <?php foreach ($grouped as $category => $items): ?>
      <section aria-labelledby="cat-<?= h(slugify($category)) ?>">
        <h3 id="cat-<?= h(slugify($category)) ?>"><?= h($category) ?></h3>
        <ul>
          <?php foreach ($items as $p): ?>
            <li id="place-<?= (int)$p['id'] ?>">
              <?php if ($p['url'] !== ''): ?>
                <a href="<?= h($p['url']) ?>" rel="noopener noreferrer" target="_blank">
                  <?= h($p['name']) ?>
                </a>
              <?php else: ?>
                <strong><?= h($p['name']) ?></strong>
              <?php endif; ?>
              <?php if (!empty($p['description'])): ?>
                <br><span style="color:#555"><?= renderContent($p['description']) ?></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
</main>

<?= siteFooter() ?>
</body>
</html>
