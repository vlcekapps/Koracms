<?php
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
<div class="article-layout">
  <article class="surface" aria-labelledby="novinka-nadpis">
    <p class="section-kicker">Aktualita</p>
    <header class="section-heading">
      <div>
        <h1 id="novinka-nadpis" class="section-title section-title--hero"><?= h((string)$news['title']) ?></h1>
        <p class="meta-row">
          <time datetime="<?= h(str_replace(' ', 'T', (string)$news['created_at'])) ?>"><?= formatCzechDate((string)$news['created_at']) ?></time>
          <?php if (!empty($news['author_name'])): ?>
            <?= $renderAuthorName($news) ?>
          <?php endif; ?>
        </p>
      </div>
    </header>

    <div class="prose article-shell__content">
      <?= renderContent((string)$news['content']) ?>
    </div>

    <div class="article-actions">
      <a class="button-secondary" href="<?= BASE_URL ?>/news/index.php"><span aria-hidden="true">←</span> Zpět na novinky</a>
      <button type="button" class="button-secondary js-copy-link"
              data-url="<?= h(newsPublicUrl($news)) ?>"
              aria-label="Kopírovat odkaz na novinku">Kopírovat odkaz</button>
    </div>
  </article>
</div>
