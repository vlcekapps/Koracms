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
  <section class="surface" aria-labelledby="blog-series-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Série článků</p>
        <h1 id="blog-series-title" class="section-title section-title--hero"><?= h((string)$series['title']) ?></h1>
        <?php if (trim((string)($series['description'] ?? '')) !== ''): ?>
          <p class="section-subtitle"><?= h((string)$series['description']) ?></p>
        <?php endif; ?>
      </div>
      <div class="button-row button-row--wrap button-row--start">
        <a class="section-link" href="<?= h(blogIndexPath($blog)) ?>">Zpět na blog <span aria-hidden="true">→</span></a>
      </div>
    </div>

    <section aria-labelledby="blog-series-articles-heading">
      <h2 id="blog-series-articles-heading" class="section-title section-title--compact">Články v sérii</h2>
      <div class="card-grid">
        <?php foreach ($articles as $article): ?>
          <?php $articleTitleId = 'blog-series-article-title-' . (int)$article['id']; ?>
          <article class="card" aria-labelledby="<?= h($articleTitleId) ?>">
            <?php if (!empty($article['image_file'])): ?>
              <a class="card__media" href="<?= h($articleLink($article)) ?>">
                <img src="<?= BASE_URL ?>/uploads/articles/thumbs/<?= rawurlencode((string)$article['image_file']) ?>"
                     alt="<?= h((string)$article['title']) ?>" loading="lazy">
              </a>
            <?php endif; ?>
            <div class="card__body">
              <h3 id="<?= h($articleTitleId) ?>" class="card__title">
                <a href="<?= h($articleLink($article)) ?>"><?= h((string)$article['title']) ?></a>
              </h3>
              <p class="meta-row meta-row--tight">
                <time datetime="<?= h(str_replace(' ', 'T', (string)$article['created_at'])) ?>"><?= formatCzechDate((string)$article['created_at']) ?></time>
                <span><?= h(articleReadingMeta(((string)($article['perex'] ?? '')) . ((string)($article['content'] ?? '')), (int)($article['view_count'] ?? 0))) ?></span>
                <?php if (!empty($article['author_name'])): ?>
                  <?= $renderAuthorName($article) ?>
                <?php endif; ?>
              </p>
              <?php if (!empty($article['perex'])): ?>
                <p><?= h((string)$article['perex']) ?></p>
              <?php endif; ?>
              <p><a class="section-link" href="<?= h($articleLink($article)) ?>">Číst článek <span aria-hidden="true">→</span></a></p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  </section>
</div>
