<?php
$articleLink = static fn(array $article): string => articlePublicPath($article);
$renderAuthorName = static function (array $article): string {
    if (empty($article['author_name'])) {
        return '';
    }

    $label = h((string)$article['author_name']);
    if (!empty($article['author_public_path'])) {
        return '<a href="' . h((string)$article['author_public_path']) . '">' . $label . '</a>';
    }

    return '<span>' . $label . '</span>';
};
?>
<div class="listing-shell">
  <section class="surface" aria-labelledby="blog-title">
    <div class="section-heading">
      <div>
        <h1 id="blog-title" class="section-title section-title--hero"><?= h($pageHeading) ?></h1>
      </div>
    </div>

    <?php if (!empty($categories)): ?>
      <nav aria-label="Kategorie blogu" class="form-stack">
        <ul class="chip-list">
          <li><a class="chip-link" href="<?= BASE_URL ?>/blog/index.php"<?= ($katId === null && $tagSlug === '') ? ' aria-current="page"' : '' ?>>Vše</a></li>
          <?php foreach ($categories as $category): ?>
            <li>
              <a class="chip-link" href="<?= BASE_URL ?>/blog/index.php?kat=<?= (int)$category['id'] ?>"<?= $katId === (int)$category['id'] ? ' aria-current="page"' : '' ?>>
                <?= h($category['name']) ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </nav>
    <?php endif; ?>

    <?php if (!empty($allTags)): ?>
      <nav aria-label="Tagy blogu" class="form-stack">
        <ul class="chip-list">
          <?php foreach ($allTags as $tag): ?>
            <li>
              <a class="chip-link" href="<?= BASE_URL ?>/blog/index.php?tag=<?= rawurlencode($tag['slug']) ?>"<?= $tagSlug === $tag['slug'] ? ' aria-current="page"' : '' ?>>
                #<?= h($tag['name']) ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </nav>
    <?php endif; ?>

    <?php if (empty($articles)): ?>
      <p class="empty-state">Žádné články.</p>
    <?php else: ?>
      <div class="card-grid">
        <?php foreach ($articles as $article): ?>
          <article class="card">
            <?php if (!empty($article['image_file'])): ?>
              <a class="card__media" href="<?= h($articleLink($article)) ?>">
                <img src="<?= BASE_URL ?>/uploads/articles/thumbs/<?= rawurlencode($article['image_file']) ?>"
                     alt="<?= h($article['title']) ?>" loading="lazy">
              </a>
            <?php endif; ?>
            <div class="card__body">
              <p class="meta-row meta-row--tight">
                <?php if (!empty($article['category'])): ?>
                  <a class="pill" href="<?= BASE_URL ?>/blog/index.php?kat=<?= (int)$article['category_id'] ?>"><?= h($article['category']) ?></a>
                <?php endif; ?>
                <span><?= readingTime(($article['perex'] ?? '') . ($article['content'] ?? '')) ?> min čtení</span>
              </p>
              <h2 class="card__title">
                <a href="<?= h($articleLink($article)) ?>"><?= h($article['title']) ?></a>
              </h2>
              <?php if (!empty($article['perex'])): ?>
                <p><?= h($article['perex']) ?></p>
              <?php endif; ?>
              <p class="meta-row meta-row--tight">
                <time datetime="<?= h(str_replace(' ', 'T', $article['created_at'])) ?>"><?= formatCzechDate($article['created_at']) ?></time>
                <?php if (!empty($article['author_name'])): ?>
                  <?= $renderAuthorName($article) ?>
                <?php endif; ?>
              </p>
              <p>
                <a class="section-link" href="<?= h($articleLink($article)) ?>">Číst dále <span aria-hidden="true">→</span></a>
                <?php if (isset($_SESSION['cms_user_id'])): ?>
                  · <a href="<?= BASE_URL ?>/admin/blog_form.php?id=<?= (int)$article['id'] ?>">Upravit</a>
                <?php endif; ?>
              </p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <?php if ($pages > 1): ?>
        <nav aria-label="Stránkování blogu">
          <ul class="pager">
            <?php if ($page > 1): ?>
              <li><a href="<?= h($paginBase) ?>strana=<?= $page - 1 ?>" rel="prev"><span aria-hidden="true">←</span> Novější</a></li>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $pages; $p++): ?>
              <li>
                <?php if ($p === $page): ?>
                  <span aria-current="page"><?= $p ?></span>
                <?php else: ?>
                  <a href="<?= h($paginBase) ?>strana=<?= $p ?>"><?= $p ?></a>
                <?php endif; ?>
              </li>
            <?php endfor; ?>
            <?php if ($page < $pages): ?>
              <li><a href="<?= h($paginBase) ?>strana=<?= $page + 1 ?>" rel="next">Starší <span aria-hidden="true">→</span></a></li>
            <?php endif; ?>
          </ul>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>
