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
        <p class="section-kicker">Aktuálně</p>
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
            <p><a class="section-link" href="<?= h($newsLink($item)) ?>">Číst dále <span aria-hidden="true">→</span></a></p>
          </article>
        <?php endforeach; ?>
      </div>

      <?php if ($pages > 1): ?>
        <nav aria-label="Stránkování novinek">
          <ul class="pager">
            <?php if ($page > 1): ?>
              <li><a href="?strana=<?= $page - 1 ?>" rel="prev"><span aria-hidden="true">←</span> Starší</a></li>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $pages; $p++): ?>
              <li>
                <?php if ($p === $page): ?>
                  <span aria-current="page"><?= $p ?></span>
                <?php else: ?>
                  <a href="?strana=<?= $p ?>"><?= $p ?></a>
                <?php endif; ?>
              </li>
            <?php endfor; ?>
            <?php if ($page < $pages): ?>
              <li><a href="?strana=<?= $page + 1 ?>" rel="next">Novější <span aria-hidden="true">→</span></a></li>
            <?php endif; ?>
          </ul>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>
