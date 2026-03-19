<?php
require_once __DIR__ . '/db.php';
checkMaintenanceMode();

$siteName = getSetting('site_name', 'Kora CMS');
$q        = trim($_GET['q'] ?? '');
$results  = [];

if ($q !== '' && mb_strlen($q) >= 2) {
    $pdo  = db_connect();
    $like = '%' . $q . '%';

    // Články blogu
    if (isModuleEnabled('blog')) {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, title, perex, created_at, 'blog' AS type
                 FROM cms_articles
                 WHERE (publish_at IS NULL OR publish_at <= NOW())
                   AND (title LIKE ? OR perex LIKE ? OR content LIKE ?)
                 ORDER BY created_at DESC LIMIT 10"
            );
            $stmt->execute([$like, $like, $like]);
            foreach ($stmt->fetchAll() as $row) $results[] = $row;
        } catch (\PDOException $e) {}
    }

    // Novinky
    if (isModuleEnabled('news')) {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, content AS title, '' AS perex, created_at, 'news' AS type
                 FROM cms_news WHERE content LIKE ?
                 ORDER BY created_at DESC LIMIT 5"
            );
            $stmt->execute([$like]);
            foreach ($stmt->fetchAll() as $row) $results[] = $row;
        } catch (\PDOException $e) {}
    }

    // Statické stránky
    try {
        $stmt = $pdo->prepare(
            "SELECT id, title, '' AS perex, created_at, 'page' AS type, slug
             FROM cms_pages
             WHERE is_published = 1 AND (title LIKE ? OR content LIKE ?)
             ORDER BY title LIMIT 5"
        );
        $stmt->execute([$like, $like]);
        foreach ($stmt->fetchAll() as $row) $results[] = $row;
    } catch (\PDOException $e) {}

    // Události
    if (isModuleEnabled('events')) {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, title, description AS perex, event_date AS created_at, 'event' AS type
                 FROM cms_events
                 WHERE is_published = 1 AND (title LIKE ? OR description LIKE ? OR location LIKE ?)
                 ORDER BY event_date DESC LIMIT 5"
            );
            $stmt->execute([$like, $like, $like]);
            foreach ($stmt->fetchAll() as $row) $results[] = $row;
        } catch (\PDOException $e) {}
    }

    // Podcast
    if (isModuleEnabled('podcast')) {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, title, description AS perex, created_at, 'podcast' AS type
                 FROM cms_podcasts
                 WHERE (publish_at IS NULL OR publish_at <= NOW()) AND (title LIKE ? OR description LIKE ?)
                 ORDER BY created_at DESC LIMIT 5"
            );
            $stmt->execute([$like, $like]);
            foreach ($stmt->fetchAll() as $row) $results[] = $row;
        } catch (\PDOException $e) {}
    }

    // FAQ
    if (isModuleEnabled('faq')) {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, question AS title, answer AS perex, created_at, 'faq' AS type
                 FROM cms_faqs WHERE is_published = 1 AND (question LIKE ? OR answer LIKE ?)
                 ORDER BY sort_order, id LIMIT 10"
            );
            $stmt->execute([$like, $like]);
            foreach ($stmt->fetchAll() as $row) $results[] = $row;
        } catch (\PDOException $e) {}
    }

    // Zajímavá místa
    if (isModuleEnabled('places')) {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, name AS title, description AS perex, created_at, 'place' AS type
                 FROM cms_places WHERE is_published = 1 AND (name LIKE ? OR description LIKE ?)
                 ORDER BY sort_order, name LIMIT 5"
            );
            $stmt->execute([$like, $like]);
            foreach ($stmt->fetchAll() as $row) $results[] = $row;
        } catch (\PDOException $e) {}
    }
}

// Funkce pro URL výsledku
function resultUrl(array $r): string {
    $b = BASE_URL;
    return match($r['type']) {
        'blog'    => $b . '/blog/article.php?id=' . (int)$r['id'],
        'news'    => $b . '/news/index.php',
        'page'    => $b . '/page.php?slug=' . rawurlencode($r['slug'] ?? ''),
        'event'   => $b . '/events/index.php#event-' . (int)$r['id'],
        'podcast' => $b . '/podcast/index.php#ep-' . (int)$r['id'],
        'faq'     => $b . '/faq/index.php',
        'place'   => $b . '/places/index.php#place-' . (int)$r['id'],
        default   => $b . '/',
    };
}

function typeLabel(string $type): string {
    return match($type) {
        'blog'    => 'Článek',
        'news'    => 'Novinka',
        'page'    => 'Stránka',
        'event'   => 'Akce',
        'podcast' => 'Podcast',
        'faq'     => 'FAQ',
        'place'   => 'Místo',
        default   => '',
    };
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<?= faviconTag() ?>
<?= seoMeta(['title' => 'Vyhledávání – ' . $siteName]) ?>
  <title>Vyhledávání – <?= h($siteName) ?></title>
</head>
<body>
<?= adminBar() ?>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<header>
  <h1><?= h($siteName) ?></h1>
  <?= siteNav() ?>
</header>

<main id="obsah">
  <h2>Vyhledávání</h2>

  <form method="get" role="search">
    <label for="q">Hledat na webu</label>
    <div style="display:flex;gap:.5rem;margin-top:.3rem">
      <input type="search" id="q" name="q" required minlength="2"
             value="<?= h($q) ?>" style="flex:1;max-width:400px" aria-label="Hledaný výraz">
      <button type="submit">Hledat</button>
    </div>
  </form>

  <?php if ($q !== ''): ?>
    <p style="margin-top:1rem">
      <?php if (empty($results)): ?>
        Žádné výsledky pro <strong><?= h($q) ?></strong>.
      <?php else: ?>
        Nalezeno <?= count($results) ?> výsledk<?= count($results) === 1 ? '' : (count($results) < 5 ? 'y' : 'ů') ?>
        pro <strong><?= h($q) ?></strong>:
      <?php endif; ?>
    </p>

    <?php foreach ($results as $r): ?>
      <article style="border-top:1px solid #ddd;padding:.75rem 0">
        <p style="margin:0 0 .2rem">
          <small><?= h(typeLabel($r['type'])) ?></small>
        </p>
        <h3 style="margin:0 0 .3rem">
          <a href="<?= h(resultUrl($r)) ?>"><?= h(mb_substr($r['title'], 0, 120)) ?></a>
        </h3>
        <?php if (!empty($r['perex'])): ?>
          <p style="margin:0;color:#555"><?= h(mb_substr(strip_tags($r['perex']), 0, 200)) ?></p>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
</main>

<?= siteFooter() ?>
</body>
</html>
