<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('downloads')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo      = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

$items = $pdo->query(
    "SELECT id, title, category, description, filename, original_name, file_size, created_at
     FROM cms_downloads
     WHERE status = 'published' AND is_published = 1
     ORDER BY category, sort_order, title"
)->fetchAll();

// Seskupit podle kategorie
$grouped = [];
foreach ($items as $d) {
    $grouped[$d['category'] ?: 'Ostatní'][] = $d;
}
ksort($grouped);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<?= faviconTag() ?>
<?= seoMeta(['title' => 'Ke stažení – ' . $siteName, 'url' => BASE_URL . '/downloads/index.php']) ?>
  <title>Ke stažení – <?= h($siteName) ?></title>
</head>
<body>
<?= adminBar(BASE_URL . '/admin/downloads.php') ?>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<header>
  <h1><?= h($siteName) ?></h1>
  <?= siteNav('downloads') ?>
</header>

<main id="obsah">
  <h2>Ke stažení</h2>

  <?php if (empty($items)): ?>
    <p>Zatím žádné soubory ke stažení.</p>
  <?php else: ?>
    <?php foreach ($grouped as $category => $files): ?>
      <?php if (count($grouped) > 1): ?>
        <h3><?= h($category) ?></h3>
      <?php endif; ?>
      <ul>
        <?php foreach ($files as $d): ?>
          <li>
            <?php if ($d['filename'] !== ''): ?>
              <a href="<?= BASE_URL ?>/uploads/downloads/<?= rawurlencode($d['filename']) ?>"
                 download="<?= h($d['original_name']) ?>">
                <?= h($d['title']) ?>
              </a>
            <?php else: ?>
              <?= h($d['title']) ?>
            <?php endif; ?>
            <?php if ($d['file_size'] > 0): ?>
              <small style="color:#666">(<?= h(formatFileSize($d['file_size'])) ?>)</small>
            <?php endif; ?>
            <?php if (!empty($d['description'])): ?>
              <br><span style="color:#555;font-size:.9rem"><?= h($d['description']) ?></span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
  <?php endif; ?>
</main>

<?= siteFooter() ?>
</body>
</html>
