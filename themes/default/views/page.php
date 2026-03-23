<article class="surface article-shell">
  <p class="section-kicker">Stránka</p>
  <h1 class="section-title section-title--hero"><?= h($page['title']) ?></h1>
  <div class="prose article-shell__content">
    <?= renderContent($page['content']) ?>
  </div>
</article>
