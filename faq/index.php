<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('faq')) {
    header('Location: ' . BASE_URL . '/index.php'); exit;
}

$pdo      = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

$faqs = $pdo->query(
    "SELECT f.id, f.question, f.answer, f.category_id, c.name AS category_name, c.sort_order AS cat_sort
     FROM cms_faqs f
     LEFT JOIN cms_faq_categories c ON c.id = f.category_id
     WHERE f.is_published = 1
     ORDER BY c.sort_order, c.name, f.sort_order, f.id"
)->fetchAll();

// Group by category
$grouped = [];
foreach ($faqs as $f) {
    $catName = $f['category_name'] ?: 'Ostatní';
    $grouped[$catName][] = $f;
}

$multipleCategories = count($grouped) > 1;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<?= faviconTag() ?>
<?= seoMeta(['title' => 'FAQ – ' . $siteName, 'url' => BASE_URL . '/faq/index.php']) ?>
  <title>FAQ – <?= h($siteName) ?></title>
  <style>
    .faq-section { margin-bottom: 2rem; }
    .faq-item { border: 1px solid #ddd; border-radius: 6px; margin-bottom: .5rem; }
    .faq-item summary {
      cursor: pointer;
      padding: .75rem 1rem;
      font-weight: bold;
      list-style: none;
      position: relative;
      padding-right: 2.5rem;
    }
    .faq-item summary::-webkit-details-marker { display: none; }
    .faq-item summary::after {
      content: '+';
      position: absolute;
      right: 1rem;
      top: 50%;
      transform: translateY(-50%);
      font-size: 1.3rem;
      font-weight: normal;
      color: #666;
    }
    .faq-item[open] summary::after { content: '\2212'; }
    .faq-item summary:hover { background: #f8f8f8; }
    .faq-item summary:focus-visible {
      outline: 3px solid #005fcc;
      outline-offset: -3px;
      border-radius: 6px;
    }
    .faq-answer { padding: 0 1rem 1rem; line-height: 1.6; }
    .faq-nav { display: flex; gap: .75rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
    .faq-nav a { text-decoration: none; padding: .3rem .6rem; border: 1px solid #ccc; border-radius: 4px; font-size: .9rem; }
    .faq-nav a:hover { background: #f0f0f0; }
  </style>
</head>
<body>
<?= adminBar() ?>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<header>
  <h1><?= h($siteName) ?></h1>
  <?= siteNav('faq') ?>
</header>

<main id="obsah">
  <h2>Často kladené otázky</h2>

  <?php if (empty($faqs)): ?>
    <p>Žádné otázky.</p>
  <?php else: ?>

    <?php if ($multipleCategories): ?>
      <nav class="faq-nav" aria-label="Kategorie otázek">
        <?php $catIndex = 0; foreach ($grouped as $catName => $items): ?>
          <a href="#faq-kat-<?= $catIndex ?>"><?= h($catName) ?> (<?= count($items) ?>)</a>
        <?php $catIndex++; endforeach; ?>
      </nav>
    <?php endif; ?>

    <?php $catIndex = 0; foreach ($grouped as $catName => $items): ?>
      <section class="faq-section" <?= $multipleCategories ? 'aria-labelledby="faq-kat-' . $catIndex . '"' : '' ?>>
        <?php if ($multipleCategories): ?>
          <h3 id="faq-kat-<?= $catIndex ?>"><?= h($catName) ?></h3>
        <?php endif; ?>

        <?php foreach ($items as $f): ?>
          <details class="faq-item">
            <summary><?= h($f['question']) ?></summary>
            <div class="faq-answer"><?= $f['answer'] ?></div>
          </details>
        <?php endforeach; ?>
      </section>
    <?php $catIndex++; endforeach; ?>

  <?php endif; ?>
</main>

<?= siteFooter() ?>
</body>
</html>
