<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('food')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo      = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

$id   = inputInt('get', 'id');
if ($id === null) { header('Location: archive.php'); exit; }

$stmt = $pdo->prepare(
    "SELECT * FROM cms_food_cards
     WHERE id = ? AND status = 'published' AND is_published = 1"
);
$stmt->execute([$id]);
$card = $stmt->fetch();
if (!$card) { header('Location: archive.php'); exit; }

$typeLabels = ['food' => 'Jídelní lístek', 'beverage' => 'Nápojový lístek'];
$typeLabel  = $typeLabels[$card['type']] ?? '';

$from = $card['valid_from'] ? formatCzechDate($card['valid_from']) : null;
$to   = $card['valid_to']   ? formatCzechDate($card['valid_to'])   : null;
$validityStr = '';
if ($from && $to)  $validityStr = $from . ' – ' . $to;
elseif ($from)     $validityStr = 'od ' . $from;
elseif ($to)       $validityStr = 'do ' . $to;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<?= faviconTag() ?>
<?= seoMeta([
    'title'       => h($card['title']) . ' – ' . $siteName,
    'description' => $card['description'] ?: ($typeLabel . ($validityStr ? ', ' . $validityStr : '')),
    'url'         => BASE_URL . '/food/card.php?id=' . $id,
]) ?>
  <title><?= h($card['title']) ?> – <?= h($siteName) ?></title>
  <style>
    .card-meta   { font-size:.85rem; color:#666; margin-bottom:1.5rem; }
    .card-badge  { display:inline-block; background:#060; color:#fff;
                   font-size:.75rem; padding:.15rem .5rem; border-radius:2px;
                   margin-right:.5rem; vertical-align:middle; }
    .food-content { line-height:1.7; }
    .food-content h2, .food-content h3 { margin-top:1.5rem; }
    .back-links  { margin-top:2rem; font-size:.9rem; }
    .back-links a { margin-right:1.5rem; }
  </style>
</head>
<body>
<?= adminBar(BASE_URL . '/admin/food_form.php?id=' . $id) ?>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<header>
  <h1><?= h($siteName) ?></h1>
  <?= siteNav('food') ?>
</header>

<main id="obsah">
  <p style="font-size:.85rem;color:#666">
    <a href="index.php">Jídelní lístek</a> &rsaquo;
    <a href="archive.php">Archiv</a> &rsaquo;
    <?= h($card['title']) ?>
  </p>

  <h2>
    <?php if ($card['is_current']): ?>
      <span class="card-badge"><span aria-hidden="true">&#9733;</span> Aktuální</span>
    <?php endif; ?>
    <?= h($card['title']) ?>
  </h2>

  <p class="card-meta">
    <strong><?= h($typeLabel) ?></strong>
    <?php if ($validityStr): ?>
      <span aria-hidden="true"> · </span> Platnost: <?= h($validityStr) ?>
    <?php endif; ?>
    <?php if (!empty($card['description'])): ?>
      <span aria-hidden="true"> · </span> <?= h($card['description']) ?>
    <?php endif; ?>
  </p>

  <div class="food-content">
    <?php if (!empty($card['content'])): ?>
      <?= $card['content'] ?>
    <?php else: ?>
      <p><em>Obsah tohoto lístku nebyl zadán.</em></p>
    <?php endif; ?>
  </div>

  <div class="back-links">
    <a href="archive.php"><span aria-hidden="true">&larr;</span> Zpět do archivu</a>
    <a href="index.php">Aktuální lístek</a>
  </div>
</main>

<?= siteFooter() ?>
</body>
</html>
