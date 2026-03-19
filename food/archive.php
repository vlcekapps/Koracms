<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('food')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo      = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

// Filtrování
$filterType = $_GET['typ'] ?? 'vse';
if (!in_array($filterType, ['food', 'beverage', 'vse'])) $filterType = 'vse';

$where  = "WHERE status = 'published' AND is_published = 1";
$params = [];
if ($filterType !== 'vse') {
    $where   .= " AND type = ?";
    $params[] = $filterType;
}

$cards = $pdo->prepare(
    "SELECT id, type, title, description, valid_from, valid_to, is_current, created_at
     FROM cms_food_cards
     {$where}
     ORDER BY is_current DESC, COALESCE(valid_from, created_at) DESC"
);
$cards->execute($params);
$cards = $cards->fetchAll();

$typeLabels = ['food' => 'Jídelní lístek', 'beverage' => 'Nápojový lístek'];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<?= faviconTag() ?>
<?= seoMeta(['title' => 'Archiv lístků – ' . $siteName, 'url' => BASE_URL . '/food/archive.php']) ?>
  <title>Archiv lístků – <?= h($siteName) ?></title>
  <style>
    .filter-bar { display:flex; gap:.5rem; margin-bottom:1.5rem; flex-wrap:wrap; align-items:center; }
    .filter-bar a { padding:.35rem .9rem; border:1px solid #ccc; text-decoration:none;
                    color:#333; border-radius:3px; font-size:.9rem; }
    .filter-bar a.active { background:#333; color:#fff; border-color:#333; }
    .cards-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px,1fr));
                  gap:1rem; margin-top:1rem; }
    .card-item  { border:1px solid #ddd; padding:1rem; border-radius:4px; }
    .card-item.is-current { border-color:#060; background:#f4fff4; }
    .card-badge { font-size:.75rem; font-weight:bold; color:#060; margin-bottom:.4rem; }
    .card-type  { font-size:.8rem; color:#666; margin-bottom:.25rem; }
    .card-title { font-size:1rem; font-weight:bold; margin:0 0 .4rem; }
    .card-meta  { font-size:.8rem; color:#666; }
    .card-desc  { font-size:.85rem; color:#555; margin-top:.35rem; }
    .card-link  { display:inline-block; margin-top:.75rem; font-size:.9rem; }
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
  <h2>Archiv jídelních a nápojových lístků</h2>

  <p><a href="index.php"><span aria-hidden="true">&larr;</span> Aktuální lístek</a></p>

  <div class="filter-bar" aria-label="Filtrovat podle typu">
    <a href="archive.php?typ=vse"      <?= $filterType === 'vse'      ? 'class="active" aria-current="true"' : '' ?>>Vše</a>
    <a href="archive.php?typ=food"     <?= $filterType === 'food'     ? 'class="active" aria-current="true"' : '' ?>>Jídelní lístky</a>
    <a href="archive.php?typ=beverage" <?= $filterType === 'beverage' ? 'class="active" aria-current="true"' : '' ?>>Nápojové lístky</a>
  </div>

  <?php if (empty($cards)): ?>
    <p>Archiv je zatím prázdný.</p>
  <?php else: ?>
    <div class="cards-grid">
      <?php foreach ($cards as $c): ?>
        <div class="card-item<?= $c['is_current'] ? ' is-current' : '' ?>">
          <?php if ($c['is_current']): ?>
            <div class="card-badge"><span aria-hidden="true">&#9733;</span> Aktuální</div>
          <?php endif; ?>
          <div class="card-type"><?= $typeLabels[$c['type']] ?></div>
          <div class="card-title"><?= h($c['title']) ?></div>
          <div class="card-meta">
            <?php
            $from = $c['valid_from'] ? formatCzechDate($c['valid_from']) : null;
            $to   = $c['valid_to']   ? formatCzechDate($c['valid_to'])   : null;
            if ($from && $to)   echo h($from) . ' – ' . h($to);
            elseif ($from)      echo 'od ' . h($from);
            elseif ($to)        echo 'do ' . h($to);
            else                echo 'Přidáno ' . formatCzechDate($c['created_at']);
            ?>
          </div>
          <?php if (!empty($c['description'])): ?>
            <div class="card-desc"><?= h($c['description']) ?></div>
          <?php endif; ?>
          <a class="card-link" href="card.php?id=<?= (int)$c['id'] ?>">Zobrazit lístek <span aria-hidden="true">&rarr;</span></a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<?= siteFooter() ?>
</body>
</html>
