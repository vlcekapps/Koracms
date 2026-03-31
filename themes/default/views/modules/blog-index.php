<?php
$blogLogo = blogLogoUrl($blog);
$blogLogoAlt = blogLogoAltText($blog);
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
$searchQuery = trim((string)($searchQuery ?? ''));
$archiveFilter = trim((string)($archiveFilter ?? ''));
$filterLink = static function (array $params = []) use ($blog, $activeAuthor, $searchQuery, $katId, $tagSlug, $archiveFilter): string {
    $query = [];
    if (!empty($activeAuthor['author_slug'])) {
        $query['autor'] = (string)$activeAuthor['author_slug'];
    }
    if ($searchQuery !== '') {
        $query['q'] = $searchQuery;
    }
    if ($katId !== null) {
        $query['kat'] = (int)$katId;
    }
    if ($tagSlug !== '') {
        $query['tag'] = $tagSlug;
    }
    if ($archiveFilter !== '') {
        $query['archiv'] = $archiveFilter;
    }

    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
            continue;
        }
        $query[$key] = $value;
    }

    $base = blogIndexPath($blog);
    return $query === [] ? $base : $base . '?' . http_build_query($query);
};
$showAnyFilter = $katId !== null || $tagSlug !== '' || !empty($activeAuthor) || $searchQuery !== '' || $archiveFilter !== '';
?>
<div class="listing-shell">
  <section class="surface" aria-labelledby="blog-title">
    <div class="section-heading">
      <div>
        <h1 id="blog-title" class="section-title section-title--hero"><?= h($pageHeading) ?></h1>
        <?php if ($blogLogo !== ''): ?>
          <div class="blog-brand-mark">
            <img class="blog-brand-mark__image" src="<?= h($blogLogo) ?>" alt="<?= h($blogLogoAlt) ?>" loading="eager" decoding="async" style="display:block;max-width:min(100%,22rem);max-height:8rem;width:auto;height:auto">
          </div>
        <?php endif; ?>
        <?php if (!empty($blog['description'])): ?>
          <p class="section-subtitle"><?= h((string)$blog['description']) ?></p>
        <?php endif; ?>
        <?php if (!empty($blog['intro_content'])): ?>
          <div class="prose" style="margin-top:1rem">
            <?= renderContent((string)$blog['intro_content']) ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="button-row button-row--wrap button-row--start">
        <a class="section-link" href="<?= h(blogFeedPath($blog)) ?>">RSS feed <span aria-hidden="true">→</span></a>
        <?php if (!empty($showAuthorsIndexLink)): ?>
          <a class="section-link" href="<?= authorIndexPath() ?>">Autoři <span aria-hidden="true">→</span></a>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($featuredArticle)): ?>
      <article class="card card--feature" aria-labelledby="featured-article-title" style="margin-bottom:1.25rem">
        <?php if (!empty($featuredArticle['image_file'])): ?>
          <a class="card__media" href="<?= h($articleLink($featuredArticle)) ?>">
            <img src="<?= BASE_URL ?>/uploads/articles/thumbs/<?= rawurlencode((string)$featuredArticle['image_file']) ?>"
                 alt="<?= h((string)$featuredArticle['title']) ?>" loading="lazy">
          </a>
        <?php endif; ?>
        <div class="card__body">
          <p class="meta-row meta-row--tight">
            <span class="pill">Doporučený článek</span>
            <time datetime="<?= h(str_replace(' ', 'T', (string)$featuredArticle['created_at'])) ?>"><?= formatCzechDate((string)$featuredArticle['created_at']) ?></time>
            <span><?= h(articleReadingMeta(((string)($featuredArticle['perex'] ?? '')) . ((string)($featuredArticle['content'] ?? '')), (int)($featuredArticle['view_count'] ?? 0))) ?></span>
            <?php if (!empty($featuredArticle['author_name'])): ?>
              <?= $renderAuthorName($featuredArticle) ?>
            <?php endif; ?>
          </p>
          <h2 id="featured-article-title" class="card__title card__title--feature">
            <a href="<?= h($articleLink($featuredArticle)) ?>"><?= h((string)$featuredArticle['title']) ?></a>
          </h2>
          <?php if (!empty($featuredArticle['perex'])): ?>
            <p><?= h((string)$featuredArticle['perex']) ?></p>
          <?php endif; ?>
          <p><a class="section-link" href="<?= h($articleLink($featuredArticle)) ?>">Číst článek <span aria-hidden="true">→</span></a></p>
        </div>
      </article>
    <?php endif; ?>

    <?php if (empty($articles) && empty($featuredArticle)): ?>
      <p class="empty-state">
        <?php if ($searchQuery !== ''): ?>
          Pro hledání „<?= h($searchQuery) ?>“ jsme v tomto blogu nenašli žádné články.
        <?php elseif ($archiveFilter !== ''): ?>
          V zadaném období zatím nejsou v blogu žádné články.
        <?php elseif (!empty($activeAuthor)): ?>
          Autor zatím nemá žádné veřejně publikované články.
        <?php else: ?>
          Zatím tu nejsou žádné články.
        <?php endif; ?>
      </p>
    <?php elseif (!empty($articles)): ?>
      <div class="card-grid">
        <?php foreach ($articles as $article): ?>
          <article class="card">
            <?php if (!empty($article['image_file'])): ?>
              <a class="card__media" href="<?= h($articleLink($article)) ?>">
                <img src="<?= BASE_URL ?>/uploads/articles/thumbs/<?= rawurlencode((string)$article['image_file']) ?>"
                     alt="<?= h((string)$article['title']) ?>" loading="lazy">
              </a>
            <?php endif; ?>
            <div class="card__body">
              <h2 class="card__title">
                <a href="<?= h($articleLink($article)) ?>"><?= h((string)$article['title']) ?></a>
              </h2>
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
              <p>
                <a class="section-link" href="<?= h($articleLink($article)) ?>">Číst článek <span aria-hidden="true">→</span></a>
                <?php if (isset($_SESSION['cms_user_id'])): ?>
                  · <a href="<?= BASE_URL ?>/admin/blog_form.php?id=<?= (int)$article['id'] ?>">Upravit</a>
                <?php endif; ?>
              </p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <?= renderPager($page, $pages, $paginBase, 'Stránkování blogu', 'Novější', 'Starší') ?>
    <?php endif; ?>

    <div class="blog-secondary-tools">
      <?php if (!empty($publicBlogs) && count($publicBlogs) > 1): ?>
        <nav class="form-stack blog-secondary-tools__block" aria-labelledby="blog-blogs-heading">
          <h2 id="blog-blogs-heading" class="section-title section-title--compact blog-secondary-tools__title">Další blogy webu</h2>
          <ul class="chip-list">
            <?php foreach ($publicBlogs as $publicBlog): ?>
              <?php $isCurrentBlog = (int)($publicBlog['id'] ?? 0) === (int)($blog['id'] ?? 0); ?>
              <li>
                <?php if ($isCurrentBlog): ?>
                  <span class="pill"><?= h((string)$publicBlog['name']) ?></span>
                <?php else: ?>
                  <a class="chip-link" href="<?= h(blogIndexPath($publicBlog)) ?>"><?= h((string)$publicBlog['name']) ?></a>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </nav>
      <?php endif; ?>

      <form action="<?= h(blogIndexPath($blog)) ?>" method="get" class="form-stack blog-secondary-tools__block" role="search" aria-labelledby="blog-search-heading">
        <h2 id="blog-search-heading" class="section-title section-title--compact blog-secondary-tools__title">Hledání v blogu</h2>
        <?php if ($katId !== null): ?><input type="hidden" name="kat" value="<?= (int)$katId ?>"><?php endif; ?>
        <?php if ($tagSlug !== ''): ?><input type="hidden" name="tag" value="<?= h($tagSlug) ?>"><?php endif; ?>
        <?php if (!empty($activeAuthor['author_slug'])): ?><input type="hidden" name="autor" value="<?= h((string)$activeAuthor['author_slug']) ?>"><?php endif; ?>
        <?php if ($archiveFilter !== ''): ?><input type="hidden" name="archiv" value="<?= h($archiveFilter) ?>"><?php endif; ?>
        <label for="blog-search-q">Hledat v blogu</label>
        <div class="button-row button-row--wrap button-row--start">
          <input type="search" id="blog-search-q" name="q" value="<?= h($searchQuery) ?>" placeholder="Hledat v článcích blogu…" style="min-width:min(26rem,100%)">
          <button type="submit" class="button-primary">Hledat</button>
          <?php if ($searchQuery !== ''): ?>
            <a class="button-secondary" href="<?= h($filterLink(['q' => null])) ?>">Vyčistit hledání</a>
          <?php endif; ?>
        </div>
      </form>

      <?php if (!empty($categories)): ?>
        <nav class="form-stack blog-secondary-tools__block" aria-labelledby="blog-categories-heading">
          <h2 id="blog-categories-heading" class="section-title section-title--compact blog-secondary-tools__title">Kategorie blogu</h2>
          <ul class="chip-list">
            <li><a class="chip-link" href="<?= h($filterLink(['kat' => null, 'tag' => null])) ?>"<?= ($katId === null && $tagSlug === '') ? ' aria-current="page"' : '' ?>>Vše</a></li>
            <?php foreach ($categories as $category): ?>
              <li>
                <a class="chip-link" href="<?= h($filterLink(['kat' => (int)$category['id'], 'tag' => null])) ?>"<?= $katId === (int)$category['id'] ? ' aria-current="page"' : '' ?>>
                  <?= h($category['name']) ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </nav>
      <?php endif; ?>

      <?php if (!empty($allTags)): ?>
        <nav class="form-stack blog-secondary-tools__block" aria-labelledby="blog-tags-heading">
          <h2 id="blog-tags-heading" class="section-title section-title--compact blog-secondary-tools__title">Štítky blogu</h2>
          <ul class="chip-list">
            <?php foreach ($allTags as $tag): ?>
              <li>
                <a class="chip-link" href="<?= h($filterLink(['tag' => (string)$tag['slug']])) ?>"<?= $tagSlug === $tag['slug'] ? ' aria-current="page"' : '' ?>>
                  #<?= h($tag['name']) ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </nav>
      <?php endif; ?>

      <?php if (!empty($blogArchives)): ?>
        <nav class="form-stack blog-secondary-tools__block" aria-labelledby="blog-archives-heading">
          <h2 id="blog-archives-heading" class="section-title section-title--compact blog-secondary-tools__title">Archiv blogu</h2>
          <ul class="chip-list">
            <li><a class="chip-link" href="<?= h($filterLink(['archiv' => null])) ?>"<?= $archiveFilter === '' ? ' aria-current="page"' : '' ?>>Všechny měsíce</a></li>
            <?php foreach ($blogArchives as $archive): ?>
              <li>
                <a class="chip-link" href="<?= h($filterLink(['archiv' => (string)$archive['key']])) ?>"<?= $archiveFilter === $archive['key'] ? ' aria-current="page"' : '' ?>>
                  <?= h((string)$archive['label']) ?> <span aria-hidden="true">(<?= (int)$archive['count'] ?>)</span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </nav>
      <?php endif; ?>

      <?php if (!empty($activeAuthor)): ?>
        <nav class="form-stack blog-secondary-tools__block" aria-labelledby="blog-active-author-heading">
          <h2 id="blog-active-author-heading" class="section-title section-title--compact blog-secondary-tools__title">Aktivní autor blogu</h2>
          <ul class="chip-list">
            <li><a class="chip-link" href="<?= authorIndexPath() ?>">Všichni autoři</a></li>
            <li><a class="chip-link" href="<?= h(blogIndexPath($blog)) ?>">Všechny články</a></li>
            <li>
              <?php if (!empty($activeAuthor['author_public_path'])): ?>
                <a class="chip-link" href="<?= h((string)$activeAuthor['author_public_path']) ?>">Autor: <?= h($activeAuthor['author_display_name']) ?></a>
              <?php else: ?>
                <span class="pill">Autor: <?= h($activeAuthor['author_display_name']) ?></span>
              <?php endif; ?>
            </li>
          </ul>
        </nav>
      <?php endif; ?>

      <?php if ($showAnyFilter): ?>
        <p class="button-row button-row--start blog-secondary-tools__actions">
          <a class="button-secondary" href="<?= h(blogIndexPath($blog)) ?>">Zobrazit celý blog</a>
        </p>
      <?php endif; ?>
    </div>
  </section>
</div>
