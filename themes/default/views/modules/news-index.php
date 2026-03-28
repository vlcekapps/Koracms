<?php
$newsLink = static fn(array $item): string => newsPublicPath($item);
$renderAuthorName = static function (array $item): string {
    if (empty($item['author_name'])) {
        return '';
    }

    $label = h((string)$item['author_name']);
    if (!empty($item['author_public_path'])) {
        return '<a href="' . h((string)$item['author_public_path']) . '">' . $label . '</a>';
    }

    return '<span>' . $label . '</span>';
};
?>
<div class="listing-shell">
  <section class="surface" aria-labelledby="news-title">
    <div class="section-heading">
      <div>
        <h1 id="news-title" class="section-title section-title--hero">Novinky</h1>
      </div>
    </div>

    <?php if (empty($items)): ?>
      <p class="empty-state">Žádné novinky.</p>
    <?php else: ?>
      <div class="news-stream">
        <?php foreach ($items as $item): ?>
          <article class="news-item">
            <p class="meta-row meta-row--tight">
              <time datetime="<?= h(str_replace(' ', 'T', (string)$item['created_at'])) ?>"><?= formatCzechDate((string)$item['created_at']) ?></time>
              <?php if (!empty($item['author_name'])): ?>
                <?= $renderAuthorName($item) ?>
              <?php endif; ?>
            </p>
            <h2 class="card__title">
              <a href="<?= h($newsLink($item)) ?>"><?= h((string)$item['title']) ?></a>
            </h2>
            <?php if (!empty($item['excerpt'])): ?>
              <p><?= h((string)$item['excerpt']) ?></p>
            <?php endif; ?>
            <p><a class="section-link" href="<?= h($newsLink($item)) ?>">Zobrazit novinku <span aria-hidden="true">→</span></a></p>
          </article>
        <?php endforeach; ?>
      </div>

      <?= renderPager($page, $pages, '?', 'Stránkování novinek', 'Starší', 'Novější') ?>
    <?php endif; ?>
  </section>
</div>
