<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('food')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo      = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

// Načtení aktuálních lístků
$foodCard = $pdo->query(
    "SELECT * FROM cms_food_cards
     WHERE type = 'food' AND is_current = 1 AND status = 'published' AND is_published = 1
     LIMIT 1"
)->fetch() ?: null;

$beverageCard = $pdo->query(
    "SELECT * FROM cms_food_cards
     WHERE type = 'beverage' AND is_current = 1 AND status = 'published' AND is_published = 1
     LIMIT 1"
)->fetch() ?: null;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<?= faviconTag() ?>
<?= seoMeta(['title' => 'Jídelní a nápojový lístek – ' . $siteName, 'url' => BASE_URL . '/food/index.php']) ?>
  <title>Jídelní a nápojový lístek – <?= h($siteName) ?></title>
  <style>
    .food-tabs { display:flex; gap:.5rem; margin-bottom:1.5rem; flex-wrap:wrap; }
    .food-tab  { padding:.4rem 1.2rem; border:2px solid #ccc; background:#f8f8f8;
                 cursor:pointer; border-radius:3px; font-size:1rem; }
    .food-tab[aria-selected="true"] { border-color:#333; background:#333; color:#fff; }
    .food-panel { display:none; }
    .food-panel.active { display:block; }
    .food-meta  { font-size:.85rem; color:#666; margin-bottom:1rem; }
    .food-content { line-height:1.7; }
    .food-content h2, .food-content h3 { margin-top:1.5rem; }
    .food-archive-link { margin-top:2rem; }
  </style>
</head>
<body>
<?= adminBar(BASE_URL . '/admin/food.php') ?>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<header>
  <h1><?= h($siteName) ?></h1>
  <?= siteNav('food') ?>
</header>

<main id="obsah">
  <h2>Jídelní a nápojový lístek</h2>

  <div class="food-tabs" role="tablist">
    <button class="food-tab" role="tab" aria-selected="true"  aria-controls="panel-food"
            id="tab-food"     onclick="switchTab('food')">Jídelní lístek</button>
    <button class="food-tab" role="tab" aria-selected="false" aria-controls="panel-beverage"
            id="tab-beverage" onclick="switchTab('beverage')">Nápojový lístek</button>
  </div>

  <div id="panel-food" class="food-panel active" role="tabpanel" aria-labelledby="tab-food">
    <?php if ($foodCard): ?>
      <h3><?= h($foodCard['title']) ?></h3>
      <p class="food-meta">
        <?php
        $from = $foodCard['valid_from'] ? formatCzechDate($foodCard['valid_from']) : null;
        $to   = $foodCard['valid_to']   ? formatCzechDate($foodCard['valid_to'])   : null;
        if ($from || $to) {
            echo 'Platnost: ';
            if ($from && $to)  echo h($from) . ' – ' . h($to);
            elseif ($from)     echo 'od ' . h($from);
            elseif ($to)       echo 'do ' . h($to);
        }
        ?>
        <?php if (!empty($foodCard['description'])): ?>
          <?= ($from || $to) ? '<span aria-hidden="true"> | </span>' : '' ?>
          <?= h($foodCard['description']) ?>
        <?php endif; ?>
      </p>
      <div class="food-content">
        <?= renderContent($foodCard['content']) ?>
      </div>
    <?php else: ?>
      <p>Aktuální jídelní lístek zatím není k dispozici.</p>
    <?php endif; ?>
  </div>

  <div id="panel-beverage" class="food-panel" role="tabpanel" aria-labelledby="tab-beverage">
    <?php if ($beverageCard): ?>
      <h3><?= h($beverageCard['title']) ?></h3>
      <p class="food-meta">
        <?php
        $from = $beverageCard['valid_from'] ? formatCzechDate($beverageCard['valid_from']) : null;
        $to   = $beverageCard['valid_to']   ? formatCzechDate($beverageCard['valid_to'])   : null;
        if ($from || $to) {
            echo 'Platnost: ';
            if ($from && $to)  echo h($from) . ' – ' . h($to);
            elseif ($from)     echo 'od ' . h($from);
            elseif ($to)       echo 'do ' . h($to);
        }
        ?>
        <?php if (!empty($beverageCard['description'])): ?>
          <?= ($from || $to) ? '<span aria-hidden="true"> | </span>' : '' ?>
          <?= h($beverageCard['description']) ?>
        <?php endif; ?>
      </p>
      <div class="food-content">
        <?= renderContent($beverageCard['content']) ?>
      </div>
    <?php else: ?>
      <p>Aktuální nápojový lístek zatím není k dispozici.</p>
    <?php endif; ?>
  </div>

  <p class="food-archive-link">
    <a href="archive.php"><span aria-hidden="true">&#128203;</span> Archiv jídelních a nápojových lístků</a>
  </p>
</main>

<script>
function switchTab(type) {
    document.querySelectorAll('.food-tab').forEach(function(btn) {
        var active = btn.id === 'tab-' + type;
        btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    document.querySelectorAll('.food-panel').forEach(function(panel) {
        panel.classList.toggle('active', panel.id === 'panel-' + type);
    });
}
// Otevřít záložku z URL hash
(function() {
    var hash = window.location.hash.replace('#', '');
    if (hash === 'napojovy' || hash === 'beverage') switchTab('beverage');
})();
</script>

<?= siteFooter() ?>
</body>
</html>
