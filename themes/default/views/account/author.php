<?php
$articleLink = static fn(array $article): string => articlePublicPath($article);
$profileTitle = currentSiteProfileKey() === 'personal' ? 'O mně' : 'O autorovi';
?>
<div class="page-stack">
  <section class="surface author-panel" aria-labelledby="author-title">
    <div class="author-panel__media">
      <?php if ($author['author_avatar_url'] !== ''): ?>
        <img
          class="author-avatar author-avatar--large"
          src="<?= h($author['author_avatar_url']) ?>"
          alt="Profilová fotografie autora <?= h($author['author_display_name']) ?>"
          loading="lazy">
      <?php else: ?>
        <div class="author-avatar author-avatar--placeholder author-avatar--large" aria-hidden="true">
          <?= h(mb_strtoupper(mb_substr($author['author_display_name'], 0, 1))) ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="author-panel__content">
      <p class="section-kicker"><?= h($profileTitle) ?></p>
      <h1 id="author-title" class="section-title section-title--hero"><?= h($author['author_display_name']) ?></h1>

      <?php if (!empty($author['author_bio'])): ?>
        <div class="prose">
          <?= renderContent((string)$author['author_bio']) ?>
        </div>
      <?php endif; ?>

      <div class="button-row button-row--start">
        <a class="button-secondary" href="<?= authorIndexPath() ?>">Všichni autoři</a>
        <?php if ($blogEnabled): ?>
          <a class="button-secondary" href="<?= BASE_URL ?>/blog/index.php">Blog</a>
        <?php endif; ?>
        <?php if ($author['author_website_url'] !== ''): ?>
          <a class="button-secondary" href="<?= h($author['author_website_url']) ?>" rel="noopener noreferrer" target="_blank">Web autora</a>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <?php if ($blogEnabled): ?>
    <section class="surface" aria-labelledby="author-articles-title">
      <div class="section-heading">
        <div>
          <h2 id="author-articles-title" class="section-title">Články autora</h2>
        </div>
      </div>

      <?php if ($articles === []): ?>
        <p class="empty-state">Autor zatím nemá žádné veřejně publikované články.</p>
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
                <?php if (!empty($article['category'])): ?>
                  <p class="meta-row meta-row--tight">
                    <a class="pill" href="<?= BASE_URL ?>/blog/index.php?kat=<?= (int)$article['category_id'] ?>"><?= h($article['category']) ?></a>
                  </p>
                <?php endif; ?>
                <h3 class="card__title">
                  <a href="<?= h($articleLink($article)) ?>"><?= h($article['title']) ?></a>
                </h3>
                <p class="meta-row meta-row--tight">
                  <time datetime="<?= h(str_replace(' ', 'T', $article['publish_at'] ?: $article['created_at'])) ?>">
                    <?= formatCzechDate($article['publish_at'] ?: $article['created_at']) ?>
                  </time>
                  <span><?= h(articleReadingMeta(($article['perex'] ?? '') . ($article['content'] ?? ''), (int)($article['view_count'] ?? 0))) ?></span>
                </p>
                <?php if (!empty($article['perex'])): ?>
                  <p><?= h($article['perex']) ?></p>
                <?php endif; ?>
                <p><a class="section-link" href="<?= h($articleLink($article)) ?>">Číst článek <span aria-hidden="true">→</span></a></p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>
