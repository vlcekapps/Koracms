<?php
require_once __DIR__ . '/db.php';
checkMaintenanceMode();

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo  = db_connect();
$stmt = $pdo->prepare(
    "SELECT * FROM cms_pages WHERE slug = ? AND status = 'published' AND is_published = 1"
);
$stmt->execute([$slug]);
$page = $stmt->fetch();

if (!$page) {
    http_response_code(404);
    $siteName = getSetting('site_name', 'Kora CMS');
    echo '<!DOCTYPE html><html lang="cs"><head><meta charset="utf-8">'
       . '<title>Stránka nenalezena – ' . h($siteName) . '</title></head><body>'
       . '<h1>404 – Stránka nenalezena</h1>'
       . '<p><a href="' . BASE_URL . '/index.php">Zpět na úvod</a></p>'
       . '</body></html>';
    exit;
}

$siteName = getSetting('site_name', 'Kora CMS');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<?= faviconTag() ?>
<?= seoMeta([
    'title' => $page['title'] . ' – ' . $siteName,
    'url'   => BASE_URL . '/page.php?slug=' . rawurlencode($slug),
]) ?>
  <title><?= h($page['title']) ?> – <?= h($siteName) ?></title>
  <style>
    .skip-link { position: absolute; left: -9999px; }
    .skip-link:focus { left: 1rem; top: 1rem; z-index: 9999;
      background: #fff; padding: .5rem 1rem; border: 2px solid #000; }
  </style>
</head>
<body>
<?= adminBar(BASE_URL . '/admin/page_form.php?id=' . (int)$page['id']) ?>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<header>
  <h1><?= h($siteName) ?></h1>
  <?php $logo = getSetting('site_logo', ''); if ($logo !== ''): ?>
    <img src="<?= BASE_URL ?>/uploads/site/<?= h($logo) ?>" alt="<?= h($siteName) ?>"
         style="max-height:60px" loading="lazy">
  <?php endif; ?>
  <?= siteNav('page:' . $slug) ?>
</header>

<main id="obsah">
  <h2><?= h($page['title']) ?></h2>
  <div class="stranka-obsah">
    <?= $page['content'] ?>
  </div>
</main>

<?= siteFooter() ?>
</body>
</html>
