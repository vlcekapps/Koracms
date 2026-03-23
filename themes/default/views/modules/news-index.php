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
            <h2 class="card__title">
              <time datetime="<?= h(str_replace(' ', 'T', $item['created_at'])) ?>"><?= formatCzechDate($item['created_at']) ?></time>
            </h2>
            <p class="meta-row meta-row--tight">
              <?php if (!empty($item['author_name'])): ?>
                <span><?= h($item['author_name']) ?></span>
              <?php endif; ?>
            </p>
            <p><?= h($item['content']) ?></p>
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
