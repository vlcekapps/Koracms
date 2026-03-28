<div class="page-stack">
  <section class="surface" aria-labelledby="authors-title">
    <div class="section-heading">
      <div>
        <h1 id="authors-title" class="section-title section-title--hero">Autoři</h1>
        <p class="section-subtitle">Poznejte autory, kteří na webu publikují články a další obsah.</p>
      </div>
      <?php if ($blogEnabled): ?>
        <?php $defBlog = getDefaultBlog(); if ($defBlog): ?>
          <a class="section-link" href="<?= h(blogIndexPath($defBlog)) ?>"><?= h($defBlog['name']) ?> <span aria-hidden="true">→</span></a>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <?php if ($authors === []): ?>
      <p class="empty-state">Zatím tu nejsou žádní veřejní autoři.</p>
    <?php else: ?>
      <div class="card-grid">
        <?php foreach ($authors as $author): ?>
          <article class="card">
            <div class="card__body">
              <div class="author-panel">
                <div class="author-panel__media">
                  <?php if ($author['author_avatar_url'] !== ''): ?>
                    <img
                      class="author-avatar"
                      src="<?= h($author['author_avatar_url']) ?>"
                      alt="Profilová fotografie autora <?= h($author['author_display_name']) ?>"
                      loading="lazy">
                  <?php else: ?>
                    <div class="author-avatar author-avatar--placeholder" aria-hidden="true">
                      <?= h(mb_strtoupper(mb_substr($author['author_display_name'], 0, 1))) ?>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="author-panel__content">
                  <h2 class="card__title">
                    <a href="<?= h($author['author_public_path']) ?>"><?= h($author['author_display_name']) ?></a>
                  </h2>
                  <p class="meta-row meta-row--tight">
                    <span><?= h(articleCountLabel((int)($author['article_count'] ?? 0))) ?></span>
                  </p>
                  <?php if (!empty($author['author_bio'])): ?>
                    <p><?= h(mb_strimwidth(normalizePlainText((string)$author['author_bio']), 0, 220, '...', 'UTF-8')) ?></p>
                  <?php endif; ?>
                  <div class="button-row button-row--start">
                    <a class="button-primary" href="<?= h($author['author_public_path']) ?>">Profil autora</a>
                    <?php if ($blogEnabled && (int)($author['article_count'] ?? 0) > 0): ?>
                      <?php $aBlog = getDefaultBlog(); ?>
                      <a class="button-secondary" href="<?= h(blogIndexPath($aBlog ?? [])) ?>?autor=<?= rawurlencode((string)$author['author_slug']) ?>">Články autora</a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
