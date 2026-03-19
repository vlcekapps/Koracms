<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('blog')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$id = inputInt('get', 'id');
if ($id === null) { header('Location: index.php'); exit; }

$pdo  = db_connect();

// Podpora náhledu (preview) přes token pro nepublikované články
$previewToken = trim($_GET['preview'] ?? '');
if ($previewToken !== '') {
    $stmt = $pdo->prepare(
        "SELECT a.*, c.name AS category, c.id AS category_id,
                COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),'')) AS author_name
         FROM cms_articles a
         LEFT JOIN cms_categories c ON c.id = a.category_id
         LEFT JOIN cms_users u ON u.id = a.author_id
         WHERE a.id = ? AND a.preview_token = ?"
    );
    $stmt->execute([$id, $previewToken]);
} else {
    $stmt = $pdo->prepare(
        "SELECT a.*, c.name AS category, c.id AS category_id,
                COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),'')) AS author_name
         FROM cms_articles a
         LEFT JOIN cms_categories c ON c.id = a.category_id
         LEFT JOIN cms_users u ON u.id = a.author_id
         WHERE a.id = ? AND a.status = 'published' AND (a.publish_at IS NULL OR a.publish_at <= NOW())"
    );
    $stmt->execute([$id]);
}
$article = $stmt->fetch();
if (!$article) { header('Location: index.php'); exit; }

// Tagy článku
$tags = [];
try {
    $ts = $pdo->prepare(
        "SELECT t.name, t.slug FROM cms_tags t
         JOIN cms_article_tags at2 ON at2.tag_id = t.id
         WHERE at2.article_id = ? ORDER BY t.name"
    );
    $ts->execute([$id]);
    $tags = $ts->fetchAll();
} catch (\PDOException $e) {}

$siteName       = getSetting('site_name', 'Kora CMS');
$commentErrors  = [];
$commentSuccess = false;

// ── Zpracování nového komentáře ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('comment', 5, 120);

    if (honeypotTriggered()) {
        header('Location: ' . BASE_URL . '/blog/article.php?id=' . $id . '&komentar=ok');
        exit;
    }

    verifyCsrf();

    if (!captchaVerify($_POST['captcha'] ?? ''))
        $commentErrors[] = 'Nesprávná odpověď na ověřovací příklad.';

    $authorName  = trim($_POST['author_name']  ?? '');
    $authorEmail = trim($_POST['author_email'] ?? '');
    $content     = trim($_POST['comment']      ?? '');

    if ($authorName === '')  $commentErrors[] = 'Jméno je povinné.';
    if (mb_strlen($authorName) > 100) $commentErrors[] = 'Jméno je příliš dlouhé.';
    if ($authorEmail !== '' && !filter_var($authorEmail, FILTER_VALIDATE_EMAIL))
        $commentErrors[] = 'Neplatná e-mailová adresa.';
    if ($content === '')    $commentErrors[] = 'Text komentáře je povinný.';

    if (empty($commentErrors)) {
        $pdo->prepare(
            "INSERT INTO cms_comments (article_id, author_name, author_email, content)
             VALUES (?, ?, ?, ?)"
        )->execute([$id, $authorName, $authorEmail, $content]);

        header('Location: ' . BASE_URL . '/blog/article.php?id=' . $id . '&komentar=ok');
        exit;
    }
}

// Schválené komentáře
$comments = $pdo->prepare(
    "SELECT author_name, author_email, content, created_at
     FROM cms_comments
     WHERE article_id = ? AND is_approved = 1
     ORDER BY created_at ASC"
);
$comments->execute([$id]);
$comments = $comments->fetchAll();

$captchaExpr = captchaGenerate();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<?= faviconTag() ?>
<?= seoMeta([
    'title'       => (!empty($article['meta_title']))       ? $article['meta_title']       : $article['title'] . ' – ' . $siteName,
    'description' => (!empty($article['meta_description'])) ? $article['meta_description'] : ($article['perex'] ?? ''),
    'image'       => !empty($article['image_file'])
                     ? BASE_URL . '/uploads/articles/' . rawurlencode($article['image_file'])
                     : '',
    'url'         => BASE_URL . '/blog/article.php?id=' . (int)$article['id'],
    'type'        => 'article',
]) ?>
  <title><?= h(!empty($article['meta_title']) ? $article['meta_title'] : $article['title'] . ' – ' . $siteName) ?></title>
  <style>
    .skip-link { position: absolute; left: -9999px; }
    .skip-link:focus { left: 1rem; top: 1rem; z-index: 9999;
      background: #fff; padding: .5rem 1rem; border: 2px solid #000; }
    .comments { margin-top: 2rem; }
    .comment { border-top: 1px solid #ddd; padding: .75rem 0; }
    .comment__meta { font-size: .85rem; color: #555; margin: 0 0 .4rem; }
    .comment__text { margin: 0; }
    .comment-form { margin-top: 1.5rem; }
    .comment-form label { display: block; margin-top: .75rem; font-weight: bold; }
    .comment-form input, .comment-form textarea {
      width: 100%; box-sizing: border-box; padding: .35rem; margin-top: .2rem; }
    .comment-form textarea { min-height: 100px; }
    .captcha-field { max-width: 8rem; }
    .form-errors { color: #c00; }
    .form-success { color: #060; }
    .tags { margin: .5rem 0; }
    .tags a { margin-right: .4rem; }
    .article-image { max-width: 100%; height: auto; margin-bottom: 1rem; }
  </style>
</head>
<body>
<?= adminBar(BASE_URL . '/admin/blog_form.php?id=' . (int)$article['id']) ?>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<header>
  <h1><?= h($siteName) ?></h1>
  <?php $logo = getSetting('site_logo', ''); if ($logo !== ''): ?>
    <img src="<?= BASE_URL ?>/uploads/site/<?= h($logo) ?>" alt="<?= h($siteName) ?>"
         style="max-height:60px" loading="lazy">
  <?php endif; ?>
  <?= siteNav('blog') ?>
</header>

<main id="obsah">
  <div id="a11y-live" role="status" aria-live="polite" aria-atomic="true" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0"></div>
  <article aria-labelledby="clanek-nadpis">
    <header>
      <h2 id="clanek-nadpis"><?= h($article['title']) ?></h2>
      <?php if (!empty($article['category'])): ?>
        <p><small>Kategorie:
          <a href="index.php?kat=<?= (int)$article['category_id'] ?>"><?= h($article['category']) ?></a>
        </small></p>
      <?php endif; ?>
      <p>
        <time datetime="<?= h(str_replace(' ', 'T', $article['created_at'])) ?>">
          <?= formatCzechDate($article['created_at']) ?>
        </time>
        <?php if (!empty($article['author_name'])): ?>
          · <?= h($article['author_name']) ?>
        <?php endif; ?>
      </p>
    </header>

    <?php if (!empty($article['image_file'])): ?>
      <img src="<?= BASE_URL ?>/uploads/articles/<?= rawurlencode($article['image_file']) ?>"
           alt="<?= h($article['title']) ?>" class="article-image" loading="lazy">
    <?php endif; ?>

    <?php if (!empty($article['perex'])): ?>
      <p><strong><?= h($article['perex']) ?></strong></p>
    <?php endif; ?>

    <div class="clanek-obsah">
      <?= $article['content'] ?>
    </div>

    <?php if (!empty($tags)): ?>
      <p class="tags" aria-label="Tagy článku">
        <?php foreach ($tags as $t): ?>
          <a href="index.php?tag=<?= rawurlencode($t['slug']) ?>">#<?= h($t['name']) ?></a>
        <?php endforeach; ?>
      </p>
    <?php endif; ?>
  </article>

  <p><a href="index.php"><span aria-hidden="true">←</span> Zpět na seznam článků</a></p>

  <!-- ── Komentáře ─────────────────────────────────────────────────────── -->
  <section class="comments" aria-labelledby="komentare-nadpis">
    <h3 id="komentare-nadpis">Komentáře<?= count($comments) > 0 ? ' (' . count($comments) . ')' : '' ?></h3>

    <?php if (isset($_GET['komentar']) && $_GET['komentar'] === 'ok'): ?>
      <p class="form-success" role="status">
        Komentář byl přijat a čeká na schválení. Děkujeme!
      </p>
    <?php endif; ?>

    <?php if (empty($comments)): ?>
      <p>Zatím žádné komentáře. Buďte první!</p>
    <?php else: ?>
      <?php foreach ($comments as $c): ?>
        <article class="comment">
          <p class="comment__meta">
            <strong><?= h($c['author_name']) ?></strong>
            <?php if ($c['author_email'] !== ''): ?>
              – <a href="mailto:<?= h($c['author_email']) ?>"><?= h($c['author_email']) ?></a>
            <?php endif; ?>
            ·
            <time datetime="<?= h(str_replace(' ', 'T', $c['created_at'])) ?>"><?= formatCzechDate($c['created_at']) ?></time>
          </p>
          <p class="comment__text"><?= nl2br(h($c['content'])) ?></p>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>

    <!-- ── Formulář pro nový komentář ─────────────────────────────────── -->
    <section class="comment-form" aria-labelledby="pridat-komentar-nadpis">
      <h4 id="pridat-komentar-nadpis">Přidat komentář</h4>

      <?php if (!empty($commentErrors)): ?>
        <ul id="comment-errors" class="form-errors" role="alert">
          <?php foreach ($commentErrors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <form method="post" novalidate aria-describedby="comment-errors">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <?= honeypotField() ?>
        <fieldset>
          <legend>Přidat komentář</legend>

          <label for="author_name">Jméno <span aria-hidden="true">*</span></label>
          <input type="text" id="author_name" name="author_name" required
                 aria-required="true" maxlength="100"
                 value="<?= h($_POST['author_name'] ?? '') ?>">

          <label for="author_email">E-mail <small>(nepovinný, nebude zveřejněn)</small></label>
          <input type="email" id="author_email" name="author_email" maxlength="255"
                 value="<?= h($_POST['author_email'] ?? '') ?>">

          <label for="comment">Komentář <span aria-hidden="true">*</span></label>
          <textarea id="comment" name="comment" required
                    aria-required="true"><?= h($_POST['comment'] ?? '') ?></textarea>

          <label for="captcha">
            Ověření: kolik je <?= h($captchaExpr) ?>?
            <span aria-hidden="true">*</span>
          </label>
          <input type="text" id="captcha" name="captcha" required
                 aria-required="true" inputmode="numeric"
                 autocomplete="off" class="captcha-field">

          <button type="submit" style="margin-top:1rem">Odeslat komentář</button>
        </fieldset>
      </form>
    </section>
  </section>
</main>

<?= siteFooter() ?>
<script>document.addEventListener("DOMContentLoaded",function(){var l=document.getElementById("a11y-live");if(!l)return;var m=document.querySelector('[role="status"]:not(#a11y-live),[role="alert"]');if(m){var t=m.textContent.trim();if(t)setTimeout(function(){l.textContent=t;},150);}});</script>
</body>
</html>
