<?php
$profileTitle = currentSiteProfileKey() === 'personal' ? 'O mně' : 'O autorovi';
$backBlogPath = trim((string)($backBlogPath ?? ''));
$backBlogLabel = trim((string)($backBlogLabel ?? ''));
$contentItems = is_array($contentItems ?? null) ? $contentItems : [];
$contentCounts = is_array($contentCounts ?? null) ? $contentCounts : [];
$contentType = normalizeAuthorContentType((string)($contentType ?? 'vse'));
$contentFilterOptions = is_array($contentFilterOptions ?? null) ? $contentFilterOptions : authorContentFilterOptions($contentCounts);
$page = (int)($page ?? 1);
$pages = (int)($pages ?? 1);
$pagerBaseUrl = (string)($pagerBaseUrl ?? (authorPublicPath($author) . '?'));
$contentEmptyMessage = match ($contentType) {
    'clanky' => 'Autor zatím nemá žádné veřejně publikované články.',
    'novinky' => 'Autor zatím nemá žádné veřejně publikované novinky.',
    default => 'Autor zatím nemá žádný veřejně publikovaný obsah.',
};
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
        <?php if ($blogEnabled && $backBlogPath !== '' && $backBlogLabel !== ''): ?>
          <a class="button-secondary" href="<?= h($backBlogPath) ?>"><?= h($backBlogLabel) ?></a>
        <?php endif; ?>
        <?php if ($author['author_website_url'] !== ''): ?>
          <a class="button-secondary" href="<?= h($author['author_website_url']) ?>" rel="noopener noreferrer" target="_blank">Web autora<?= newWindowLinkSrOnlySuffix() ?></a>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="surface" aria-labelledby="author-content-title">
    <div class="section-heading">
      <div>
        <h2 id="author-content-title" class="section-title">Obsah autora</h2>
        <p class="section-subtitle"><?= h(authorContentSummaryLabel($contentCounts)) ?></p>
      </div>
    </div>

    <nav class="form-stack" aria-labelledby="author-content-filter-heading">
      <h3 id="author-content-filter-heading" class="sr-only">Filtr obsahu autora</h3>
      <ul class="chip-list">
        <?php foreach ($contentFilterOptions as $filterOption): ?>
          <?php
          $filterType = normalizeAuthorContentType((string)($filterOption['type'] ?? 'vse'));
          $filterUrl = authorPublicPath($author);
          if ($filterType !== 'vse') {
              $filterUrl .= '?typ=' . rawurlencode($filterType);
          }
          ?>
          <li>
            <a class="chip-link" href="<?= h($filterUrl) ?>"<?= $filterType === $contentType ? ' aria-current="page"' : '' ?>>
              <?= h((string)($filterOption['label'] ?? 'Vše')) ?>
              <span class="pill"><?= (int)($filterOption['count'] ?? 0) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <?php if ($contentItems === []): ?>
      <p class="empty-state"><?= h($contentEmptyMessage) ?></p>
    <?php else: ?>
      <div class="card-grid">
        <?php foreach ($contentItems as $contentItem): ?>
          <?php
          $contentTypeIdPart = preg_replace('/[^a-z0-9_-]+/i', '', (string)($contentItem['content_type'] ?? 'item')) ?: 'item';
          $contentTitleId = 'author-content-title-' . $contentTypeIdPart . '-' . (int)$contentItem['id'];
          ?>
          <article class="card" aria-labelledby="<?= h($contentTitleId) ?>">
            <?php if (($contentItem['content_type'] ?? '') === 'article' && !empty($contentItem['image_file'])): ?>
              <a class="card__media" href="<?= h((string)$contentItem['public_path']) ?>">
                <img src="<?= BASE_URL ?>/uploads/articles/thumbs/<?= rawurlencode((string)$contentItem['image_file']) ?>"
                     alt="<?= h((string)$contentItem['title']) ?>" loading="lazy">
              </a>
            <?php endif; ?>
            <div class="card__body">
              <p class="meta-row meta-row--tight">
                <span class="pill"><?= h((string)($contentItem['type_label'] ?? 'Obsah')) ?></span>
                <time datetime="<?= h(str_replace(' ', 'T', (string)$contentItem['display_date'])) ?>">
                  <?= formatCzechDate((string)$contentItem['display_date']) ?>
                </time>
                <?php if (!empty($contentItem['reading_meta'])): ?>
                  <span><?= h((string)$contentItem['reading_meta']) ?></span>
                <?php endif; ?>
              </p>
              <h3 id="<?= h($contentTitleId) ?>" class="card__title">
                <a href="<?= h((string)$contentItem['public_path']) ?>"><?= h((string)$contentItem['title']) ?></a>
              </h3>
              <?php if (!empty($contentItem['excerpt'])): ?>
                <p><?= h((string)$contentItem['excerpt']) ?></p>
              <?php endif; ?>
              <p>
                <a class="section-link" href="<?= h((string)$contentItem['public_path']) ?>">
                  <?= (($contentItem['content_type'] ?? '') === 'news') ? 'Zobrazit novinku' : 'Číst článek' ?>
                  <span aria-hidden="true">→</span>
                </a>
              </p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <?= renderPager($page, $pages, $pagerBaseUrl, 'Stránkování obsahu autora') ?>
    <?php endif; ?>
  </section>
</div>
