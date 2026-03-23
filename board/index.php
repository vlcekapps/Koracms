<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('board')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo      = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

$items = $pdo->query(
    "SELECT b.id, b.title, b.description, b.posted_date, b.removal_date,
            b.filename, b.original_name, b.file_size,
            COALESCE(c.name, '') AS category_name
     FROM cms_board b
     LEFT JOIN cms_board_categories c ON c.id = b.category_id
     WHERE b.status = 'published' AND b.is_published = 1
     ORDER BY c.sort_order, c.name, b.sort_order, b.posted_date DESC, b.title"
)->fetchAll();

$today   = date('Y-m-d');
$current = [];
$archive = [];
foreach ($items as $d) {
    if ($d['removal_date'] === null || $d['removal_date'] >= $today) {
        $current[] = $d;
    } else {
        $archive[] = $d;
    }
}

// Seskupit podle kategorie
function groupByCategory(array $items): array {
    $grouped = [];
    foreach ($items as $d) {
        $grouped[$d['category_name'] ?: 'Ostatní'][] = $d;
    }
    ksort($grouped);
    return $grouped;
}

$currentGrouped = groupByCategory($current);
$archiveGrouped = groupByCategory($archive);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<?= faviconTag() ?>
<?= seoMeta(['title' => 'Úřední deska – ' . $siteName, 'url' => BASE_URL . '/board/index.php']) ?>
  <title>Úřední deska – <?= h($siteName) ?></title>
<?= publicA11yStyleTag() ?>
</head>
<body>
<?= adminBar(BASE_URL . '/admin/board.php') ?>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<header>
  <h1><?= h($siteName) ?></h1>
  <?= siteNav('board') ?>
</header>

<main id="obsah">
  <h2>Úřední deska</h2>

  <?php if (empty($current) && empty($archive)): ?>
    <p>Žádné dokumenty na úřední desce.</p>
  <?php else: ?>

    <?php if (!empty($current)): ?>
      <?php foreach ($currentGrouped as $category => $files): ?>
        <?php $catId = 'cat-' . slugify($category); ?>
        <?php if (count($currentGrouped) > 1): ?>
          <section aria-labelledby="<?= h($catId) ?>">
            <h3 id="<?= h($catId) ?>"><?= h($category) ?></h3>
        <?php endif; ?>
        <ul>
          <?php foreach ($files as $d): ?>
            <li>
              <strong>
                <?php if ($d['filename'] !== ''): ?>
                  <a href="<?= BASE_URL ?>/uploads/board/<?= rawurlencode($d['filename']) ?>"
                     download="<?= h($d['original_name']) ?>">
                    <?= h($d['title']) ?>
                  </a>
                <?php else: ?>
                  <?= h($d['title']) ?>
                <?php endif; ?>
              </strong>
              <?php if ($d['file_size'] > 0): ?>
                <small style="color:#666">(<?= h(formatFileSize($d['file_size'])) ?>)</small>
              <?php endif; ?>
              <br>
              <small>
                Vyvěšeno: <time datetime="<?= h($d['posted_date']) ?>"><?= h($d['posted_date']) ?></time>
                <?php if ($d['removal_date']): ?>
                  · Sejmuto: <time datetime="<?= h($d['removal_date']) ?>"><?= h($d['removal_date']) ?></time>
                <?php endif; ?>
              </small>
              <?php if (!empty($d['description'])): ?>
                <br><span style="color:#555;font-size:.9rem"><?= renderContent($d['description']) ?></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if (count($currentGrouped) > 1): ?>
          </section>
        <?php endif; ?>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($archive)): ?>
      <details>
        <summary>Archiv (<?= count($archive) ?> dokument<?= count($archive) === 1 ? '' : (count($archive) < 5 ? 'y' : 'ů') ?>)</summary>
        <?php foreach ($archiveGrouped as $category => $files): ?>
          <?php $catId = 'arch-' . slugify($category); ?>
          <?php if (count($archiveGrouped) > 1): ?>
            <section aria-labelledby="<?= h($catId) ?>">
              <h3 id="<?= h($catId) ?>"><?= h($category) ?></h3>
          <?php endif; ?>
          <ul>
            <?php foreach ($files as $d): ?>
              <li>
                <?php if ($d['filename'] !== ''): ?>
                  <a href="<?= BASE_URL ?>/uploads/board/<?= rawurlencode($d['filename']) ?>"
                     download="<?= h($d['original_name']) ?>">
                    <?= h($d['title']) ?>
                  </a>
                <?php else: ?>
                  <?= h($d['title']) ?>
                <?php endif; ?>
                <?php if ($d['file_size'] > 0): ?>
                  <small style="color:#666">(<?= h(formatFileSize($d['file_size'])) ?>)</small>
                <?php endif; ?>
                <br>
                <small>
                  Vyvěšeno: <time datetime="<?= h($d['posted_date']) ?>"><?= h($d['posted_date']) ?></time>
                  <?php if ($d['removal_date']): ?>
                    · Sejmuto: <time datetime="<?= h($d['removal_date']) ?>"><?= h($d['removal_date']) ?></time>
                  <?php endif; ?>
                </small>
              </li>
            <?php endforeach; ?>
          </ul>
          <?php if (count($archiveGrouped) > 1): ?>
            </section>
          <?php endif; ?>
        <?php endforeach; ?>
      </details>
    <?php endif; ?>

  <?php endif; ?>
</main>

<?= siteFooter() ?>
</body>
</html>
